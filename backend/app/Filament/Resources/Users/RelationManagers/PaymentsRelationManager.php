<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pagamentos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i'),
                TextColumn::make('plan.name')->label('Plano'),
                TextColumn::make('method')->label('Forma')->badge(),
                TextColumn::make('amount_cents')->label('Valor')->money('BRL', divideBy: 100),
                TextColumn::make('status')->badge(),
                TextColumn::make('provider_payment_id')->label('ID provedor')->copyable(),
            ]);
    }
}
