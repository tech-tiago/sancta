<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Google\ServiceAccountToken;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Credenciais das integrações e segurança do painel, editáveis em
 * Painel → Sistema → Integrações e segurança (somente super administrador).
 *
 * - O que é salvo no painel tem prioridade; o .env continua valendo como padrão.
 * - Segredos (tokens, senhas, contas de serviço) são criptografados com a APP_KEY,
 *   nunca voltam para a tela e nunca entram no log de auditoria.
 * - apply() roda a cada requisição (AppServiceProvider) e injeta os valores no config(),
 *   então os serviços continuam lendo config('services.*') sem saber de onde veio.
 */
class Integrations
{
    private const PREFIX = 'integrations.';

    /** Campo do painel => [chave do config, tipo]. Tipos: string, secret, bool, int, list. */
    public const FIELDS = [
        'mercadopago.access_token' => ['services.mercadopago.access_token', 'secret'],
        'mercadopago.public_key' => ['services.mercadopago.public_key', 'string'],
        'mercadopago.webhook_secret' => ['services.mercadopago.webhook_secret', 'secret'],
        'payments.pix_enabled' => ['sancta.payments.pix_enabled', 'bool'],
        'payments.card_enabled' => ['sancta.payments.card_enabled', 'bool'],
        'payments.pix_expiration_minutes' => ['sancta.payments.pix_expiration_minutes', 'int'],

        'google_play.package_name' => ['services.google_play.package_name', 'string'],
        'google_play.service_account' => ['services.google_play.service_account', 'secret'],
        'google_play.rtdn_audience' => ['services.google_play.rtdn_audience', 'string'],
        'google_play.rtdn_service_account_email' => ['services.google_play.rtdn_service_account_email', 'string'],

        'google.web_client_id' => ['services.google.web_client_id', 'string'],
        'google.client_ids' => ['services.google.client_ids', 'list'],
        'fcm.project_id' => ['services.fcm.project_id', 'string'],
        'fcm.service_account' => ['services.fcm.service_account', 'secret'],

        'gemini.api_key' => ['services.gemini.api_key', 'secret'],
        'gemini.model' => ['services.gemini.model', 'string'],
        'ai.daily_limit' => ['sancta.ai.daily_limit', 'int'],

        'youtube.api_key' => ['services.youtube.api_key', 'secret'],

        'mail.mailer' => ['mail.default', 'string'],
        'mail.host' => ['mail.mailers.smtp.host', 'string'],
        'mail.port' => ['mail.mailers.smtp.port', 'int'],
        'mail.scheme' => ['mail.mailers.smtp.scheme', 'string'],
        'mail.username' => ['mail.mailers.smtp.username', 'string'],
        'mail.password' => ['mail.mailers.smtp.password', 'secret'],
        'mail.from_address' => ['mail.from.address', 'string'],
        'mail.from_name' => ['mail.from.name', 'string'],

        'admin.allowed_ips' => ['sancta.admin.allowed_ips', 'list'],
        'admin.require_mfa' => ['sancta.admin.require_mfa', 'bool'],
    ];

    /** Valores do .env/config antes do painel — padrão quando o campo não foi salvo no painel. */
    private array $defaults = [];

    private bool $captured = false;

    public function __construct(private readonly Settings $settings) {}

    /** Injeta no config() o que foi salvo no painel. Seguro de chamar várias vezes. */
    public function apply(): void
    {
        try {
            $stored = $this->stored();
        } catch (Throwable) {
            return; // banco/cache ainda indisponível (instalação, migrations): fica com o .env
        }

        if (! $this->captured) {
            foreach (self::FIELDS as $field => [$configKey]) {
                $this->defaults[$field] = config($configKey);
            }
            $this->captured = true;
        }

        foreach (self::FIELDS as $field => [$configKey]) {
            $value = array_key_exists($field, $stored) ? $this->decode($field, $stored[$field]) : null;
            config([$configKey => $value ?? $this->defaults[$field]]);
        }

        // O .env é um piso de segurança: o painel pode exigir 2FA, mas não dispensar o que o servidor exige.
        if ($this->defaults['admin.require_mfa'] ?? false) {
            config(['sancta.admin.require_mfa' => true]);
        }
    }

    /** Para processos longos (fila): relê o que foi salvo no painel desde o início do processo. */
    public function refresh(): void
    {
        $this->settings->refresh();
        $this->apply();
    }

    /** Valor em uso (painel ou .env). Não use para exibir segredos. */
    public function value(string $field): mixed
    {
        return config(self::FIELDS[$field][0]);
    }

    public function isSecret(string $field): bool
    {
        return self::FIELDS[$field][1] === 'secret';
    }

    public function inPanel(string $field): bool
    {
        $stored = $this->stored();

        return array_key_exists($field, $stored) && $this->decode($field, $stored[$field]) !== null;
    }

