<?php

namespace Tests\Feature\Security;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\Gateways\GatewayPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pentest SEC-06 — Webhook do Mercado Pago:
 *  a) a assinatura vale para o data.id da URL, mas o processamento usava o data.id do corpo;
 *     o id processado precisa ser exatamente o id assinado;
 *  b) notificação fora de ordem (aprovado → pendente) não pode regredir um pagamento aprovado.
 */
class WebhookHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'segredo-webhook-teste';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mercadopago.access_token' => 'TEST-TOKEN', 'services.mercadopago.webhook_secret' => self::SECRET]);
    }

    private function payment(string $providerId, string $status = 'pending'): Payment
    {
        $plan = $this->makePlan(['price_cents' => 14990]);
        $payment = new Payment;
        $payment->forceFill([
            'user_id' => $this->makeUser()->id, 'plan_id' => $plan->id, 'provider' => 'mercadopago', 'method' => 'pix',
            'status' => $status, 'amount_cents' => 14990, 'provider_payment_id' => $providerId, 'expires_at' => now()->addMinutes(30),
        ])->save();

        return $payment;
    }

    private function signed(string $dataId, string $requestId): array
    {
        $ts = time();

        return [
            'x-signature' => "ts={$ts},v1=".hash_hmac('sha256', "id:{$dataId};request-id:{$requestId};ts:{$ts};", self::SECRET),
            'x-request-id' => $requestId,
        ];
    }

    public function test_processed_id_is_the_signed_id(): void
    {
        $target = $this->payment('123456789');
        Http::fake([
            'api.mercadopago.com/v1/payments/123456789' => Http::response([
                'id' => 123456789, 'status' => 'approved', 'transaction_amount' => 149.90, 'external_reference' => $target->uuid,
            ]),
            'api.mercadopago.com/*' => Http::response(['message' => 'not found'], 404),
        ]);

        // Assinado para o id 111 (URL), corpo aponta para outro pagamento.
        $this->withHeaders($this->signed('111', 'req-a'))
            ->postJson('/api/webhooks/mercadopago?data.id=111&type=payment', ['id' => 'evt-a', 'type' => 'payment', 'data' => ['id' => '123456789']])
            ->assertOk();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '123456789'));
        $this->assertSame(PaymentStatus::Pending, $target->fresh()->status);
    }

    public function test_body_id_without_query_id_is_still_accepted_when_signed(): void
    {
        $payment = $this->payment('555');
        Http::fake(['api.mercadopago.com/*' => Http::response([
            'id' => 555, 'status' => 'approved', 'transaction_amount' => 149.90, 'external_reference' => $payment->uuid,
        ])]);

        $this->withHeaders($this->signed('555', 'req-b'))
            ->postJson('/api/webhooks/mercadopago?type=payment', ['id' => 'evt-b', 'type' => 'payment', 'data' => ['id' => '555']])
            ->assertOk()->assertJsonPath('status', 'processed');

        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
    }

    public function test_out_of_order_pending_does_not_regress_an_approved_payment(): void
    {
        $payment = $this->payment('777');
        $checkout = app(CheckoutService::class);

        $checkout->apply($payment, new GatewayPayment('777', PaymentStatus::Approved, 14990, $payment->uuid));
        $this->assertTrue($payment->user->fresh()->isPremium());

        $checkout->apply($payment, new GatewayPayment('777', PaymentStatus::Pending, 14990, $payment->uuid));

        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $this->assertTrue($payment->user->fresh()->isPremium());
        $this->assertSame(1, $payment->user->subscriptions()->count());
    }

    public function test_refund_after_approval_still_revokes(): void
    {
        $payment = $this->payment('888');
        $checkout = app(CheckoutService::class);

        $checkout->apply($payment, new GatewayPayment('888', PaymentStatus::Approved, 14990, $payment->uuid));
        $checkout->apply($payment, new GatewayPayment('888', PaymentStatus::ChargedBack, 14990, $payment->uuid));

        $this->assertSame(PaymentStatus::ChargedBack, $payment->fresh()->status);
        $this->assertFalse($payment->user->fresh()->isPremium());
    }
}
