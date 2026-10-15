<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JourneyType: string implements HasLabel
{
    case Journey = 'journey';
    case Novena = 'novena';
    case ReadingPlan = 'reading_plan';
    case Challenge = 'challenge';

    public function getLabel(): string
    {
        return match ($this) {
            self::Journey => 'Jornada',
            self::Novena => 'Novena',
            self::ReadingPlan => 'Plano de leitura',
            self::Challenge => 'Desafio',
        };
    }
}
