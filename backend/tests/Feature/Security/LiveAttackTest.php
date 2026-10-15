<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Filament\Resources\LiveChannels\Pages\ManageLiveChannels;
use App\Models\LiveChannel;
use App\Services\Live\LiveStatusService;
use App\Services\Push\PushService;
use App\Services\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pentest fase 3 — área "Ao vivo" (sancta:live-sync, RSS/XML, YouTube Data API, push, painel).
 *
 *  - SEC-15: "Verificar agora" do painel tinha limite só por pessoa e por minuto; editores (ou uma conta
 *    de editor comprometida) podiam esgotar a cota diária da YouTube Data API e parar a detecção
 *    automática. Agora há também um teto global por hora.
 *  - SEC-16: a recusa de XML com DOCTYPE/ENTITY era feita por busca de texto e não valia para feeds
 *    em UTF-16 (entidades internas eram expandidas). Agora o documento já interpretado é recusado
 *    se tiver DOCTYPE, qualquer que seja a codificação.
 *  - Resistiram: chave só no cabeçalho (nunca na URL/log), ID de canal validado antes de qualquer rede,
 *    RSS sem seguir redirecionamentos e com limite de tamanho, tópico de push validado, moderador
 *    não executa "Verificar agora".
 */
class LiveAttackTest extends TestCase
{
    use RefreshDatabase;

    private const CHANNEL_ID = 'UCabcdefghijklmnopqrstuv';

    private const API_KEY = 'AIzaChaveSecretaDoYoutube999';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.youtube.api_key' => self::API_KEY]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function channel(array $attributes = []): LiveChannel
    {
        return LiveChannel::query()->create(array_merge(['name' => 'TV Teste', 'youtube_channel_id' => self::CHANNEL_ID, 'is_active' => true], $attributes));
    }

    private function feed(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:yt="http://www.youtube.com/xml/schemas/2015">'
            .'<entry><id>yt:video:abcdefghijk</id><yt:videoId>abcdefghijk</yt:videoId></entry></feed>';
    }

    public function test_utf16_feed_with_doctype_is_rejected(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE feed [<!ENTITY x "abcdefghijk">]>'
            .'<feed xmlns="http://www.w3.org/2005/Atom" xmlns:yt="http://www.youtube.com/xml/schemas/2015"><entry><yt:videoId>&x;</yt:videoId></entry></feed>';
        $utf16 = "\xFF\xFE".mb_convert_encoding($xml, 'UTF-16LE', 'UTF-8');

        $this->assertNull(app(LiveStatusService::class)->parseFeed($utf16), 'DTD em UTF-16 também é recusado');
        $this->assertSame(['abcdefghijk'], app(LiveStatusService::class)->parseFeed($this->feed()), 'Feed normal continua aceito');
    }

    public function test_check_now_has_a_global_cap_that_protects_the_api_quota(): void
    {
        $channel = $this->channel();
        Http::fake([
            'www.youtube.com/feeds/videos.xml*' => Http::response($this->feed()),
            'www.googleapis.com/*' => Http::response(['items' => []]),
        ]);
        $this->mock(PushService::class)->shouldNotReceive('notifyTopic');

        // Vários editores já usaram o teto da hora (cada um dentro do próprio limite por minuto).
        for ($i = 0; $i < 100; $i++) {
            RateLimiter::hit('live-check:todos', 3600);
        }

        $this->actingAs($this->makeUser(UserRole::Editor));
        Livewire::test(ManageLiveChannels::class)->callTableAction('check_now', $channel);

        Http::assertNothingSent();
    }

