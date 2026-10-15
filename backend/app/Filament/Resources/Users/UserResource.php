<?php

namespace App\Filament\Resources\Users;

use App\Enums\ThemePreference;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Users\RelationManagers\SubscriptionsRelationManager;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\Billing\EntitlementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Usuários';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'usuário';

    protected static ?string $pluralModelLabel = 'Usuários';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dados da conta')->columns(2)->schema([
                TextInput::make('name')->label('Nome')->required()->maxLength(120),
                TextInput::make('email')->label('E-mail')->email()->required()->maxLength(191)->unique(ignoreRecord: true),
                Select::make('theme')->label('Tema')->options(ThemePreference::class)->default('system'),
                Toggle::make('marketing_opt_in')->label('Aceita comunicações de marketing'),
                TextInput::make('new_password')->label(fn (string $operation) => $operation === 'create' ? 'Senha' : 'Definir nova senha')
                    ->password()->revealable()->rule(Password::defaults())
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText('Mínimo 8 caracteres, com letras e números. Ao trocar, todas as sessões do usuário são encerradas.'),
            ]),
            Section::make('Acesso')->columns(2)->schema([
                Select::make('role')->label('Papel')->options(UserRole::class)->required()->default('user')
                    ->disabled(fn () => ! auth()->user()->can('changeRole', User::class))
                    ->dehydrated(fn () => auth()->user()->can('changeRole', User::class))
                    ->helperText('Somente o super administrador altera papéis.'),
                Select::make('status')->label('Situação')->options(UserStatus::class)->required()->default('active'),
                Textarea::make('suspension_reason')->label('Motivo da suspensão')->maxLength(500)->columnSpanFull(),
            ]),
            Section::make('Atividade')->columns(3)->visibleOn('edit')->schema([
                Placeholder::make('premium')->label('Sancta+')->content(fn (?User $record) => $record?->activeSubscription() ? 'Ativo até '.($record->activeSubscription()->ends_at?->format('d/m/Y') ?? 'sem término') : 'Gratuito'),
                Placeholder::make('streak')->label('Sequência')->content(fn (?User $record) => ($record?->current_streak ?? 0).' dias (recorde '.($record?->longest_streak ?? 0).')'),
                Placeholder::make('minutes')->label('Tempo de oração')->content(fn (?User $record) => intdiv((int) $record?->total_prayer_seconds, 60).' min'),
                Placeholder::make('last_login')->label('Último acesso')->content(fn (?User $record) => $record?->last_login_at?->format('d/m/Y H:i') ?? '—'),
                Placeholder::make('source_label')->label('Origem do cadastro')->content(fn (?User $record) => $record?->source),
                Placeholder::make('mfa')->label('2FA')->content(fn (?User $record) => $record?->app_authentication_secret ? 'Ativado' : 'Desativado'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withExists(['subscriptions as is_premium' => fn ($s) => $s->entitled()]))
            ->columns([
                TextColumn::make('name')->label('Nome')->description(fn (User $r) => $r->email)->searchable(['name', 'email'])->sortable(),
                IconColumn::make('is_premium')->label('Sancta+')->boolean()->trueIcon(Heroicon::Star)->falseIcon(Heroicon::OutlinedMinus),
                TextColumn::make('role')->label('Papel')->badge(),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('current_streak')->label('Sequência')->suffix(' d')->sortable()->toggleable(),
                TextColumn::make('source')->label('Origem')->badge()->color('gray')->toggleable(),
                TextColumn::make('last_login_at')->label('Último acesso')->since()->sortable()->toggleable(),
                TextColumn::make('created_at')->label('Cadastro')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('premium')->label('Assinante')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('subscriptions', fn ($s) => $s->entitled()),
                        false: fn (Builder $query) => $query->whereDoesntHave('subscriptions', fn ($s) => $s->entitled()),
                    ),
                SelectFilter::make('role')->label('Papel')->options(UserRole::class),
                SelectFilter::make('status')->label('Situação')->options(UserStatus::class),
                SelectFilter::make('source')->label('Origem')->options(['app' => 'App', 'site' => 'Site', 'admin' => 'Painel', 'import' => 'Importação']),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make(static::accountActions()),
            ]);
    }

    /** Ações sensíveis sobre a conta — todas autorizadas e auditadas. */
    public static function accountActions(): array
    {
        return [
            Action::make('grantPremium')
                ->label('Conceder Sancta+')
                ->icon(Heroicon::OutlinedGift)
                ->visible(fn () => auth()->user()->hasRoleAtLeast(UserRole::Admin))
                ->schema([
                    Select::make('plan_id')->label('Plano (referência)')->options(fn () => Plan::query()->pluck('name', 'id')),
                    TextInput::make('days')->label('Dias')->numeric()->required()->minValue(1)->maxValue(3650)->default(30),
                    Textarea::make('notes')->label('Motivo')->required()->maxLength(500),
                ])
                ->action(function (User $record, array $data): void {
                    app(EntitlementService::class)->grantManual($record, $data['plan_id'] ? Plan::query()->find($data['plan_id']) : null, (int) $data['days'], auth()->user(), $data['notes']);
                    Notification::make()->title('Acesso Sancta+ concedido')->success()->send();
                }),

            Action::make('toggleSuspension')
                ->label(fn (User $record) => $record->isActive() ? 'Suspender' : 'Reativar')
                ->icon(fn (User $record) => $record->isActive() ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                ->color(fn (User $record) => $record->isActive() ? 'danger' : 'success')
                ->visible(fn (User $record) => auth()->user()->can('update', $record) && auth()->user()->isNot($record))
                ->requiresConfirmation()
                ->schema(fn (User $record) => $record->isActive() ? [Textarea::make('reason')->label('Motivo')->required()->maxLength(500)] : [])
                ->action(function (User $record, array $data): void {
                    $suspend = $record->isActive();
                    $record->forceFill([
                        'status' => $suspend ? UserStatus::Suspended : UserStatus::Active,
                        'suspension_reason' => $suspend ? $data['reason'] : null,
                    ])->save();
                    if ($suspend) {
                        $record->tokens()->delete();
                        DB::table('sessions')->where('user_id', $record->id)->delete();
                    }
                    AuditLog::record($suspend ? 'user.suspended' : 'user.reactivated', $record, [], ['reason' => $data['reason'] ?? null]);
                    Notification::make()->title($suspend ? 'Usuário suspenso' : 'Usuário reativado')->success()->send();
                }),

            Action::make('revokeSessions')
                ->label('Encerrar sessões')
                ->icon(Heroicon::OutlinedKey)
                ->visible(fn (User $record) => auth()->user()->can('update', $record))
                ->requiresConfirmation()
                ->modalDescription('Desconecta o usuário de todos os aparelhos e navegadores.')
                ->action(function (User $record): void {
                    $record->tokens()->delete();
                    DB::table('sessions')->where('user_id', $record->id)->delete();
                    AuditLog::record('user.sessions_revoked', $record);
                    Notification::make()->title('Sessões encerradas')->success()->send();
                }),

            Action::make('resetMfa')
                ->label('Redefinir 2FA')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->visible(fn (User $record) => $record->isStaff() && auth()->user()->hasRoleAtLeast(UserRole::SuperAdmin) && $record->app_authentication_secret)
                ->requiresConfirmation()
                ->action(function (User $record): void {
                    $record->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
                    AuditLog::record('user.mfa_reset', $record);
                    Notification::make()->title('2FA redefinido')->success()->send();
                }),

            Action::make('deleteAccount')
                ->label('Excluir conta (LGPD)')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (User $record) => auth()->user()->can('delete', $record))
                ->requiresConfirmation()
                ->modalDescription('Remove os dados pessoais e de uso do usuário e anonimiza os pagamentos. Ação irreversível.')
                ->schema([TextInput::make('confirm')->label('Digite EXCLUIR')->required()->in(['EXCLUIR'])])
                ->action(function (User $record): void {
                    AuditLog::record('user.deleted_by_admin', $record);
                    app(AccountService::class)->delete($record);
                    Notification::make()->title('Conta excluída')->success()->send();
                }),
        ];
    }

    public static function getRelations(): array
    {
        return [SubscriptionsRelationManager::class, PaymentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
