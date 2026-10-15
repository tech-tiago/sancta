<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Temas disponíveis no app e no site. */
enum ThemePreference: string implements HasLabel
{
    case System = 'system';
    case Light = 'light';      // "Aurora"
    case Dark = 'dark';        // "Vigília"
    case Sepia = 'sepia';      // "Pergaminho" (leitura)

    public function getLabel(): string
    {
        return match ($this) {
            self::System => 'Automático (sistema)',
            self::Light => 'Claro — Aurora',
            self::Dark => 'Escuro — Vigília',
            self::Sepia => 'Pergaminho — leitura',
        };
    }
}
