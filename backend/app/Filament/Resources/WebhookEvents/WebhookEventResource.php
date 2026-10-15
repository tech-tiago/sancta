<?php

namespace App\Filament\Resources\WebhookEvents;

use App\Filament\Resources\WebhookEvents\Pages\ManageWebhookEvents;
use App\Models\WebhookEvent;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Registro dos webhooks recebidos (Mercado Pago / Google Play) — rastreabilidade de pagamentos. */
class WebhookEventResource extends Resource
{
    protected static ?string $model = WebhookEvent::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'webhook';

    protected static ?string $pluralModelLabel = 'Webhooks recebidos';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('provider')->label('Provedor'),
            TextEntry::make('type')->label('Tipo'),
            TextEntry::make('event_id')->label('ID do evento')->copyable(),
            TextEntry::make('status')->label('Situação')->badge(),
            TextEntry::make('created_at')->label('Recebido')->dateTime('d/m/Y H:i:s'),
            TextEntry::make('processed_at')->label('Processado')->dateTime('d/m/Y H:i:s')->placeholder('—'),
            TextEntry::make('error')->label('Erro')->placeholder('—')->columnSpanFull(),
            KeyValueEntry::make('payload')->label('Conteúdo')->columnSpanFull()
                ->state(fn (WebhookEvent $r) => collect($r->payload ?? [])->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Recebido')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('provider')->label('Provedor')->badge()->color('gray'),
                TextColumn::make('type')->label('Tipo'),
                TextColumn::make('status')->label('Situação')->badge()->color(fn (string $state) => match ($state) {
                    'processed' => 'success', 'failed' => 'danger', 'ignored' => 'gray', default => 'warning',
                }),
                TextColumn::make('error')->label('Erro')->limit(50)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('provider')->options(['mercadopago' => 'Mercado Pago', 'google_play' => 'Google Play']),
                SelectFilter::make('status')->options(['received' => 'Recebido', 'processed' => 'Processado', 'ignored' => 'Ignorado', 'failed' => 'Falhou']),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageWebhookEvents::route('/')];
    }
}
