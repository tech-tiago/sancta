<?php

namespace App\Services\Billing\Gateways;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;

interface PaymentGateway
{
    public function name(): string;

    /**
     * Cria uma cobrança Pix e devolve os dados para exibir o QR Code.
     *
     * @return array{provider_payment_id: string, qr_code: string, qr_code_base64: ?string, expires_at: \DateTimeInterface}
     */
    public function createPix(Payment $payment, User $user, Plan $plan): array;

    /**
     * Cria um checkout hospedado pelo provedor para cartão de crédito (os dados do
     * cartão NUNCA passam pelo nosso servidor — escopo PCI reduzido).
     *
     * @return array{preference_id: string, checkout_url: string}
     */
    public function createCardCheckout(Payment $payment, User $user, Plan $plan): array;

    /** Consulta o pagamento no provedor (nunca confie apenas no payload do webhook). */
    public function fetchPayment(string $providerPaymentId): ?GatewayPayment;

    /** Busca pagamento pela referência externa (uuid do nosso Payment). */
    public function findByExternalReference(string $reference): ?GatewayPayment;
}
