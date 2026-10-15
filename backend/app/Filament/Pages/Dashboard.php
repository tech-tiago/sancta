<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Painel';

    protected static ?string $navigationLabel = 'Painel';

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 4];
    }
}
