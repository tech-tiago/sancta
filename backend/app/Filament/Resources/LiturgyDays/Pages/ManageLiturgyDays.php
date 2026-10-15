<?php

namespace App\Filament\Resources\LiturgyDays\Pages;

use App\Filament\Resources\LiturgyDays\LiturgyDayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLiturgyDays extends ManageRecords
{
    protected static string $resource = LiturgyDayResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Cadastrar manualmente')];
    }
}
