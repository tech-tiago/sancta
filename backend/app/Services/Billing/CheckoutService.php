<?php

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\Gateways\GatewayPayment;
use App\Services\Push\PushService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Checkout do site (Pix e cartão). O VALOR é sempre calculado no servidor a partir
 * do plano e do cupom — o navegador nunca informa preço.
 */
class CheckoutService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly EntitlementService $entitlements,
        private readonly GiftService $gifts,
    ) {}

    public function quote(Plan $plan, ?string $couponCode): array
    {
        $coupon = $this->resolveCoupon($plan, $couponCode);
        $discount = $coupon?->discountFor($plan->price_cents) ?? 0;

        return [
            'coupon' => $coupon,
            'discount_cents' => $discount,
            'amount_cents' => $plan->price_cents - $discount,
        ];
    }

    /**
     * @param  bool  $gift  compra para presentear: na aprovação gera um código (GiftService) em vez de dar acesso ao comprador
     */
    public function start(User $user, Plan $plan, PaymentMethod $method, ?string $couponCode, ?string $ip = null, bool $gift = false): Payment
    {
        if (! $plan->is_active || ! $plan->available_on_web) {
            throw new RuntimeException('Este plano não está disponível no momento.');
        }
        if (! in_array($method, [PaymentMethod::Pix, PaymentMethod::CreditCard], true)) {
            throw new RuntimeException('Forma de pagamento inválida.');
        }
        if (! in_array($method, $this->gateways->enabledMethods(), true)) {
            throw new RuntimeException('Esta forma de pagamento não está disponível no momento.');
        }

        // Evita acúmulo de cobranças abertas: reaproveita um Pix pendente e válido do mesmo plano
        // (e do mesmo tipo: presente nunca reaproveita cobrança de assinatura própria e vice-versa).
        $existing = $user->payments()
            ->where('plan_id', $plan->id)
            ->where('method', $method->value)
            ->where('is_gift', $gift)
            ->where('status', PaymentStatus::Pending->value)
            ->where('expires_at', '>', now()->addMinutes(5))
            ->latest()
            ->first();

        // Reenvio do mesmo checkout Pix com o mesmo cupom também reaproveita (não consome outro uso do cupom).
        $sameCoupon = $couponCode && $existing?->coupon_id && $existing->coupon?->code === strtoupper(trim($couponCode));
        if ($existing && $method === PaymentMethod::Pix && (! $couponCode || $sameCoupon)) {
            return $existing;
        }

        $quote = $this->quote($plan, $couponCode);
        $gateway = $this->gateways->current();

        // A reserva do cupom e a criação da cobrança acontecem juntas, sob trava da linha do cupom:
        // cobranças pendentes e válidas contam como uso (senão várias abertas ao mesmo tempo furam o limite).
        $payment = DB::transaction(function () use ($quote, $user, $plan, $gift, $gateway, $method, $ip): Payment {
            if ($coupon = $quote['coupon']) {
                $this->reserveCoupon($coupon, $user);
            }

            $payment = new Payment;
            $payment->forceFill([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'coupon_id' => $quote['coupon']?->id,
                'is_gift' => $gift,
                'provider' => $gateway->name() === 'fake' ? PaymentProvider::Fake : PaymentProvider::MercadoPago,
                'method' => $method,
                'status' => PaymentStatus::Pending,
                'amount_cents' => $quote['amount_cents'],
                'discount_cents' => $quote['discount_cents'],
                'currency' => $plan->currency,
                'ip_address' => $ip,
                'expires_at' => now()->addDay(),
            ])->save();

            return $payment;
        });

        try {
            if ($method === PaymentMethod::Pix) {
                $pix = $gateway->createPix($payment, $user, $plan);
                $payment->forceFill([
                    'provider_payment_id' => $pix['provider_payment_id'],
                    'pix_qr_code' => $pix['qr_code'],
                    'pix_qr_code_base64' => $pix['qr_code_base64'],
                    'expires_at' => $pix['expires_at'],
                ])->save();
            } else {
                $checkout = $gateway->createCardCheckout($payment, $user, $plan);
                $payment->forceFill([
                    'provider_preference_id' => $checkout['preference_id'],
                    'checkout_url' => $checkout['checkout_url'],
                ])->save();
            }
        } catch (RuntimeException $e) {
            $payment->forceFill(['status' => PaymentStatus::Canceled, 'failure_reason' => 'Falha ao criar cobrança'])->save();
            throw $e;
        }

        return $payment;
    }

    /** Consulta o provedor e aplica o resultado (usado pelo webhook e pela tela de status). */
    public function sync(Payment $payment): Payment
    {
        if ($payment->status->isFinal() && $payment->status !== PaymentStatus::Approved) {
            return $payment;
        }

        $gateway = $this->gateways->for($payment->provider->value);

        $remote = $payment->provider_payment_id
            ? $gateway->fetchPayment($payment->provider_payment_id)
            : $gateway->findByExternalReference($payment->uuid);

        if (! $remote) {
            if ($payment->isPending() && $payment->expires_at?->isPast()) {
                $payment->forceFill(['status' => PaymentStatus::Expired])->save();
            }

            return $payment;
        }

        return $this->apply($payment, $remote);
    }

    /**
     * Aplica um retrato confiável do provedor ao pagamento local.
     * Validações anti-fraude: referência e valor precisam bater exatamente.
     */
    public function apply(Payment $payment, GatewayPayment $remote): Payment
    {
        return DB::transaction(function () use ($payment, $remote): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($remote->externalReference !== null && $remote->externalReference !== $payment->uuid) {
                Log::warning('Pagamento com referência divergente', ['payment' => $payment->uuid, 'remote' => $remote->externalReference]);

                return $payment;
            }

            if ($remote->status === PaymentStatus::Approved && $remote->amountCents !== $payment->amount_cents) {
                Log::alert('Valor pago divergente do esperado — acesso NÃO liberado', [
                    'payment' => $payment->uuid, 'expected' => $payment->amount_cents, 'received' => $remote->amountCents,
                ]);
                $payment->forceFill(['failure_reason' => 'Valor divergente — revisar manualmente'])->save();

                return $payment;
            }

            $wasApproved = $payment->status === PaymentStatus::Approved;

            // Notificações fora de ordem não fazem o pagamento "voltar no tempo": aprovado só muda para
            // estornado/contestado; estornado/contestado é definitivo.
            $reversal = in_array($remote->status, [PaymentStatus::Refunded, PaymentStatus::ChargedBack], true);
            if (($wasApproved && $remote->status !== PaymentStatus::Approved && ! $reversal)
                || in_array($payment->status, [PaymentStatus::Refunded, PaymentStatus::ChargedBack], true)) {
                if ($payment->status !== $remote->status) {
                    Log::info('Atualização de pagamento fora de ordem ignorada', ['payment' => $payment->uuid, 'local' => $payment->status->value, 'remote' => $remote->status->value]);
                }

                return $payment;
            }

            $payment->forceFill([
                'provider_payment_id' => $payment->provider_payment_id ?: $remote->providerPaymentId,
                'status' => $remote->status,
                'installments' => $remote->installments ?: 1,
                'paid_at' => $remote->status === PaymentStatus::Approved ? ($payment->paid_at ?? now()) : $payment->paid_at,
                'refunded_at' => in_array($remote->status, [PaymentStatus::Refunded, PaymentStatus::ChargedBack], true) ? now() : $payment->refunded_at,
                'failure_reason' => $remote->status === PaymentStatus::Rejected ? $remote->statusDetail : null,
            ])->save();

            if ($remote->status === PaymentStatus::Approved && ! $wasApproved) {
                if ($payment->is_gift) {
                    // Presente: gera o código (e-mail ao comprador); o comprador NÃO ganha acesso.
                    $this->gifts->issueForPayment($payment);
                } else {
                    $this->entitlements->grantFromPayment($payment);
                    DB::afterCommit(fn () => app(PushService::class)->notifyUser(
                        $payment->user,
                        'Bem-vindo ao Sancta+ ✨',
                        'Seu pagamento foi aprovado. Aproveite todo o conteúdo exclusivo!',
                        'sancta://perfil'
                    ));
                }
            }

            if (in_array($remote->status, [PaymentStatus::Refunded, PaymentStatus::ChargedBack], true) && $wasApproved) {
                if ($payment->is_gift) {
                    // Código disponível → revogado; já resgatado → período do resgatador revogado.
                    $this->gifts->revokeForPayment($payment);
                } else {
                    $this->entitlements->revokeFromPayment($payment);
                }
            }

            return $payment->refresh();
        });
    }

    private function resolveCoupon(Plan $plan, ?string $code): ?Coupon
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        $coupon = Coupon::query()->where('code', $code)->first();
        if (! $coupon || ! $coupon->isValidFor($plan)) {
            throw new RuntimeException('Cupom inválido ou expirado.');
        }

        return $coupon;
    }

    /**
     * Confere os limites de uso do cupom sob trava da linha (chamar dentro de transação):
     *  - por pessoa: pagamentos da conta com o cupom já pagos (aprovados, inclusive depois estornados) ou
     *    pendentes e válidos — presentes incluídos (pentest fase 3, SEC-11);
     *  - global: resgates + cobranças pendentes ainda válidas (SEC-05).
     */
    private function reserveCoupon(Coupon $coupon, User $user): void
    {
        $locked = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->max_redemptions_per_user !== null) {
            $usedByUser = Payment::query()
                ->where('coupon_id', $locked->id)
                ->where('user_id', $user->id)
                ->where(fn ($q) => $q
                    ->whereIn('status', [PaymentStatus::Approved->value, PaymentStatus::Refunded->value, PaymentStatus::ChargedBack->value])
                    ->orWhere(fn ($q) => $q->where('status', PaymentStatus::Pending->value)->where('expires_at', '>', now())))
                ->count();

            if ($usedByUser >= $locked->max_redemptions_per_user) {
                throw new RuntimeException('Você já usou este cupom (ou tem um pagamento em aberto com ele). Continue sem cupom ou conclua o pagamento pendente.');
            }
        }

        if ($locked->max_redemptions === null) {
            return;
        }

        $reserved = Payment::query()
            ->where('coupon_id', $locked->id)
            ->where('status', PaymentStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->count();

        if ($locked->redemptions_count + $reserved >= $locked->max_redemptions) {
            throw new RuntimeException('Cupom esgotado. Tente novamente mais tarde ou continue sem cupom.');
        }
    }
}
