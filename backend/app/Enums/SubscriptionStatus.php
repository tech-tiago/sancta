<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';     // pagamento em atraso / período de carência (Google Play)
    case Canceled = 'canceled';    // cancelada, mas pode ainda estar dentro do período pago
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Trialing => 'Período de teste',
            self::Active => 'Ativa',
            self::PastDue => 'Em carência',
            self::Canceled => 'Cancelada',
            self::Expired => 'Expirada',
            self::Refunded => 'Reembolsada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Trialing => 'info',
            self::Active => 'success',
            self::PastDue => 'warning',
            self::Canceled => 'warning',
            self::Expired => 'gray',
            self::Refunded => 'danger',
        };
    }

    /** Status que concedem acesso ao conteúdo Sancta+ (desde que dentro de ends_at). */
    public static function entitled(): array
    {
        return [self::Trialing->value, self::Active->value, self::PastDue->value, self::Canceled->value];
    }
}
