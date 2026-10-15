<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScheduleType: string implements HasLabel
{
    case Missa = 'missa';
    case Confissao = 'confissao';
    case Adoracao = 'adoracao';
    case Secretaria = 'secretaria';
    case Terco = 'terco';
    case Outro = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Missa => 'Missa',
            self::Confissao => 'Confissão',
            self::Adoracao => 'Adoração',
            self::Secretaria => 'Secretaria',
            self::Terco => 'Terço',
            self::Outro => 'Outro',
        };
    }

    public static function weekdayLabel(?int $weekday): string
    {
        return [
            0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira',
            4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado',
        ][$weekday] ?? 'Consultar';
    }

    public static function weekdayOptions(): array
    {
        return [
            0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira',
            4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado',
        ];
    }
}
