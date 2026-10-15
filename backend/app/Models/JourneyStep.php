<?php

namespace App\Models;

use App\Support\Html;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class JourneyStep extends Model
{
    protected $fillable = ['journey_id', 'position', 'title', 'content', 'bible_reference', 'prayer_id', 'audio_path', 'duration_seconds'];

    protected static function booted(): void
    {
        static::saving(function (JourneyStep $step): void {
            if ($step->isDirty('content') && $step->content !== null) {
                $step->content = Html::sanitize($step->content);
            }
        });
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    public function prayer(): BelongsTo
    {
        return $this->belongsTo(Prayer::class);
    }

    public function audioUrl(): ?string
    {
        return $this->audio_path ? Storage::disk('public')->url($this->audio_path) : null;
    }
}
