<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum GiftCodeStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Redeemed = 'redeemed';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Disponível',
            self::Redeemed => 'Resgatado',
            self::Revoked => 'Revogado',
            self::Expired => 'Expirado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Redeemed => 'info',
            self::Revoked => 'danger',
            self::Expired => 'gray',
        };
    }
}
