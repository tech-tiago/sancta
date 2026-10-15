<?php

namespace App\Filament\Resources\Prayers\Pages;

use App\Filament\Resources\Prayers\PrayerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrayer extends EditRecord
{
    protected static string $resource = PrayerResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
