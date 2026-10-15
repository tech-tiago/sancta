<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Papéis de acesso. A hierarquia define o que cada um pode fazer no painel:
 *  - moderator: modera sugestões de igrejas e intenções de oração
 *  - editor:    gerencia conteúdo (orações, jornadas, notícias, igrejas, avisos)
 *  - admin:     tudo, inclusive usuários, assinaturas e pagamentos
 *  - super_admin: tudo + configurações sensíveis e gestão de administradores
 */
enum UserRole: string implements HasColor, HasLabel
{
    case User = 'user';
    case Moderator = 'moderator';
    case Editor = 'editor';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'Usuário',
            self::Moderator => 'Moderador',
            self::Editor => 'Editor',
            self::Admin => 'Administrador',
            self::SuperAdmin => 'Super administrador',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::User => 'gray',
            self::Moderator => 'info',
            self::Editor => 'success',
            self::Admin => 'warning',
            self::SuperAdmin => 'danger',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::User => 0,
            self::Moderator => 10,
            self::Editor => 20,
            self::Admin => 30,
            self::SuperAdmin => 40,
        };
    }

    public function isStaff(): bool
    {
        return $this->level() >= self::Moderator->level();
    }

    public function atLeast(self $role): bool
    {
        return $this->level() >= $role->level();
    }
}
