<?php

namespace Tests\Feature\Billing;

use App\Enums\GiftCodeStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Coupon;
use App\Models\GiftCode;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Prayer;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\GiftCodeIssued;
use App\Services\Billing\GooglePlayBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Security\Concerns\GoogleTestKeys;
use Tests\TestCase;

/**
 * Fluxos de pagamento de ponta a ponta — cada teste é um fluxo completo, do clique em "Assinar" (ou da
 * compra no Google Play) até o conteúdo Sancta+ liberado (ou bloqueado de novo) NA API DO APP.
 *
 * Nada vai para a rede: o Mercado Pago, o Google Play (API de compras, OAuth e RTDN/Pub-Sub) e as chaves
 * públicas do Google são simulados com Http::fake. O gateway simulado é o mesmo do ambiente local.
 *
 *  1. Pix no site (gateway simulado)            → assinatura → app liberado
 *  2. Cartão no site (gateway simulado)         → assinatura → app liberado
 *  3. Pix com Mercado Pago + webhook assinado   → aprovação consultada no MP → app liberado
 *  4. Cartão (Checkout Pro) + webhook assinado  → pagamento localizado pela referência → app liberado
 *  5. Cupom                                     → valor com desconto calculado no servidor → app liberado
 *  6. Estorno notificado pelo Mercado Pago      → acesso revogado → app bloqueado
 *  7. Google Play: teste grátis (trialing) → renovação (RTDN) → expiração (RTDN) → app bloqueado
 *  8. Presente: compra → código → resgate por outra conta → acesso → estorno → acesso revogado
 */
class PaymentFlowsTest extends TestCase
{
    use GoogleTestKeys, RefreshDatabase;

    private const MP_SECRET = 'segredo-do-webhook-de-teste';

    private const PACKAGE = 'com.maria.app_igreja';

    private const RTDN_AUDIENCE = 'https://sancta.example/api/webhooks/google-play';

    private const RTDN_SENDER = 'pubsub@sancta-teste.iam.gserviceaccount.com';

    /** Pagamentos "existentes" no Mercado Pago simulado (id → dados da API /v1/payments/{id}). */
    private array $mp = [];

    /** Próximo id criado pelo Mercado Pago simulado. */
    private int $mpNextId = 1_000_000_001;

    /** Estado da assinatura no Google Play simulado (subscriptionsv2). */
    private array $play = [];

    private Plan $annual;

    private Prayer $premiumPrayer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->annual = $this->makePlan(['slug' => 'anual', 'name' => 'Anual', 'interval' => 'year', 'price_cents' => 14990, 'max_installments' => 12, 'trial_days' => 7]);
        $this->premiumPrayer = Prayer::query()->create([
            'title' => 'Lectio Divina guiada', 'body' => '<p>Leia.</p><p>Medite.</p><p>Reze e contemple (parte exclusiva).</p>',
            'audio_url' => 'https://cdn.example.com/lectio.mp3', 'is_premium' => true, 'status' => 'published',
        ]);

        config([
            'services.google_play.package_name' => self::PACKAGE,
            'services.google_play.service_account' => $this->serviceAccountJson(),
            'services.google_play.rtdn_audience' => self::RTDN_AUDIENCE,
            'services.google_play.rtdn_service_account_email' => self::RTDN_SENDER,
        ]);

