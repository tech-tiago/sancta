<?php

namespace App\Http\Resources;

use App\Models\Journey;
use App\Support\Html;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Journey */
class JourneyResource extends JsonResource
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
        $completed = $user
            ? $user->journeyProgress()->where('journey_id', $this->id)->pluck('journey_step_id')->all()
            : [];
        $stepsCount = $this->steps_count ?? $this->steps()->count();

        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'subtitle' => $this->subtitle,
            'cover_url' => $this->coverUrl(),
            'color' => $this->color,
            'is_premium' => $this->is_premium,
            'locked' => $locked,
            'steps_count' => $stepsCount,
            'completed_steps' => count($completed),
        ];

        if (! $this->detailed) {
            return $data;
        }

        return $data + [
            'description' => Html::toText($this->description),
            'steps' => $this->steps->map(fn ($step) => [
                'id' => $step->id,
                'position' => $step->position,
                'title' => $step->title,
                'bible_reference' => $step->bible_reference,
                'duration_minutes' => $step->duration_seconds ? max(1, (int) round($step->duration_seconds / 60)) : null,
                'completed' => in_array($step->id, $completed, true),
                // Assinantes (ou jornada gratuita) recebem o conteúdo; demais só veem o 1º dia.
                'blocks' => (! $locked || $step->position === 1) ? Html::toBlocks($step->content) : [],
                'prayer_slug' => (! $locked || $step->position === 1) ? $step->prayer?->slug : null,
                'audio_url' => $locked ? null : $step->audioUrl(),
                'locked' => $locked && $step->position > 1,
            ])->values(),
        ];
    }
}
