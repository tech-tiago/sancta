<?php

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Google\ServiceAccountToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Assinaturas compradas dentro do app (Google Play Billing).
 * O app envia o purchaseToken; o servidor consulta a Google Play Developer API
 * (subscriptionsv2) — fonte da verdade — e só então libera o acesso.
 */
class GooglePlayBilling
{
    private const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    public function __construct(private readonly ServiceAccountToken $tokens) {}

    public function isConfigured(): bool
    {
        return filled(config('services.google_play.service_account'));
    }

    /** Identificador ofuscado que o app envia na compra — vincula o token à conta. */
    public static function obfuscatedAccountId(User $user): string
    {
        return hash('sha256', $user->uuid.'|'.config('app.key'));
    }

    public function verifyAndGrant(User $user, string $productId, string $purchaseToken): Subscription
    {
        $data = $this->fetch($purchaseToken);

        // O token precisa ter sido comprado por ESTA conta (evita reaproveitar o token de outra pessoa).
        $accountId = data_get($data, 'externalAccountIdentifiers.obfuscatedExternalAccountId');
        if ($accountId !== null && ! hash_equals(self::obfuscatedAccountId($user), (string) $accountId)) {
            throw new RuntimeException('Esta compra pertence a outra conta.');
        }

        $lineItem = collect($data['lineItems'] ?? [])->firstWhere('productId', $productId) ?? ($data['lineItems'][0] ?? null);
        if (! $lineItem || ($lineItem['productId'] ?? null) !== $productId) {
            throw new RuntimeException('Produto da compra não confere.');
        }

        $subscription = $this->upsert($purchaseToken, $data, $user);

        if (($data['acknowledgementState'] ?? '') === 'ACKNOWLEDGEMENT_STATE_PENDING') {
            $this->acknowledge($productId, $purchaseToken);
        }

        return $subscription;
    }

    /** Chamado pelas notificações em tempo real (RTDN) — reconsulta e atualiza. */
    public function refresh(string $purchaseToken): ?Subscription
    {
        $existing = Subscription::query()->where('purchase_token_hash', hash('sha256', $purchaseToken))->first();
        if (! $existing) {
            return null;
        }

        return $this->upsert($purchaseToken, $this->fetch($purchaseToken), $existing->user);
    }

