<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Account\AccountService;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [ActionGroup::make(UserResource::accountActions())->label('Ações')->button()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $record->fill(Arr::only($data, ['name', 'email', 'theme', 'marketing_opt_in']));

        if (auth()->user()->can('changeRole', User::class) && isset($data['role']) && auth()->user()->isNot($record)) {
            $record->forceFill(['role' => $data['role']]);
        }

        if (isset($data['status']) && auth()->user()->isNot($record)) {
            $record->forceFill(['status' => $data['status'], 'suspension_reason' => $data['suspension_reason'] ?? null]);
        }

        $record->save();

        if (filled($data['new_password'] ?? null)) {
            app(AccountService::class)->changePassword($record, $data['new_password']);
        }

        return $record;
    }
}
