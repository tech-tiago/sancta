<?php

namespace App\Filament\Resources\GiftCodes;

use App\Enums\GiftCodeStatus;
use App\Filament\Resources\GiftCodes\Pages\ListGiftCodes;
use App\Models\GiftCode;
use App\Services\Billing\GiftService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Presentes Sancta+ comprados no site. Somente leitura (registro financeiro): o código
 * completo NUNCA aparece — só os 4 últimos caracteres. Única ação: "Revogar" (auditada).
 */
class GiftCodeResource extends Resource
{
    protected static ?string $model = GiftCode::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Assinaturas';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Presentes';

    protected static ?string $modelLabel = 'presente';

    protected static ?string $pluralModelLabel = 'Presentes';

    protected static ?string $slug = 'presentes';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['purchaser', 'plan', 'redeemer', 'payment']))
            ->columns([
                TextColumn::make('created_at')->label('Compra')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('purchaser.name')->label('Comprador')
                    ->description(fn (GiftCode $r) => $r->purchaser?->email)
                    ->searchable(['name', 'email']),
                TextColumn::make('plan.name')->label('Plano'),
                TextColumn::make('last4')->label('Código')
                    ->formatStateUsing(fn (GiftCode $r) => $r->maskedCode())
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('status')->label('Situação')
                    ->getStateUsing(fn (GiftCode $r) => $r->effectiveStatus())
                    ->badge()
                    ->description(fn (GiftCode $r) => $r->revoked_reason),
                TextColumn::make('redeemer.name')->label('Resgatado por')
                    ->description(fn (GiftCode $r) => $r->redeemer?->email)
                    ->placeholder('—'),
                TextColumn::make('redeemed_at')->label('Resgatado em')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('expires_at')->label('Validade')->date('d/m/Y')->sortable(),
                TextColumn::make('payment.amount_cents')->label('Valor')->money('BRL', divideBy: 100)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(GiftCodeStatus::class),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revogar')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (GiftCode $r) => auth()->user()->can('revoke', $r))
                    ->requiresConfirmation()
                    ->modalHeading('Revogar este presente?')
                    ->modalDescription(fn (GiftCode $r) => $r->status === GiftCodeStatus::Redeemed
                        ? 'O código já foi resgatado: o período concedido a quem resgatou será encerrado agora.'
                        : 'O código deixa de funcionar imediatamente.')
                    ->schema([Textarea::make('reason')->label('Motivo')->required()->maxLength(255)])
                    ->action(function (GiftCode $record, array $data): void {
                        abort_unless(auth()->user()->can('revoke', $record), 403);
                        app(GiftService::class)->revoke($record, $data['reason'], auth()->user());
                        Notification::make()->title('Presente revogado')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListGiftCodes::route('/')];
    }
}