    public function test_check_now_still_works_and_is_rate_limited_per_person(): void
    {
        $channel = $this->channel();
        Http::fake([
            // Resposta nova a cada chamada (o corpo do RSS é lido como stream).
            'www.youtube.com/feeds/videos.xml*' => fn () => Http::response($this->feed()),
            'www.googleapis.com/*' => fn () => Http::response(['items' => []]),
        ]);

        $this->actingAs($this->makeUser(UserRole::Editor));
        $page = Livewire::test(ManageLiveChannels::class);
        for ($i = 0; $i < 12; $i++) {
            $page->callTableAction('check_now', $channel);
        }

        Http::assertSentCount(20); // 10 verificações × (RSS + API); as 2 últimas foram barradas
    }

    public function test_moderator_cannot_trigger_check_now(): void
    {
        $channel = $this->channel();
        Http::fake();

        $this->actingAs($this->makeUser(UserRole::Moderator));
        try {
            Livewire::test(ManageLiveChannels::class)->callTableAction('check_now', $channel);
        } catch (\Throwable) {
            // O Filament pode recusar a ação oculta com erro: o que importa é não chegar à rede.
        }

        Http::assertNothingSent();
    }

    public function test_api_key_only_travels_in_the_header_and_never_reaches_logs(): void
    {
        $this->channel();
        $logged = '';
        Log::listen(function ($event) use (&$logged): void {
            $logged .= $event->message.' '.json_encode($event->context)."\n";
        });

        Http::fake([
            'www.youtube.com/feeds/videos.xml*' => Http::response($this->feed()),
            'www.googleapis.com/*' => Http::response(['error' => ['code' => 403, 'message' => 'API key '.self::API_KEY.' blocked', 'errors' => [['reason' => 'forbidden']]]], 403),
        ]);

        app(LiveStatusService::class)->syncAll();

        Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'googleapis.com')
            || ($r->hasHeader('x-goog-api-key', self::API_KEY) && ! str_contains(urldecode($r->url()), self::API_KEY)));
        $this->assertStringNotContainsString(self::API_KEY, $logged);
    }

    public function test_public_api_and_site_never_expose_the_key(): void
    {
        $this->channel();
        app(Settings::class)->set('features.live', true);

        foreach (['/api/v1/config', '/api/v1/home', '/api/v1/live-channels'] as $uri) {
            $this->assertStringNotContainsString(self::API_KEY, $this->getJson($uri)->assertOk()->getContent(), $uri);
        }
        $this->assertStringNotContainsString(self::API_KEY, $this->get('/ao-vivo')->assertOk()->getContent());
    }

    public function test_invalid_channel_ids_never_reach_the_network(): void
    {
        $channel = $this->channel();
        // Valor gravado fora do formulário (ex.: importação): o serviço revalida antes de montar a URL.
        $channel->forceFill(['youtube_channel_id' => 'UC../../../../etc/passwd?x='])->saveQuietly();
        Http::fake();

        app(LiveStatusService::class)->syncAll();
        $this->assertNull(app(LiveStatusService::class)->recentVideoIds('https://169.254.169.254/'));

        Http::assertNothingSent();
    }

    public function test_feed_redirects_are_not_followed_and_size_is_limited(): void
    {
        Http::fake([
            'www.youtube.com/feeds/videos.xml?channel_id='.self::CHANNEL_ID => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        ]);
        $this->assertNull(app(LiveStatusService::class)->recentVideoIds(self::CHANNEL_ID));
        Http::assertSentCount(1);

        Http::fake(['www.youtube.com/*' => Http::response(str_repeat('a', 1_048_577))]);
        $this->assertNull(app(LiveStatusService::class)->recentVideoIds(self::CHANNEL_ID));
    }

    public function test_push_topic_is_validated(): void
    {
        config(['services.fcm.project_id' => 'sancta-teste', 'services.fcm.service_account' => '{"x":1}']);
        Http::fake();

        $push = app(PushService::class);
        $this->assertFalse($push->notifyTopic('sancta_all/../../x', 'T', 'B'));
        $this->assertFalse($push->notifyTopic("sancta_live_1' || 'sancta_all' in topics", 'T', 'B'));

        Http::assertNothingSent();
    }
}
