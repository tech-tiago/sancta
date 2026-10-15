<?php

namespace App\Services\Billing;

use RuntimeException;

/**
 * Falha no resgate de um código de presente. A mensagem é SEMPRE genérica: não revela
 * se o código existe, já foi usado, venceu ou foi revogado (anti-enumeração).
 */
class GiftCodeException extends RuntimeException
{
    public const GENERIC = 'Código inválido, expirado ou já utilizado. Confira e tente novamente.';

    public static function generic(): self
    {
        return new self(self::GENERIC);
    }
}
