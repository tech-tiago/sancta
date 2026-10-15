<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Páginas institucionais (termos, privacidade, exclusão de conta, sobre). */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'página';

    protected static ?string $pluralModelLabel = 'Páginas';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Título')->required()->maxLength(191),
            TextInput::make('slug')->label('Endereço (slug)')->required()->alphaDash()->maxLength(100)->unique(ignoreRecord: true)->prefix('/p/'),
            RichEditor::make('content')->label('Conteúdo')->columnSpanFull()
                ->toolbarButtons([['bold', 'italic', 'underline'], ['h2', 'h3', 'blockquote'], ['bulletList', 'orderedList'], ['link'], ['undo', 'redo']]),
            Toggle::make('is_published')->label('Publicada')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Título')->searchable(),
                TextColumn::make('slug')->label('Endereço')->prefix('/p/'),
                IconColumn::make('is_published')->label('Publicada')->boolean(),
                TextColumn::make('updated_at')->label('Atualizada')->since(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('open')->label('Abrir')->icon(Heroicon::OutlinedEye)->url(fn (Page $r) => route('pages.show', $r->slug), true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
