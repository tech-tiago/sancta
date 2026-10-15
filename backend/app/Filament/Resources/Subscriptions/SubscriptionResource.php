<?php

namespace App\Filament\Resources\Subscriptions;

use App\Enums\PaymentProvider;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Assinaturas: visão de quem é assinante, de onde veio, até quando vale.
 * Criação manual = "cortesia" (EntitlementService), sempre auditada.
 */
class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Assinaturas';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'assinatura';

    protected static ?string $pluralModelLabel = 'Assinaturas';

    public static function getNavigationBadge(): ?string
    {
        return (string) Subscription::query()->entitled()->distinct('user_id')->count('user_id');
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Assinantes com acesso ativo';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'plan']))
            ->columns([
                TextColumn::make('user.name')->label('Assinante')->description(fn (Subscription $r) => $r->user?->email)->searchable(['name', 'email']),
                TextColumn::make('plan.name')->label('Plano')->placeholder('Cortesia'),
                TextColumn::make('provider')->label('Origem')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                IconColumn::make('auto_renew')->label('Renova')->boolean(),
                TextColumn::make('starts_at')->label('Início')->date('d/m/Y')->sortable(),
                TextColumn::make('ends_at')->label('Acesso até')->date('d/m/Y')->sortable()->placeholder('Vitalício')
                    ->color(fn (Subscription $r) => $r->ends_at && $r->ends_at->isPast() ? 'danger' : ($r->ends_at && $r->ends_at->lt(now()->addDays(7)) ? 'warning' : null)),
                TextColumn::make('notes')->label('Obs.')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('entitled')->label('Somente com acesso ativo')->query(fn (Builder $query) => $query->entitled())->default(),
                SelectFilter::make('status')->options(SubscriptionStatus::class),
                SelectFilter::make('provider')->label('Origem')->options(PaymentProvider::class),
                SelectFilter::make('plan_id')->label('Plano')->relationship('plan', 'name'),
                Filter::make('expiring')->label('Vencendo em 7 dias')->query(fn (Builder $query) => $query->entitled()->whereBetween('ends_at', [now(), now()->addDays(7)])),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revogar')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (Subscription $r) => $r->isEntitled() && $r->provider !== PaymentProvider::GooglePlay && auth()->user()->can('update', $r))
                    ->requiresConfirmation()
                    ->schema([Textarea::make('reason')->label('Motivo')->required()->maxLength(255)])
                    ->action(function (Subscription $record, array $data): void {
                        app(EntitlementService::class)->revoke($record, auth()->user(), $data['reason']);
                        Notification::make()->title('Acesso revogado')->success()->send();
                    }),
            ])
            ->headerActions([static::grantAction()]);
    }

    public static function grantAction(): Action
    {
        return Action::make('grant')
            ->label('Conceder cortesia')
            ->icon(Heroicon::OutlinedGift)
            ->visible(fn () => auth()->user()->can('create', Subscription::class))
            ->schema([
                Select::make('user_id')->label('Usuário')->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::query()
                        ->where(fn ($q) => $q->where('email', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                        ->limit(20)->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} <{$u->email}>"]))
                    ->getOptionLabelUsing(fn ($value) => User::query()->find($value)?->email),
                Select::make('plan_id')->label('Plano (referência)')->options(fn () => Plan::query()->pluck('name', 'id')),
                TextInput::make('days')->label('Dias de acesso')->numeric()->required()->minValue(1)->maxValue(3650)->default(30),
                Textarea::make('notes')->label('Motivo / observação')->required()->maxLength(500),
            ])
            ->action(function (array $data): void {
                app(EntitlementService::class)->grantManual(
                    User::query()->findOrFail($data['user_id']),
                    $data['plan_id'] ? Plan::query()->find($data['plan_id']) : null,
                    (int) $data['days'],
                    auth()->user(),
                    $data['notes'],
                );
                Notification::make()->title('Cortesia concedida')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return ['index' => ManageSubscriptions::route('/')];
    }
}
