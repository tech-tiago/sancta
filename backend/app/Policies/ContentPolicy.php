<?php

namespace App\Policies;

use App\Enums\UserRole;

/** Conteúdo editorial: editores (e acima) gerenciam; moderadores apenas visualizam. */
class ContentPolicy extends RolePolicy
{
    protected UserRole $viewRole = UserRole::Moderator;

    protected UserRole $manageRole = UserRole::Editor;
}
