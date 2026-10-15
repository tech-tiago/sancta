<?php

namespace App\Models;

use App\Enums\ScheduleType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horário fixo de transmissão de um canal ao vivo (fuso de Brasília).
 * weekday: 0 = domingo … 6 = sábado; null = todos os dias.
 */
class LiveSchedule extends Model
{
    protected $fillable = ['live_channel_id', 'weekday', 'starts_at', 'duration_minutes', 'title', 'sort_order'];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(LiveChannel::class, 'live_channel_id');
    }

    /** Horário no formato HH:MM (o banco pode devolver HH:MM:SS). */
    public function time(): string
    {
        [$hour, $minute] = $this->timeParts();

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /** @return array{0: int, 1: int} */
    private function timeParts(): array
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', (string) $this->starts_at, $m)) {
            return [min(23, (int) $m[1]), min(59, (int) $m[2])];
        }

        return [0, 0];
    }

    public function duration(): int
    {
        return max(1, (int) ($this->duration_minutes ?: 60));
    }

    /** Início desta agenda no dia informado (null se não acontece nesse dia da semana). */
    public function startOn(CarbonImmutable $day): ?CarbonImmutable
    {
        $day = $day->setTimezone(LiveChannel::TIMEZONE);
        if ($this->weekday !== null && $day->dayOfWeek !== $this->weekday) {
            return null;
        }

        [$hour, $minute] = $this->timeParts();

        return $day->setTime($hour, $minute);
    }

    /** Está no horário agora? (considera transmissões que atravessam a meia-noite) */
    public function isActiveAt(CarbonImmutable $now): bool
    {
        $now = $now->setTimezone(LiveChannel::TIMEZONE);

        foreach ([$now->startOfDay(), $now->subDay()->startOfDay()] as $day) {
            $start = $this->startOn($day);
            if ($start && $now->gte($start) && $now->lt($start->addMinutes($this->duration()))) {
                return true;
            }
        }

        return false;
    }

    /** Próximo início estritamente depois de $now (até 8 dias à frente). */
    public function nextStartAfter(CarbonImmutable $now): ?CarbonImmutable
    {
        $now = $now->setTimezone(LiveChannel::TIMEZONE);

        for ($i = 0; $i <= 7; $i++) {
            $start = $this->startOn($now->startOfDay()->addDays($i));
            if ($start && $start->gt($now)) {
                return $start;
            }
        }

        return null;
    }

    public function weekdayLabel(): string
    {
        return $this->weekday === null ? 'Todos os dias' : ScheduleType::weekdayLabel($this->weekday);
    }

    /** Formato da API: {weekday, time, duration_minutes, title}. */
    public function toApi(): array
    {
        return [
            'weekday' => $this->weekday,
            'time' => $this->time(),
            'duration_minutes' => $this->duration(),
            'title' => $this->title,
        ];
    }
}
