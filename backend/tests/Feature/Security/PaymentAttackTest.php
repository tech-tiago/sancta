<?php

namespace Tests\Feature\Security;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Billing\GooglePlayBilling;
use App\Services\Billing\PaymentGatewayManager;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Security\Concerns\GoogleTestKeys;
use Tests\TestCase;

/**
 * Pentest — pagamentos: tentativas que o sistema RESISTIU.
 * Google Play (token de outra conta, conta ofuscada divergente, produto trocado, compra pendente),
 * pagamento simulado fora do ambiente local, auto-concessão de Sancta+ e IDOR no checkout.
 */
class PaymentAttackTest extends TestCase
{
    use GoogleTestKeys, RefreshDatabase;

    private const PACKAGE = 'com.maria.app_igreja';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_play.service_account' => $this->serviceAccountJson(), 'services.google_play.package_name' => self::PACKAGE]);
    }

    private function fakePlay(array $purchase): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => ['access_token' => 'ya29.teste', 'expires_in' => 3600],
            'androidpublisher.googleapis.com/*:acknowledge' => Http::response([], 200),
            'androidpublisher.googleapis.com/*' => array_replace_recursive([
                'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
                'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
                'latestOrderId' => 'GPA.1234-5678',
                'startTime' => now()->toIso8601String(),
                'lineItems' => [['productId' => 'sancta_plus', 'expiryTime' => now()->addMonth()->toIso8601String(), 'offerDetails' => ['basePlanId' => 'anual']]],
            ], $purchase),
        ]);
    }

    private function verify($user, string $token = 'token-de-compra-0123456789', string $product = 'sancta_plus')
    {
        return $this->withHeaders($this->bearer($user))->postJson('/api/v1/billing/google-play/verify', ['product_id' => $product, 'purchase_token' => $token]);
    }

    public function test_purchase_made_by_another_account_is_rejected(): void
    {
        $this->makePlan();
        $owner = $this->makeUser();
        $attacker = $this->makeUser();
        $this->fakePlay(['externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => GooglePlayBilling::obfuscatedAccountId($owner)]]);

        $this->verify($attacker)->assertUnprocessable();
        $this->assertFalse($attacker->fresh()->isPremium());
        $this->assertSame(0, Subscription::query()->count());
    }

    public function test_token_already_bound_cannot_be_claimed_by_another_account(): void
    {
        $this->makePlan();
        $owner = $this->makeUser();
        $attacker = $this->makeUser();
        $this->fakePlay([]); // compra sem identificador ofuscado (ex.: resgatada fora do app)

        $this->verify($owner)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->verify($attacker)->assertUnprocessable();

        $this->assertTrue($owner->fresh()->isPremium());
        $this->assertFalse($attacker->fresh()->isPremium());
    }

    public function test_product_mismatch_does_not_grant_access(): void
    {
        $this->makePlan();
        $this->makePlan(['slug' => 'outro', 'google_play_product_id' => 'outro_produto']);
        $user = $this->makeUser();

        // Envia um produto, mas o token é de outro.
        $this->fakePlay(['lineItems' => [['productId' => 'outro_produto']]]);
        $this->verify($user, product: 'sancta_plus')->assertUnprocessable();
        $this->assertFalse($user->fresh()->isPremium());
    }

    public function test_pending_purchase_does_not_grant_access(): void
    {
        $this->makePlan();
        $user = $this->makeUser();

        // Compra ainda pendente (pagamento em processamento no Play).
        $this->fakePlay(['subscriptionState' => 'SUBSCRIPTION_STATE_PENDING']);
        $this->verify($user)->assertOk();
        $this->assertFalse($user->fresh()->isPremium());
    }

    public function test_simulated_gateway_is_never_available_in_production(): void
    {
        config(['sancta.payments.fake_enabled' => true]);
        $this->app['env'] = 'production';
        // Em "produção" o trustHosts define a lista estática do Symfony; limpa ao final para não vazar a outros testes.
        $this->beforeApplicationDestroyed(fn () => Request::setTrustedHosts([]));

        $this->assertFalse(app(PaymentGatewayManager::class)->fakeAllowed());

        $user = $this->makeUser();
        $payment = new Payment;
        $payment->forceFill(['user_id' => $user->id, 'plan_id' => $this->makePlan()->id, 'provider' => 'fake', 'method' => 'pix', 'status' => 'pending', 'amount_cents' => 100, 'expires_at' => now()->addHour()])->save();

        // CSRF não é o alvo aqui (em "produção" o teste passaria a exigi-lo): isola a regra do simulador.
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user)->post("/pagamento/{$payment->uuid}/simular", ['result' => 'approve'])->assertNotFound();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertFalse($user->fresh()->isPremium());
    }

    public function test_user_cannot_grant_premium_or_staff_fields_to_himself(): void
    {
        $user = $this->makeUser();

        $this->withHeaders($this->bearer($user))->patchJson('/api/v1/me', [
            'name' => 'Nome', 'is_premium' => true, 'role' => 'super_admin', 'status' => 'active',
            'email_verified_at' => now()->toIso8601String(), 'email' => 'outro@exemplo.com',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertFalse($fresh->isPremium());
        $this->assertSame('user', $fresh->role->value);
        $this->assertNotSame('outro@exemplo.com', $fresh->email);
    }

    public function test_other_users_pending_pix_is_not_reused_nor_visible(): void
    {
        $plan = $this->makePlan();
        $victim = $this->makeUser();
        $attacker = $this->makeUser();

        $this->actingAs($victim)->post("/assinar/{$plan->slug}", ['method' => 'pix', 'accept_terms' => '1']);
        $victimPayment = Payment::query()->where('user_id', $victim->id)->firstOrFail();

        $this->actingAs($attacker)->post("/assinar/{$plan->slug}", ['method' => 'pix', 'accept_terms' => '1']);
        $attackerPayment = Payment::query()->where('user_id', $attacker->id)->firstOrFail();

        $this->assertNotSame($victimPayment->id, $attackerPayment->id);
        $this->actingAs($attacker)->get("/pagamento/{$victimPayment->uuid}")->assertNotFound();
        $this->actingAs($attacker)->getJson("/pagamento/{$victimPayment->uuid}/situacao")->assertNotFound();
        $this->actingAs($attacker)->post("/pagamento/{$victimPayment->uuid}/simular", ['result' => 'approve'])->assertNotFound();
        $this->assertSame(PaymentStatus::Pending, $victimPayment->fresh()->status);
    }
}
