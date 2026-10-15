<?php

namespace App\Filament\Resources\NewsSources;

use App\Filament\Resources\NewsSources\Pages\ManageNewsSources;
use App\Models\NewsSource;
use App\Services\Content\NewsFetcher;
use App\Support\NetworkGuard;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class NewsSourceResource extends Resource
{
    protected static ?string $model = NewsSource::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected static string|UnitEnum|null $navigationGroup = 'Mídia';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'fonte de notícias';

    protected static ?string $pluralModelLabel = 'Fontes de notícias';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(120),
            Select::make('type')->label('Tipo')->options(['rss' => 'Feed RSS', 'manual' => 'Somente manual'])->default('rss')->required(),
            TextInput::make('url')->label('URL do feed RSS')->url()->maxLength(500)->columnSpanFull()
                ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                    if ($value && ! NetworkGuard::isPublicHttpUrl($value)) {
                        $fail('Informe uma URL pública http(s) válida.');
                    }
                }),
            TextInput::make('website')->label('Site')->url()->maxLength(255),
            TextInput::make('fetch_interval_minutes')->label('Intervalo de atualização (min)')->numeric()->minValue(15)->maxValue(1440)->default(60),
            TextInput::make('max_items')->label('Máx. itens por leitura')->numeric()->minValue(1)->maxValue(100)->default(30),
            Toggle::make('is_active')->label('Ativa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Fonte')->description(fn (NewsSource $r) => $r->url)->searchable(),
                TextColumn::make('articles_count')->counts('articles')->label('Notícias'),
                TextColumn::make('last_fetched_at')->label('Última leitura')->since()->placeholder('Nunca'),
                TextColumn::make('last_error')->label('Último erro')->limit(40)->placeholder('—')->color('danger'),
                ToggleColumn::make('is_active')->label('Ativa'),
            ])
            ->recordActions([
                Action::make('fetch')->label('Buscar agora')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (NewsSource $r) => $r->type === 'rss' && auth()->user()->can('update', $r))
                    ->action(function (NewsSource $record): void {
                        $count = app(NewsFetcher::class)->fetch($record);
                        $record->refresh();
                        $record->last_error
                            ? Notification::make()->title('Falha ao ler o feed')->body($record->last_error)->danger()->send()
                            : Notification::make()->title("{$count} novas notícias importadas")->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNewsSources::route('/')];
    }
}
