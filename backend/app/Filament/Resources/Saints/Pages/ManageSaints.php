<?php

namespace App\Filament\Resources\Saints\Pages;

use App\Filament\Resources\Saints\SaintResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSaints extends ManageRecords
{
    protected static string $resource = SaintResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
