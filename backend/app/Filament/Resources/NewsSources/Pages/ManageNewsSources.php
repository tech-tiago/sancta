<?php

namespace App\Filament\Resources\NewsSources\Pages;

use App\Filament\Resources\NewsSources\NewsSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNewsSources extends ManageRecords
{
    protected static string $resource = NewsSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
