<?php

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Services\Billing\Gateways\FakeGateway;
use App\Services\Billing\Gateways\MercadoPagoGateway;
use App\Services\Billing\Gateways\PaymentGateway;

class PaymentGatewayManager
{
    /** Gateway ativo: Mercado Pago quando configurado; simulado apenas em local/testing. */
    public function current(): PaymentGateway
    {
        if (filled(config('services.mercadopago.access_token'))) {
            return app(MercadoPagoGateway::class);
        }

        if ($this->fakeAllowed()) {
            return app(FakeGateway::class);
        }

        return app(MercadoPagoGateway::class); // lançará erro "não configurado" ao uso
    }

    public function for(string $provider): PaymentGateway
    {
        return match ($provider) {
            'fake' => $this->fakeAllowed() ? app(FakeGateway::class) : throw new \RuntimeException('Gateway simulado indisponível.'),
            default => app(MercadoPagoGateway::class),
        };
    }

    public function fakeAllowed(): bool
    {
        return ! app()->isProduction()
            && app()->environment(['local', 'testing'])
            && (bool) config('sancta.payments.fake_enabled');
    }

    public function isConfigured(): bool
    {
        return (filled(config('services.mercadopago.access_token')) || $this->fakeAllowed()) && $this->enabledMethods() !== [];
    }

    /**
     * Formas de pagamento aceitas no site (ligadas/desligadas no painel).
     *
     * @return list<PaymentMethod>
     */
    public function enabledMethods(): array
    {
        return array_values(array_filter([
            config('sancta.payments.pix_enabled', true) ? PaymentMethod::Pix : null,
            config('sancta.payments.card_enabled', true) ? PaymentMethod::CreditCard : null,
        ]));
    }
}
