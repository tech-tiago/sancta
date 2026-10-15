<?php

namespace App\Policies;

use App\Enums\UserRole;

/**
 * Registros imutáveis (pagamentos, webhooks, auditoria): somente leitura no painel.
 * Alterações acontecem apenas por ações específicas e auditadas.
 */
class ReadOnlyAdminPolicy extends RolePolicy
{
    protected UserRole $viewRole = UserRole::Admin;

    protected UserRole $manageRole = UserRole::Admin;

    protected bool $readOnly = true;
}
