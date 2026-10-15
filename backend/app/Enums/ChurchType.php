<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ChurchType: string implements HasLabel
{
    case Paroquia = 'paroquia';
    case Comunidade = 'comunidade';
    case Capela = 'capela';
    case Santuario = 'santuario';
    case Catedral = 'catedral';
    case Basilica = 'basilica';
    case Mosteiro = 'mosteiro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Paroquia => 'Paróquia',
            self::Comunidade => 'Comunidade',
            self::Capela => 'Capela',
            self::Santuario => 'Santuário',
            self::Catedral => 'Catedral',
            self::Basilica => 'Basílica',
            self::Mosteiro => 'Mosteiro / Convento',
        };
    }
}
