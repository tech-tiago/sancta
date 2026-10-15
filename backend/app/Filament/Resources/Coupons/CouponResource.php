<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Models\Coupon;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Assinaturas';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'cupom';

    protected static ?string $pluralModelLabel = 'Cupons';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Código')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state)),
            TextInput::make('description')->label('Descrição interna')->maxLength(255),
            Select::make('type')->label('Tipo')->options(['percent' => 'Percentual (%)', 'fixed' => 'Valor fixo (centavos)'])->required()->default('percent')->live(),
            TextInput::make('value')->label(fn (Get $get) => $get('type') === 'fixed' ? 'Desconto (centavos)' : 'Desconto (%)')
                ->numeric()->required()->minValue(1)->maxValue(fn (Get $get) => $get('type') === 'fixed' ? 1_000_000 : 100),
            TextInput::make('max_redemptions')->label('Limite de usos')->numeric()->minValue(1)->helperText('Vazio = ilimitado'),
            TextInput::make('max_redemptions_per_user')->label('Limite por pessoa')->integer()->minValue(1)->maxValue(1000)->default(1)
                ->helperText('Quantas vezes a MESMA conta pode usar o cupom (presentes incluídos). Vazio = sem limite por pessoa.'),
            Select::make('plan_ids')->label('Planos válidos')->multiple()->options(fn () => Plan::query()->pluck('name', 'id'))->helperText('Vazio = todos'),
            DateTimePicker::make('starts_at')->label('Válido a partir de'),
            DateTimePicker::make('expires_at')->label('Expira em')->after('starts_at'),
            Toggle::make('is_active')->label('Ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Código')->copyable()->weight('bold')->searchable(),
                TextColumn::make('value')->label('Desconto')->formatStateUsing(fn (Coupon $r) => $r->type === 'percent' ? "{$r->value}%" : 'R$ '.number_format($r->value / 100, 2, ',', '.')),
                TextColumn::make('redemptions_count')->label('Usos')->formatStateUsing(fn (Coupon $r) => $r->redemptions_count.($r->max_redemptions ? " / {$r->max_redemptions}" : '')),
                TextColumn::make('expires_at')->label('Expira')->dateTime('d/m/Y H:i')->placeholder('—'),
                IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoupons::route('/')];
    }
}
