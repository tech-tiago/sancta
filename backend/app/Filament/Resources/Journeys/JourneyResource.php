<?php

namespace App\Filament\Resources\Journeys;

use App\Enums\ContentStatus;
use App\Enums\JourneyType;
use App\Filament\Resources\Journeys\Pages\CreateJourney;
use App\Filament\Resources\Journeys\Pages\EditJourney;
use App\Filament\Resources\Journeys\Pages\ListJourneys;
use App\Models\Journey;
use App\Models\JourneyProgress;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Jornadas: novenas, planos de leitura, desafios — com etapas diárias. */
class JourneyResource extends Resource
{
    protected static ?string $model = Journey::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'jornada';

    protected static ?string $pluralModelLabel = 'Jornadas';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Jornada')->columnSpan(2)->columns(2)->schema([
                TextInput::make('title')->label('Título')->required()->maxLength(191)->columnSpanFull(),
                Select::make('type')->label('Tipo')->options(JourneyType::class)->required()->default('journey'),
                TextInput::make('subtitle')->label('Subtítulo')->maxLength(255),
                RichEditor::make('description')->label('Descrição')->toolbarButtons([['bold', 'italic'], ['bulletList'], ['link']])->columnSpanFull(),
            ]),
            Section::make('Publicação')->columnSpan(1)->schema([
                Select::make('status')->label('Situação')->options(ContentStatus::class)->default('published')->required(),
                Toggle::make('is_premium')->label('Exclusiva Sancta+')->helperText('Não assinantes veem apenas o 1º dia.'),
                Toggle::make('is_featured')->label('Destaque'),
                DatePicker::make('available_from')->label('Disponível de'),
                DatePicker::make('available_until')->label('Disponível até'),
                ColorPicker::make('color')->label('Cor'),
                TextInput::make('sort_order')->label('Ordem')->numeric()->default(0),
                FileUpload::make('cover_image')->label('Capa')->image()->disk('public')->directory('journeys')->visibility('public')->maxSize(4096),
            ]),
            Section::make('Etapas (dias)')->columnSpanFull()->schema([
                Repeater::make('steps')->relationship()->label('')
                    ->orderColumn('position')
                    ->collapsible()->cloneable()
                    ->itemLabel(fn (array $state) => $state['title'] ?? 'Nova etapa')
                    ->addActionLabel('Adicionar etapa')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')->label('Título')->required()->maxLength(191)->columnSpanFull(),
                        TextInput::make('bible_reference')->label('Leitura bíblica')->placeholder('Jo 3,1-21')->maxLength(120),
                        Select::make('prayer_id')->label('Oração vinculada')->relationship('prayer', 'title')->searchable()->preload(),
                        RichEditor::make('content')->label('Meditação')->toolbarButtons([['bold', 'italic'], ['bulletList'], ['blockquote']])->columnSpanFull(),
                        TextInput::make('duration_seconds')->label('Duração (s)')->numeric()->default(600),
                        FileUpload::make('audio_path')->label('Áudio')->disk('public')->directory('journeys/audio')->visibility('public')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg'])->maxSize(51200),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->description(fn (Journey $r) => $r->subtitle),
                TextColumn::make('type')->label('Tipo')->badge(),
                TextColumn::make('steps_count')->counts('steps')->label('Etapas'),
                TextColumn::make('progress_count')->label('Etapas concluídas')
                    ->getStateUsing(fn (Journey $r) => JourneyProgress::query()->where('journey_id', $r->id)->count()),
                IconColumn::make('is_premium')->label('Sancta+')->boolean(),
                TextColumn::make('status')->label('Situação')->badge(),
            ])
            ->filters([SelectFilter::make('type')->label('Tipo')->options(JourneyType::class)])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJourneys::route('/'),
            'create' => CreateJourney::route('/create'),
            'edit' => EditJourney::route('/{record}/edit'),
        ];
    }
}
