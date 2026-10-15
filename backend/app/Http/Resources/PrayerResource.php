<?php

namespace App\Http\Resources;

use App\Models\Prayer;
use App\Support\Html;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Oração. Conteúdo Sancta+ só é enviado a assinantes — o bloqueio é feito no
 * SERVIDOR (não basta esconder no app, pois a API poderia ser lida diretamente).
 *
 * @mixin Prayer
 */
class PrayerResource extends JsonResource
{
    private bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum');
        $locked = $this->is_premium && ! ($user?->isPremium() ?? false);

        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
                'color' => $this->category->color,
            ] : null),
            'cover_url' => $this->coverUrl(),
            'duration_minutes' => $this->duration_seconds ? max(1, (int) round($this->duration_seconds / 60)) : null,
            'has_audio' => (bool) ($this->audio_path || $this->audio_url),
            'is_premium' => $this->is_premium,
            'is_featured' => $this->is_featured,
            'locked' => $locked,
        ];

        if (! $this->detailed) {
            return $data;
        }

        $blocks = $this->blocks ? self::flattenBlocks($this->blocks) : Html::toBlocks($this->body);

        return $data + [
            'description' => $this->description,
            'author' => $this->author,
            'audio_url' => $locked ? null : $this->audioUrl(),
            'blocks' => $locked ? array_slice($blocks, 0, 2) : $blocks,
            'is_favorite' => $user ? $user->favoritePrayers()->whereKey($this->id)->exists() : false,
        ];
    }

    /** Converte o formato do Builder do painel para blocos simples do app. */
    public static function flattenBlocks(array $blocks): array
    {
        return array_values(array_filter(array_map(function ($block) {
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];

            return match ($type) {
                'expansivel' => [
                    'type' => 'expandable',
                    'title' => $data['titulo'] ?? '',
                    'children' => self::flattenBlocks($data['blocos'] ?? []),
                ],
                'icones' => ['type' => 'postures', 'icons' => array_values((array) ($data['icones'] ?? []))],
                'rubrica' => ['type' => 'rubric', 'text' => $data['texto'] ?? ''],
                'sacerdote' => ['type' => 'priest', 'text' => $data['texto'] ?? '', 'speaker' => $data['orador'] ?? null, 'highlight' => (bool) ($data['destaque'] ?? false)],
                'assembleia' => ['type' => 'assembly', 'text' => $data['texto'] ?? ''],
                'dialogo' => ['type' => 'dialogue', 'text' => $data['texto'] ?? '', 'speaker' => $data['orador'] ?? null],
                'titulo' => ['type' => 'heading', 'text' => $data['texto'] ?? ''],
                'paragrafo' => ['type' => 'paragraph', 'text' => $data['texto'] ?? ''],
                default => null,
            };
        }, $blocks)));
    }
}
