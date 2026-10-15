<?php

namespace App\Models;

use App\Enums\Audience;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Avisos/pop-ups exibidos no app (e opcionalmente no site): eventos, promoções, comunicados. */
class Announcement extends Model
{
    use Auditable;

    protected $fillable = [
        'title', 'body', 'image', 'kind', 'category', 'cta_label', 'cta_url', 'audience', 'platform', 'frequency',
        'priority', 'dismissible', 'is_active', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'audience' => Audience::class,
            'dismissible' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(AnnouncementInteraction::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderByDesc('priority')
            ->orderByDesc('id');
    }

    /** Restringe ao público do visitante (convidado, gratuito ou assinante). */
    public function scopeForViewer(Builder $query, ?User $user): Builder
    {
        $audiences = [Audience::All->value];

        if (! $user) {
            $audiences[] = Audience::Guests->value;
        } elseif ($user->isPremium()) {
            $audiences[] = Audience::Premium->value;
        } else {
            $audiences[] = Audience::Free->value;
        }

        return $query->whereIn('audience', $audiences);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
