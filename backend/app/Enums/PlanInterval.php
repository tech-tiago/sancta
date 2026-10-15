<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlanInterval: string implements HasLabel
{
    case Month = 'month';
    case Year = 'year';
    case Lifetime = 'lifetime';

    public function getLabel(): string
    {
        return match ($this) {
            self::Month => 'Mês(es)',
            self::Year => 'Ano(s)',
            self::Lifetime => 'Vitalício',
        };
    }
}
