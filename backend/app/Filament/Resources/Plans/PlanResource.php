<?php

namespace App\Filament\Resources\Plans;

use App\Enums\PlanInterval;
use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Assinaturas';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'plano';

    protected static ?string $pluralModelLabel = 'Planos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Plano')->columns(2)->schema([
                TextInput::make('name')->label('Nome')->required()->maxLength(80),
                TextInput::make('slug')->label('Identificador (URL)')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true),
                Textarea::make('description')->label('Descrição curta')->maxLength(500)->columnSpanFull(),
                TextInput::make('badge')->label('Selo')->placeholder('Mais popular')->maxLength(40),
                TextInput::make('sort_order')->label('Ordem')->numeric()->default(0),
            ]),
            Section::make('Preço e período')->columns(3)->schema([
                TextInput::make('price_cents')->label('Preço (centavos)')->helperText('Ex.: 14990 = R$ 149,90')->numeric()->minValue(100)->required(),
                TextInput::make('compare_at_price_cents')->label('Preço "de" (centavos)')->numeric()->minValue(0),
                TextInput::make('currency')->label('Moeda')->default('BRL')->length(3)->required(),
                Select::make('interval')->label('Período')->options(PlanInterval::class)->required()->default('month'),
                TextInput::make('interval_count')->label('Quantidade de períodos')->numeric()->minValue(1)->maxValue(36)->default(1)->required(),
                TextInput::make('trial_days')->label('Dias grátis (app)')->numeric()->minValue(0)->maxValue(90)->default(0),
                TextInput::make('max_installments')->label('Máx. parcelas no cartão')->numeric()->minValue(1)->maxValue(12)->default(1),
            ]),
            Section::make('Google Play')->description('Produto de assinatura e plano-base cadastrados no Play Console.')->columns(2)->schema([
                TextInput::make('google_play_product_id')->label('ID do produto')->placeholder('sancta_plus')->maxLength(120),
                TextInput::make('google_play_base_plan_id')->label('ID do plano-base')->placeholder('anual')->maxLength(120),
            ]),
            Section::make('Exibição')->columns(4)->schema([
                TagsInput::make('features')->label('Benefícios listados')->columnSpanFull(),
                Toggle::make('is_active')->label('Ativo')->default(true),
                Toggle::make('is_featured')->label('Destaque'),
                Toggle::make('available_on_web')->label('Vender no site')->default(true),
                Toggle::make('available_on_app')->label('Vender no app')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Plano')->weight('bold')->description(fn (Plan $r) => $r->badge),
                TextColumn::make('price_cents')->label('Preço')->money('BRL', divideBy: 100)->sortable(),
                TextColumn::make('interval')->label('Período')->formatStateUsing(fn (Plan $r) => $r->periodLabel()),
                TextColumn::make('subscriptions_count')->counts(['subscriptions' => fn ($q) => $q->entitled()])->label('Assinantes ativos'),
                IconColumn::make('is_active')->label('Ativo')->boolean(),
                IconColumn::make('available_on_web')->label('Site')->boolean(),
                IconColumn::make('available_on_app')->label('App')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
