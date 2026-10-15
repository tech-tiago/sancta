<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Canal/transmissão do YouTube da área "Ao vivo".
 * Os campos live_* são mantidos pelo comando sancta:live-sync (App\Services\Live\LiveStatusService)
 * e nunca vêm de formulário (não estão em $fillable).
 */
class LiveChannel extends Model
{
    use Auditable;

    /** Fuso de referência da agenda (horários cadastrados no painel são de Brasília). */
    public const TIMEZONE = 'America/Sao_Paulo';

    public const CHANNEL_ID_REGEX = '/^UC[\w-]{22}$/';

    public const CATEGORIES = [
        'missa' => 'Missa',
        'terco' => 'Terço',
        'adoracao' => 'Adoração',
        'oracao' => 'Oração',
        'canal' => 'Canal',
    ];

    /** Sem verificação bem-sucedida há mais que isso, o status "ao vivo"/"agendado" deixa de valer (evita selo preso). */
    public const STALE_AFTER_MINUTES = 20;

    protected $fillable = [
        'name', 'description', 'type', 'category', 'youtube_url', 'youtube_channel_id', 'fallback_url',
        'logo', 'cover', 'is_active', 'sort_order',
    ];

    protected $attributes = [
        'category' => 'canal',
        'live_status' => 'none',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'live_started_at' => 'datetime',
            'live_scheduled_at' => 'datetime',
            'live_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Só com o ID do canal: o link do YouTube passa a ser a página do canal.
        static::saving(function (LiveChannel $channel): void {
            if (blank($channel->youtube_url) && self::isValidChannelId($channel->youtube_channel_id)) {
                $channel->youtube_url = 'https://www.youtube.com/channel/'.$channel->youtube_channel_id;
            }
        });
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LiveSchedule::class)->orderBy('sort_order')->orderBy('starts_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function isValidChannelId(?string $id): bool
    {
        return is_string($id) && preg_match(self::CHANNEL_ID_REGEX, $id) === 1;
    }

    // ───────────────────────── Status ao vivo ─────────────────────────

    private function statusIsFresh(): bool
    {
        return $this->live_checked_at !== null && $this->live_checked_at->gt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public function isLive(): bool
    {
        return $this->live_status === 'live' && filled($this->live_video_id) && $this->statusIsFresh();
    }

    public function isUpcoming(): bool
    {
        return ! $this->isLive()
            && $this->live_status === 'upcoming'
            && $this->live_scheduled_at !== null
            && $this->live_scheduled_at->gt(now()->subHour())
            && $this->statusIsFresh();
    }

    /** Status exibido no painel: live | upcoming | none. */
    public function displayStatus(): string
    {
        return $this->isLive() ? 'live' : ($this->isUpcoming() ? 'upcoming' : 'none');
    }

    /** Tópico FCM do "Me avise" deste canal. */
    public function topic(): string
    {
        return 'sancta_live_'.$this->id;
    }

    // ───────────────────────── Agenda ─────────────────────────

    public static function nowLocal(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
    }

    /** @return Collection<int, LiveSchedule> */
    private function scheduleItems(): Collection
    {
        return $this->relationLoaded('schedules') ? $this->schedules : $this->schedules()->get();
    }

    /** Agenda em andamento agora (pelo horário cadastrado), se houver. */
    public function currentSchedule(?CarbonImmutable $now = null): ?LiveSchedule
    {
        $now = ($now ?? self::nowLocal())->setTimezone(self::TIMEZONE);

        return $this->scheduleItems()->first(fn (LiveSchedule $s) => $s->isActiveAt($now));
    }

    public function isOnScheduleNow(?CarbonImmutable $now = null): bool
    {
        return $this->currentSchedule($now) !== null;
    }

    /** Próximo início (futuro) da agenda, no fuso de Brasília. */
    public function nextScheduledAt(?CarbonImmutable $now = null): ?CarbonImmutable
    {
        $now = ($now ?? self::nowLocal())->setTimezone(self::TIMEZONE);

        return $this->scheduleItems()
            ->map(fn (LiveSchedule $s) => $s->nextStartAfter($now))
            ->filter()
            ->sortBy(fn (CarbonImmutable $start) => $start->getTimestamp())
            ->first();
    }

    // ───────────────────────── Links e imagens ─────────────────────────

    public function channelUrl(): string
    {
        if (filled($this->youtube_url)) {
            return $this->youtube_url;
        }

        return self::isValidChannelId($this->youtube_channel_id) ? 'https://www.youtube.com/channel/'.$this->youtube_channel_id : '';
    }

    /** Link para assistir: o vídeo ao vivo quando houver, senão o link do canal. */
    public function watchUrl(): string
    {
        return $this->isLive() ? 'https://www.youtube.com/watch?v='.$this->live_video_id : $this->channelUrl();
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover ? Storage::disk('public')->url($this->cover) : null;
    }

    public function liveThumbnailUrl(): ?string
    {
        return $this->isLive() ? self::videoThumbnail($this->live_video_id) : null;
    }

    public static function videoThumbnail(?string $videoId): ?string
    {
        return $videoId && preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) ? "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg" : null;
    }

    /** Extrai o ID do vídeo do YouTube (para miniatura/player), quando houver. */
    public function youtubeVideoId(): ?string
    {
        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~[?&]v=([A-Za-z0-9_-]{11})~',
            '~/(?:embed|live|shorts)/([A-Za-z0-9_-]{11})~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, (string) $this->youtube_url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /** Miniatura para listas: vídeo ao vivo → capa → logo → miniatura do vídeo do link. */
    public function thumbnailUrl(): ?string
    {
        return $this->liveThumbnailUrl()
            ?? $this->coverUrl()
            ?? $this->logoUrl()
            ?? self::videoThumbnail($this->youtubeVideoId());
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? self::CATEGORIES['canal'];
    }
}
