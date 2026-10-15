<?php

namespace App\Services\Push;

use App\Enums\Audience;
use App\Models\Device;
use App\Models\PushCampaign;
use App\Models\User;
use App\Services\Google\ServiceAccountToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notificações push via Firebase Cloud Messaging (API HTTP v1).
 * Campanhas usam tópicos (sancta_all / sancta_free / sancta_premium), que o app
 * assina conforme o status do usuário — escala para milhões sem loops no servidor.
 */
class PushService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public const TOPICS = [
        'all' => 'sancta_all',
        'free' => 'sancta_free',
        'premium' => 'sancta_premium',
    ];

    /** Canal Android padrão ("Avisos e novidades"); o app cria os canais e o Manifest usa o mesmo ID. */
    public const DEFAULT_CHANNEL = 'sancta_avisos';

    /** Cor de destaque das notificações no Android (Azul Manto). */
    public const ACCENT_COLOR = '#1F3D9C';

    public function __construct(private readonly ServiceAccountToken $tokens) {}

    public function isConfigured(): bool
    {
        return filled(config('services.fcm.project_id')) && filled(config('services.fcm.service_account'));
    }

    public function sendCampaign(PushCampaign $campaign): bool
    {
        if (! $this->isConfigured()) {
            $campaign->forceFill(['status' => 'failed', 'last_error' => 'FCM não configurado no servidor (.env).'])->save();

            return false;
        }

        $audience = $campaign->audience ?? Audience::All;
        $topic = self::TOPICS[$audience->value] ?? self::TOPICS['all'];

        $campaign->forceFill(['status' => 'sending'])->save();

        $ok = $this->send(['topic' => $topic], $campaign->title, $campaign->body, $campaign->deep_link, $campaign->image_url);

        $campaign->forceFill([
            'status' => $ok ? 'sent' : 'failed',
            'sent_at' => $ok ? now() : null,
            'sent_count' => $ok ? 1 : 0,
            'failed_count' => $ok ? 0 : 1,
            'last_error' => $ok ? null : 'Falha no envio — verifique os logs.',
        ])->save();

        return $ok;
    }

    /** Notificação individual (ex.: pagamento aprovado). Remove tokens inválidos. */
    public function notifyUser(User $user, string $title, string $body, ?string $deepLink = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $user->devices()->where('notifications_enabled', true)->each(function (Device $device) use ($title, $body, $deepLink): void {
            $ok = $this->send(['token' => $device->fcm_token], $title, $body, $deepLink);
            if ($ok === null) {
                $device->delete(); // token expirado/desinstalado
            }
        });
    }

    /**
     * Envio para um tópico FCM (ex.: "sancta_live_3" — "Me avise" de um canal ao vivo).
     *
     * @param  array<string, string>  $data  dados extras para o app (somente strings)
     */
    public function notifyTopic(string $topic, string $title, string $body, ?string $deepLink = null, ?string $channelId = null, ?string $image = null, array $data = []): bool
    {
        if (! $this->isConfigured() || ! preg_match('/^[A-Za-z0-9_.~%-]{1,900}$/', $topic)) {
            return false;
        }

        return (bool) $this->send(['topic' => $topic], $title, $body, $deepLink, $image, $channelId, $data);
    }

    /** @return bool|null null = token inválido (deve ser removido) */
    private function send(array $target, string $title, string $body, ?string $deepLink = null, ?string $image = null, ?string $channelId = null, array $data = []): ?bool
    {
        try {
            $token = $this->tokens->get((string) config('services.fcm.service_account'), self::SCOPE);

            $message = $target + [
                'notification' => array_filter(['title' => $title, 'body' => $body, 'image' => $image]),
                'data' => array_filter(['deep_link' => $deepLink] + array_map('strval', $data)),
                'android' => [
                    'priority' => 'high',
                    'notification' => ['channel_id' => $channelId ?? self::DEFAULT_CHANNEL, 'color' => self::ACCENT_COLOR, 'icon' => 'ic_notification'],
                ],
            ];

            $response = Http::withToken($token)->timeout(15)
                ->post('https://fcm.googleapis.com/v1/projects/'.config('services.fcm.project_id').'/messages:send', ['message' => $message]);

            if ($response->successful()) {
                return true;
            }

            if (in_array($response->json('error.status'), ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true) && isset($target['token'])) {
                return null;
            }

            Log::warning('FCM falhou', ['status' => $response->status(), 'error' => $response->json('error.message')]);

            return false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
