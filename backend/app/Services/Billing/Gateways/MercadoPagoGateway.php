<?php

namespace App\Services\Billing\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integração Mercado Pago:
 *  - Pix: API de pagamentos (/v1/payments) → QR Code exibido no nosso site.
 *  - Cartão: Checkout Pro (preferência) → o cliente digita o cartão no ambiente do Mercado Pago.
 */
class MercadoPagoGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'mercadopago';
    }

    public function createPix(Payment $payment, User $user, Plan $plan): array
    {
        $expiresAt = now()->addMinutes(config('sancta.payments.pix_expiration_minutes', 30));

        $response = $this->client()
            ->withHeaders(['X-Idempotency-Key' => $payment->uuid])
            ->post('/v1/payments', [
                'transaction_amount' => Money::toDecimal($payment->amount_cents),
                'description' => ($payment->is_gift ? 'Presente ' : '')."Sancta+ {$plan->name}",
                'payment_method_id' => 'pix',
                'external_reference' => $payment->uuid,
                'notification_url' => route('webhooks.mercadopago'),
                'date_of_expiration' => $expiresAt->format('Y-m-d\TH:i:s.vP'),
                'payer' => [
                    'email' => $user->email,
                    'first_name' => $user->firstName(),
                ],
                'metadata' => ['payment_uuid' => $payment->uuid],
            ]);

        if ($response->failed()) {
            report(new RuntimeException('Mercado Pago (Pix) HTTP '.$response->status().': '.$response->json('message')));
            throw new RuntimeException('Não foi possível gerar o Pix agora. Tente novamente em instantes.');
        }

        $data = $response->json();

        return [
            'provider_payment_id' => (string) $data['id'],
            'qr_code' => (string) data_get($data, 'point_of_interaction.transaction_data.qr_code'),
            'qr_code_base64' => data_get($data, 'point_of_interaction.transaction_data.qr_code_base64'),
            'expires_at' => $expiresAt,
        ];
    }

    public function createCardCheckout(Payment $payment, User $user, Plan $plan): array
    {
        $response = $this->client()
            ->withHeaders(['X-Idempotency-Key' => $payment->uuid])
            ->post('/checkout/preferences', [
                'items' => [[
                    'id' => $plan->slug,
                    'title' => ($payment->is_gift ? 'Presente ' : '')."Sancta+ {$plan->name}",
                    'quantity' => 1,
                    'currency_id' => 'BRL',
                    'unit_price' => Money::toDecimal($payment->amount_cents),
                ]],
                'payer' => ['email' => $user->email, 'name' => $user->firstName()],
                'external_reference' => $payment->uuid,
                'notification_url' => route('webhooks.mercadopago'),
                'back_urls' => [
                    'success' => route('checkout.status', $payment->uuid),
                    'pending' => route('checkout.status', $payment->uuid),
                    'failure' => route('checkout.status', $payment->uuid),
                ],
                'auto_return' => 'approved',
                'statement_descriptor' => 'SANCTA',
                'payment_methods' => [
                    'excluded_payment_types' => [['id' => 'ticket'], ['id' => 'atm'], ['id' => 'bank_transfer']],
                    'installments' => max(1, (int) $plan->max_installments),
                ],
                'expires' => true,
                'expiration_date_to' => now()->addDay()->format('Y-m-d\TH:i:s.vP'),
            ]);

        if ($response->failed()) {
            report(new RuntimeException('Mercado Pago (preferência) HTTP '.$response->status().': '.$response->json('message')));
            throw new RuntimeException('Não foi possível iniciar o pagamento com cartão agora.');
        }

        return [
            'preference_id' => (string) $response->json('id'),
            'checkout_url' => (string) $response->json('init_point'),
        ];
    }

    public function fetchPayment(string $providerPaymentId): ?GatewayPayment
    {
        if (! ctype_digit($providerPaymentId)) {
            return null;
        }

        $response = $this->client()->get("/v1/payments/{$providerPaymentId}");

        return $response->successful() ? $this->map($response->json()) : null;
    }

    public function findByExternalReference(string $reference): ?GatewayPayment
    {
        $response = $this->client()->get('/v1/payments/search', [
            'external_reference' => $reference,
            'sort' => 'date_created',
            'criteria' => 'desc',
        ]);

        $first = $response->successful() ? ($response->json('results.0')) : null;

        return $first ? $this->map($first) : null;
    }

    private function map(array $data): GatewayPayment
    {
        $status = match ($data['status'] ?? null) {
            'approved', 'authorized' => PaymentStatus::Approved,
            'rejected' => PaymentStatus::Rejected,
            'cancelled' => PaymentStatus::Canceled,
            'refunded' => PaymentStatus::Refunded,
            'charged_back' => PaymentStatus::ChargedBack,
            default => PaymentStatus::Pending, // pending, in_process, in_mediation
        };

        return new GatewayPayment(
            providerPaymentId: (string) $data['id'],
            status: $status,
            amountCents: (int) round(((float) ($data['transaction_amount'] ?? 0)) * 100),
            externalReference: $data['external_reference'] ?? null,
            method: ($data['payment_type_id'] ?? null) === 'bank_transfer' ? 'pix' : ($data['payment_type_id'] ?? null),
            installments: (int) ($data['installments'] ?? 1),
            statusDetail: $data['status_detail'] ?? null,
        );
    }

    private function client(): PendingRequest
    {
        $token = config('services.mercadopago.access_token');
        if (! $token) {
            throw new RuntimeException('Mercado Pago não configurado (MERCADOPAGO_ACCESS_TOKEN).');
        }

        return Http::baseUrl(config('services.mercadopago.base_url'))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 300, throw: false);
    }
}
