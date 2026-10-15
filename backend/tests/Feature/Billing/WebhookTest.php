<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Webhooks: assinatura obrigatória, anti-replay, idempotência e consulta ao provedor. */
class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'segredo-webhook-teste';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.mercadopago.access_token' => 'TEST-TOKEN',
            'services.mercadopago.webhook_secret' => self::SECRET,
        ]);
    }

    private function pendingPayment(int $amount = 14990): Payment
    {
        $plan = $this->makePlan(['price_cents' => $amount]);
        $user = $this->makeUser();
        $payment = new Payment;
        $payment->forceFill([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'mercadopago', 'method' => 'pix',
            'status' => 'pending', 'amount_cents' => $amount, 'provider_payment_id' => '123456789',
            'expires_at' => now()->addMinutes(30),
        ])->save();

        return $payment;
    }

    private function signedHeaders(string $dataId, string $requestId = 'req-1', ?int $ts = null): array
    {
        $ts ??= time();
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";

        return [
            'x-signature' => "ts={$ts},v1=".hash_hmac('sha256', $manifest, self::SECRET),
            'x-request-id' => $requestId,
        ];
    }

    public function test_webhook_without_signature_is_rejected(): void
    {
        $this->pendingPayment();

        $this->postJson('/api/webhooks/mercadopago?data.id=123456789&type=payment', ['type' => 'payment', 'data' => ['id' => '123456789']])
            ->assertUnauthorized();
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_webhook_with_forged_signature_is_rejected(): void
    {
        $this->pendingPayment();
        $headers = ['x-signature' => 'ts='.time().',v1='.str_repeat('a', 64), 'x-request-id' => 'req-1'];

        $this->withHeaders($headers)->postJson('/api/webhooks/mercadopago?data.id=123456789&type=payment', ['type' => 'payment', 'data' => ['id' => '123456789']])
            ->assertUnauthorized();
    }

    public function test_replayed_old_webhook_is_rejected(): void
    {
        $this->pendingPayment();

        $this->withHeaders($this->signedHeaders('123456789', 'req-1', time() - 3600))
            ->postJson('/api/webhooks/mercadopago?data.id=123456789&type=payment', ['type' => 'payment', 'data' => ['id' => '123456789']])
            ->assertUnauthorized();
    }

    public function test_valid_webhook_confirms_with_provider_before_granting_access(): void
    {
        $payment = $this->pendingPayment();
        Http::fake(['api.mercadopago.com/v1/payments/123456789' => Http::response([
            'id' => 123456789, 'status' => 'approved', 'transaction_amount' => 149.90,
            'external_reference' => $payment->uuid, 'payment_type_id' => 'bank_transfer', 'installments' => 1,
        ])]);

        $this->withHeaders($this->signedHeaders('123456789'))
            ->postJson('/api/webhooks/mercadopago?data.id=123456789&type=payment', ['id' => 'evt-1', 'type' => 'payment', 'action' => 'payment.updated', 'data' => ['id' => '123456789']])
            ->assertOk()->assertJsonPath('status', 'processed');

        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $this->assertTrue($payment->user->fresh()->isPremium());

        // Mesmo evento reenviado: ignorado (idempotência).
        $this->withHeaders($this->signedHeaders('123456789'))
            ->postJson('/api/webhooks/mercadopago?data.id=123456789&type=payment', ['id' => 'evt-1', 'type' => 'payment', 'data' => ['id' => '123456789']])
            ->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, $payment->user->subscriptions()->count());
    }

    public function test_webhook_payload_lies_are_ignored_when_provider_says_pending(): void
    {
        $payment = $this->pendingPayment();
        Http::fake(['api.mercadopago.com/*' => Http::response(['id' => 123456789, 'status' => 'pending', 'transaction_amount' => 149.90, 'external_reference' => $payment->uuid])]);

        $this->withHeaders($this->signedHeaders('123456789'))
            ->postJson('/api/webhooks/mercadopago?data.id=123456789&type=payment', ['id' => 'evt-2', 'type' => 'payment', 'data' => ['id' => '123456789', 'status' => 'approved']])
            ->assertOk();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertFalse($payment->user->fresh()->isPremium());
    }

    public function test_google_play_rtdn_requires_google_oidc_token(): void
    {
        config(['services.google_play.rtdn_audience' => 'https://sancta.app.br/api/webhooks/google-play', 'services.google_play.rtdn_service_account_email' => 'pubsub@projeto.iam.gserviceaccount.com']);

        $this->postJson('/api/webhooks/google-play', ['message' => ['messageId' => '1', 'data' => base64_encode('{}')]])->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer forjado.token.jwt')
            ->postJson('/api/webhooks/google-play', ['message' => ['messageId' => '1', 'data' => base64_encode('{}')]])->assertUnauthorized();
    }

    public function test_google_play_verification_rejects_unknown_product(): void
    {
        $this->makePlan();
        $user = $this->makeUser();

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/billing/google-play/verify', ['product_id' => 'produto_falso', 'purchase_token' => str_repeat('x', 40)])
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
    }

    public function test_google_play_verification_fails_safely_without_credentials(): void
    {
        $this->makePlan();
        $user = $this->makeUser();

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/billing/google-play/verify', ['product_id' => 'sancta_plus', 'purchase_token' => str_repeat('x', 40)])
            ->assertUnprocessable();
        $this->assertFalse($user->fresh()->isPremium());
    }
}
