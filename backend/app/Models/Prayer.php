<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUniqueSlug;
use App\Support\Html;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Prayer extends Model
{
    use Auditable, HasUniqueSlug, SoftDeletes;

    protected $fillable = [
        'category_id', 'title', 'slug', 'subtitle', 'description', 'cover_image', 'blocks', 'body', 'audio_path',
        'audio_url', 'duration_seconds', 'author', 'tags', 'is_premium', 'is_featured', 'status', 'published_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'tags' => 'array',
            'is_premium' => 'boolean',
            'is_featured' => 'boolean',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Todo HTML vindo do painel é sanitizado antes de ser salvo (defesa contra XSS armazenado).
        static::saving(function (Prayer $prayer): void {
            if ($prayer->isDirty('body') && $prayer->body !== null) {
                $prayer->body = Html::sanitize($prayer->body);
            }
            if ($prayer->status === ContentStatus::Published && ! $prayer->published_at) {
                $prayer->published_at = now();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'prayer_favorites');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }

    public function audioUrl(): ?string
    {
        if ($this->audio_path) {
            return Storage::disk('public')->url($this->audio_path);
        }

        return $this->audio_url;
    }
}
