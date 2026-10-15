<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gera um UUID público. IDs numéricos nunca são expostos em URLs públicas
 * de recursos sensíveis (evita enumeração/IDOR).
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }
}
