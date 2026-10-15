<?php

namespace App\Filament\Resources\Prayers\Pages;

use App\Filament\Resources\Prayers\PrayerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrayers extends ListRecords
{
    protected static string $resource = PrayerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
