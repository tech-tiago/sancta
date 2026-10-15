<?php

namespace App\Filament\Resources\Prayers;

use App\Enums\ContentStatus;
use App\Filament\Resources\Prayers\Pages\CreatePrayer;
use App\Filament\Resources\Prayers\Pages\EditPrayer;
use App\Filament\Resources\Prayers\Pages\ListPrayers;
use App\Models\Prayer;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Orações: texto livre (editor rico), blocos litúrgicos (Missa/Via-Sacra, com
 * falas do sacerdote, assembleia, rubricas e posturas) e áudio guiado.
 */
class PrayerResource extends Resource
{
    protected static ?string $model = Prayer::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'oração';

    protected static ?string $pluralModelLabel = 'Orações';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Tabs::make()->columnSpan(2)->tabs([
                Tab::make('Conteúdo')->icon(Heroicon::OutlinedDocumentText)->schema([
                    TextInput::make('title')->label('Título')->required()->maxLength(191),
                    TextInput::make('subtitle')->label('Subtítulo')->maxLength(255),
                    Textarea::make('description')->label('Descrição / introdução')->rows(2),
                    RichEditor::make('body')->label('Texto da oração')
                        ->toolbarButtons([['bold', 'italic', 'underline'], ['h2', 'h3', 'blockquote'], ['bulletList', 'orderedList'], ['link'], ['undo', 'redo']])
                        ->helperText('Use para orações em texto corrido. Para liturgias com falas, use a aba "Blocos litúrgicos".'),
                ]),
                Tab::make('Blocos litúrgicos')->icon(Heroicon::OutlinedQueueList)->schema([
                    Builder::make('blocks')->label('')->collapsible()->blockNumbers(false)->addActionLabel('Adicionar bloco')
                        ->blocks(static::liturgicalBlocks(withExpandable: true)),
                ]),
                Tab::make('Áudio')->icon(Heroicon::OutlinedSpeakerWave)->schema([
                    FileUpload::make('audio_path')->label('Arquivo de áudio (MP3/AAC/OGG, até 50 MB)')
                        ->disk('public')->directory('prayers/audio')->visibility('public')
                        ->acceptedFileTypes(['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/x-m4a'])
                        ->maxSize(51200),
                    TextInput::make('audio_url')->label('…ou URL externa do áudio')->url()->rule('starts_with:https://')->maxLength(500),
                    TextInput::make('duration_seconds')->label('Duração (segundos)')->numeric()->minValue(0),
                ]),
            ]),
            Section::make('Publicação')->columnSpan(1)->schema([
                Select::make('status')->label('Situação')->options(ContentStatus::class)->default('published')->required(),
                DateTimePicker::make('published_at')->label('Publicar em')->helperText('Futuro = agendado'),
                Select::make('category_id')->label('Categoria')->relationship('category', 'name')->searchable()->preload(),
                Toggle::make('is_premium')->label('Exclusivo Sancta+'),
                Toggle::make('is_featured')->label('Destaque na tela inicial'),
                TextInput::make('author')->label('Autor/fonte')->maxLength(120),
                TagsInput::make('tags')->label('Tags'),
                TextInput::make('sort_order')->label('Ordem')->numeric()->default(0),
                FileUpload::make('cover_image')->label('Capa')->image()->imageEditor()->disk('public')->directory('prayers/covers')->visibility('public')->maxSize(4096),
                TextInput::make('slug')->label('Slug')->maxLength(191)->alphaDash()->unique(ignoreRecord: true)->helperText('Gerado automaticamente'),
            ]),
        ]);
    }

    /** Blocos usados nas orações estruturadas (formato herdado do app antigo). */
    public static function liturgicalBlocks(bool $withExpandable = false): array
    {
        $blocks = [
            Block::make('titulo')->label('Título')->icon(Heroicon::OutlinedHashtag)->schema([TextInput::make('texto')->label('Título')->required()]),
            Block::make('paragrafo')->label('Parágrafo')->icon(Heroicon::OutlinedBars3BottomLeft)->schema([Textarea::make('texto')->label('Texto')->required()->rows(3)]),
            Block::make('rubrica')->label('Rubrica (instrução)')->icon(Heroicon::OutlinedInformationCircle)->schema([Textarea::make('texto')->label('Rubrica')->required()->rows(2)]),
            Block::make('sacerdote')->label('Sacerdote (PR)')->icon(Heroicon::OutlinedUser)->schema([
                Textarea::make('texto')->label('Fala')->required()->rows(3),
                TextInput::make('orador')->label('Rótulo do orador')->placeholder('PR / ℣.'),
                Toggle::make('destaque')->label('Destacar (ex.: palavras da consagração)'),
            ]),
            Block::make('assembleia')->label('Assembleia (AS)')->icon(Heroicon::OutlinedUserGroup)->schema([Textarea::make('texto')->label('Resposta')->required()->rows(2)]),
            Block::make('dialogo')->label('Diálogo (℣./℟.)')->icon(Heroicon::OutlinedChatBubbleLeftRight)->schema([
                TextInput::make('orador')->label('Orador')->placeholder('℣. ou ℟.')->required(),
                Textarea::make('texto')->label('Texto')->required()->rows(2),
            ]),
            Block::make('icones')->label('Postura da assembleia')->icon(Heroicon::OutlinedHandRaised)->schema([
                CheckboxList::make('icones')->label('Posturas')->options(['de_pe' => 'De pé', 'maos_postas' => 'Mãos postas', 'de_joelhos' => 'De joelhos'])->columns(3),
            ]),
        ];

        if ($withExpandable) {
            $blocks[] = Block::make('expansivel')->label('Seção recolhível')->icon(Heroicon::OutlinedChevronUpDown)->schema([
                TextInput::make('titulo')->label('Título da seção')->required(),
                Builder::make('blocos')->label('Conteúdo')->blocks(static::liturgicalBlocks())->collapsible(),
            ]);
        }

        return $blocks;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image')->label('')->disk('public')->square()->size(40),
                TextColumn::make('title')->label('Título')->searchable()->sortable()->description(fn (Prayer $r) => $r->subtitle)->wrap(),
                TextColumn::make('category.name')->label('Categoria')->badge()->color('gray'),
                IconColumn::make('is_premium')->label('Sancta+')->boolean()->trueIcon(Heroicon::Star)->falseIcon(Heroicon::OutlinedMinus),
                IconColumn::make('audio_path')->label('Áudio')->boolean()->getStateUsing(fn (Prayer $r) => (bool) ($r->audio_path || $r->audio_url)),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('views_count')->label('Acessos')->numeric()->sortable(),
                TextColumn::make('updated_at')->label('Atualizada')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Categoria')->relationship('category', 'name'),
                SelectFilter::make('status')->options(ContentStatus::class),
                TernaryFilter::make('is_premium')->label('Sancta+'),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), ReplicateAction::make()->excludeAttributes(['slug', 'views_count']), DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRecordRouteBindingEloquentQuery(): EloquentBuilder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrayers::route('/'),
            'create' => CreatePrayer::route('/create'),
            'edit' => EditPrayer::route('/{record}/edit'),
        ];
    }
}
