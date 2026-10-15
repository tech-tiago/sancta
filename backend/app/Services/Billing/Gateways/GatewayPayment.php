<?php

namespace App\Services\Billing\Gateways;

use App\Enums\PaymentStatus;

/** Retrato de um pagamento consultado diretamente no provedor (fonte confiável). */
final readonly class GatewayPayment
{
    public function __construct(
        public string $providerPaymentId,
        public PaymentStatus $status,
        public int $amountCents,
        public ?string $externalReference,
        public ?string $method = null,
        public int $installments = 1,
        public ?string $statusDetail = null,
    ) {}
}
