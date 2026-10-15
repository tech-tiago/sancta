<?php

namespace App\Services\Live;

use App\Models\LiveChannel;
use App\Services\Push\PushService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

/**
 * Detecção automática de transmissões ao vivo (comando sancta:live-sync, a cada 3 min).
 *
 * 1) Lê o RSS público de cada canal (vídeos mais recentes) — sem chave, sem cota.
 * 2) Consulta a YouTube Data API (videos.list com liveStreamingDetails) em lotes de até
 *    50 vídeos = 1 unidade de cota por lote. Chave em Painel → Integrações → Conteúdo.
 * 3) live = actualStartTime sem actualEndTime · upcoming = scheduledStartTime futuro.
 *
 * Sem chave cadastrada: nenhuma chamada de rede; live_status fica "none" e a API
 * informa on_schedule_now pela agenda. Falhas de rede nunca derrubam o comando
 * (log de aviso) e mantêm o status anterior — que expira sozinho (LiveChannel::STALE_AFTER_MINUTES).
 *
 * Segurança: URLs fixas (o ID do canal é validado ^UC[\w-]{22}$), tempo e tamanho
 * limitados, XML com DOCTYPE/ENTITY recusado (anti-XXE) e sem acesso à rede (LIBXML_NONET).
 */
class LiveStatusService
{
    public const FEED_URL = 'https://www.youtube.com/feeds/videos.xml';

    public const API_URL = 'https://www.googleapis.com/youtube/v3/videos';

    public const PUSH_CHANNEL = 'sancta_ao_vivo';

    public const DEEP_LINK = 'sancta://ao-vivo';

    private const ATOM_NS = 'http://www.w3.org/2005/Atom';

    private const YT_NS = 'http://www.youtube.com/xml/schemas/2015';

    private const MAX_FEED_BYTES = 1_048_576;

    private const RECENT_VIDEOS = 10;

    private const BATCH_SIZE = 50;

    /** Transmissão agendada que atrasou: continua "agendada" por até 60 min após o horário. */
    private const UPCOMING_GRACE_MINUTES = 60;

    /** Agendamentos muito distantes (salas de chat permanentes) são ignorados. */
    private const UPCOMING_HORIZON_DAYS = 7;

    /** Live que começou há mais tempo que isso (ex.: canal 24h recém-cadastrado) não gera push. */
    private const NOTIFY_MAX_AGE_HOURS = 3;

    public function __construct(private readonly PushService $push) {}

    public function hasApiKey(): bool
    {
        return filled(config('services.youtube.api_key'));
    }

    /**
     * Sincroniza os canais informados (padrão: todos os ativos com ID do canal).
     *
     * @param  Collection<int, LiveChannel>|null  $channels
     * @return array{channels: int, live: int, upcoming: int, notified: int, errors: int, without_key: bool}
     */
    public function syncAll(?Collection $channels = null): array
    {
        $channels = ($channels ?? LiveChannel::query()->active()->whereNotNull('youtube_channel_id')->get())
            ->filter(fn (LiveChannel $c) => LiveChannel::isValidChannelId($c->youtube_channel_id))
            ->values();

        $stats = ['channels' => $channels->count(), 'live' => 0, 'upcoming' => 0, 'notified' => 0, 'errors' => 0, 'without_key' => false];

        if ($channels->isEmpty()) {
            return $stats;
        }

        if (! $this->hasApiKey()) {
            // Sem chave: status pela agenda (calculado na API). Limpa status antigo, sem rede.
            $stats['without_key'] = true;
            foreach ($channels as $channel) {
                if ($channel->live_status !== 'none') {
                    $this->store($channel, 'none', null, checked: false);
                }
            }

            return $stats;
        }

        // 1) RSS: vídeos recentes de cada canal.
        $candidates = [];
        foreach ($channels as $channel) {
            $ids = $this->recentVideoIds($channel->youtube_channel_id);
            if ($ids === null) {
                $stats['errors']++;

                continue; // falha: mantém o status anterior deste canal
            }

            // Transmissões 24h podem ter saído do RSS: confere também o vídeo já conhecido e o do link fixo.
            $known = array_filter([
                $channel->live_status !== 'none' ? $channel->live_video_id : null,
                $channel->youtubeVideoId(),
            ]);
            $candidates[$channel->id] = array_values(array_unique([...$ids, ...$known]));
        }

        if ($candidates === []) {
            return $stats;
        }

        // 2) YouTube Data API em lotes de 50 (1 unidade de cota por lote).
        $details = $this->fetchDetails(array_values(array_unique(array_merge(...array_values($candidates)))));
        if ($details === null) {
            $stats['errors']++;

            return $stats; // API indisponível/cota esgotada: mantém tudo como estava
        }

        // 3) Decide o status de cada canal.
        foreach ($channels as $channel) {
            if (! isset($candidates[$channel->id])) {
                continue;
            }

            [$status, $video] = $this->decide($candidates[$channel->id], $details);
            $this->store($channel, $status, $video);

            if ($status === 'live') {
                $stats['live']++;
                if ($this->notifyIfNew($channel, $video)) {
                    $stats['notified']++;
                }
            } elseif ($status === 'upcoming') {
                $stats['upcoming']++;
            }
        }

        return $stats;
    }

