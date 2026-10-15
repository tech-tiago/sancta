<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\Audience;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Avisos e pop-ups do app: eventos, promoções, comunicados e novidades.
 * Segmentação por público, período, frequência e prioridade; métricas de visualização/clique.
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Comunicação';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Avisos e pop-ups';

    protected static ?string $modelLabel = 'aviso';

    protected static ?string $pluralModelLabel = 'Avisos e pop-ups';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Mensagem')->columnSpan(2)->columns(2)->schema([
                TextInput::make('title')->label('Título')->required()->maxLength(191)->columnSpanFull(),
                Textarea::make('body')->label('Texto')->rows(4)->maxLength(1000)->columnSpanFull(),
                TextInput::make('cta_label')->label('Texto do botão')->maxLength(40)->placeholder('Saiba mais'),
                TextInput::make('cta_url')->label('Destino do botão')->maxLength(500)
                    ->placeholder('https://… ou sancta://plano')
                    ->rule('regex:/^(https:\/\/|sancta:\/\/)/i')
                    ->helperText('Links internos: sancta://planos, sancta://liturgia, sancta://oracao/{slug}, sancta://jornada/{slug}, sancta://igrejas'),
                FileUpload::make('image')->label('Imagem')->image()->imageEditor()->disk('public')->directory('announcements')->visibility('public')->maxSize(4096)->columnSpanFull(),
            ]),
            Section::make('Exibição')->columnSpan(1)->schema([
                Toggle::make('is_active')->label('Ativo')->default(true),
                Select::make('kind')->label('Formato')->options(['popup' => 'Pop-up (centro)', 'banner' => 'Banner (topo da tela inicial)', 'fullscreen' => 'Tela cheia'])->default('popup')->required(),
                Select::make('category')->label('Categoria')->options(['notice' => 'Aviso', 'event' => 'Evento', 'promotion' => 'Promoção', 'update' => 'Novidade'])->default('notice')->required(),
                Select::make('audience')->label('Público')->options(Audience::class)->default('all')->required(),
                Select::make('platform')->label('Onde')->options(['app' => 'App', 'site' => 'Site', 'both' => 'App e site'])->default('app')->required(),
                Select::make('frequency')->label('Frequência')->options(['once' => 'Uma vez por pessoa', 'daily' => 'Uma vez por dia', 'every_open' => 'Toda vez que abrir o app'])->default('once')->required(),
                TextInput::make('priority')->label('Prioridade (0-255)')->numeric()->minValue(0)->maxValue(255)->default(0),
                Toggle::make('dismissible')->label('Pode ser fechado')->default(true),
                DateTimePicker::make('starts_at')->label('Início'),
                DateTimePicker::make('ends_at')->label('Fim')->after('starts_at'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->description(fn (Announcement $r) => $r->category)->wrap(),
                TextColumn::make('kind')->label('Formato')->badge()->color('gray'),
                TextColumn::make('audience')->label('Público')->badge(),
                TextColumn::make('starts_at')->label('Período')->formatStateUsing(fn (Announcement $r) => ($r->starts_at?->format('d/m') ?? '—').' → '.($r->ends_at?->format('d/m') ?? '∞'))->placeholder('Sempre'),
                TextColumn::make('views_count')->label('Visualizações')->numeric()->sortable(),
                TextColumn::make('clicks_count')->label('Cliques')->numeric()->sortable()
                    ->description(fn (Announcement $r) => $r->views_count ? round($r->clicks_count / $r->views_count * 100, 1).'% CTR' : null),
                ToggleColumn::make('is_active')->label('Ativo'),
            ])
            ->recordActions([EditAction::make(), ReplicateAction::make()->excludeAttributes(['views_count', 'clicks_count']), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
