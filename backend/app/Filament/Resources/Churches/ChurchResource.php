<?php

namespace App\Filament\Resources\Churches;

use App\Enums\ChurchType;
use App\Enums\ContentStatus;
use App\Enums\ScheduleType;
use App\Filament\Resources\Churches\Pages\CreateChurch;
use App\Filament\Resources\Churches\Pages\EditChurch;
use App\Filament\Resources\Churches\Pages\ListChurches;
use App\Models\Church;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ChurchResource extends Resource
{
    protected static ?string $model = Church::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Igrejas';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'igreja';

    protected static ?string $pluralModelLabel = 'Igrejas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->persistTabInQueryString()->tabs([
                Tab::make('Dados')->icon(Heroicon::OutlinedBuildingLibrary)->columns(2)->schema([
                    TextInput::make('name')->label('Nome')->required()->maxLength(191)->columnSpanFull(),
                    Select::make('type')->label('Tipo')->options(ChurchType::class)->required()->default('paroquia'),
                    Select::make('status')->label('Situação')->options([ContentStatus::Published->value => 'Publicada', ContentStatus::Hidden->value => 'Oculta'])->default('published')->required(),
                    TextInput::make('diocese')->label('(Arqui)diocese')->maxLength(191),
                    TextInput::make('patron')->label('Padroeiro(a)')->maxLength(191),
                    Toggle::make('is_verified')->label('Informações verificadas')->helperText('Exibe o selo "verificada" no app e no site.'),
                    Textarea::make('description')->label('Sobre')->rows(3)->columnSpanFull(),
                    FileUpload::make('cover_image')->label('Foto')->image()->imageEditor()->disk('public')->directory('churches')->visibility('public')->maxSize(4096)->columnSpanFull(),
                ]),
                Tab::make('Endereço e mapa')->icon(Heroicon::OutlinedMapPin)->columns(3)->schema([
                    TextInput::make('address')->label('Endereço')->maxLength(255)->columnSpan(2),
                    TextInput::make('neighborhood')->label('Bairro')->maxLength(120),
                    TextInput::make('city')->label('Cidade')->maxLength(120)->required(),
                    TextInput::make('state')->label('UF')->length(2)->required(),
                    TextInput::make('postal_code')->label('CEP')->maxLength(12),
                    TextInput::make('latitude')->label('Latitude')->numeric()->minValue(-90)->maxValue(90)->helperText('No Google Maps: clique com o botão direito no local → copie as coordenadas.'),
                    TextInput::make('longitude')->label('Longitude')->numeric()->minValue(-180)->maxValue(180),
                    TextInput::make('country')->label('País')->default('Brasil')->maxLength(60),
                ]),
                Tab::make('Contato')->icon(Heroicon::OutlinedPhone)->columns(2)->schema([
                    TextInput::make('phone')->label('Telefone')->tel()->maxLength(30),
                    TextInput::make('whatsapp')->label('WhatsApp')->tel()->maxLength(30),
                    TextInput::make('email')->label('E-mail')->email()->maxLength(191),
                    TextInput::make('website')->label('Site')->url()->rule('regex:/^https?:\/\//i')->maxLength(255),
                    TextInput::make('instagram')->label('Instagram')->prefix('@')->maxLength(80),
                    TextInput::make('facebook')->label('Facebook (URL)')->url()->rule('regex:/^https:\/\//i')->maxLength(255),
                    TextInput::make('youtube')->label('YouTube (URL)')->url()->rule('regex:/^https:\/\//i')->maxLength(255),
                ]),
                Tab::make('Horários')->icon(Heroicon::OutlinedClock)->schema([
                    Repeater::make('schedules')->relationship()->label('')
                        ->orderColumn('sort_order')
                        ->columns(5)->cloneable()->addActionLabel('Adicionar horário')
                        ->itemLabel(function (array $state): string {
                            $type = $state['type'] ?? null;
                            $type = $type instanceof ScheduleType ? $type : ScheduleType::tryFrom((string) $type);
                            $weekday = isset($state['weekday']) && $state['weekday'] !== '' && $state['weekday'] !== null ? (int) $state['weekday'] : null;

                            return ($type?->getLabel() ?? 'Horário').' — '.ScheduleType::weekdayLabel($weekday).' '.substr((string) ($state['starts_at'] ?? ''), 0, 5);
                        })
                        ->schema([
                            Select::make('type')->label('Tipo')->options(ScheduleType::class)->required()->default('missa'),
                            Select::make('weekday')->label('Dia')->options(ScheduleType::weekdayOptions())->placeholder('Texto livre'),
                            TimePicker::make('starts_at')->label('Início')->seconds(false),
                            TimePicker::make('ends_at')->label('Fim')->seconds(false),
                            TextInput::make('notes')->label('Observação')->maxLength(191),
                        ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Igreja')->searchable()->sortable()->description(fn (Church $r) => $r->fullAddress())->wrap(),
                TextColumn::make('type')->label('Tipo')->badge()->color('gray'),
                TextColumn::make('city')->label('Cidade')->formatStateUsing(fn (Church $r) => "{$r->city}/{$r->state}")->searchable()->sortable(),
                TextColumn::make('schedules_count')->counts('schedules')->label('Horários'),
                TextColumn::make('favorited_by_count')->counts('favoritedBy')->label('Favoritos')->sortable(),
                IconColumn::make('is_verified')->label('Verificada')->boolean(),
                IconColumn::make('has_coordinates')->label('No mapa')->boolean()->getStateUsing(fn (Church $r) => $r->latitude !== null && $r->longitude !== null),
                TextColumn::make('status')->label('Situação')->badge(),
            ])
            ->filters([
                SelectFilter::make('state')->label('UF')->options(fn () => Church::query()->whereNotNull('state')->distinct()->orderBy('state')->pluck('state', 'state')->all()),
                SelectFilter::make('type')->label('Tipo')->options(ChurchType::class),
                TernaryFilter::make('is_verified')->label('Verificada'),
                TernaryFilter::make('coordinates')->label('Com coordenadas')
                    ->queries(true: fn (Builder $query) => $query->whereNotNull('latitude'), false: fn (Builder $query) => $query->whereNull('latitude')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('view_site')->label('Ver no site')->icon(Heroicon::OutlinedGlobeAlt)->url(fn (Church $r) => route('churches.show', $r->slug), true),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChurches::route('/'),
            'create' => CreateChurch::route('/create'),
            'edit' => EditChurch::route('/{record}/edit'),
        ];
    }
}
