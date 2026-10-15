<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Intenções de oração da comunidade — só aparecem após moderação. */
class PrayerIntention extends Model
{
    use Auditable, HasUuid;

    protected $fillable = ['text', 'is_anonymous'];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'status' => ModerationStatus::class,
            'moderated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function prayedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'intention_prayers');
    }

    public function displayName(): string
    {
        if ($this->is_anonymous || ! $this->user) {
            return 'Anônimo';
        }

        return $this->user->firstName();
    }
}
