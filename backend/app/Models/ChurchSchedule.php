<?php

namespace App\Models;

use App\Enums\ScheduleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchSchedule extends Model
{
    protected $fillable = ['church_id', 'type', 'weekday', 'starts_at', 'ends_at', 'notes', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => ScheduleType::class,
            'weekday' => 'integer',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function timeLabel(): string
    {
        $start = $this->starts_at ? substr($this->starts_at, 0, 5) : null;
        $end = $this->ends_at ? substr($this->ends_at, 0, 5) : null;

        return trim(($start ?? '').($end ? " às {$end}" : ''));
    }

    public function label(): string
    {
        return trim(ScheduleType::weekdayLabel($this->weekday).' '.$this->timeLabel().($this->notes ? " — {$this->notes}" : ''));
    }
}
