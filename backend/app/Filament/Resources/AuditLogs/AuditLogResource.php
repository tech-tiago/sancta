<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Trilha de auditoria: quem fez o quê no painel, quando e de qual IP. */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'registro de auditoria';

    protected static ?string $pluralModelLabel = 'Auditoria';

    private static function flatten(?array $values): array
    {
        return collect($values ?? [])->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))->all();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('created_at')->label('Quando')->dateTime('d/m/Y H:i:s'),
            TextEntry::make('user.name')->label('Quem')->placeholder('Sistema'),
            TextEntry::make('event')->label('Evento')->badge(),
            TextEntry::make('auditable_type')->label('Registro')->formatStateUsing(fn (?string $state, AuditLog $r) => class_basename((string) $state).' #'.$r->auditable_id),
            TextEntry::make('ip_address')->label('IP'),
            TextEntry::make('user_agent')->label('Navegador')->limit(80),
            KeyValueEntry::make('old_values')->label('Antes')->state(fn (AuditLog $r) => self::flatten($r->old_values))->columnSpanFull(),
            KeyValueEntry::make('new_values')->label('Depois')->state(fn (AuditLog $r) => self::flatten($r->new_values))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Quem')->placeholder('Sistema')->searchable(),
                TextColumn::make('event')->label('Evento')->badge()->searchable(),
                TextColumn::make('auditable_type')->label('Registro')->formatStateUsing(fn (?string $state, AuditLog $r) => class_basename((string) $state).' #'.$r->auditable_id),
                TextColumn::make('ip_address')->label('IP')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event')->label('Evento')->options(fn () => AuditLog::query()->distinct()->orderBy('event')->pluck('event', 'event')->all()),
                SelectFilter::make('user_id')->label('Quem')->relationship('user', 'name'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }
}
