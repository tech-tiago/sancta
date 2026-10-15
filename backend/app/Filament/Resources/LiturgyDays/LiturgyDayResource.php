<?php

namespace App\Filament\Resources\LiturgyDays;

use App\Filament\Resources\LiturgyDays\Pages\ManageLiturgyDays;
use App\Models\LiturgyDay;
use App\Services\Content\LiturgyService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Liturgia diária: importada automaticamente da API (agendador) e editável.
 * Ao editar, marque "Travar" para que a importação não sobrescreva o conteúdo.
 */
class LiturgyDayResource extends Resource
{
    protected static ?string $model = LiturgyDay::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'liturgia';

    protected static ?string $pluralModelLabel = 'Liturgia diária';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('date')->label('Data')->required()->disabledOn('edit'),
            TextInput::make('color')->label('Cor litúrgica')->maxLength(20)->placeholder('Verde'),
            TextInput::make('title')->label('Celebração')->maxLength(255)->columnSpanFull(),
            Repeater::make('readings')->label('Leituras')->columnSpanFull()->collapsible()
                ->itemLabel(fn (array $state) => trim(($state['label'] ?? '').' — '.($state['reference'] ?? '')))
                ->schema([
                    TextInput::make('label')->label('Tipo')->required()->placeholder('Primeira leitura / Salmo / Evangelho'),
                    TextInput::make('reference')->label('Referência'),
                    TextInput::make('title')->label('Título'),
                    TextInput::make('refrain')->label('Refrão (salmo)'),
                    Textarea::make('text')->label('Texto')->rows(6),
                ]),
            Section::make('Reflexão / homilia (Sancta+)')->columnSpanFull()->columns(2)->schema([
                Textarea::make('homily')->label('Reflexão')->rows(8)->columnSpanFull(),
                TextInput::make('homily_author')->label('Autor')->maxLength(120),
                Toggle::make('is_locked')->label('Travar (não sobrescrever pela importação automática)')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Data')->date('d/m/Y')->sortable(),
                TextColumn::make('title')->label('Celebração')->limit(60)->wrap()->searchable(),
                TextColumn::make('color')->label('Cor')->badge()->color(fn (LiturgyDay $r) => match (mb_strtolower((string) $r->color)) {
                    'verde' => 'success', 'vermelho' => 'danger', 'roxo', 'violeta' => 'info', 'branco' => 'gray', default => 'warning',
                }),
                IconColumn::make('homily')->label('Reflexão')->boolean()->getStateUsing(fn (LiturgyDay $r) => filled($r->homily)),
                IconColumn::make('is_locked')->label('Travada')->boolean(),
                TextColumn::make('fetched_at')->label('Importada')->since(),
            ])
            ->headerActions([
                Action::make('fetchWeek')->label('Importar próximos 7 dias')->icon(Heroicon::OutlinedCloudArrowDown)
                    ->visible(fn () => auth()->user()->can('create', LiturgyDay::class))
                    ->action(function (): void {
                        $ok = 0;
                        for ($i = 0; $i < 7; $i++) {
                            $ok += app(LiturgyService::class)->fetch(Carbon::today()->addDays($i)) ? 1 : 0;
                        }
                        Notification::make()->title("{$ok} de 7 dias importados")->success()->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('refetch')->label('Reimportar')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (LiturgyDay $r) => ! $r->is_locked && auth()->user()->can('update', $r))
                    ->action(function (LiturgyDay $record): void {
                        $ok = app(LiturgyService::class)->fetch($record->date);
                        Notification::make()->title($ok ? 'Liturgia atualizada' : 'API indisponível')->{$ok ? 'success' : 'warning'}()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLiturgyDays::route('/')];
    }
}