    /** "Verificar agora" do painel: sincroniza um canal e devolve uma mensagem curta. */
    public function syncChannel(LiveChannel $channel): string
    {
        if (! LiveChannel::isValidChannelId($channel->youtube_channel_id)) {
            return 'Cadastre o ID do canal do YouTube (começa com UC) para a detecção automática.';
        }

        $stats = $this->syncAll(collect([$channel]));
        $channel->refresh();

        return match (true) {
            $stats['without_key'] => 'Sem chave do YouTube: o status segue a agenda. Cadastre a chave em Integrações e segurança → Conteúdo.',
            $stats['errors'] > 0 => 'Não foi possível consultar o YouTube agora. Tente novamente em alguns minutos.',
            $channel->isLive() => 'Ao vivo agora: '.Str::limit((string) $channel->live_title, 80),
            $channel->isUpcoming() => 'Transmissão agendada para '.$channel->live_scheduled_at->setTimezone(LiveChannel::TIMEZONE)->format('d/m H:i').'.',
            default => 'Nenhuma transmissão ao vivo ou agendada neste momento.',
        };
    }

    /**
     * IDs dos vídeos mais recentes do RSS público do canal (null = falha).
     *
     * @return list<string>|null
     */
    public function recentVideoIds(string $channelId): ?array
    {
        if (! LiveChannel::isValidChannelId($channelId)) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'SanctaBot/1.0 (+https://sancta.app.br)'])
                ->withOptions(['allow_redirects' => false, 'stream' => true])
                ->get(self::FEED_URL, ['channel_id' => $channelId]);
        } catch (Throwable $e) {
            Log::warning('Ao vivo: falha ao ler o RSS do YouTube', ['canal' => $channelId, 'erro' => Str::limit($e->getMessage(), 200)]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Ao vivo: RSS do YouTube respondeu com erro', ['canal' => $channelId, 'status' => $response->status()]);

            return null;
        }

        $body = $this->readLimited($response, self::MAX_FEED_BYTES);
        if ($body === null) {
            Log::warning('Ao vivo: RSS do YouTube excede o tamanho permitido', ['canal' => $channelId]);

            return null;
        }

        $ids = $this->parseFeed($body);
        if ($ids === null) {
            Log::warning('Ao vivo: RSS do YouTube inválido ou recusado', ['canal' => $channelId]);
        }

        return $ids;
    }

    /**
     * Extrai os IDs de vídeo de um feed Atom do YouTube. Documentos com DOCTYPE/ENTITY
     * são recusados (XXE, "billion laughs"); entidades externas nunca são carregadas.
     *
     * @return list<string>|null
     */
    public function parseFeed(string $xml): ?array
    {
        if ($xml === '' || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $doc instanceof SimpleXMLElement) {
            return null;
        }

        // A busca de texto acima não enxerga DOCTYPE em outras codificações (ex.: UTF-16): confere também
        // no documento já interpretado. Pentest fase 3 — SEC-16.
        if (dom_import_simplexml($doc)->ownerDocument?->doctype !== null) {
            return null;
        }

        $entries = $doc->children(self::ATOM_NS)->entry;
        if (count($entries) === 0) {
            $entries = $doc->entry;
        }

        $ids = [];
        foreach ($entries as $entry) {
            $id = trim((string) $entry->children(self::YT_NS)->videoId);
            if ($id === '') {
                $raw = trim((string) ($entry->children(self::ATOM_NS)->id ?: $entry->id));
                $id = str_starts_with($raw, 'yt:video:') ? substr($raw, 9) : '';
            }

            if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id) && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }

            if (count($ids) >= self::RECENT_VIDEOS) {
                break;
            }
        }

        return $ids;
    }

    /**
     * videos.list (part=snippet,liveStreamingDetails) em lotes de até 50 IDs.
     *
     * @param  list<string>  $ids
     * @return array<string, array>|null id do vídeo => item; null = falha
     */
    public function fetchDetails(array $ids): ?array
    {
        $out = [];
        $ids = array_values(array_filter($ids, fn ($id) => is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)));

        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            try {
                // Chave no cabeçalho (não vai para URLs/logs).
                $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.youtube.api_key')])
                    ->acceptJson()
                    ->timeout(10)
                    ->connectTimeout(5)
                    ->get(self::API_URL, [
                        'part' => 'snippet,liveStreamingDetails',
                        'id' => implode(',', $chunk),
                        'maxResults' => self::BATCH_SIZE,
                        'fields' => 'items(id,snippet(title,liveBroadcastContent),liveStreamingDetails(actualStartTime,actualEndTime,scheduledStartTime))',
                    ]);
            } catch (Throwable $e) {
                Log::warning('Ao vivo: falha ao consultar a YouTube Data API', ['erro' => Str::limit($e->getMessage(), 200)]);

                return null;
            }

            if ($response->failed()) {
                Log::warning('Ao vivo: YouTube Data API respondeu com erro', [
                    'status' => $response->status(),
                    'motivo' => $response->json('error.errors.0.reason'),
                ]);

                return null;
            }

            foreach ((array) $response->json('items') as $item) {
                if (is_array($item) && is_string($item['id'] ?? null)) {
                    $out[$item['id']] = $item;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, array>  $details
     * @return array{0: string, 1: array{id: string, title: ?string, started_at: ?CarbonImmutable, scheduled_at: ?CarbonImmutable}|null}
     */
    private function decide(array $ids, array $details): array
    {
        $now = CarbonImmutable::now();
        $live = null;
        $upcoming = null;

        foreach ($ids as $id) {
            $item = $details[$id] ?? null;
            $stream = is_array($item['liveStreamingDetails'] ?? null) ? $item['liveStreamingDetails'] : null;
            if (! $stream) {
                continue; // vídeo comum
            }

            $video = [
                'id' => $id,
                'title' => $this->cleanTitle(data_get($item, 'snippet.title')),
                'started_at' => $this->date($stream['actualStartTime'] ?? null),
                'scheduled_at' => $this->date($stream['scheduledStartTime'] ?? null),
            ];
            $ended = filled($stream['actualEndTime'] ?? null);

            if ($video['started_at'] && ! $ended) {
                if (! $live || $video['started_at']->gt($live['started_at'])) {
                    $live = $video;
                }

                continue;
            }

            $scheduled = $video['scheduled_at'];
            if (! $video['started_at'] && ! $ended && $scheduled
                && $scheduled->gt($now->subMinutes(self::UPCOMING_GRACE_MINUTES))
                && $scheduled->lt($now->addDays(self::UPCOMING_HORIZON_DAYS))) {
                if (! $upcoming || $scheduled->lt($upcoming['scheduled_at'])) {
                    $upcoming = $video;
                }
            }
        }

        if ($live) {
            return ['live', $live];
        }

        return $upcoming ? ['upcoming', $upcoming] : ['none', null];
    }

    private function store(LiveChannel $channel, string $status, ?array $video, bool $checked = true): void
    {
        $channel->forceFill([
            'live_status' => $status,
            'live_video_id' => $video['id'] ?? null,
            'live_title' => $video['title'] ?? null,
            'live_started_at' => $status === 'live' ? $video['started_at'] : null,
            'live_scheduled_at' => $video['scheduled_at'] ?? null,
            'live_checked_at' => $checked ? now() : $channel->live_checked_at,
        ]);

        // Sem eventos: atualização automática não deve poluir a auditoria do painel.
        $channel->saveQuietly();
    }

    /** Push "Ao vivo agora" — uma única vez por vídeo, mesmo com execuções simultâneas. */
    private function notifyIfNew(LiveChannel $channel, array $video): bool
    {
        $videoId = $video['id'];
        if ($channel->last_notified_video_id === $videoId) {
            return false;
        }

        // Reserva atômica do vídeo: só quem conseguir gravar envia.
        $claimed = LiveChannel::query()
            ->whereKey($channel->getKey())
            ->where(fn ($q) => $q->whereNull('last_notified_video_id')->orWhere('last_notified_video_id', '!=', $videoId))
            ->update(['last_notified_video_id' => $videoId]);

        $channel->forceFill(['last_notified_video_id' => $videoId])->syncOriginalAttribute('last_notified_video_id');

        if ($claimed !== 1) {
            return false;
        }

        if ($video['started_at'] && $video['started_at']->lt(now()->subHours(self::NOTIFY_MAX_AGE_HOURS))) {
            return false;
        }

        try {
            return $this->push->notifyTopic(
                $channel->topic(),
                Str::limit('Ao vivo agora: '.$channel->name, 120),
                Str::limit($video['title'] ?: 'A transmissão começou. Toque para assistir.', 200),
                self::DEEP_LINK,
                self::PUSH_CHANNEL,
                LiveChannel::videoThumbnail($videoId),
                ['type' => 'live', 'channel_id' => (string) $channel->getKey(), 'video_id' => $videoId],
            );
        } catch (Throwable $e) {
            Log::warning('Ao vivo: falha ao enviar push', ['canal' => $channel->getKey(), 'erro' => Str::limit($e->getMessage(), 200)]);

            return false;
        }
    }

    /** Lê o corpo da resposta sem passar do limite (null = excedeu). */
    private function readLimited(Response $response, int $max): ?string
    {
        $length = $response->header('Content-Length');
        if ($length !== '' && is_numeric($length) && (int) $length > $max) {
            return null;
        }

        $stream = $response->toPsrResponse()->getBody();
        $body = '';
        while (! $stream->eof() && strlen($body) <= $max) {
            $chunk = $stream->read(65536);
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        return strlen($body) > $max ? null : $body;
    }

    private function cleanTitle(mixed $title): ?string
    {
        $title = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', strip_tags((string) $title)) ?? '');

        return $title === '' ? null : Str::limit($title, 250, '…');
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
