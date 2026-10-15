<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Público-alvo de avisos (pop-ups) e campanhas de push. */
enum Audience: string implements HasLabel
{
    case All = 'all';
    case Free = 'free';
    case Premium = 'premium';
    case Guests = 'guests';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Todos',
            self::Free => 'Usuários gratuitos',
            self::Premium => 'Assinantes Sancta+',
            self::Guests => 'Visitantes (sem login)',
        };
    }
}
