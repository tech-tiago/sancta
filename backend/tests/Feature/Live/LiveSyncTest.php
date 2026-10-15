<?php

namespace Tests\Feature\Live;

use App\Models\LiveChannel;
use App\Services\Live\LiveStatusService;
use App\Services\Push\PushService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Detecção automática de lives (sancta:live-sync): RSS + YouTube Data API, push uma vez por vídeo. */
class LiveSyncTest extends TestCase
{
    use RefreshDatabase;

    private const CHANNEL_ID = 'UCabcdefghijklmnopqrstuv';

    private const API_KEY = 'AIzaChaveDeTesteDoYoutube123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.youtube.api_key' => self::API_KEY]);
    }

    private function channel(array $attributes = []): LiveChannel
    {
        return LiveChannel::query()->create(array_merge([
            'name' => 'TV Teste',
            'category' => 'missa',
            'youtube_channel_id' => self::CHANNEL_ID,
            'is_active' => true,
        ], $attributes));
    }

    /** Feed Atom no formato do YouTube. */
    private function feed(array $ids): string
    {
        $entries = collect($ids)->map(fn (string $id) => "<entry><id>yt:video:{$id}</id><yt:videoId>{$id}</yt:videoId><yt:channelId>".self::CHANNEL_ID."</yt:channelId><title>Vídeo {$id}</title><published>2026-10-04T10:00:00+00:00</published></entry>")->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?><feed xmlns:yt="http://www.youtube.com/xml/schemas/2015" xmlns:media="http://search.yahoo.com/mrss/" xmlns="http://www.w3.org/2005/Atom"><title>Canal</title>'.$entries.'</feed>';
    }

    private function liveItem(string $id, string $title, ?string $startedAt = null): array
    {
        return [
            'id' => $id,
            'snippet' => ['title' => $title, 'liveBroadcastContent' => 'live'],
            'liveStreamingDetails' => ['actualStartTime' => $startedAt ?? now()->subMinutes(10)->toIso8601String()],
        ];
    }

    private function fakeYoutube(array $feedIds, array $items): void
    {
        Http::fake([
            'www.youtube.com/feeds/videos.xml*' => Http::response($this->feed($feedIds), 200, ['Content-Type' => 'application/atom+xml']),
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => $items]),
        ]);
    }

    private function expectPushes(int $times): void
    {
        $this->mock(PushService::class, function ($mock) use ($times): void {
            $mock->shouldReceive('notifyTopic')->times($times)->andReturn(true);
        });
    }

    public function test_detects_live_and_sends_push_once_per_video(): void
    {
        $channel = $this->channel();
        $this->fakeYoutube(['LIVEvideo01', 'VODvideo002'], [
            $this->liveItem('LIVEvideo01', 'Santa Missa das 10h'),
            ['id' => 'VODvideo002', 'snippet' => ['title' => 'Homilia', 'liveBroadcastContent' => 'none']],
        ]);

        $this->mock(PushService::class, function ($mock) use ($channel): void {
            $mock->shouldReceive('notifyTopic')->once()->withArgs(fn (string $topic, string $title, string $body, ?string $link, ?string $androidChannel, ?string $image, array $data) => $topic === 'sancta_live_'.$channel->id
                && $title === 'Ao vivo agora: TV Teste'
                && $body === 'Santa Missa das 10h'
                && $link === 'sancta://ao-vivo'
                && $androidChannel === 'sancta_ao_vivo'
                && $image === 'https://i.ytimg.com/vi/LIVEvideo01/hqdefault.jpg'
                && $data['video_id'] === 'LIVEvideo01')->andReturn(true);
        });

        $this->artisan('sancta:live-sync')->assertSuccessful();
        $this->artisan('sancta:live-sync')->assertSuccessful(); // mesma live: nenhum push novo

        $channel->refresh();
        $this->assertTrue($channel->isLive());
        $this->assertSame('LIVEvideo01', $channel->live_video_id);
        $this->assertSame('Santa Missa das 10h', $channel->live_title);
        $this->assertSame('LIVEvideo01', $channel->last_notified_video_id);
        $this->assertNotNull($channel->live_checked_at);

        // A chave vai no cabeçalho (nunca na URL) e os vídeos em um único lote.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'googleapis.com/youtube/v3/videos')
            && $r->hasHeader('x-goog-api-key', self::API_KEY)
            && ! str_contains($r->url(), self::API_KEY)
            && str_contains(urldecode($r->url()), 'id=LIVEvideo01,VODvideo002'));
    }

    public function test_a_new_live_video_triggers_a_new_push(): void
    {
        $channel = $this->channel();
        $current = 'LIVEvideo01';
        Http::fake(function (Request $request) use (&$current) {
            if (str_contains($request->url(), 'feeds/videos.xml')) {
                return Http::response($this->feed([$current]));
            }

            return Http::response(['items' => [$this->liveItem($current, "Live {$current}")]]);
        });
        $this->expectPushes(2);

        $this->artisan('sancta:live-sync')->assertSuccessful();
        $current = 'LIVEvideo02';
        $this->artisan('sancta:live-sync')->assertSuccessful();

        $this->assertSame('LIVEvideo02', $channel->fresh()->last_notified_video_id);
    }

    public function test_upcoming_broadcast_is_detected_without_push(): void
    {
        $channel = $this->channel();
        $scheduled = now()->addHours(2)->startOfMinute();
        $this->fakeYoutube(['UPCvideo003'], [[
            'id' => 'UPCvideo003',
            'snippet' => ['title' => 'Terço da noite', 'liveBroadcastContent' => 'upcoming'],
            'liveStreamingDetails' => ['scheduledStartTime' => $scheduled->toIso8601String()],
        ]]);
        $this->expectPushes(0);

        $this->artisan('sancta:live-sync')->assertSuccessful();

        $channel->refresh();
        $this->assertFalse($channel->isLive());
        $this->assertTrue($channel->isUpcoming());
        $this->assertSame('upcoming', $channel->live_status);
        $this->assertTrue($channel->live_scheduled_at->equalTo($scheduled));
    }

    public function test_finished_broadcast_goes_back_to_none(): void
    {
        $channel = $this->channel();
        $channel->forceFill(['live_status' => 'live', 'live_video_id' => 'LIVEvideo01', 'live_checked_at' => now(), 'last_notified_video_id' => 'LIVEvideo01'])->save();
        $this->fakeYoutube(['LIVEvideo01'], [[
            'id' => 'LIVEvideo01',
            'snippet' => ['title' => 'Missa', 'liveBroadcastContent' => 'none'],
            'liveStreamingDetails' => ['actualStartTime' => now()->subHours(2)->toIso8601String(), 'actualEndTime' => now()->subMinutes(5)->toIso8601String()],
        ]]);
        $this->expectPushes(0);

        $this->artisan('sancta:live-sync')->assertSuccessful();

        $this->assertSame('none', $channel->fresh()->live_status);
        $this->assertNull($channel->fresh()->live_video_id);
    }

    public function test_live_that_started_long_ago_does_not_push(): void
    {
        // Ex.: canal 24h recém-cadastrado — não faz sentido avisar "começou agora".
        $channel = $this->channel();
        $this->fakeYoutube(['LIVEvideo01'], [$this->liveItem('LIVEvideo01', 'Transmissão 24h', now()->subDays(30)->toIso8601String())]);
        $this->expectPushes(0);

        $this->artisan('sancta:live-sync')->assertSuccessful();

        $this->assertTrue($channel->fresh()->isLive());
        $this->assertSame('LIVEvideo01', $channel->fresh()->last_notified_video_id);
    }

    public function test_fixed_video_from_link_is_checked_even_if_not_in_rss(): void
    {
        $channel = $this->channel(['youtube_url' => 'https://www.youtube.com/watch?v=TV24horas01']);
        $this->fakeYoutube(['VODvideo002'], [$this->liveItem('TV24horas01', 'TV ao vivo 24h')]);
        $this->expectPushes(1);

        $this->artisan('sancta:live-sync')->assertSuccessful();

        $this->assertSame('TV24horas01', $channel->fresh()->live_video_id);
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), 'TV24horas01'));
    }

    public function test_without_api_key_no_network_and_status_follows_schedule(): void
    {
        config(['services.youtube.api_key' => null]);
        Http::fake();
        $this->expectPushes(0);
        $channel = $this->channel();
        // Status antigo (de quando havia chave) é limpo.
        $channel->forceFill(['live_status' => 'live', 'live_video_id' => 'LIVEvideo01', 'live_checked_at' => now()])->save();

        $this->artisan('sancta:live-sync')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('none', $channel->fresh()->live_status);
        $this->assertFalse($channel->fresh()->isLive());
    }

    public function test_malicious_xml_with_external_entities_is_rejected(): void
    {
        $secretFile = tempnam(sys_get_temp_dir(), 'xxe');
        file_put_contents($secretFile, 'SEGREDO-DO-SERVIDOR');

        $xxe = '<?xml version="1.0"?><!DOCTYPE feed [<!ENTITY xxe SYSTEM "file://'.str_replace('\\', '/', $secretFile).'">]>'
            .'<feed xmlns="http://www.w3.org/2005/Atom" xmlns:yt="http://www.youtube.com/xml/schemas/2015"><entry><yt:videoId>&xxe;</yt:videoId></entry></feed>';
        $billionLaughs = '<?xml version="1.0"?><!DOCTYPE lolz [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;&lol;">]><feed>&lol2;</feed>';

        $service = app(LiveStatusService::class);
        $this->assertNull($service->parseFeed($xxe));
        $this->assertNull($service->parseFeed($billionLaughs));
        $this->assertNull($service->parseFeed('isto não é xml'));

        // Pelo comando: o feed malicioso é ignorado, nada é consultado na API e o comando não quebra.
        $channel = $this->channel();
        Http::fake([
            'www.youtube.com/feeds/videos.xml*' => Http::response($xxe),
            'www.googleapis.com/*' => Http::response(['items' => []]),
        ]);
        $this->expectPushes(0);

        $this->artisan('sancta:live-sync')->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'googleapis.com'));
        $this->assertSame('none', $channel->fresh()->live_status);
        $this->assertStringNotContainsString('SEGREDO', (string) $channel->fresh()->live_video_id);
        unlink($secretFile);
    }

    public function test_feed_parser_reads_youtube_atom(): void
    {
        $ids = app(LiveStatusService::class)->parseFeed($this->feed(['abcdefghijk', 'ABCDEFGHIJK', 'invalido']));

        $this->assertSame(['abcdefghijk', 'ABCDEFGHIJK'], $ids);
    }

    public function test_network_failures_never_break_the_command(): void
    {
        $channel = $this->channel();
        $channel->forceFill(['live_status' => 'live', 'live_video_id' => 'LIVEvideo01', 'live_checked_at' => now(), 'last_notified_video_id' => 'LIVEvideo01'])->save();
        Http::fake(fn () => throw new ConnectionException('Tempo esgotado'));
        $this->expectPushes(0);

        $this->artisan('sancta:live-sync')->assertSuccessful();

        // Mantém o status anterior (que expira sozinho se as falhas continuarem).
        $this->assertSame('live', $channel->fresh()->live_status);
    }

    public function test_api_quota_error_keeps_previous_status(): void
    {
        $channel = $this->channel();
        $channel->forceFill(['live_status' => 'live', 'live_video_id' => 'LIVEvideo01', 'live_checked_at' => now()->subMinutes(2)])->save();
        Http::fake([
            'www.youtube.com/feeds/videos.xml*' => Http::response($this->feed(['LIVEvideo01'])),
            'www.googleapis.com/*' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'quotaExceeded']]]], 403),
        ]);
        $this->expectPushes(0);

        $stats = app(LiveStatusService::class)->syncAll();

        $this->assertSame(1, $stats['errors']);
        $this->assertSame('live', $channel->fresh()->live_status);
    }

    public function test_stale_live_status_expires(): void
    {
        $channel = $this->channel();
        $channel->forceFill(['live_status' => 'live', 'live_video_id' => 'LIVEvideo01', 'live_checked_at' => now()->subHour()])->save();

        $this->assertFalse($channel->fresh()->isLive());
        $this->getJson('/api/v1/live-channels')->assertOk()->assertJsonPath('0.is_live', false)->assertJsonPath('0.live', null);
    }

    public function test_batches_of_fifty_videos_per_api_call(): void
    {
        $ids = collect(range(1, 60))->map(fn (int $i) => 'video'.str_pad((string) $i, 6, '0', STR_PAD_LEFT))->all();
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => []])]);

        $this->assertSame([], app(LiveStatusService::class)->fetchDetails($ids));

        Http::assertSentCount(2);
    }

    public function test_command_is_scheduled_every_three_minutes(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('sancta:live-sync')->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'sancta:live-sync'));
        $this->assertSame('*/3 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
