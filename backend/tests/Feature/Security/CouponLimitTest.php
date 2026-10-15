<?php

namespace Tests\Feature\Security;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\Payment;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\Gateways\GatewayPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Pentest SEC-05 — Limite de usos do cupom contornável.
 * O limite (max_redemptions) só era contado na APROVAÇÃO; a validação acontecia na criação
 * da cobrança. Várias cobranças abertas ao mesmo tempo (por uma ou várias contas) passavam
 * todas na checagem e, pagas, ultrapassavam o limite. Agora cada cobrança pendente e válida
 * "reserva" um uso, sob trava de linha.
 */
class CouponLimitTest extends TestCase
{
    use RefreshDatabase;

    private function coupon(int $max = 1): Coupon
    {
        return Coupon::query()->create(['code' => 'UNICO', 'type' => 'percent', 'value' => 50, 'is_active' => true, 'max_redemptions' => $max]);
    }

    public function test_pending_checkouts_reserve_coupon_uses(): void
    {
        $plan = $this->makePlan(['price_cents' => 10000]);
        $this->coupon(1);

        $first = app(CheckoutService::class)->start($this->makeUser(), $plan, PaymentMethod::Pix, 'UNICO');
        $this->assertSame(5000, $first->amount_cents);

        try {
            app(CheckoutService::class)->start($this->makeUser(), $plan, PaymentMethod::CreditCard, 'UNICO');
            $this->fail('O segundo uso simultâneo do cupom deveria ser recusado.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Cupom', $e->getMessage());
        }

        $this->assertSame(1, Payment::query()->whereNotNull('coupon_id')->count());
    }

    public function test_limit_is_never_exceeded_after_approvals(): void
    {
        $plan = $this->makePlan(['price_cents' => 10000]);
        $coupon = $this->coupon(2);
        $checkout = app(CheckoutService::class);

        $payments = [];
        foreach (range(1, 4) as $i) {
            try {
                $payments[] = $checkout->start($this->makeUser(), $plan, PaymentMethod::Pix, 'UNICO');
            } catch (RuntimeException) {
                // esgotado
            }
        }

        foreach ($payments as $payment) {
            $checkout->apply($payment, new GatewayPayment((string) $payment->provider_payment_id, PaymentStatus::Approved, $payment->amount_cents, $payment->uuid));
        }

        $this->assertCount(2, $payments);
        $this->assertSame(2, $coupon->fresh()->redemptions_count);
    }

    public function test_expired_pending_checkout_releases_the_reservation(): void
    {
        $plan = $this->makePlan(['price_cents' => 10000]);
        $this->coupon(1);

        $first = app(CheckoutService::class)->start($this->makeUser(), $plan, PaymentMethod::Pix, 'UNICO');
        $first->forceFill(['expires_at' => now()->subMinute()])->save();

        $second = app(CheckoutService::class)->start($this->makeUser(), $plan, PaymentMethod::Pix, 'UNICO');
        $this->assertSame(5000, $second->amount_cents);
    }
}
