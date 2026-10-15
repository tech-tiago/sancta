<?php

namespace App\Http\Resources;

use App\Models\NewsArticle;
use App\Support\Html;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NewsArticle */
class NewsArticleResource extends JsonResource
{
    private bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'image_url' => $this->imageUrl(),
            'source' => $this->source?->name ?? 'Sancta',
            'url' => $this->url,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toIso8601String(),
        ];

        if (! $this->detailed) {
            return $data;
        }

        return $data + [
            'author' => $this->author,
            'blocks' => $this->content ? Html::toBlocks($this->content) : [['type' => 'paragraph', 'text' => (string) $this->summary]],
            'has_full_content' => (bool) $this->content,
        ];
    }
}
