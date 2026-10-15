<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** Papel e situação só por forceFill, após autorização (nunca por mass assignment). */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User(Arr::only($data, ['name', 'email', 'theme', 'marketing_opt_in']));
        $user->password = $data['new_password'];
        $user->forceFill([
            'role' => auth()->user()->can('changeRole', User::class) ? ($data['role'] ?? 'user') : 'user',
            'status' => $data['status'] ?? 'active',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
            'source' => 'admin',
        ])->save();

        AuditLog::record('user.created_by_admin', $user, [], ['role' => $user->role->value]);

        return $user;
    }
}
