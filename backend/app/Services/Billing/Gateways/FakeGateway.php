<?php

namespace App\Services\Billing\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;

/**
 * Gateway SIMULADO para demonstrar o fluxo completo sem credenciais reais.
 * Só é instanciado fora de produção (ver PaymentGatewayManager).
 * A aprovação é feita pela rota local "/pagamento/{uuid}/simular" (desabilitada em produção).
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createPix(Payment $payment, User $user, Plan $plan): array
    {
        $code = '00020126580014BR.GOV.BCB.PIX0136SANCTA-DEMO-'.Str::upper(Str::random(20)).'5204000053039865802BR5906SANCTA6009SAO PAULO6304ABCD';

        $svg = (new QRCode(new QROptions(['outputBase64' => false, 'outputType' => 'svg'])))->render($code);

        return [
            'provider_payment_id' => 'FAKE-'.$payment->uuid,
            'qr_code' => $code,
            'qr_code_base64' => 'svg:'.base64_encode($svg),
            'expires_at' => now()->addMinutes(30),
        ];
    }

    public function createCardCheckout(Payment $payment, User $user, Plan $plan): array
    {
        return [
            'preference_id' => 'FAKE-PREF-'.$payment->uuid,
            'checkout_url' => route('checkout.status', $payment->uuid).'?simulado=1',
        ];
    }

    public function fetchPayment(string $providerPaymentId): ?GatewayPayment
    {
        $payment = Payment::query()->where('provider_payment_id', $providerPaymentId)->first();

        return $payment ? new GatewayPayment($providerPaymentId, $payment->status, $payment->amount_cents, $payment->uuid) : null;
    }

    public function findByExternalReference(string $reference): ?GatewayPayment
    {
        $payment = Payment::query()->where('uuid', $reference)->first();

        return $payment ? new GatewayPayment((string) $payment->provider_payment_id, $payment->status, $payment->amount_cents, $payment->uuid) : null;
    }

    /** Usado apenas pela rota de simulação local. */
    public function simulate(Payment $payment, PaymentStatus $status): GatewayPayment
    {
        return new GatewayPayment((string) ($payment->provider_payment_id ?: 'FAKE-'.$payment->uuid), $status, $payment->amount_cents, $payment->uuid, $payment->method->value);
    }
}
