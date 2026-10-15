<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'pergunta frequente';

    protected static ?string $pluralModelLabel = 'Perguntas frequentes';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('question')->label('Pergunta')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('answer')->label('Resposta')->required()->rows(4)->columnSpanFull(),
            Select::make('audience')->label('Exibir em')->options(['both' => 'App e site', 'app' => 'App', 'site' => 'Site'])->default('both')->required(),
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
                TextColumn::make('question')->label('Pergunta')->searchable()->wrap(),
                TextColumn::make('audience')->label('Exibir em')->badge()->color('gray'),
                ToggleColumn::make('is_active')->label('Ativa'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFaqs::route('/')];
    }
}
