<?php

namespace App\Services\Live;

use App\Models\LiveChannel;
use App\Models\LiveSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Monta a área "Ao vivo" para a API (/api/v1/live-channels, /home → live_now) e para o
 * site (/ao-vivo). Ordem: ao vivo → no horário da agenda → ordem definida no painel.
 * Horários sempre no fuso de Brasília (America/Sao_Paulo), em ISO-8601.
 */
class LiveDirectory
{
    private ?CarbonImmutable $now = null;

    public function now(): CarbonImmutable
    {
        return $this->now ??= LiveChannel::nowLocal();
    }

    /** @return Collection<int, LiveChannel> */
    public function channels(): Collection
    {
        $now = $this->now();

        return LiveChannel::query()
            ->active()
            ->with('schedules')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (LiveChannel $c) => [
                $c->isLive() ? 0 : 1,
                $c->isOnScheduleNow($now) ? 0 : 1,
                (int) $c->sort_order,
                (int) $c->id,
            ])
            ->values();
    }

    /** Contrato de GET /api/v1/live-channels (ver docs/API.md). */
    public function toApi(LiveChannel $channel): array
    {
        $now = $this->now();
        $isLive = $channel->isLive();
        $next = $channel->nextScheduledAt($now);
        $current = $channel->currentSchedule($now);

        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'description' => $channel->description,
            'category' => $channel->category ?: 'canal',
            'category_label' => $channel->categoryLabel(),
            'cover_url' => $channel->coverUrl(),
            'logo_url' => $channel->logoUrl(),
            'youtube_url' => $channel->channelUrl(),
            'watch_url' => $channel->watchUrl(),
            'is_live' => $isLive,
            'live' => $isLive ? [
                'video_id' => $channel->live_video_id,
                'title' => $channel->live_title,
                'started_at' => $this->iso($channel->live_started_at),
                'thumbnail_url' => LiveChannel::videoThumbnail($channel->live_video_id),
            ] : null,
            'upcoming' => $channel->isUpcoming() ? [
                'video_id' => $channel->live_video_id,
                'title' => $channel->live_title,
                'scheduled_at' => $this->iso($channel->live_scheduled_at),
            ] : null,
            'on_schedule_now' => $current !== null,
            'current_schedule_title' => $current?->title,
            'schedule' => $channel->schedules->map(fn (LiveSchedule $s) => $s->toApi())->values()->all(),
            'next_at' => $next?->toIso8601String(),
            'topic' => $channel->topic(),

            // Compatibilidade com o app 3.0 (campos antigos).
            'type' => $channel->type,
            'url' => $channel->watchUrl(),
            'video_id' => $isLive ? $channel->live_video_id : $channel->youtubeVideoId(),
            'fallback_url' => $channel->fallback_url,
            'thumbnail_url' => $channel->thumbnailUrl(),
        ];
    }

    /** @return list<array> */
    public function all(): array
    {
        return $this->channels()->map(fn (LiveChannel $c) => $this->toApi($c))->all();
    }

    /**
     * Destaque "Ao vivo agora" da tela Hoje: canais ao vivo ou no horário da agenda.
     *
     * @param  Collection<int, LiveChannel>|null  $channels  lista já carregada por channels()
     * @return list<array>
     */
    public function liveNow(int $limit = 6, ?Collection $channels = null): array
    {
        $now = $this->now();

        return ($channels ?? $this->channels())
            ->filter(fn (LiveChannel $c) => $c->isLive() || $c->isOnScheduleNow($now))
            ->take($limit)
            ->map(fn (LiveChannel $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'category' => $c->category ?: 'canal',
                'is_live' => $c->isLive(),
                'on_schedule_now' => $c->isOnScheduleNow($now),
                'title' => $c->isLive() ? $c->live_title : $c->currentSchedule($now)?->title,
                'started_at' => $c->isLive() ? $this->iso($c->live_started_at) : null,
                'watch_url' => $c->watchUrl(),
                'thumbnail_url' => $c->thumbnailUrl(),
                'logo_url' => $c->logoUrl(),
                'topic' => $c->topic(),
            ])
            ->values()
            ->all();
    }

    /**
     * Agenda de hoje (todos os canais), em ordem de horário — usada na página /ao-vivo.
     *
     * @param  Collection<int, LiveChannel>|null  $channels
     * @return list<array{channel: LiveChannel, schedule: LiveSchedule, starts_at: CarbonImmutable, ends_at: CarbonImmutable, is_now: bool, is_past: bool}>
     */
    public function todaySchedule(?Collection $channels = null): array
    {
        $now = $this->now();
        $today = $now->startOfDay();
        $items = [];

        foreach ($channels ?? $this->channels() as $channel) {
            foreach ($channel->schedules as $schedule) {
                $start = $schedule->startOn($today);
                if (! $start) {
                    continue;
                }
                $end = $start->addMinutes($schedule->duration());
                $items[] = [
                    'channel' => $channel,
                    'schedule' => $schedule,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'is_now' => $now->gte($start) && $now->lt($end),
                    'is_past' => $now->gte($end),
                ];
            }
        }

        usort($items, fn ($a, $b) => $a['starts_at'] <=> $b['starts_at']);

        return $items;
    }

    private function iso(mixed $date): ?string
    {
        return $date ? CarbonImmutable::instance($date)->setTimezone(LiveChannel::TIMEZONE)->toIso8601String() : null;
    }
}
