<?php

namespace Tests\Feature\Billing;

use App\Enums\GiftCodeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\GiftCodes\Pages\ListGiftCodes;
use App\Models\AuditLog;
use App\Models\GiftCode;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\GiftCodeIssued;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\Gateways\GatewayPayment;
use App\Services\Billing\GiftCodeException;
use App\Services\Billing\GiftService;
use App\Support\GiftCodeFormat;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** "Presentear Sancta+": compra (gateway simulado), código, resgate, estorno, novo código e painel. */
class GiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function yearlyPlan(): Plan
    {
        return $this->makePlan(['slug' => 'anual', 'name' => 'Anual', 'interval' => 'year', 'price_cents' => 14990]);
    }

    /** Compra um presente pelo site e aprova no gateway simulado. Devolve [pagamento, código em claro]. */
    private function buyGift(User $buyer, ?Plan $plan = null): array
    {
        $plan ??= $this->yearlyPlan();

        $this->actingAs($buyer)->post("/assinar/{$plan->slug}", [
            'method' => 'pix', 'gift' => '1', 'accept_terms' => '1', 'amount' => 1, 'price_cents' => 1,
        ])->assertRedirect();

        $payment = Payment::query()->where('user_id', $buyer->id)->latest('id')->firstOrFail();
        $this->actingAs($buyer)->post("/pagamento/{$payment->uuid}/simular", ['result' => 'approve'])->assertRedirect();

        $code = null;
        Notification::assertSentTo($buyer, GiftCodeIssued::class, function (GiftCodeIssued $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return [$payment->fresh(), $code];
    }

    private function refund(Payment $payment, PaymentStatus $status = PaymentStatus::Refunded): void
    {
        app(CheckoutService::class)->apply($payment, new GatewayPayment((string) $payment->provider_payment_id, $status, $payment->amount_cents, $payment->uuid));
    }

    public function test_gift_purchase_generates_a_code_instead_of_giving_access(): void
    {
        $buyer = $this->makeUser();
        [$payment, $code] = $this->buyGift($buyer);

        $this->assertTrue($payment->is_gift);
        $this->assertSame(14990, $payment->amount_cents, 'Valor calculado no servidor');
        $this->assertSame(PaymentStatus::Approved, $payment->status);
        $this->assertNull($payment->subscription_id);
        $this->assertFalse($buyer->fresh()->isPremium(), 'O comprador não ganha o acesso');

        $this->assertMatchesRegularExpression('/^SANC-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/', $code);

        $gift = GiftCode::query()->sole();
        $canonical = GiftCodeFormat::canonical($code);
        $this->assertSame(hash_hmac('sha256', 'SANC-'.$canonical, config('app.key')), $gift->code_hash);
        $this->assertSame(substr($canonical, -4), $gift->last4);
        $this->assertSame(GiftCodeStatus::Available, $gift->status);
        $this->assertSame($buyer->id, $gift->purchaser_id);
        $this->assertTrue($gift->expires_at->between(now()->addMonths(12)->subDay(), now()->addMonths(12)->addDay()));

        // O código em claro não fica no banco.
        $this->assertStringNotContainsString($canonical, json_encode(GiftCode::query()->get()->makeVisible('code_hash')->toArray()));
        $this->assertStringNotContainsString($canonical, json_encode(Payment::query()->get()->toArray()));
    }

    public function test_code_is_shown_once_on_the_payment_page(): void
    {
        $buyer = $this->makeUser();
        [$payment, $code] = $this->buyGift($buyer);

        $this->actingAs($buyer)->get("/pagamento/{$payment->uuid}")
            ->assertOk()
            ->assertSee($code)
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($buyer)->get("/pagamento/{$payment->uuid}")
            ->assertOk()
            ->assertDontSee($code)
            ->assertSee('Enviamos o código do presente');
    }

    public function test_approval_is_idempotent(): void
    {
        $buyer = $this->makeUser();
        $plan = $this->yearlyPlan();
        $service = app(CheckoutService::class);
        $payment = $service->start($buyer, $plan, PaymentMethod::Pix, null, null, true);
        $remote = new GatewayPayment((string) $payment->provider_payment_id, PaymentStatus::Approved, $payment->amount_cents, $payment->uuid);

        $service->apply($payment, $remote);
        $service->apply($payment->fresh(), $remote);
        app(GiftService::class)->issueForPayment($payment->fresh());

        $this->assertSame(1, GiftCode::query()->count());
        Notification::assertSentToTimes($buyer, GiftCodeIssued::class, 1);
        $this->assertSame(0, $buyer->subscriptions()->count());
    }

    public function test_gift_and_regular_pending_pix_are_never_reused_for_each_other(): void
    {
        $buyer = $this->makeUser();
        $plan = $this->yearlyPlan();
        $service = app(CheckoutService::class);

        $own = $service->start($buyer, $plan, PaymentMethod::Pix, null);
        $gift = $service->start($buyer, $plan, PaymentMethod::Pix, null, null, true);

        $this->assertNotSame($own->id, $gift->id);
        $this->assertFalse((bool) $own->is_gift);
        $this->assertTrue($gift->is_gift);
    }

    public function test_gift_payment_never_grants_access_to_the_buyer(): void
    {
        $payment = new Payment;
        $payment->forceFill([
            'user_id' => $this->makeUser()->id, 'plan_id' => $this->yearlyPlan()->id, 'provider' => 'fake', 'method' => 'pix',
            'status' => 'approved', 'amount_cents' => 14990, 'is_gift' => true,
        ])->save();

        $this->expectException(\LogicException::class);
        app(EntitlementService::class)->grantFromPayment($payment);
    }

    public function test_redeem_adds_the_period_to_current_access(): void
    {
        [, $code] = $this->buyGift($this->makeUser());
        $friend = $this->makeUser();
        $current = $this->makePremium($friend); // acesso até +1 mês
        $previousEnd = $current->ends_at->copy();

        $this->actingAs($friend)->post('/resgatar', ['code' => strtolower(str_replace('-', ' ', $code))])
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status');

        $gift = GiftCode::query()->sole();
        $this->assertSame(GiftCodeStatus::Redeemed, $gift->status);
        $this->assertSame($friend->id, $gift->redeemed_by);
        $this->assertNotNull($gift->redeemed_at);

        $granted = $gift->subscription;
        $this->assertSame($friend->id, $granted->user_id);
        $this->assertTrue($granted->starts_at->equalTo($previousEnd), 'Soma ao acesso atual');
        $this->assertTrue($granted->ends_at->equalTo($previousEnd->copy()->addYearNoOverflow()));
        $this->assertTrue($friend->fresh()->isPremium());
    }

    public function test_invalid_codes_get_the_same_generic_message(): void
    {
        $this->buyGift($this->makeUser());
        $user = $this->makeUser();

        foreach (['SANC-0000-0000-0000', 'nada-a-ver', 'SANC-AAAA-BBBB-CCC', ''] as $attempt) {
            $response = $this->actingAs($user)->from('/resgatar')->post('/resgatar', ['code' => $attempt]);
            $response->assertRedirect('/resgatar');
            if ($attempt !== '') {
                $response->assertSessionHasErrors(['code' => GiftCodeException::GENERIC]);
            }
        }

        $this->assertSame(GiftCodeStatus::Available, GiftCode::query()->sole()->status);
        $this->assertFalse($user->fresh()->isPremium());
    }

    public function test_expired_code_cannot_be_redeemed(): void
    {
        [, $code] = $this->buyGift($this->makeUser());
        $this->travel(13)->months();
        $user = $this->makeUser();

        $this->actingAs($user)->from('/resgatar')->post('/resgatar', ['code' => $code])
            ->assertSessionHasErrors(['code' => GiftCodeException::GENERIC]);

        $this->assertSame(GiftCodeStatus::Expired, GiftCode::query()->sole()->status);
        $this->assertFalse($user->fresh()->isPremium());
    }

    public function test_code_can_only_be_used_once_even_with_concurrent_attempts(): void
    {
        [, $code] = $this->buyGift($this->makeUser());
        [$first, $second] = [$this->makeUser(), $this->makeUser()];
        $service = app(GiftService::class);

        $results = [];
        foreach ([$first, $second] as $user) {
            try {
                $service->redeem($user, $code);
                $results[] = 'ok';
            } catch (GiftCodeException) {
                $results[] = 'recusado';
            }
        }

        $this->assertSame(['ok', 'recusado'], $results);
        $this->assertTrue($first->fresh()->isPremium());
        $this->assertFalse($second->fresh()->isPremium());
        $this->assertSame(1, GiftCode::query()->where('status', 'redeemed')->count());
        $this->assertSame(0, $second->subscriptions()->count());
    }

    public function test_refund_of_unredeemed_gift_revokes_the_code(): void
    {
        [$payment, $code] = $this->buyGift($this->makeUser());

        $this->refund($payment);

        $this->assertSame(GiftCodeStatus::Revoked, GiftCode::query()->sole()->status);
        $this->expectException(GiftCodeException::class);
        app(GiftService::class)->redeem($this->makeUser(), $code);
    }

    public function test_chargeback_after_redemption_revokes_the_redeemers_period(): void
    {
        [$payment, $code] = $this->buyGift($this->makeUser());
        $friend = $this->makeUser();
        app(GiftService::class)->redeem($friend, $code);
        $this->assertTrue($friend->fresh()->isPremium());

        $this->refund($payment, PaymentStatus::ChargedBack);

        $gift = GiftCode::query()->sole();
        $this->assertSame(GiftCodeStatus::Revoked, $gift->status);
        $this->assertFalse($friend->fresh()->isPremium());
        $this->assertSame('refunded', $gift->subscription->status->value);
    }

    public function test_buyer_regenerates_the_code_by_email_only(): void
    {
        $buyer = $this->makeUser();
        [, $oldCode] = $this->buyGift($buyer);
        $gift = GiftCode::query()->sole();

        // Sem confirmar: recusado.
        $this->actingAs($buyer)->post(route('account.gifts.regenerate', $gift->uuid))->assertSessionHasErrors('confirm');

        $response = $this->actingAs($buyer)->post(route('account.gifts.regenerate', $gift->uuid), ['confirm' => '1']);
        $response->assertRedirect()->assertSessionHas('status');

        $newCode = null;
        Notification::assertSentTo($buyer, GiftCodeIssued::class, function (GiftCodeIssued $n) use (&$newCode) {
            if ($n->regenerated) {
                $newCode = $n->code;
            }

            return $n->regenerated;
        });
        $this->assertNotSame($oldCode, $newCode);
        $this->assertStringNotContainsString((string) $newCode, (string) $response->getContent(), 'O novo código só vai por e-mail');
        $this->assertTrue(AuditLog::query()->where('event', 'gift_code.regenerated')->exists());

        $friend = $this->makeUser();
        try {
            app(GiftService::class)->redeem($friend, $oldCode);
            $this->fail('O código antigo deveria ter deixado de valer.');
        } catch (GiftCodeException) {
        }
        app(GiftService::class)->redeem($friend, $newCode);
        $this->assertTrue($friend->fresh()->isPremium());

        // Já resgatado: não gera mais código.
        $this->actingAs($buyer)->post(route('account.gifts.regenerate', $gift->uuid), ['confirm' => '1'])->assertSessionHasErrors('gift');
    }

    public function test_only_the_buyer_can_regenerate(): void
    {
        $this->buyGift($this->makeUser());
        $gift = GiftCode::query()->sole();

        $this->actingAs($this->makeUser())->post(route('account.gifts.regenerate', $gift->uuid), ['confirm' => '1'])->assertNotFound();
    }

    public function test_redeem_is_rate_limited(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post('/resgatar', ['code' => 'SANC-0000-0000-000'.$i])->assertRedirect();
        }

        $this->actingAs($user)->post('/resgatar', ['code' => 'SANC-0000-0000-0009'])->assertStatus(429);
    }

    public function test_routes_are_protected_and_there_is_no_redeem_in_the_app_api(): void
    {
        $this->get('/presentear')->assertOk()->assertSee('Presentear');
        $this->get('/resgatar')->assertRedirect('/entrar');
        $this->post('/resgatar', ['code' => 'SANC-0000-0000-0000'])->assertRedirect('/entrar');
        $this->post('/minha-conta/presentes/'.Str::uuid().'/novo-codigo', ['confirm' => '1'])->assertRedirect('/entrar');

        // Política do Google Play: nenhuma rota de presente/resgate na API do app.
        $apiGiftRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/') && preg_match('/gift|presente|resgat/i', $r->uri()));
        $this->assertCount(0, $apiGiftRoutes);
    }

    public function test_redeem_form_requires_csrf(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->withMiddleware()->app->instance('env', 'production-like');
        // Fora de "testing" o TrustHosts grava hosts confiáveis estáticos: limpa ao final para não vazar a outros testes.
        $this->beforeApplicationDestroyed(fn () => Request::setTrustedHosts([]));

        $response = $this->call('POST', '/resgatar', ['code' => 'SANC-0000-0000-0000'], [], [], ['HTTP_ACCEPT' => 'text/html']);

        $this->assertSame(419, $response->getStatusCode());
    }

    public function test_account_lists_gifts_without_the_code(): void
    {
        $buyer = $this->makeUser();
        [, $code] = $this->buyGift($buyer);
        $gift = GiftCode::query()->sole();

        $this->actingAs($buyer)->get('/minha-conta')
            ->assertOk()
            ->assertSee('Presentes que comprei')
            ->assertSee('SANC-••••-••••-'.$gift->last4)
            ->assertDontSee($code);
    }

    public function test_panel_is_read_only_admin_only_and_revoke_is_audited(): void
    {
        $buyer = $this->makeUser();
        [, $code] = $this->buyGift($buyer);
        $gift = GiftCode::query()->sole();
        $base = '/'.config('sancta.admin.path').'/presentes';

        $this->flushSession();
        $this->actingAs($this->makeUser(UserRole::Editor))->get($base)->assertForbidden();

        $this->flushSession();
        $admin = $this->makeUser(UserRole::Admin);
        $this->actingAs($admin)->get($base)
            ->assertOk()
            ->assertSee('SANC-••••-••••-'.$gift->last4)
            ->assertDontSee($code)
            ->assertDontSee($gift->code_hash);

        $this->assertFalse($admin->can('create', GiftCode::class));
        $this->assertFalse($admin->can('update', $gift));
        $this->assertFalse($admin->can('delete', $gift));
        $this->assertTrue($admin->can('revoke', $gift));
        $this->assertFalse($this->makeUser(UserRole::Editor)->can('revoke', $gift));

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListGiftCodes::class)
            ->callTableAction('revoke', $gift, data: ['reason' => 'Pedido do comprador'])
            ->assertHasNoTableActionErrors();

        $gift->refresh();
        $this->assertSame(GiftCodeStatus::Revoked, $gift->status);
        $this->assertSame('Pedido do comprador', $gift->revoked_reason);
        $log = AuditLog::query()->where('event', 'gift_code.revoked')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringNotContainsString(GiftCodeFormat::canonical($code), json_encode($log->toArray()));
        $this->assertFalse($admin->can('revoke', $gift), 'Já revogado');
    }

    public function test_code_format_helpers(): void
    {
        $codes = collect(range(1, 50))->map(fn () => GiftCodeFormat::generate());

        $this->assertCount(50, $codes->unique());
        $codes->each(fn ($c) => $this->assertNotNull(GiftCodeFormat::canonical($c)));
        $this->assertSame('ABCD1234EFGH', GiftCodeFormat::canonical(' sanc abcd-1234 efgh '));
        $this->assertSame('0110ABCDEFGH', GiftCodeFormat::canonical('OILO-ABCD-EFGH'));
        $this->assertNull(GiftCodeFormat::canonical('SANC-ABCD-1234-EFGU')); // U não existe no Crockford
        $this->assertNull(GiftCodeFormat::canonical('ABC'));
    }
}
