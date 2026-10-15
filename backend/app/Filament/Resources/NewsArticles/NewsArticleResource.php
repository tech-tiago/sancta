<?php

namespace App\Filament\Resources\NewsArticles;

use App\Enums\ContentStatus;
use App\Filament\Resources\NewsArticles\Pages\CreateNewsArticle;
use App\Filament\Resources\NewsArticles\Pages\EditNewsArticle;
use App\Filament\Resources\NewsArticles\Pages\ListNewsArticles;
use App\Models\NewsArticle;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Mídia';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'notícia';

    protected static ?string $pluralModelLabel = 'Notícias';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Notícia')->columnSpan(2)->schema([
                TextInput::make('title')->label('Título')->required()->maxLength(255),
                Textarea::make('summary')->label('Resumo')->rows(3)->maxLength(1000),
                RichEditor::make('content')->label('Conteúdo completo (opcional)')
                    ->toolbarButtons([['bold', 'italic', 'underline'], ['h2', 'h3', 'blockquote'], ['bulletList', 'orderedList'], ['link'], ['undo', 'redo']]),
            ]),
            Section::make('Publicação')->columnSpan(1)->schema([
                Select::make('status')->label('Situação')->options(ContentStatus::class)->default('published')->required(),
                DateTimePicker::make('published_at')->label('Publicada em')->default(now()),
                Toggle::make('is_featured')->label('Destaque (fixar no topo)'),
                Select::make('news_source_id')->label('Fonte')->relationship('source', 'name'),
                TextInput::make('author')->label('Autor')->maxLength(120),
                TextInput::make('url')->label('Link original')->url()->rule('regex:/^https?:\/\//i')->maxLength(500),
                FileUpload::make('image')->label('Imagem')->image()->disk('public')->directory('news')->visibility('public')->maxSize(4096),
                TextInput::make('image_url')->label('…ou URL da imagem')->url()->rule('starts_with:https://')->maxLength(500),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('image_url')->label('')->getStateUsing(fn (NewsArticle $r) => $r->imageUrl())->square()->size(44),
                TextColumn::make('title')->label('Título')->searchable()->wrap()->limit(90),
                TextColumn::make('source.name')->label('Fonte')->badge()->color('gray')->placeholder('Sancta'),
                TextColumn::make('published_at')->label('Publicada')->since()->sortable(),
                ToggleColumn::make('is_featured')->label('Destaque'),
                TextColumn::make('status')->label('Situação')->badge(),
            ])
            ->filters([
                SelectFilter::make('news_source_id')->label('Fonte')->relationship('source', 'name'),
                SelectFilter::make('status')->options(ContentStatus::class),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('hide')->label('Ocultar selecionadas')->icon(Heroicon::OutlinedEyeSlash)
                    ->action(fn (Collection $records) => $records->each->update(['status' => ContentStatus::Hidden])),
                DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsArticles::route('/'),
            'create' => CreateNewsArticle::route('/create'),
            'edit' => EditNewsArticle::route('/{record}/edit'),
        ];
    }
}
