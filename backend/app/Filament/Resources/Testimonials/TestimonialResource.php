<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Depoimentos exibidos no site. Publique apenas depoimentos reais e autorizados. */
class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'depoimento';

    protected static ?string $pluralModelLabel = 'Depoimentos';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('author')->label('Nome')->required()->maxLength(120),
            TextInput::make('location')->label('Cidade/UF')->maxLength(120),
            Textarea::make('content')->label('Depoimento')->required()->rows(3)->maxLength(600)->columnSpanFull(),
            TextInput::make('rating')->label('Nota (1-5)')->numeric()->minValue(1)->maxValue(5)->default(5),
            TextInput::make('sort_order')->label('Ordem')->numeric()->default(0),
            Toggle::make('is_active')->label('Exibir no site')->helperText('Somente depoimentos reais, com autorização do autor.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('author')->label('Nome')->description(fn (Testimonial $r) => $r->location),
                TextColumn::make('content')->label('Depoimento')->limit(80)->wrap(),
                ToggleColumn::make('is_active')->label('Exibir'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTestimonials::route('/')];
    }
}
