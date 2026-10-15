<?php

namespace App\Policies;

use App\Enums\UserRole;

/** Fila de moderação (sugestões de igrejas, intenções): moderadores e acima. */
class ModerationPolicy extends RolePolicy
{
    protected UserRole $viewRole = UserRole::Moderator;

    protected UserRole $manageRole = UserRole::Moderator;
}
