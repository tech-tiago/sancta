<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasUniqueSlug;
use App\Support\Html;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class NewsArticle extends Model
{
    use HasUniqueSlug;

    protected $fillable = [
        'news_source_id', 'title', 'slug', 'summary', 'content', 'image_url', 'image', 'url', 'author',
        'published_at', 'is_featured', 'status',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'status' => ContentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (NewsArticle $article): void {
            if ($article->isDirty('content') && $article->content !== null) {
                $article->content = Html::sanitize($article->content);
            }
            if ($article->isDirty('url')) {
                $article->url_hash = $article->url ? hash('sha256', $article->url) : null;
            }
            $article->published_at ??= now();
        });
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'news_source_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value)
            ->where('published_at', '<=', now())
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at');
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : $this->image_url;
    }
}
