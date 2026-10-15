<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\Payment;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\Gateways\GatewayPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Checkout do site (gateway simulado em ambiente de teste). */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requires_login(): void
    {
        $plan = $this->makePlan();

        $this->get("/assinar/{$plan->slug}")->assertRedirect('/entrar');
    }

    public function test_pix_checkout_uses_server_side_price_and_grants_access_after_approval(): void
    {
        $plan = $this->makePlan(['price_cents' => 14990]);
        $user = $this->makeUser();

        // Tentativa de adulterar o valor pelo formulário é ignorada.
        $this->actingAs($user)->post("/assinar/{$plan->slug}", [
            'method' => 'pix', 'accept_terms' => '1', 'amount' => 1, 'price_cents' => 1,
        ])->assertRedirect();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(14990, $payment->amount_cents);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNotEmpty($payment->pix_qr_code);
        $this->assertFalse($user->fresh()->isPremium());

        $this->actingAs($user)->get("/pagamento/{$payment->uuid}")->assertOk()->assertSee('Copiar código Pix');

        $this->actingAs($user)->post("/pagamento/{$payment->uuid}/simular", ['result' => 'approve'])->assertRedirect();

        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $this->assertTrue($user->fresh()->isPremium());
        $this->assertNotNull($payment->fresh()->subscription_id);
    }

    public function test_approval_is_idempotent(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser();
        $payment = app(CheckoutService::class)->start($user, $plan, PaymentMethod::Pix, null);

        $remote = new GatewayPayment($payment->provider_payment_id, PaymentStatus::Approved, $payment->amount_cents, $payment->uuid);
        app(CheckoutService::class)->apply($payment, $remote);
        app(CheckoutService::class)->apply($payment->fresh(), $remote);

        $this->assertSame(1, $user->subscriptions()->count());
    }

    public function test_divergent_amount_does_not_grant_access(): void
    {
        $plan = $this->makePlan(['price_cents' => 14990]);
        $user = $this->makeUser();
        $payment = app(CheckoutService::class)->start($user, $plan, PaymentMethod::Pix, null);

        app(CheckoutService::class)->apply($payment, new GatewayPayment('X1', PaymentStatus::Approved, 100, $payment->uuid));

        $this->assertFalse($user->fresh()->isPremium());
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_foreign_reference_is_ignored(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser();
        $payment = app(CheckoutService::class)->start($user, $plan, PaymentMethod::Pix, null);

        app(CheckoutService::class)->apply($payment, new GatewayPayment('X2', PaymentStatus::Approved, $payment->amount_cents, 'outra-referencia'));

        $this->assertFalse($user->fresh()->isPremium());
    }

    public function test_refund_revokes_access(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser();
        $service = app(CheckoutService::class);
        $payment = $service->start($user, $plan, PaymentMethod::Pix, null);

        $service->apply($payment, new GatewayPayment('R1', PaymentStatus::Approved, $payment->amount_cents, $payment->uuid));
        $this->assertTrue($user->fresh()->isPremium());

        $service->apply($payment->fresh(), new GatewayPayment('R1', PaymentStatus::Refunded, $payment->amount_cents, $payment->uuid));
        $this->assertFalse($user->fresh()->isPremium());
    }

    public function test_prepaid_periods_stack(): void
    {
        $plan = $this->makePlan(['interval' => 'month', 'interval_count' => 1, 'price_cents' => 1990]);
        $user = $this->makeUser();
        $service = app(CheckoutService::class);

        foreach (['A', 'B'] as $id) {
            $payment = $service->start($user, $plan, PaymentMethod::CreditCard, null);
            $service->apply($payment, new GatewayPayment($id, PaymentStatus::Approved, $payment->amount_cents, $payment->uuid));
        }

        $ends = $user->fresh()->activeSubscription()->ends_at;
        $this->assertTrue($ends->greaterThan(now()->addMonthsNoOverflow(2)->subDay()));
    }

    public function test_coupon_discount_is_applied_server_side(): void
    {
        $plan = $this->makePlan(['price_cents' => 10000]);
        Coupon::query()->create(['code' => 'FE10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        $user = $this->makeUser();

        $payment = app(CheckoutService::class)->start($user, $plan, PaymentMethod::Pix, 'fe10');

        $this->assertSame(9000, $payment->amount_cents);
        $this->assertSame(1000, $payment->discount_cents);
    }

    public function test_invalid_coupon_is_rejected(): void
    {
        $plan = $this->makePlan();
        Coupon::query()->create(['code' => 'VENCIDO', 'type' => 'percent', 'value' => 50, 'is_active' => true, 'expires_at' => now()->subDay()]);
        $user = $this->makeUser();

        $this->actingAs($user)->post("/assinar/{$plan->slug}", ['method' => 'pix', 'coupon' => 'VENCIDO', 'accept_terms' => '1'])
            ->assertSessionHasErrors('method');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_user_cannot_view_another_users_payment(): void
    {
        $plan = $this->makePlan();
        $owner = $this->makeUser();
        $intruder = $this->makeUser();
        $payment = app(CheckoutService::class)->start($owner, $plan, PaymentMethod::Pix, null);

        $this->actingAs($intruder)->get("/pagamento/{$payment->uuid}")->assertNotFound();
        $this->actingAs($intruder)->get("/pagamento/{$payment->uuid}/situacao")->assertNotFound();
        $this->actingAs($intruder)->post("/pagamento/{$payment->uuid}/simular", ['result' => 'approve'])->assertNotFound();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_simulation_is_disabled_when_fake_gateway_is_off(): void
    {
        config(['sancta.payments.fake_enabled' => false]);
        $plan = $this->makePlan();
        $user = $this->makeUser();

        $payment = new Payment;
        $payment->forceFill([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'fake', 'method' => 'pix',
            'status' => 'pending', 'amount_cents' => $plan->price_cents,
        ])->save();

        $this->actingAs($user)->post("/pagamento/{$payment->uuid}/simular", ['result' => 'approve'])->assertNotFound();
    }

    public function test_inactive_plan_cannot_be_purchased(): void
    {
        $plan = $this->makePlan(['is_active' => false]);
        $user = $this->makeUser();

        $this->actingAs($user)->get("/assinar/{$plan->slug}")->assertNotFound();
        $this->actingAs($user)->post("/assinar/{$plan->slug}", ['method' => 'pix', 'accept_terms' => '1'])->assertNotFound();
    }
}
