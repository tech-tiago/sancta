<?php

namespace App\Filament\Resources\Saints;

use App\Filament\Resources\Saints\Pages\ManageSaints;
use App\Models\Saint;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SaintResource extends Resource
{
    protected static ?string $model = Saint::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'santo do dia';

    protected static ?string $pluralModelLabel = 'Santos do dia';

    private const MONTHS = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(191)->columnSpanFull(),
            Select::make('feast_month')->label('Mês')->options(self::MONTHS)->required(),
            TextInput::make('feast_day')->label('Dia')->numeric()->minValue(1)->maxValue(31)->required(),
            TextInput::make('title')->label('Título')->maxLength(191)->placeholder('Mártir, Doutor da Igreja…'),
            Toggle::make('is_active')->label('Ativo')->default(true),
            Textarea::make('biography')->label('Biografia')->rows(5)->columnSpanFull(),
            Textarea::make('prayer')->label('Oração')->rows(4)->columnSpanFull(),
            FileUpload::make('image')->label('Imagem')->image()->disk('public')->directory('saints')->visibility('public')->maxSize(4096)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->orderBy('feast_month')->orderBy('feast_day'))
            ->columns([
                TextColumn::make('feast_day')->label('Data')->formatStateUsing(fn (Saint $r) => str_pad((string) $r->feast_day, 2, '0', STR_PAD_LEFT).'/'.str_pad((string) $r->feast_month, 2, '0', STR_PAD_LEFT)),
                TextColumn::make('name')->label('Nome')->searchable()->description(fn (Saint $r) => $r->title),
                ToggleColumn::make('is_active')->label('Ativo'),
            ])
            ->filters([SelectFilter::make('feast_month')->label('Mês')->options(self::MONTHS)])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSaints::route('/')];
    }
}
