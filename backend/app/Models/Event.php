<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUniqueSlug;
use App\Support\Html;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Event extends Model
{
    use Auditable, HasUniqueSlug, SoftDeletes;

    protected $fillable = [
        'church_id', 'title', 'slug', 'description', 'cover_image', 'location', 'link', 'starts_at', 'ends_at',
        'is_online', 'is_premium', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_online' => 'boolean',
            'is_premium' => 'boolean',
            'status' => ContentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Event $event): void {
            if ($event->isDirty('description') && $event->description !== null) {
                $event->description = Html::sanitize($event->description);
            }
        });
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value)
            ->where(fn ($q) => $q->where('starts_at', '>=', now()->startOfDay())->orWhere('ends_at', '>=', now()))
            ->orderBy('starts_at');
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }
}