    private function upsert(string $purchaseToken, array $data, User $user): Subscription
    {
        $lineItem = $data['lineItems'][0] ?? [];
        $basePlanId = data_get($lineItem, 'offerDetails.basePlanId');
        $plan = Plan::query()
            ->where('google_play_product_id', $lineItem['productId'] ?? '')
            ->when($basePlanId, fn ($q) => $q->where('google_play_base_plan_id', $basePlanId))
            ->first();

        $expiry = isset($lineItem['expiryTime']) ? Carbon::parse($lineItem['expiryTime']) : null;
        $status = $this->mapState((string) ($data['subscriptionState'] ?? ''), $data);
        $hash = hash('sha256', $purchaseToken);

        return DB::transaction(function () use ($user, $plan, $expiry, $status, $hash, $purchaseToken, $data, $lineItem): Subscription {
            $subscription = Subscription::query()->where('purchase_token_hash', $hash)->lockForUpdate()->first();

            if ($subscription && $subscription->user_id !== $user->id) {
                throw new RuntimeException('Esta compra já está vinculada a outra conta.');
            }

            $subscription ??= new Subscription;
            $isNew = ! $subscription->exists;

            $subscription->fill([
                'plan_id' => $plan?->id,
                'provider' => PaymentProvider::GooglePlay,
                'status' => $status,
                'provider_reference' => $data['latestOrderId'] ?? null,
                'auto_renew' => (bool) data_get($lineItem, 'autoRenewingPlan.autoRenewEnabled', false),
                'starts_at' => isset($data['startTime']) ? Carbon::parse($data['startTime']) : now(),
                'ends_at' => $expiry,
                'canceled_at' => $status === SubscriptionStatus::Canceled ? ($subscription->canceled_at ?? now()) : null,
                'metadata' => ['subscriptionState' => $data['subscriptionState'] ?? null, 'basePlanId' => data_get($lineItem, 'offerDetails.basePlanId')],
            ]);
            $subscription->user()->associate($user);
            $subscription->forceFill(['purchase_token_hash' => $hash, 'purchase_token' => $purchaseToken])->save();

            // Upgrade/downgrade: o token anterior deixa de valer.
            if (! empty($data['linkedPurchaseToken'])) {
                Subscription::query()
                    ->where('purchase_token_hash', hash('sha256', $data['linkedPurchaseToken']))
                    ->where('id', '!=', $subscription->id)
                    ->update(['status' => SubscriptionStatus::Expired->value, 'ends_at' => now()]);
            }

            // Registra o pedido (orderId) como pagamento para relatórios de receita.
            $orderId = $data['latestOrderId'] ?? null;
            if ($orderId && $plan && in_array($status, [SubscriptionStatus::Active, SubscriptionStatus::Canceled], true)) {
                $exists = Payment::query()->where('provider', PaymentProvider::GooglePlay->value)->where('provider_payment_id', $orderId)->exists();
                if (! $exists) {
                    $payment = new Payment;
                    $payment->forceFill([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'subscription_id' => $subscription->id,
                        'provider' => PaymentProvider::GooglePlay,
                        'method' => PaymentMethod::GooglePlay,
                        'status' => PaymentStatus::Approved,
                        'amount_cents' => $plan->price_cents, // valor de referência (o Google repassa líquido)
                        'currency' => $plan->currency,
                        'provider_payment_id' => $orderId,
                        'paid_at' => now(),
                    ])->save();
                }
            }

            if ($isNew) {
                $user->forgetPremiumCache();
            }

            return $subscription;
        });
    }

    private function mapState(string $state, array $data): SubscriptionStatus
    {
        // Teste grátis: fase atual da oferta (`offerPhase.freeTrial`, API atual) ou a tag "trial" na oferta (Play Console).
        $inTrial = collect($data['lineItems'] ?? [])->contains(fn ($item) => data_get($item, 'offerPhase.freeTrial') !== null
            || in_array('trial', (array) data_get($item, 'offerDetails.offerTags', []), true));

        return match ($state) {
            'SUBSCRIPTION_STATE_ACTIVE' => $inTrial ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => SubscriptionStatus::PastDue,
            'SUBSCRIPTION_STATE_CANCELED' => SubscriptionStatus::Canceled, // acesso até expiryTime
            'SUBSCRIPTION_STATE_ON_HOLD', 'SUBSCRIPTION_STATE_PAUSED', 'SUBSCRIPTION_STATE_EXPIRED' => SubscriptionStatus::Expired,
            'SUBSCRIPTION_STATE_PENDING' => SubscriptionStatus::Pending,
            default => SubscriptionStatus::Pending,
        };
    }

    private function fetch(string $purchaseToken): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Play Billing não configurado no servidor.');
        }

        $package = config('services.google_play.package_name');
        $url = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$package}/purchases/subscriptionsv2/tokens/".rawurlencode($purchaseToken);

        $response = Http::withToken($this->accessToken())->timeout(20)->get($url);

        if ($response->status() === 404 || $response->status() === 410) {
            throw new RuntimeException('Compra não encontrada no Google Play.');
        }
        if ($response->failed()) {
            throw new RuntimeException('Falha ao validar a compra no Google Play.');
        }

        return $response->json();
    }

    private function acknowledge(string $productId, string $purchaseToken): void
    {
        $package = config('services.google_play.package_name');
        $url = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$package}/purchases/subscriptions/"
            .rawurlencode($productId).'/tokens/'.rawurlencode($purchaseToken).':acknowledge';

        Http::withToken($this->accessToken())->timeout(20)->post($url, []);
    }

    private function accessToken(): string
    {
        return $this->tokens->get((string) config('services.google_play.service_account'), self::SCOPE);
    }
}
