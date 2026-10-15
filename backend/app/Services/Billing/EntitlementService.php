<?php

namespace App\Services\Billing;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\GiftCode;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Fonte única de verdade sobre o acesso Sancta+.
 * Pagamentos aprovados (site) e compras do Google Play terminam aqui.
 */
class EntitlementService
{
    /**
     * Concede/estende o acesso a partir de um pagamento APROVADO do site.
     * Idempotente: um pagamento só gera período uma vez (subscription_id preenchido).
     */
    public function grantFromPayment(Payment $payment): Subscription
    {
        return DB::transaction(function () use ($payment): Subscription {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($payment->subscription_id) {
                return $payment->subscription;
            }

            if ($payment->status !== PaymentStatus::Approved) {
                throw new \LogicException('Somente pagamentos aprovados concedem acesso.');
            }

            // Presente: o comprador recebe um código (GiftService), não o acesso.
            if ($payment->is_gift) {
                throw new \LogicException('Pagamento de presente não concede acesso ao comprador.');
            }

            $plan = $payment->plan;
            $user = $payment->user;

            // Planos pré-pagos se acumulam: se já houver acesso ativo, o novo período começa ao fim dele.
            $start = $this->currentAccessEnd($user) ?? now();

            $subscription = new Subscription([
                'plan_id' => $plan->id,
                'provider' => $payment->provider,
                'status' => SubscriptionStatus::Active,
                'provider_reference' => $payment->provider_payment_id,
                'auto_renew' => false,
                'starts_at' => $start,
                'ends_at' => $plan->periodEndFrom($start),
            ]);
            $subscription->user()->associate($user);
            $subscription->save();

            $payment->forceFill(['subscription_id' => $subscription->id])->save();

            if ($payment->coupon_id) {
                $payment->coupon()->increment('redemptions_count');
            }

            $user->forgetPremiumCache();

            return $subscription;
        });
    }

    /** Cortesia concedida pelo administrador (ex.: benfeitores do app antigo, parcerias). */
    public function grantManual(User $user, ?Plan $plan, int $days, User $admin, ?string $notes = null): Subscription
    {
        $start = $this->currentAccessEnd($user) ?? now();

        $subscription = new Subscription([
            'plan_id' => $plan?->id,
            'provider' => PaymentProvider::Manual,
            'status' => SubscriptionStatus::Active,
            'auto_renew' => false,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays($days),
            'notes' => $notes,
        ]);
        $subscription->user()->associate($user);
        $subscription->forceFill(['granted_by' => $admin->id])->save();

        AuditLog::record('subscription.granted', $subscription, [], ['days' => $days, 'notes' => $notes], $admin);
        $user->forgetPremiumCache();

        return $subscription;
    }

    public function revoke(Subscription $subscription, User $admin, string $reason): void
    {
        $subscription->forceFill([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
            'ends_at' => now(),
            'cancel_reason' => $reason,
            'auto_renew' => false,
        ])->save();

        AuditLog::record('subscription.revoked', $subscription, [], ['reason' => $reason], $admin);
    }

    /** Encerra o acesso gerado por um pagamento estornado/chargeback. */
    public function revokeFromPayment(Payment $payment): void
    {
        $subscription = $payment->subscription;
        if (! $subscription) {
            return;
        }

        $this->endRefunded($subscription, 'Pagamento '.$payment->status->getLabel());
    }

    /**
     * Concede a quem resgatou um código de presente o período do plano comprado.
     * Soma ao acesso atual, como os planos pré-pagos do site. Chame dentro da
     * transação do resgate (GiftService::redeem), com o código já travado.
     */
    public function grantFromGift(GiftCode $gift, User $user): Subscription
    {
        $plan = $gift->plan;
        $payment = $gift->payment;
        $start = $this->currentAccessEnd($user) ?? now();

        $subscription = new Subscription([
            'plan_id' => $plan?->id,
            'provider' => $payment?->provider ?? PaymentProvider::Manual,
            'status' => SubscriptionStatus::Active,
            'provider_reference' => $payment?->provider_payment_id,
            'auto_renew' => false,
            'starts_at' => $start,
            'ends_at' => $plan ? $plan->periodEndFrom($start) : $start->copy()->addMonth(),
            'notes' => 'Presente resgatado (código final '.$gift->last4.')',
            'metadata' => ['gift_code_id' => $gift->id],
        ]);
        $subscription->user()->associate($user);
        $subscription->save();

        $user->forgetPremiumCache();

        return $subscription;
    }

    /** Encerra o período concedido por um presente (estorno/chargeback do pagamento ou revogação no painel). */
    public function revokeGiftSubscription(Subscription $subscription, string $reason): void
    {
        $this->endRefunded($subscription, $reason);
        $subscription->user?->forgetPremiumCache();
    }

    private function endRefunded(Subscription $subscription, string $reason): void
    {
        $subscription->forceFill([
            'status' => SubscriptionStatus::Refunded,
            'ends_at' => now(),
            'canceled_at' => now(),
            'cancel_reason' => mb_substr($reason, 0, 255),
        ])->save();
    }

    private function currentAccessEnd(User $user): ?CarbonInterface
    {
        $active = $user->subscriptions()->entitled()->orderByDesc('ends_at')->first();

        if (! $active) {
            return null;
        }

        // Assinatura vitalícia ou com renovação automática do Google Play: novo período começa agora.
        if ($active->ends_at === null || $active->auto_renew) {
            return null;
        }

        return $active->ends_at;
    }
}
