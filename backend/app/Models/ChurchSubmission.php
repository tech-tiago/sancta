<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sugestão de nova igreja ou correção de dados, enviada pelo app ou site.
 * Nada é publicado automaticamente: o administrador aprova no painel
 * (ChurchSubmissionService::approve), que cria/atualiza a igreja.
 */
class ChurchSubmission extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => ModerationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === ModerationStatus::Pending;
    }
}
