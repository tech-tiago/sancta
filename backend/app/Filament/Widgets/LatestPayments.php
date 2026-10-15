<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestPayments extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Últimos pagamentos';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRoleAtLeast(UserRole::Admin);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Payment::query()->with(['user', 'plan'])->latest()->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label('Data')->dateTime('d/m H:i'),
                TextColumn::make('user.name')->label('Cliente')->description(fn (Payment $r) => $r->user?->email),
                TextColumn::make('plan.name')->label('Plano'),
                TextColumn::make('method')->label('Forma')->badge(),
                TextColumn::make('amount_cents')->label('Valor')->money('BRL', divideBy: 100),
                TextColumn::make('status')->label('Status')->badge(),
            ]);
    }
}
