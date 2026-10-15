<?php

namespace App\Filament\Resources\Journeys\Pages;

use App\Filament\Resources\Journeys\JourneyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJourney extends EditRecord
{
    protected static string $resource = JourneyResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
