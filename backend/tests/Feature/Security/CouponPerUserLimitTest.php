<?php

namespace Tests\Feature\Security;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\Gateways\GatewayPayment;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Pentest fase 3 — SEC-11 (risco residual da fase 1): cupons não tinham limite por pessoa.
 * Um cupom promocional podia ser usado várias vezes pela mesma conta — inclusive comprando
 * "presentes" com desconto e resgatando-os na própria conta. Agora cada cupom tem um limite de
 * usos por pessoa (padrão: 1, configurável no painel), conferido sob a mesma trava de linha do
 * limite global; cobranças pendentes e válidas contam como uso (como no SEC-05).
 */
class CouponPerUserLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::query()->create(array_merge(['code' => 'BEMVINDO', 'type' => 'percent', 'value' => 50, 'is_active' => true], $attributes));
    }

    private function approve(Payment $payment): void
    {
        app(CheckoutService::class)->apply($payment, new GatewayPayment((string) $payment->provider_payment_id, PaymentStatus::Approved, $payment->amount_cents, $payment->uuid));
    }

    private function attempt(User $user, PaymentMethod $method = PaymentMethod::Pix, bool $gift = false): ?Payment
    {
        try {
            return app(CheckoutService::class)->start($user, $this->plan, $method, 'BEMVINDO', null, $gift);
        } catch (RuntimeException) {
            return null;
        }
    }

    private ?Plan $plan = null;

    public function test_new_coupons_default_to_one_use_per_person(): void
    {
        $this->assertSame(1, $this->coupon()->fresh()->max_redemptions_per_user);
    }

    public function test_same_person_cannot_reuse_a_coupon_after_paying(): void
    {
        $this->plan = $this->makePlan(['price_cents' => 10000]);
        $this->coupon();
        $user = $this->makeUser();

        $first = $this->attempt($user);
        $this->assertNotNull($first);
        $this->approve($first);

        $this->assertNull($this->attempt($user), 'Segundo uso do mesmo cupom pela mesma pessoa');
        $this->assertNull($this->attempt($user, PaymentMethod::CreditCard));
        $this->assertNotNull($this->attempt($this->makeUser()), 'Outra pessoa continua podendo usar');
    }

    public function test_open_checkouts_count_as_use_for_the_same_person(): void
    {
        $this->plan = $this->makePlan(['price_cents' => 10000]);
        $this->coupon();
        $user = $this->makeUser();

        $pix = $this->attempt($user);
        $this->assertNotNull($pix);
        $this->assertNull($this->attempt($user, PaymentMethod::CreditCard), 'Cobrança pendente reserva o uso da pessoa');

        // Reenviar o mesmo checkout Pix devolve a mesma cobrança (não cria outra nem é recusado).
        $this->assertSame($pix->id, $this->attempt($user)?->id);

        // Expirada, a reserva é liberada.
        $pix->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertNotNull($this->attempt($user, PaymentMethod::CreditCard));
        $this->assertSame(2, Payment::query()->where('user_id', $user->id)->whereNotNull('coupon_id')->count());
    }

    public function test_gifts_bought_with_the_coupon_count_for_the_buyer(): void
    {
        $this->plan = $this->makePlan(['price_cents' => 10000]);
        $this->coupon();
        $user = $this->makeUser();

        $gift = $this->attempt($user, gift: true);
        $this->assertNotNull($gift);
        $this->approve($gift);

        $this->assertNull($this->attempt($user, gift: true), 'Não dá para comprar vários presentes com desconto e resgatar na própria conta');
        $this->assertNull($this->attempt($user));
    }

    public function test_refunded_use_still_counts(): void
    {
        $this->plan = $this->makePlan(['price_cents' => 10000]);
        $this->coupon();
        $user = $this->makeUser();

        $first = $this->attempt($user);
        $this->approve($first);
        app(CheckoutService::class)->apply($first->fresh(), new GatewayPayment((string) $first->provider_payment_id, PaymentStatus::Refunded, $first->amount_cents, $first->uuid));

        $this->assertNull($this->attempt($user));
    }

    public function test_limit_is_configurable_and_can_be_unlimited(): void
    {
        $this->plan = $this->makePlan(['price_cents' => 10000]);
        $coupon = $this->coupon(['max_redemptions_per_user' => 2]);
        $user = $this->makeUser();

        $this->approve($this->attempt($user));
        $this->approve($this->attempt($user, PaymentMethod::CreditCard));
        $this->assertNull($this->attempt($user), 'Terceiro uso com limite 2');

        $coupon->forceFill(['max_redemptions_per_user' => null])->save();
        $this->assertNotNull($this->attempt($user), 'Vazio = sem limite por pessoa');
    }

    public function test_admin_sets_the_per_person_limit_in_the_panel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->makeUser(UserRole::Admin));

        Livewire::test(ManageCoupons::class)
            ->callAction('create', data: ['code' => 'natal', 'type' => 'percent', 'value' => 10, 'max_redemptions_per_user' => 3, 'is_active' => true])
            ->assertHasNoActionErrors();

        $this->assertSame(3, Coupon::query()->where('code', 'NATAL')->sole()->max_redemptions_per_user);
    }
}
