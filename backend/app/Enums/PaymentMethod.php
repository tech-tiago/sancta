<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasIcon, HasLabel
{
    case Pix = 'pix';
    case CreditCard = 'credit_card';
    case GooglePlay = 'google_play';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pix => 'Pix',
            self::CreditCard => 'Cartão de crédito',
            self::GooglePlay => 'Google Play',
            self::Manual => 'Manual (cortesia)',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pix => 'heroicon-o-qr-code',
            self::CreditCard => 'heroicon-o-credit-card',
            self::GooglePlay => 'heroicon-o-device-phone-mobile',
            self::Manual => 'heroicon-o-gift',
        };
    }
}
