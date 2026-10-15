<?php

namespace App\Filament\Resources\LiveChannels;

use App\Enums\ScheduleType;
use App\Filament\Resources\LiveChannels\Pages\ManageLiveChannels;
use App\Models\LiveChannel;
use App\Services\Live\LiveStatusService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Canais e transmissões do YouTube exibidos na área "Ao vivo" do app e do site.
 * O status (ao vivo/agendado) é detectado pelo comando sancta:live-sync; aqui se cadastra
 * o canal, a categoria, a capa e a agenda fixa de transmissões.
 */
class LiveChannelResource extends Resource
{
    protected static ?string $model = LiveChannel::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static string|UnitEnum|null $navigationGroup = 'Mídia';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Ao vivo (YouTube)';

    protected static ?string $modelLabel = 'canal ao vivo';

    protected static ?string $pluralModelLabel = 'Canais ao vivo';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Canal')->columns(2)->schema([
                TextInput::make('name')->label('Nome')->required()->maxLength(120),
                Select::make('category')->label('Categoria')->options(LiveChannel::CATEGORIES)->default('canal')->required()->selectablePlaceholder(false),
                TextInput::make('youtube_channel_id')
                    ->label('ID do canal do YouTube')
                    ->placeholder('UCxxxxxxxxxxxxxxxxxxxxxx')
                    ->maxLength(24)
                    ->regex(LiveChannel::CHANNEL_ID_REGEX)
                    ->validationMessages(['regex' => 'O ID do canal começa com "UC" e tem 24 caracteres (letras, números, - e _).'])
                    ->helperText('Necessário para detectar a live automaticamente. No YouTube, abra o canal → "Mais sobre este canal" → "Compartilhar canal" → "Copiar ID do canal". Começa com UC e tem 24 caracteres.')
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? trim($state) : null),
                Select::make('type')->label('Tipo')->options(['live' => 'Transmissão ao vivo', 'video' => 'Vídeo', 'playlist' => 'Playlist', 'channel' => 'Canal'])->default('live')->required(),
                TextInput::make('youtube_url')->label('Link do YouTube')->url()->maxLength(500)->columnSpanFull()
                    ->requiredWithout('youtube_channel_id')
                    ->validationMessages(['required_without' => 'Informe o link do YouTube ou o ID do canal.'])
                    ->rule('regex:/^https:\/\/(www\.|m\.)?(youtube\.com|youtu\.be)\//i')
                    ->helperText('Opcional se o ID do canal for informado (usamos a página do canal). Ex.: https://www.youtube.com/@canal ou o vídeo fixo de uma transmissão 24h.'),
                TextInput::make('description')->label('Descrição')->maxLength(255)->columnSpanFull(),
                TextInput::make('fallback_url')->label('Site alternativo')->url()->rule('starts_with:https://')->maxLength(500),
                TextInput::make('sort_order')->label('Ordem')->numeric()->default(0),
                FileUpload::make('logo')->label('Logo')->image()->disk('public')->directory('live')->visibility('public')->maxSize(2048),
                FileUpload::make('cover')->label('Capa (opcional)')
                    ->helperText('Imagem horizontal (16:9). Sem capa, usamos a miniatura da live ou o logo.')
                    ->image()->disk('public')->directory('live/capas')->visibility('public')->maxSize(4096),
                Toggle::make('is_active')->label('Ativo')->default(true),
            ]),
            Section::make('Agenda de transmissões')
                ->description('Horários fixos (horário de Brasília). Sem chave do YouTube, o app mostra "No horário da transmissão" durante estes períodos.')
                ->schema([
                    Repeater::make('schedules')->relationship()->hiddenLabel()
                        ->orderColumn('sort_order')
                        ->columns(4)->cloneable()->collapsible()
                        ->defaultItems(0)
                        ->maxItems(50)
                        ->addActionLabel('Adicionar horário')
                        ->itemLabel(function (array $state): string {
                            $weekday = isset($state['weekday']) && $state['weekday'] !== '' && $state['weekday'] !== null ? (int) $state['weekday'] : null;
                            $day = $weekday === null ? 'Todos os dias' : ScheduleType::weekdayLabel($weekday);

                            return trim($day.' '.substr((string) ($state['starts_at'] ?? ''), 0, 5).' — '.($state['title'] ?? ''), ' —');
                        })
                        ->schema([
                            Select::make('weekday')->label('Dia')->options(ScheduleType::weekdayOptions())->placeholder('Todos os dias'),
                            TimePicker::make('starts_at')->label('Início')->seconds(false)->required(),
                            TextInput::make('duration_minutes')->label('Duração (min)')->integer()->minValue(5)->maxValue(720)->default(60)->required(),
                            TextInput::make('title')->label('Título')->placeholder('Santa Missa')->maxLength(120),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->description(fn () => app(LiveStatusService::class)->hasApiKey()
                ? 'Detecção automática ativa: o status é atualizado pelo YouTube a cada 3 minutos.'
                : 'Sem chave do YouTube: o status segue a agenda de cada canal. Cadastre a chave em Sistema → Integrações e segurança → Conteúdo.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('schedules')->withCount('schedules'))
            ->columns([
                ImageColumn::make('thumb')->label('')->getStateUsing(fn (LiveChannel $r) => $r->thumbnailUrl())->square()->size(44),
                TextColumn::make('name')->label('Canal')
                    ->description(fn (LiveChannel $r) => $r->categoryLabel().' · '.($r->youtube_channel_id ?: 'sem ID do canal'))
                    ->searchable(),
                TextColumn::make('live_state')->label('Status')
                    ->getStateUsing(fn (LiveChannel $r) => $r->displayStatus())
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'live' => 'Ao vivo',
                        'upcoming' => 'Agendado',
                        default => '—',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'live' => 'danger',
                        'upcoming' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (LiveChannel $r) => match ($r->displayStatus()) {
                        'live' => Str::limit((string) $r->live_title, 50),
                        'upcoming' => $r->live_scheduled_at?->setTimezone(LiveChannel::TIMEZONE)->format('d/m H:i'),
                        default => $r->isOnScheduleNow() ? 'No horário da agenda' : null,
                    }),
                TextColumn::make('schedules_count')->label('Agenda')->suffix(' horário(s)')->color('gray'),
                TextColumn::make('live_checked_at')->label('Última verificação')->since()->placeholder('Nunca')
                    ->tooltip(fn (LiveChannel $r) => $r->live_checked_at?->format('d/m/Y H:i:s')),
                ToggleColumn::make('is_active')->label('Ativo'),
            ])
            ->filters([
                SelectFilter::make('category')->label('Categoria')->options(LiveChannel::CATEGORIES),
            ])
            ->recordActions([
                Action::make('check_now')
                    ->label('Verificar agora')
                    ->icon(Heroicon::OutlinedSignal)
                    ->color('gray')
                    ->visible(fn (LiveChannel $r) => filled($r->youtube_channel_id) && auth()->user()->can('update', $r))
                    ->action(function (LiveChannel $record): void {
                        // Protege a cota diária da API (10 mil unidades): limite por pessoa (10/min) e teto
                        // global (100/h) — a verificação automática nunca fica sem cota. Pentest fase 3 — SEC-15.
                        $key = 'live-check:'.auth()->id();
                        if (RateLimiter::tooManyAttempts($key, 10) || RateLimiter::tooManyAttempts('live-check:todos', 100)) {
                            Notification::make()->title('Muitas verificações seguidas')->body('Aguarde alguns minutos. A verificação automática continua a cada 3 minutos.')->warning()->send();

                            return;
                        }
                        RateLimiter::hit($key, 60);
                        RateLimiter::hit('live-check:todos', 3600);

                        $message = app(LiveStatusService::class)->syncChannel($record);
                        Notification::make()->title($record->name)->body($message)->info()->send();
                    }),
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLiveChannels::route('/')];
    }
}
