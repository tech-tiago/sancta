<?php

namespace App\Filament\Resources\Events;

use App\Enums\ContentStatus;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'Eventos';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Título')->required()->maxLength(191)->columnSpanFull(),
            DateTimePicker::make('starts_at')->label('Início')->required(),
            DateTimePicker::make('ends_at')->label('Término')->after('starts_at'),
            TextInput::make('location')->label('Local')->maxLength(255),
            Select::make('church_id')->label('Igreja')->relationship('church', 'name')->searchable(),
            TextInput::make('link')->label('Link (inscrição/transmissão)')->url()->rule('starts_with:https://')->maxLength(500),
            Select::make('status')->label('Situação')->options(ContentStatus::class)->default('published')->required(),
            Toggle::make('is_online')->label('Online'),
            Toggle::make('is_premium')->label('Exclusivo Sancta+'),
            RichEditor::make('description')->label('Descrição')->toolbarButtons([['bold', 'italic'], ['bulletList'], ['link']])->columnSpanFull(),
            FileUpload::make('cover_image')->label('Capa')->image()->disk('public')->directory('events')->visibility('public')->maxSize(4096)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('title')->label('Título')->searchable(),
                TextColumn::make('church.name')->label('Igreja')->placeholder('—'),
                IconColumn::make('is_premium')->label('Sancta+')->boolean(),
                TextColumn::make('status')->label('Situação')->badge(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
