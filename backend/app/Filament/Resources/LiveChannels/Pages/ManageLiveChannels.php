<?php

namespace App\Filament\Resources\LiveChannels\Pages;

use App\Filament\Resources\LiveChannels\LiveChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLiveChannels extends ManageRecords
{
    protected static string $resource = LiveChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->slideOver()];
    }
}
