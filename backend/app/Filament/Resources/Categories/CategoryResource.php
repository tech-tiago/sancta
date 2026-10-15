<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'categoria';

    protected static ?string $pluralModelLabel = 'Categorias';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(80),
            TextInput::make('slug')->label('Slug')->maxLength(100)->alphaDash()->unique(ignoreRecord: true)->helperText('Gerado automaticamente se vazio'),
            TextInput::make('description')->label('Descrição')->maxLength(255)->columnSpanFull(),
            TextInput::make('icon')->label('Ícone')->maxLength(60)->placeholder('sun, moon, heart…'),
            ColorPicker::make('color')->label('Cor'),
            TextInput::make('sort_order')->label('Ordem')->numeric()->default(0),
            Toggle::make('is_active')->label('Ativa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->label('Nome')->searchable()->description(fn (Category $r) => $r->description),
                TextColumn::make('prayers_count')->counts('prayers')->label('Orações'),
                ToggleColumn::make('is_active')->label('Ativa'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
