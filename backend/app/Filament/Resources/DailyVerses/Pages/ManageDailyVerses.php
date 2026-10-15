<?php

namespace App\Filament\Resources\DailyVerses\Pages;

use App\Filament\Resources\DailyVerses\DailyVerseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDailyVerses extends ManageRecords
{
    protected static string $resource = DailyVerseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
