<?php

namespace App\Filament\Resources\PushCampaigns\Pages;

use App\Filament\Resources\PushCampaigns\PushCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePushCampaigns extends ManageRecords
{
    protected static string $resource = PushCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova notificação')
                ->after(function ($record): void {
                    $record->forceFill([
                        'status' => $record->scheduled_at ? 'scheduled' : 'draft',
                        'created_by' => auth()->id(),
                    ])->save();
                }),
        ];
    }
}
