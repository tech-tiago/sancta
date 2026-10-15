<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use App\Services\Billing\CheckoutService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use UnitEnum;

/** Pagamentos: somente leitura (registro financeiro imutável). */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Assinaturas';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'pagamento';

    protected static ?string $pluralModelLabel = 'Pagamentos';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pagamento')->columns(3)->schema([
                TextEntry::make('uuid')->label('ID interno')->copyable(),
                TextEntry::make('provider_payment_id')->label('ID no provedor')->copyable()->placeholder('—'),
                TextEntry::make('status')->badge(),
                TextEntry::make('amount_cents')->label('Valor')->money('BRL', divideBy: 100),
                TextEntry::make('discount_cents')->label('Desconto')->money('BRL', divideBy: 100),
                TextEntry::make('installments')->label('Parcelas'),
                TextEntry::make('method')->label('Forma')->badge(),
                TextEntry::make('provider')->label('Provedor'),
                TextEntry::make('coupon.code')->label('Cupom')->placeholder('—'),
                TextEntry::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i'),
                TextEntry::make('paid_at')->label('Pago em')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('refunded_at')->label('Estornado em')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('failure_reason')->label('Motivo da falha')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Cliente e plano')->columns(3)->schema([
                TextEntry::make('user.name')->label('Nome'),
                TextEntry::make('user.email')->label('E-mail')->copyable(),
                TextEntry::make('plan.name')->label('Plano'),
                TextEntry::make('subscription.ends_at')->label('Acesso gerado até')->date('d/m/Y')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'plan']))
            ->columns([
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Cliente')->description(fn (Payment $r) => $r->user?->email)->searchable(['name', 'email']),
                TextColumn::make('plan.name')->label('Plano'),
                TextColumn::make('method')->label('Forma')->badge(),
                TextColumn::make('amount_cents')->label('Valor')->money('BRL', divideBy: 100)->sortable()
                    ->summarize(Sum::make()->label('Total')->money('BRL', divideBy: 100)),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('provider_payment_id')->label('ID provedor')->toggleable(isToggledHiddenByDefault: true)->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PaymentStatus::class)->multiple(),
                SelectFilter::make('method')->label('Forma')->options(PaymentMethod::class),
                SelectFilter::make('provider')->label('Provedor')->options(PaymentProvider::class),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label('De'),
                    DatePicker::make('until')->label('Até'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('sync')
                    ->label('Consultar provedor')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Payment $r) => $r->isPending() && in_array($r->provider, [PaymentProvider::MercadoPago, PaymentProvider::Fake], true))
                    ->action(function (Payment $record): void {
                        try {
                            $payment = app(CheckoutService::class)->sync($record);
                            Notification::make()->title('Status: '.$payment->status->getLabel())->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Falha ao consultar')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
