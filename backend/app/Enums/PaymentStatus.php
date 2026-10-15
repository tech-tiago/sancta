<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Canceled = 'canceled';
    case Expired = 'expired';
    case Refunded = 'refunded';
    case ChargedBack = 'charged_back';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando',
            self::Approved => 'Aprovado',
            self::Rejected => 'Recusado',
            self::Canceled => 'Cancelado',
            self::Expired => 'Expirado',
            self::Refunded => 'Estornado',
            self::ChargedBack => 'Chargeback',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected, self::ChargedBack => 'danger',
            self::Canceled, self::Expired => 'gray',
            self::Refunded => 'info',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
