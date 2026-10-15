<?php

namespace App\Filament\Resources\DailyVerses;

use App\Filament\Resources\DailyVerses\Pages\ManageDailyVerses;
use App\Models\DailyVerse;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DailyVerseResource extends Resource
{
    protected static ?string $model = DailyVerse::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'versículo do dia';

    protected static ?string $pluralModelLabel = 'Versículos do dia';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('date')->label('Data')->required()->unique(ignoreRecord: true),
            TextInput::make('reference')->label('Referência')->required()->maxLength(60)->placeholder('Jo 3,16'),
            Textarea::make('text')->label('Texto do versículo')->required()->rows(3)->columnSpanFull(),
            TextInput::make('reflection_title')->label('Título da reflexão')->maxLength(191),
            Toggle::make('reflection_is_premium')->label('Reflexão exclusiva Sancta+')->default(true),
            Textarea::make('reflection')->label('Reflexão')->rows(4)->columnSpanFull(),
            FileUpload::make('image')->label('Imagem de fundo')->image()->disk('public')->directory('verses')->visibility('public')->maxSize(4096)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Data')->date('d/m/Y')->sortable(),
                TextColumn::make('reference')->label('Referência')->searchable(),
                TextColumn::make('text')->label('Texto')->limit(70)->wrap(),
                TextColumn::make('reflection_title')->label('Reflexão')->placeholder('—'),
                IconColumn::make('reflection_is_premium')->label('Sancta+')->boolean(),
            ])
            ->filters([Filter::make('upcoming')->label('Próximos dias')->query(fn (Builder $query) => $query->whereDate('date', '>=', today()))->default()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDailyVerses::route('/')];
    }
}
