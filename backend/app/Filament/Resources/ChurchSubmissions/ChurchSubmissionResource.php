<?php

namespace App\Filament\Resources\ChurchSubmissions;

use App\Enums\ChurchType;
use App\Enums\ModerationStatus;
use App\Filament\Resources\ChurchSubmissions\Pages\ManageChurchSubmissions;
use App\Models\ChurchSubmission;
use App\Services\Community\ChurchSubmissionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Fila de moderação: sugestões de novas igrejas e correções enviadas pelo app/site.
 * Aprovar → cria/atualiza a igreja automaticamente (aparece no mapa).
 */
class ChurchSubmissionResource extends Resource
{
    protected static ?string $model = ChurchSubmission::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Igrejas';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'sugestão';

    protected static ?string $pluralModelLabel = 'Sugestões e correções';

    public static function getNavigationBadge(): ?string
    {
        $count = ChurchSubmission::query()->where('status', ModerationStatus::Pending->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('type')->label('Tipo')->formatStateUsing(fn ($state) => $state === 'new' ? 'Nova igreja' : 'Correção'),
                TextEntry::make('status')->badge(),
                TextEntry::make('channel')->label('Canal'),
                TextEntry::make('church.name')->label('Igreja (correção)')->placeholder('—'),
                TextEntry::make('submitter_name')->label('Enviado por'),
                TextEntry::make('submitter_email')->label('E-mail')->copyable(),
                TextEntry::make('message')->label('Mensagem')->columnSpanFull()->placeholder('—'),
                KeyValueEntry::make('payload')->label('Dados sugeridos')->columnSpanFull(),
                TextEntry::make('image')->label('Foto enviada')->columnSpanFull()
                    ->state(fn (ChurchSubmission $r) => $r->image_path ? 'Imagem anexada (será publicada ao aprovar)' : 'Sem imagem'),
                TextEntry::make('review_notes')->label('Notas da revisão')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Recebida')->since()->sortable(),
                TextColumn::make('type')->label('Tipo')->badge()->formatStateUsing(fn ($state) => $state === 'new' ? 'Nova' : 'Correção')->color(fn ($state) => $state === 'new' ? 'success' : 'info'),
                TextColumn::make('payload.name')->label('Igreja')->getStateUsing(fn (ChurchSubmission $r) => $r->payload['name'] ?? $r->church?->name)->wrap(),
                TextColumn::make('payload.city')->label('Cidade')->getStateUsing(fn (ChurchSubmission $r) => trim(($r->payload['city'] ?? '').'/'.($r->payload['state'] ?? ''), '/')),
                TextColumn::make('submitter_name')->label('Enviado por')->description(fn (ChurchSubmission $r) => $r->submitter_email),
                TextColumn::make('channel')->label('Canal')->badge()->color('gray'),
                TextColumn::make('status')->label('Situação')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ModerationStatus::class)->default(ModerationStatus::Pending->value),
                SelectFilter::make('type')->label('Tipo')->options(['new' => 'Nova igreja', 'correction' => 'Correção']),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Aprovar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (ChurchSubmission $r) => $r->isPending() && auth()->user()->can('update', $r))
                    ->modalHeading('Aprovar e publicar no mapa')
                    ->modalDescription('Revise e ajuste os dados antes de publicar. Para correções, apenas os campos preenchidos serão atualizados.')
                    ->modalSubmitActionLabel('Aprovar e publicar')
                    ->fillForm(fn (ChurchSubmission $r) => $r->payload ?? [])
                    ->schema([
                        TextInput::make('name')->label('Nome')->maxLength(191),
                        Select::make('type')->label('Tipo')->options(ChurchType::class),
                        TextInput::make('address')->label('Endereço')->maxLength(255),
                        TextInput::make('neighborhood')->label('Bairro')->maxLength(120),
                        TextInput::make('city')->label('Cidade')->maxLength(120),
                        TextInput::make('state')->label('UF')->length(2),
                        TextInput::make('latitude')->label('Latitude')->numeric()->minValue(-90)->maxValue(90),
                        TextInput::make('longitude')->label('Longitude')->numeric()->minValue(-180)->maxValue(180),
                        TextInput::make('phone')->label('Telefone')->maxLength(30),
                        TextInput::make('email')->label('E-mail')->email()->maxLength(191),
                        TextInput::make('instagram')->label('Instagram')->maxLength(80),
                        TextInput::make('website')->label('Site')->url()->rule('regex:/^https?:\/\//i')->maxLength(255),
                        Textarea::make('mass_times')->label('Horários informados (texto)')->helperText('Depois de aprovar, estruture os horários na edição da igreja.'),
                        Toggle::make('is_verified')->label('Marcar como verificada'),
                        Textarea::make('review_notes')->label('Notas internas')->maxLength(500),
                    ])
                    ->action(function (ChurchSubmission $record, array $data): void {
                        $notes = $data['review_notes'] ?? null;
                        unset($data['review_notes']);
                        $church = app(ChurchSubmissionService::class)->approve($record, auth()->user(), $data, $notes);
                        Notification::make()->title('Publicado no mapa: '.$church->name)->success()->send();
                    }),
                Action::make('reject')
                    ->label('Recusar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (ChurchSubmission $r) => $r->isPending() && auth()->user()->can('update', $r))
                    ->schema([Textarea::make('notes')->label('Motivo (interno)')->maxLength(500)])
                    ->modalSubmitActionLabel('Recusar sugestão')
                    ->action(function (ChurchSubmission $record, array $data): void {
                        app(ChurchSubmissionService::class)->reject($record, auth()->user(), $data['notes'] ?? null);
                        Notification::make()->title('Sugestão recusada')->success()->send();
                    }),
                Action::make('image')
                    ->label('Ver foto')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->visible(fn (ChurchSubmission $r) => $r->image_path && Storage::disk('local')->exists($r->image_path))
                    ->modalContent(fn (ChurchSubmission $r) => new HtmlString(
                        '<img alt="" style="max-width:100%;border-radius:12px" src="data:'.Storage::disk('local')->mimeType($r->image_path).';base64,'.base64_encode(Storage::disk('local')->get($r->image_path)).'">'
                    ))
                    ->modalSubmitAction(false),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageChurchSubmissions::route('/')];
    }
}
