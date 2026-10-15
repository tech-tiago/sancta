<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Política base por papel. Cada política concreta define o papel mínimo
 * para visualizar e para gerenciar. Super administradores passam por
 * Gate::before (AppServiceProvider).
 */
abstract class RolePolicy
{
    protected UserRole $viewRole = UserRole::Editor;

    protected UserRole $manageRole = UserRole::Editor;

    protected bool $readOnly = false;

    protected function allows(User $user, UserRole $role): bool
    {
        return $user->isActive() && $user->hasRoleAtLeast($role);
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, $this->viewRole);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, $this->viewRole);
    }

    public function create(User $user): bool
    {
        return ! $this->readOnly && $this->allows($user, $this->manageRole);
    }

    public function update(User $user, Model $model): bool
    {
        return ! $this->readOnly && $this->allows($user, $this->manageRole);
    }

    public function delete(User $user, Model $model): bool
    {
        return ! $this->readOnly && $this->allows($user, $this->manageRole);
    }

    public function deleteAny(User $user): bool
    {
        return ! $this->readOnly && $this->allows($user, $this->manageRole);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return ! $this->readOnly && $this->allows($user, UserRole::Admin);
    }

    public function forceDeleteAny(User $user): bool
    {
        return ! $this->readOnly && $this->allows($user, UserRole::Admin);
    }

    public function replicate(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return $this->deleteAny($user);
    }
}
