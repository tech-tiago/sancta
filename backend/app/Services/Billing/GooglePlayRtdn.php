<?php

namespace App\Services\Billing;

use App\Enums\PaymentProvider;
use App\Models\WebhookEvent;
use App\Services\Google\GoogleJwks;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Real-time Developer Notifications do Google Play (via Pub/Sub push).
 * A requisição só é aceita se trouxer um token OIDC válido, assinado pelo Google,
 * com a audiência e a conta de serviço configuradas.
 */
class GooglePlayRtdn
{
    public function __construct(
        private readonly GoogleJwks $jwks,
        private readonly GooglePlayBilling $billing,
    ) {}

    public function authorize(Request $request): bool
    {
        $audience = config('services.google_play.rtdn_audience');
        $email = config('services.google_play.rtdn_service_account_email');
        $token = $request->bearerToken();

        if (! $audience || ! $email || ! $token) {
            return false;
        }

        try {
            $claims = $this->jwks->decode($token);
        } catch (Throwable) {
            return false;
        }

        return in_array($claims->iss ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            && ($claims->aud ?? null) === $audience
            && ($claims->email ?? null) === $email
            && filter_var($claims->email_verified ?? false, FILTER_VALIDATE_BOOL);
    }

    public function handle(Request $request): string
    {
        $messageId = (string) $request->input('message.messageId', '');
        $data = json_decode(base64_decode((string) $request->input('message.data', ''), true) ?: '', true);

        if ($messageId === '' || ! is_array($data)) {
            return 'ignored';
        }

        if (($data['packageName'] ?? null) !== config('services.google_play.package_name')) {
            return 'ignored';
        }

        try {
            $event = WebhookEvent::query()->create([
                'provider' => PaymentProvider::GooglePlay->value,
                'event_id' => $messageId,
                'type' => 'rtdn.'.(data_get($data, 'subscriptionNotification.notificationType') ?? (isset($data['testNotification']) ? 'test' : 'other')),
                'payload' => Arr::except($data, ['subscriptionNotification.purchaseToken']),
                'ip_address' => $request->ip(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return 'duplicate';
        }

        $token = data_get($data, 'subscriptionNotification.purchaseToken');
        if (! $token) {
            $event->markProcessed('ignored');

            return 'ignored';
        }

        try {
            $subscription = $this->billing->refresh($token);
            $event->markProcessed($subscription ? 'processed' : 'ignored', $subscription ? null : 'Token ainda não vinculado a uma conta');

            return 'processed';
        } catch (Throwable $e) {
            report($e);
            $event->markProcessed('failed', mb_substr($e->getMessage(), 0, 1000));

            return 'failed';
        }
    }
}
