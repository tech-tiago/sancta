<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Registra no log de auditoria criações, alterações e exclusões feitas por
 * membros da equipe (painel). Campos sensíveis nunca são gravados.
 */
trait Auditable
{
    protected static array $auditExcluded = [
        'password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes',
        'purchase_token', 'pix_qr_code_base64', 'updated_at', 'created_at',
    ];

    protected static function bootAuditable(): void
    {
        static::created(fn ($model) => static::writeAudit('created', $model, [], $model->getAttributes()));

        static::updated(function ($model): void {
            $changes = $model->getChanges();
            $original = array_intersect_key($model->getOriginal(), $changes);
            static::writeAudit('updated', $model, $original, $changes);
        });

        static::deleted(fn ($model) => static::writeAudit('deleted', $model, $model->getOriginal(), []));
    }

    protected static function writeAudit(string $event, $model, array $old, array $new): void
    {
        $user = auth()->user();

        // Só audita ações da equipe; ações do próprio usuário no app geram volume desnecessário.
        if (! $user || ! method_exists($user, 'isStaff') || ! $user->isStaff()) {
            return;
        }

        $strip = fn (array $values) => collect($values)
            ->except(static::$auditExcluded)
            ->map(fn ($v) => is_string($v) && mb_strlen($v) > 2000 ? mb_substr($v, 0, 2000).'…' : $v)
            ->all();

        $new = $strip($new);
        if ($event === 'updated' && empty($new)) {
            return;
        }

        AuditLog::record($event, $model, $strip($old), $new);
    }
}