    /** De onde vem o valor em uso: "panel", "env" ou null (não configurado). */
    public function source(string $field): ?string
    {
        if ($this->inPanel($field)) {
            return 'panel';
        }

        return filled($this->defaults[$field] ?? null) ? 'env' : null;
    }

    /** Valor padrão vindo do .env (para exibir "valor do servidor"). */
    public function defaultValue(string $field): mixed
    {
        return $this->defaults[$field] ?? null;
    }

    /** Pista não sensível de um segredo salvo: e-mail da conta de serviço ou os 4 últimos caracteres. */
    public function secretHint(string $field): ?string
    {
        $value = $this->value($field);
        if (blank($value)) {
            return null;
        }

        if (str_ends_with($field, 'service_account')) {
            return ServiceAccountToken::parse((string) $value)['client_email'] ?? 'arquivo inválido';
        }

        if (str_ends_with($field, 'password') || str_ends_with($field, 'webhook_secret')) {
            return 'definida';
        }

        return 'termina em '.mb_substr((string) $value, -4);
    }

    /** Estado inicial do formulário: valores em uso, exceto segredos (sempre em branco). */
    public function formState(): array
    {
        $state = [];
        foreach (self::FIELDS as $field => [, $type]) {
            data_set($state, $field, $type === 'secret' ? null : $this->value($field));
        }

        return $state;
    }

    /**
     * Salva os valores do painel (array plano campo => valor).
     * Segredos em branco mantêm o valor atual; campos de texto vazios voltam ao padrão do .env.
     *
     * @return list<string> campos alterados (nunca os valores — vão para a auditoria)
     */
    public function save(array $values): array
    {
        $stored = $this->stored();
        $changed = [];

        foreach (self::FIELDS as $field => [, $type]) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            $new = $this->normalize($type, $values[$field]);
            $current = array_key_exists($field, $stored) ? $this->decode($field, $stored[$field]) : null;

            if ($type === 'secret') {
                if ($new === null || $new === $current) {
                    continue;
                }
                $this->put($field, Crypt::encryptString($new));
                $changed[] = $field;

                continue;
            }

            if ($new === null) {
                if ($current !== null) {
                    $this->delete($field);
                    $changed[] = $field;
                }

                continue;
            }

            // Igual ao que já está em uso (ex.: valor do .env exibido no formulário): não grava.
            if ($new === ($current ?? $this->normalize($type, $this->defaults[$field] ?? null))) {
                continue;
            }

            $this->put($field, $new);
            $changed[] = $field;
        }

        $this->settings->flush();
        $this->apply();

        return $changed;
    }

    /** Remove o valor salvo no painel (volta ao .env). */
    public function forget(string $field): void
    {
        $this->delete($field);
        $this->settings->flush();
        $this->apply();
    }

    /** IP (v4/v6) ou faixa CIDR válida. */
    public static function isValidIpEntry(string $entry): bool
    {
        if (! str_contains($entry, '/')) {
            return filter_var($entry, FILTER_VALIDATE_IP) !== false;
        }

        [$ip, $bits] = explode('/', $entry, 2);
        $max = match (true) {
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false => 32,
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false => 128,
            default => -1,
        };

        return $max > 0 && ctype_digit($bits) && (int) $bits <= $max;
    }

    /** @return array<string, mixed> campo => valor bruto salvo no painel */
    private function stored(): array
    {
        $out = [];
        foreach ($this->settings->all() as $key => $value) {
            if (str_starts_with($key, self::PREFIX)) {
                $out[substr($key, strlen(self::PREFIX))] = $value;
            }
        }

        return $out;
    }

    private function decode(string $field, mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        if (self::FIELDS[$field][1] === 'secret') {
            if (! is_string($raw) || $raw === '') {
                return null;
            }
            try {
                return Crypt::decryptString($raw);
            } catch (DecryptException) {
                Log::warning('Credencial salva no painel não pôde ser lida (a APP_KEY mudou?)', ['campo' => $field]);

                return null;
            }
        }

        return $this->normalize(self::FIELDS[$field][1], $raw);
    }

    private function normalize(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOL),
            'int' => is_numeric($value) ? (int) $value : null,
            'list' => ($list = array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), (array) $value), 'filled')))) === [] ? null : $list,
            default => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null,
        };
    }

    private function put(string $field, mixed $value): void
    {
        // Sem eventos do model: a auditoria automática gravaria o valor; registramos só o nome do campo.
        Setting::withoutEvents(fn () => Setting::query()->updateOrCreate(['key' => self::PREFIX.$field], ['value' => $value]));
    }

    private function delete(string $field): void
    {
        Setting::withoutEvents(fn () => Setting::query()->where('key', self::PREFIX.$field)->delete());
    }
}
