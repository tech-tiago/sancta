<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\GiftCode;
use App\Models\User;

/**
 * Códigos de presente: registro financeiro somente leitura no painel (como pagamentos).
 * A única alteração possível é a ação "Revogar" (administradores, auditada).
 */
class GiftCodePolicy extends ReadOnlyAdminPolicy
{
    public function revoke(User $user, GiftCode $gift): bool
    {
        return $this->allows($user, UserRole::Admin) && $gift->isRevocable();
    }
}
