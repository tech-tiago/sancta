<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentProvider: string implements HasLabel
{
    case MercadoPago = 'mercadopago';
    case GooglePlay = 'google_play';
    case Manual = 'manual';
    case Fake = 'fake';

    public function getLabel(): string
    {
        return match ($this) {
            self::MercadoPago => 'Mercado Pago',
            self::GooglePlay => 'Google Play',
            self::Manual => 'Manual',
            self::Fake => 'Simulado (teste)',
        };
    }
}
