<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Usuários: administradores gerenciam usuários comuns; somente o super
 * administrador cria membros da equipe, altera papéis ou mexe em outros admins.
 */
class UserPolicy extends RolePolicy
{
    protected UserRole $viewRole = UserRole::Admin;

    protected UserRole $manageRole = UserRole::Admin;

    public function create(User $user): bool
    {
        return $this->allows($user, UserRole::SuperAdmin);
    }

    public function update(User $user, Model $model): bool
    {
        /** @var User $model */
        if ($model->hasRoleAtLeast(UserRole::Admin)) {
            return $this->allows($user, UserRole::SuperAdmin);
        }

        return $this->allows($user, UserRole::Admin);
    }

    public function delete(User $user, Model $model): bool
    {
        /** @var User $model */
        return $user->isNot($model) && $this->allows($user, UserRole::SuperAdmin);
    }

    public function deleteAny(User $user): bool
    {
        return false; // exclusão em massa de contas não é permitida
    }

    public function changeRole(User $user): bool
    {
        return $this->allows($user, UserRole::SuperAdmin);
    }
}
