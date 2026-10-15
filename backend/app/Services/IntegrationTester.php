<?php

namespace App\Services;

use App\Models\User;
use App\Services\Google\ServiceAccountToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Botões "Testar" do painel → Integrações e segurança. Usam os valores EM USO
 * (painel ou .env) e devolvem uma mensagem curta; nunca exibem segredos.
 */
class IntegrationTester
{
    public function __construct(private readonly ServiceAccountToken $tokens) {}

    public function mercadoPago(): string
    {
        $token = (string) config('services.mercadopago.access_token');
        if ($token === '') {
            throw new RuntimeException('Informe o Access Token do Mercado Pago e salve antes de testar.');
        }

        $response = Http::withToken($token)->acceptJson()->timeout(15)->get('https://api.mercadopago.com/users/me');
        if ($response->status() === 401) {
            throw new RuntimeException('O Mercado Pago recusou o Access Token (inválido ou revogado).');
        }
        if ($response->failed()) {
            throw new RuntimeException('Não foi possível falar com o Mercado Pago agora (HTTP '.$response->status().').');
        }

        $mode = str_starts_with($token, 'TEST-') ? 'modo TESTE' : 'modo PRODUÇÃO';
        $account = $response->json('nickname') ?: $response->json('email') ?: $response->json('id');

        return "Conectado à conta {$account} ({$mode}).";
    }

    public function googlePlay(): string
    {
        return $this->serviceAccount((string) config('services.google_play.service_account'), 'https://www.googleapis.com/auth/androidpublisher', 'Google Play');
    }

    public function firebase(): string
    {
        if (blank(config('services.fcm.project_id'))) {
            throw new RuntimeException('Informe o ID do projeto do Firebase.');
        }

        return $this->serviceAccount((string) config('services.fcm.service_account'), 'https://www.googleapis.com/auth/firebase.messaging', 'Firebase');
    }

    public function gemini(): string
    {
        $key = (string) config('services.gemini.api_key');
        if ($key === '') {
            throw new RuntimeException('Informe a chave da API do Gemini e salve antes de testar.');
        }

        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        if (! preg_match('/^[a-z0-9.\-]+$/i', $model)) {
            throw new RuntimeException('Nome de modelo inválido.');
        }

        $response = Http::withHeaders(['x-goog-api-key' => $key])->timeout(15)
            ->get('https://generativelanguage.googleapis.com/v1beta/models/'.$model);

        if (in_array($response->status(), [400, 401, 403], true)) {
            throw new RuntimeException('O Google recusou a chave do Gemini (inválida ou sem permissão).');
        }
        if ($response->status() === 404) {
            throw new RuntimeException("O modelo \"{$model}\" não existe ou não está disponível para esta chave.");
        }
        if ($response->failed()) {
            throw new RuntimeException('Não foi possível falar com o Gemini agora (HTTP '.$response->status().').');
        }

        return 'Chave válida — modelo '.($response->json('displayName') ?: $model).' disponível.';
    }

    /** YouTube Data API v3 (detecção de lives). Consome 1 unidade de cota. */
    public function youtube(): string
    {
        $key = (string) config('services.youtube.api_key');
        if ($key === '') {
            throw new RuntimeException('Informe a chave da YouTube Data API e salve antes de testar.');
        }

        // Chave no cabeçalho para não aparecer em URLs/logs.
        $response = Http::withHeaders(['x-goog-api-key' => $key])->acceptJson()->timeout(15)
            ->get('https://www.googleapis.com/youtube/v3/i18nRegions', ['part' => 'snippet', 'hl' => 'pt_BR']);

        if ($response->successful()) {
            return 'Chave válida — YouTube Data API v3 respondendo. A detecção automática de lives já está ativa.';
        }

        $reason = (string) ($response->json('error.errors.0.reason') ?? $response->json('error.details.0.reason') ?? '');

        throw new RuntimeException(match (true) {
            in_array($reason, ['accessNotConfigured', 'SERVICE_DISABLED'], true) => 'A "YouTube Data API v3" não está ativada no projeto desta chave (Google Cloud → APIs e serviços → Biblioteca).',
            in_array($reason, ['quotaExceeded', 'dailyLimitExceeded', 'RATE_LIMIT_EXCEEDED'], true) => 'A chave é válida, mas a cota diária do YouTube acabou. Ela renova à meia-noite (horário do Pacífico).',
            $response->status() === 400 || in_array($reason, ['keyInvalid', 'API_KEY_INVALID'], true) => 'O Google recusou a chave (inválida ou revogada).',
            $response->status() === 403 => 'O Google recusou a chave (sem permissão para a YouTube Data API ou restrita a outro app/IP).',
            default => 'Não foi possível falar com o YouTube agora (HTTP '.$response->status().').',
        });
    }

    public function mail(User $to): string
    {
        if (config('mail.default') === 'log') {
            throw new RuntimeException('O envio está desativado (somente registro). Escolha SMTP e salve antes de testar.');
        }

        // Descarta conexões criadas com a configuração anterior.
        Mail::purge(config('mail.default'));

        try {
            Mail::raw(
                "Este é um e-mail de teste do Sancta.\n\nSe você recebeu esta mensagem, o envio de e-mails (confirmação de cadastro, redefinição de senha e recibos) está funcionando.",
                fn ($message) => $message->to($to->email)->subject('Teste de e-mail do Sancta'),
            );
        } catch (Throwable $e) {
            report($e);
            throw new RuntimeException('Falha ao enviar: verifique servidor, porta, segurança, usuário e senha.');
        }

        return "E-mail de teste enviado para {$to->email}.";
    }

    private function serviceAccount(string $credentials, string $scope, string $name): string
    {
        $account = ServiceAccountToken::parse($credentials);
        if ($account === null) {
            throw new RuntimeException("Envie o arquivo JSON da conta de serviço do {$name} e salve antes de testar.");
        }

        try {
            $this->tokens->get($credentials, $scope);
        } catch (Throwable) {
            throw new RuntimeException("O Google recusou a conta de serviço {$account['client_email']} (chave revogada ou sem permissão).");
        }

        return "Credencial válida — conta {$account['client_email']}.";
    }
}
