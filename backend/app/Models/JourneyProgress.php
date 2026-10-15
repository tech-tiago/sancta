<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JourneyProgress extends Model
{
    public $timestamps = false;

    protected $table = 'journey_progress';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(JourneyStep::class, 'journey_step_id');
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }
}
