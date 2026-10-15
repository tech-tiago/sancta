<?php

namespace App\Filament\Resources\PushCampaigns;

use App\Enums\Audience;
use App\Filament\Resources\PushCampaigns\Pages\ManagePushCampaigns;
use App\Models\PushCampaign;
use App\Services\Push\PushService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Notificações push (Firebase): envio imediato ou agendado, por público. */
class PushCampaignResource extends Resource
{
    protected static ?string $model = PushCampaign::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Comunicação';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Notificações push';

    protected static ?string $modelLabel = 'notificação';

    protected static ?string $pluralModelLabel = 'Notificações push';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Título')->required()->maxLength(120),
            Select::make('audience')->label('Público')->options([
                Audience::All->value => Audience::All->getLabel(),
                Audience::Free->value => Audience::Free->getLabel(),
                Audience::Premium->value => Audience::Premium->getLabel(),
            ])->default('all')->required(),
            Textarea::make('body')->label('Mensagem')->required()->maxLength(500)->rows(3)->columnSpanFull(),
            TextInput::make('deep_link')->label('Abrir no app')->placeholder('sancta://liturgia')->rule('regex:/^sancta:\/\//i')->maxLength(255),
            TextInput::make('image_url')->label('Imagem (URL https)')->url()->rule('starts_with:https://')->maxLength(500),
            DateTimePicker::make('scheduled_at')->label('Agendar para')->helperText('Vazio = enviar manualmente com o botão "Enviar agora".')->minDate(now()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Título')->description(fn (PushCampaign $r) => $r->body)->wrap()->searchable(),
                TextColumn::make('audience')->label('Público')->badge(),
                TextColumn::make('status')->label('Situação')->badge()->color(fn (string $state) => match ($state) {
                    'sent' => 'success', 'failed' => 'danger', 'scheduled' => 'info', 'sending' => 'warning', default => 'gray',
                }),
                TextColumn::make('scheduled_at')->label('Agendada')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('sent_at')->label('Enviada')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('last_error')->label('Erro')->limit(40)->placeholder('—')->toggleable(),
            ])
            ->recordActions([
                Action::make('send')->label('Enviar agora')->icon(Heroicon::OutlinedPaperAirplane)->color('success')
                    ->visible(fn (PushCampaign $r) => $r->canBeSent() && auth()->user()->can('update', $r))
                    ->requiresConfirmation()
                    ->modalDescription(fn (PushCampaign $r) => 'A notificação será enviada para: '.$r->audience->getLabel().'.')
                    ->action(function (PushCampaign $record): void {
                        $ok = app(PushService::class)->sendCampaign($record);
                        $ok
                            ? Notification::make()->title('Notificação enviada')->success()->send()
                            : Notification::make()->title('Falha no envio')->body($record->fresh()->last_error)->danger()->send();
                    }),
                EditAction::make()->visible(fn (PushCampaign $r) => $r->canBeSent()),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePushCampaigns::route('/')];
    }
}
