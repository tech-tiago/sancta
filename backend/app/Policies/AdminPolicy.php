<?php

namespace App\Policies;

use App\Enums\UserRole;

/** Área administrativa/financeira (planos, assinaturas, cupons, campanhas push). */
class AdminPolicy extends RolePolicy
{
    protected UserRole $viewRole = UserRole::Admin;

    protected UserRole $manageRole = UserRole::Admin;
}
