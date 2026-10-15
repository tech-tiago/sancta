<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Assinaturas';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('plan.name')->label('Plano')->placeholder('Cortesia'),
                TextColumn::make('provider')->label('Origem')->badge(),
                TextColumn::make('status')->badge(),
                IconColumn::make('auto_renew')->label('Renova')->boolean(),
                TextColumn::make('starts_at')->label('Início')->date('d/m/Y'),
                TextColumn::make('ends_at')->label('Até')->date('d/m/Y')->placeholder('Vitalício'),
                TextColumn::make('notes')->label('Obs.')->limit(40),
            ]);
    }
}
