<?php

namespace App\Support;

final class Money
{
    public static function format(?int $cents, string $currency = 'BRL'): string
    {
        $value = number_format(($cents ?? 0) / 100, 2, ',', '.');

        return $currency === 'BRL' ? "R$ {$value}" : "{$currency} {$value}";
    }

    public static function toDecimal(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