        Http::preventStrayRequests();
        Http::fake($this->fakeGoogleJwks() + [
            'api.mercadopago.com/*' => fn (HttpRequest $request) => $this->mercadoPago($request),
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.teste', 'expires_in' => 3600]),
            'androidpublisher.googleapis.com/*:acknowledge' => Http::response([], 200),
            'androidpublisher.googleapis.com/*' => fn () => Http::response($this->play),
        ]);
    }

    // ───────────────────────── 1–2. Site com o gateway simulado ─────────────────────────

    public function test_1_pix_on_the_site_with_the_simulated_gateway_unlocks_sancta_plus_in_the_app(): void
    {
        $ana = $this->makeUser();
        $this->assertAppLocked($ana);

        // Site: escolhe o plano anual e paga com Pix (o valor vem do servidor, nunca do formulário).
        $this->web($ana)->post('/assinar/anual', ['method' => 'pix', 'accept_terms' => '1', 'price_cents' => 1])->assertRedirect();
        $payment = Payment::query()->where('user_id', $ana->id)->sole();
        $this->assertSame([14990, PaymentStatus::Pending], [$payment->amount_cents, $payment->status]);
        $this->web($ana)->get("/pagamento/{$payment->uuid}")->assertOk()->assertSee('Copiar código Pix')->assertSee('Ambiente de demonstração');

        // Ambiente local: "Aprovar" simula a confirmação do banco.
        $this->web($ana)->post("/pagamento/{$payment->uuid}/simular", ['result' => 'approve'])->assertRedirect("/pagamento/{$payment->uuid}");

        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $subscription = $payment->fresh()->subscription;
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->ends_at->between(now()->addYear()->subDay(), now()->addYear()->addDay()));
        $this->assertAppUnlocked($ana);
    }

    public function test_2_card_on_the_site_with_the_simulated_gateway_unlocks_sancta_plus_in_the_app(): void
    {
        $ana = $this->makeUser();

        // Cartão: o site redireciona para o checkout (aqui, a página simulada local).
        $redirect = $this->web($ana)->post('/assinar/anual', ['method' => 'credit_card', 'accept_terms' => '1'])->assertRedirect();
        $payment = Payment::query()->where('user_id', $ana->id)->sole();
        $this->assertStringContainsString("/pagamento/{$payment->uuid}", $redirect->headers->get('Location'));
        $this->assertAppLocked($ana);

        $this->web($ana)->post("/pagamento/{$payment->uuid}/simular", ['result' => 'approve'])->assertRedirect();

        $this->assertSame('credit_card', $payment->fresh()->method->value);
        $this->assertAppUnlocked($ana);
    }

    // ───────────────────────── 3–4. Mercado Pago (simulado) + webhook assinado ─────────────────────────

    public function test_3_pix_with_mercado_pago_and_signed_webhook_unlocks_the_app(): void
    {
        $this->useMercadoPago();
        $ana = $this->makeUser();

        $this->web($ana)->post('/assinar/anual', ['method' => 'pix', 'accept_terms' => '1'])->assertRedirect();
        $payment = Payment::query()->where('user_id', $ana->id)->sole();

        // O Pix foi criado no MP com o valor do servidor e a nossa referência.
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/payments')
            && $r['transaction_amount'] == 149.9 && $r['payment_method_id'] === 'pix' && $r['external_reference'] === $payment->uuid
            && $r->hasHeader('X-Idempotency-Key', $payment->uuid) && $r['notification_url'] === route('webhooks.mercadopago'));
        $this->assertSame('mercadopago', $payment->provider->value);
        $this->assertNotEmpty($payment->pix_qr_code);
        $this->assertAppLocked($ana);

        // O cliente paga no banco → o MP aprova e avisa o servidor (webhook assinado).
        $this->mp[$payment->provider_payment_id]['status'] = 'approved';
        $this->signedWebhook($payment->provider_payment_id, 'evt-pix-aprovado')->assertOk()->assertJsonPath('status', 'processed');

        // O servidor reconsultou o pagamento no MP antes de liberar.
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'GET' && str_ends_with($r->url(), "/v1/payments/{$payment->provider_payment_id}"));
        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $this->assertAppUnlocked($ana);

        // O MP reenviou a mesma notificação: nada muda (idempotência).
        $this->signedWebhook($payment->provider_payment_id, 'evt-pix-aprovado')->assertOk()->assertJsonPath('status', 'duplicate');
        $this->assertSame(1, $ana->subscriptions()->count());
    }

    public function test_4_card_with_mercado_pago_checkout_pro_and_signed_webhook_unlocks_the_app(): void
    {
        $this->useMercadoPago();
        $ana = $this->makeUser();

        $redirect = $this->web($ana)->post('/assinar/anual', ['method' => 'credit_card', 'accept_terms' => '1']);
        $payment = Payment::query()->where('user_id', $ana->id)->sole();

        // Preferência do Checkout Pro: valor do servidor, até 12x, sem boleto; o cartão é digitado no MP.
        $redirect->assertRedirect('https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-'.$payment->uuid);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/checkout/preferences')
            && $r['items'][0]['unit_price'] == 149.9 && $r['payment_methods']['installments'] === 12 && $r['external_reference'] === $payment->uuid);
        $this->assertNull($payment->provider_payment_id, 'o id do pagamento só existe depois que o cliente paga');

        // Pago em 12x no MP → webhook com um id que o servidor ainda não conhece → localiza pela referência.
        $paymentId = '2000000077';
        $this->mp[$paymentId] = ['id' => (int) $paymentId, 'status' => 'approved', 'transaction_amount' => 149.90, 'external_reference' => $payment->uuid, 'payment_type_id' => 'credit_card', 'installments' => 12];
        $this->signedWebhook($paymentId, 'evt-cartao')->assertOk()->assertJsonPath('status', 'processed');

        $payment->refresh();
        $this->assertSame([PaymentStatus::Approved, $paymentId, 12], [$payment->status, $payment->provider_payment_id, $payment->installments]);
        $this->assertAppUnlocked($ana);
    }

    // ───────────────────────── 5. Cupom ─────────────────────────

    public function test_5_coupon_discount_is_calculated_on_the_server_and_unlocks_the_app(): void
    {
        Coupon::query()->create(['code' => 'OUTUBRO10', 'type' => 'percent', 'value' => 10, 'is_active' => true, 'expires_at' => now()->addMonth()]);
        $ana = $this->makeUser();

        $this->web($ana)->post('/assinar/anual', ['method' => 'pix', 'coupon' => 'outubro10', 'accept_terms' => '1', 'amount' => 1])->assertRedirect();
        $payment = Payment::query()->where('user_id', $ana->id)->sole();
        $this->assertSame([13491, 1499], [$payment->amount_cents, $payment->discount_cents]);
        $this->assertSame('OUTUBRO10', $payment->coupon->code);

        $this->web($ana)->post("/pagamento/{$payment->uuid}/simular", ['result' => 'approve']);

        $this->assertAppUnlocked($ana);
    }

    // ───────────────────────── 6. Estorno ─────────────────────────

    public function test_6_refund_notified_by_mercado_pago_revokes_access_in_the_app(): void
    {
        $this->useMercadoPago();
        $ana = $this->makeUser();
        $this->web($ana)->post('/assinar/anual', ['method' => 'pix', 'accept_terms' => '1']);
        $payment = Payment::query()->where('user_id', $ana->id)->sole();
        $this->mp[$payment->provider_payment_id]['status'] = 'approved';
        $this->signedWebhook($payment->provider_payment_id, 'evt-1');
        $this->assertAppUnlocked($ana);

        // Estorno feito no painel do MP → nova notificação → o servidor confere e revoga o acesso.
        $this->mp[$payment->provider_payment_id]['status'] = 'refunded';
        $this->signedWebhook($payment->provider_payment_id, 'evt-2-estorno')->assertOk()->assertJsonPath('status', 'processed');

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->refunded_at);
        $this->assertAppLocked($ana);

        // Uma notificação atrasada de "aprovado" não devolve o acesso.
        $this->mp[$payment->provider_payment_id]['status'] = 'approved';
        $this->signedWebhook($payment->provider_payment_id, 'evt-3-atrasado');
        $this->assertAppLocked($ana);
    }

    // ───────────────────────── 7. Google Play ─────────────────────────

    public function test_7_google_play_free_trial_then_renewal_and_expiry_via_rtdn(): void
    {
        $ana = $this->makeUser();
        $token = 'token-de-compra-do-google-play-0123456789';

        // O app pega o id ofuscado da conta em /config e o envia ao Google Play na compra.
        $accountId = $this->api('GET', '/config', $ana)->json('billing.obfuscated_account_id');
        $this->assertSame(GooglePlayBilling::obfuscatedAccountId($ana), $accountId);

        // Compra no app com o teste grátis de 7 dias (oferta com a tag "trial").
        $this->play = $this->playState('SUBSCRIPTION_STATE_ACTIVE', now()->addDays(7), $accountId, trial: true, order: 'GPA.0001-trial', ack: 'ACKNOWLEDGEMENT_STATE_PENDING');
        $this->api('POST', '/billing/google-play/verify', $ana, ['product_id' => 'sancta_plus', 'purchase_token' => $token])
            ->assertOk()->assertJsonPath('subscription.status', 'trialing')->assertJsonPath('user.is_premium', true);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), ':acknowledge'));
        $this->api('GET', '/me', $ana)->assertJsonPath('subscription.status', 'trialing')->assertJsonPath('subscription.provider', 'google_play');
        $this->assertAppUnlocked($ana);
        $this->assertSame(0, Payment::query()->count(), 'teste grátis não gera receita');

        // Outra conta não consegue usar o mesmo token (a compra pertence à Ana).
        $this->api('POST', '/billing/google-play/verify', $this->makeUser(), ['product_id' => 'sancta_plus', 'purchase_token' => $token])->assertUnprocessable();

        // Fim do teste: o Google cobra e avisa (RTDN SUBSCRIPTION_RENEWED) → assinatura ativa, pedido registrado.
        $this->play = $this->playState('SUBSCRIPTION_STATE_ACTIVE', now()->addYear(), $accountId, order: 'GPA.0001-anual');
        $this->rtdn(2, $token, 'msg-renovou')->assertOk()->assertJsonPath('status', 'processed');
        $subscription = Subscription::query()->where('user_id', $ana->id)->sole();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('GPA.0001-anual', Payment::query()->sole()->provider_payment_id);
        $this->assertAppUnlocked($ana);

        // Notificação com token OIDC forjado é recusada; repetida é ignorada.
        $this->withHeader('Authorization', 'Bearer '.$this->googleJwt(['iss' => 'https://accounts.google.com', 'aud' => self::RTDN_AUDIENCE, 'email' => self::RTDN_SENDER, 'email_verified' => true], 'atacante'))
            ->postJson('/api/webhooks/google-play', $this->rtdnBody(13, $token, 'msg-forjada'))->assertUnauthorized();
        $this->rtdn(2, $token, 'msg-renovou')->assertJsonPath('status', 'duplicate');

        // Cancelou e o período acabou (RTDN SUBSCRIPTION_EXPIRED) → o app volta a bloquear.
        $this->play = $this->playState('SUBSCRIPTION_STATE_EXPIRED', now()->subMinute(), $accountId, order: 'GPA.0001-anual');
        $this->rtdn(13, $token, 'msg-expirou')->assertOk();
        $this->assertSame(SubscriptionStatus::Expired, $subscription->fresh()->status);
        $this->assertAppLocked($ana);
    }

    public function test_7b_google_play_trial_is_also_recognized_by_the_offer_phase_without_the_tag(): void
    {
        $ana = $this->makeUser();
        $this->play = $this->playState('SUBSCRIPTION_STATE_ACTIVE', now()->addDays(7), GooglePlayBilling::obfuscatedAccountId($ana));
        $this->play['lineItems'][0]['offerPhase'] = ['freeTrial' => new \stdClass];

        $this->api('POST', '/billing/google-play/verify', $ana, ['product_id' => 'sancta_plus', 'purchase_token' => 'token-de-compra-sem-tag-0123456789'])
            ->assertOk()->assertJsonPath('subscription.status', 'trialing');
    }

    // ───────────────────────── 8. Presente ─────────────────────────

    public function test_8_gift_bought_redeemed_by_another_account_unlocks_the_app_and_refund_revokes_it(): void
    {
        $this->useMercadoPago();
        $buyer = $this->makeUser(attributes: ['email' => 'madrinha@exemplo.com']);
        $friend = $this->makeUser(attributes: ['email' => 'afilhada@exemplo.com']);

        // Compra do presente no site (Pix no MP) → aprovação pelo webhook → código por e-mail ao comprador.
        $this->web($buyer)->post('/assinar/anual', ['method' => 'pix', 'gift' => '1', 'accept_terms' => '1'])->assertRedirect();
        $payment = Payment::query()->where('user_id', $buyer->id)->sole();
        $this->assertTrue($payment->is_gift);
        $this->mp[$payment->provider_payment_id]['status'] = 'approved';
        $this->signedWebhook($payment->provider_payment_id, 'evt-presente')->assertJsonPath('status', 'processed');

        $code = null;
        Notification::assertSentTo($buyer, GiftCodeIssued::class, function (GiftCodeIssued $n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->assertMatchesRegularExpression('/^SANC-\w{4}-\w{4}-\w{4}$/', $code);
        $this->assertAppLocked($buyer); // quem compra o presente não ganha o acesso

        // A afilhada resgata no site (digitando em minúsculas e com espaços) → o acesso aparece no app.
        $this->web($friend)->post('/resgatar', ['code' => strtolower(str_replace('-', ' ', $code))])->assertRedirect(route('account.show'))->assertSessionHasNoErrors();
        $this->assertSame(GiftCodeStatus::Redeemed, GiftCode::query()->sole()->status);
        $this->assertAppUnlocked($friend);
        $this->api('GET', '/me', $friend)->assertJsonPath('subscription.plan', 'Anual');

        // O mesmo código não vale de novo.
        $this->web($this->makeUser())->post('/resgatar', ['code' => $code])->assertSessionHasErrors('code');

        // Estorno do presente no MP → o período de quem resgatou é revogado.
        $this->mp[$payment->provider_payment_id]['status'] = 'refunded';
        $this->signedWebhook($payment->provider_payment_id, 'evt-presente-estorno')->assertJsonPath('status', 'processed');
        $this->assertAppLocked($friend);
    }

    // ───────────────────────── Infraestrutura ─────────────────────────

    /** Navegador do site logado como $user (sem herdar o usuário de chamadas anteriores). */
    private function web(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user);
    }

    /** Chamada como o app faz: token Bearer do aparelho, sem sessão. */
    private function api(string $method, string $uri, User $user, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $user->forgetPremiumCache();

        return $this->json($method, '/api/v1'.$uri, $data, ['Authorization' => 'Bearer '.$user->createToken('Android', ['app'], now()->addDay())->plainTextToken]);
    }

    private function assertAppUnlocked(User $user): void
    {
        $this->api('GET', '/me', $user)->assertOk()->assertJsonPath('is_premium', true);
        $this->api('GET', '/home', $user)->assertJsonPath('is_premium', true);
        $prayer = $this->api('GET', "/prayers/{$this->premiumPrayer->slug}", $user)->assertJsonPath('locked', false)->assertJsonPath('audio_url', 'https://cdn.example.com/lectio.mp3');
        $this->assertCount(3, $prayer->json('blocks'), 'oração Sancta+ completa');
    }

    private function assertAppLocked(User $user): void
    {
        $this->api('GET', '/me', $user)->assertOk()->assertJsonPath('is_premium', false);
        $prayer = $this->api('GET', "/prayers/{$this->premiumPrayer->slug}", $user)->assertJsonPath('locked', true)->assertJsonPath('audio_url', null);
        $this->assertStringNotContainsString('parte exclusiva', $prayer->getContent());
    }

    private function useMercadoPago(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-0000-token-de-teste', 'services.mercadopago.webhook_secret' => self::MP_SECRET]);
    }

    /** Mercado Pago simulado: cria Pix/preferências e devolve o estado atual de cada pagamento. */
    private function mercadoPago(HttpRequest $request)
    {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($request->method() === 'POST' && $path === '/v1/payments') {
            $id = (string) $this->mpNextId++;
            $this->mp[$id] = ['id' => (int) $id, 'status' => 'pending', 'transaction_amount' => $request['transaction_amount'], 'external_reference' => $request['external_reference'], 'payment_type_id' => 'bank_transfer', 'installments' => 1];

            return Http::response($this->mp[$id] + ['point_of_interaction' => ['transaction_data' => ['qr_code' => '00020126580014br.gov.bcb.pix-teste-'.$id, 'qr_code_base64' => base64_encode('png')]]], 201);
        }

        if ($request->method() === 'POST' && $path === '/checkout/preferences') {
            $ref = $request['external_reference'];

            return Http::response(['id' => "pref-{$ref}", 'init_point' => "https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-{$ref}"], 201);
        }

        if ($request->method() === 'GET' && preg_match('#^/v1/payments/(\d+)$#', $path, $m) && isset($this->mp[$m[1]])) {
            return Http::response($this->mp[$m[1]]);
        }

        return Http::response(['message' => 'not found'], 404);
    }

    /** Notificação do Mercado Pago assinada com o segredo (x-signature), como o MP envia. */
    private function signedWebhook(string $paymentId, string $eventId): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $ts = time();
        $requestId = 'req-'.$eventId;
        $signature = hash_hmac('sha256', "id:{$paymentId};request-id:{$requestId};ts:{$ts};", self::MP_SECRET);

        return $this->withHeaders(['x-signature' => "ts={$ts},v1={$signature}", 'x-request-id' => $requestId])
            ->postJson("/api/webhooks/mercadopago?data.id={$paymentId}&type=payment", ['id' => $eventId, 'type' => 'payment', 'action' => 'payment.updated', 'data' => ['id' => $paymentId]]);
    }

    /** Resposta da Google Play Developer API (purchases.subscriptionsv2.get). */
    private function playState(string $state, $expiry, string $accountId, bool $trial = false, string $order = 'GPA.0001', string $ack = 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED'): array
    {
        return [
            'subscriptionState' => $state,
            'acknowledgementState' => $ack,
            'latestOrderId' => $order,
            'startTime' => now()->toIso8601String(),
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => $accountId],
            'lineItems' => [[
                'productId' => 'sancta_plus',
                'expiryTime' => $expiry->toIso8601String(),
                'autoRenewingPlan' => ['autoRenewEnabled' => $state === 'SUBSCRIPTION_STATE_ACTIVE'],
                'offerDetails' => array_filter(['basePlanId' => 'anual', 'offerId' => $trial ? 'teste-gratis-7-dias' : null, 'offerTags' => $trial ? ['trial'] : null]),
            ]],
        ];
    }

    private function rtdnBody(int $type, string $token, string $messageId): array
    {
        $data = ['version' => '1.0', 'packageName' => self::PACKAGE, 'eventTimeMillis' => (string) (time() * 1000), 'subscriptionNotification' => ['version' => '1.0', 'notificationType' => $type, 'purchaseToken' => $token, 'subscriptionId' => 'sancta_plus']];

        return ['message' => ['messageId' => $messageId, 'data' => base64_encode(json_encode($data))], 'subscription' => 'projects/sancta/subscriptions/play-rtdn'];
    }

    /** Notificação em tempo real do Google Play, entregue pelo Pub/Sub com token OIDC assinado pelo Google. */
    private function rtdn(int $type, string $token, string $messageId): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $jwt = $this->googleJwt(['iss' => 'https://accounts.google.com', 'aud' => self::RTDN_AUDIENCE, 'email' => self::RTDN_SENDER, 'email_verified' => true]);

        return $this->withHeader('Authorization', 'Bearer '.$jwt)->postJson('/api/webhooks/google-play', $this->rtdnBody($type, $token, $messageId));
    }
}
