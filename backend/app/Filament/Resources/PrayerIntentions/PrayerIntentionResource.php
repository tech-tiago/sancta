<?php

namespace App\Filament\Resources\PrayerIntentions;

use App\Enums\ModerationStatus;
use App\Filament\Resources\PrayerIntentions\Pages\ManagePrayerIntentions;
use App\Models\PrayerIntention;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/** Intenções de oração da comunidade — moderadas antes de aparecer no app. */
class PrayerIntentionResource extends Resource
{
    protected static ?string $model = PrayerIntention::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Igrejas';

    protected static ?string $navigationLabel = 'Intenções de oração';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'intenção';

    protected static ?string $pluralModelLabel = 'Intenções de oração';

    public static function getNavigationBadge(): ?string
    {
        $count = PrayerIntention::query()->where('status', ModerationStatus::Pending->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    private static function moderate(PrayerIntention $intention, ModerationStatus $status): void
    {
        $intention->forceFill(['status' => $status, 'moderated_by' => auth()->id(), 'moderated_at' => now()])->save();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Enviada')->since()->sortable(),
                TextColumn::make('text')->label('Intenção')->wrap()->limit(200),
                TextColumn::make('user.name')->label('Autor')->description(fn (PrayerIntention $r) => $r->user?->email),
                IconColumn::make('is_anonymous')->label('Anônima')->boolean(),
                TextColumn::make('prayers_count')->label('Rezaram')->sortable(),
                TextColumn::make('status')->label('Situação')->badge(),
            ])
            ->filters([SelectFilter::make('status')->options(ModerationStatus::class)->default(ModerationStatus::Pending->value)])
            ->recordActions([
                Action::make('approve')->label('Aprovar')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (PrayerIntention $r) => $r->status !== ModerationStatus::Approved && auth()->user()->can('update', $r))
                    ->action(function (PrayerIntention $record): void {
                        self::moderate($record, ModerationStatus::Approved);
                        Notification::make()->title('Intenção publicada')->success()->send();
                    }),
                Action::make('reject')->label('Recusar')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (PrayerIntention $r) => $r->status !== ModerationStatus::Rejected && auth()->user()->can('update', $r))
                    ->action(function (PrayerIntention $record): void {
                        self::moderate($record, ModerationStatus::Rejected);
                        Notification::make()->title('Intenção recusada')->success()->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveAll')->label('Aprovar selecionadas')->icon(Heroicon::OutlinedCheckCircle)
                        ->action(fn (Collection $records) => $records->each(fn ($r) => self::moderate($r, ModerationStatus::Approved))),
                    BulkAction::make('rejectAll')->label('Recusar selecionadas')->icon(Heroicon::OutlinedXCircle)->color('danger')
                        ->action(fn (Collection $records) => $records->each(fn ($r) => self::moderate($r, ModerationStatus::Rejected))),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManagePrayerIntentions::route('/')];
    }
}
