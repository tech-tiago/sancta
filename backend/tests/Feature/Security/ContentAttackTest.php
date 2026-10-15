<?php

namespace Tests\Feature\Security;

use App\Enums\ModerationStatus;
use App\Models\BibleVerse;
use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Models\PrayerIntention;
use App\Models\Setting;
use App\Services\Content\NewsFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pentest — injeção e exposição: tentativas que o sistema RESISTIU.
 * XXE e expansão de entidades no RSS, SQLi na busca, XSS armazenado em intenções,
 * PII na API e limites de paginação.
 */
class ContentAttackTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'http://93.184.216.34/feed.xml';

    private function fetchFeed(string $xml): int
    {
        Http::fake([self::FEED => Http::response($xml, 200, ['Content-Type' => 'application/rss+xml'])]);
        $source = NewsSource::query()->create(['name' => 'F', 'type' => 'rss', 'url' => self::FEED, 'is_active' => true, 'max_items' => 5]);

        return app(NewsFetcher::class)->fetch($source);
    }

    public function test_rss_external_entities_are_not_resolved(): void
    {
        $secretFile = storage_path('framework/testing/xxe-segredo.txt');
        @mkdir(dirname($secretFile), 0777, true);
        file_put_contents($secretFile, 'SEGREDO-INTERNO-XXE');

        $uri = 'file://'.str_replace('\\', '/', $secretFile);
        $xml = '<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY xxe SYSTEM "'.$uri.'">]>'
            .'<rss version="2.0"><channel><title>t</title>'
            .'<item><title>&xxe;</title><link>https://exemplo.org/a</link><description>&xxe;</description></item>'
            .'</channel></rss>';

        $imported = $this->fetchFeed($xml);

        $this->assertStringNotContainsString('SEGREDO-INTERNO-XXE', json_encode(NewsArticle::query()->get()->toArray()));
        $this->assertSame(0, NewsArticle::query()->where('title', 'like', '%SEGREDO%')->count());
        @unlink($secretFile);
        $this->assertIsInt($imported);
    }

    public function test_rss_entity_expansion_bomb_does_not_blow_up(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE lolz [<!ENTITY a "aaaaaaaaaa">'
            .'<!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;"><!ENTITY c "&b;&b;&b;&b;&b;&b;&b;&b;&b;&b;">]>'
            .'<rss version="2.0"><channel><title>t</title><item><title>&c;</title>'
            .'<link>https://exemplo.org/a</link><description>&c;</description></item></channel></rss>';

        $this->assertSame(0, $this->fetchFeed($xml));
        $this->assertSame(0, NewsArticle::query()->count());
    }

    public function test_bible_search_is_not_vulnerable_to_sql_injection(): void
    {
        $this->seedMiniBible();

        foreach (["' OR '1'='1", '"; DROP TABLE bible_verses; --', '%luz%', 'luz\\'] as $payload) {
            $response = $this->getJson('/api/v1/bible/search?q='.urlencode($payload));
            $response->assertOk()->assertJsonStructure(['query', 'results']);
            $this->assertLessThanOrEqual(50, count($response->json('results')));
        }

        // A tabela continua lá (a "injeção" não destruiu nada).
        $this->assertSame(3, BibleVerse::query()->count());
    }

    public function test_stored_intention_is_sanitized_and_served_without_markup(): void
    {
        config(['sancta.features.intentions' => true]);
        Setting::query()->updateOrCreate(['key' => 'features.intentions'], ['value' => true]);
        $author = $this->makeUser();

        $this->withHeaders($this->bearer($author))->postJson('/api/v1/intentions', [
            'text' => 'Rezem por mim <script>alert(document.cookie)</script> e pela paz',
        ])->assertCreated();

        $intention = PrayerIntention::query()->firstOrFail();
        $this->assertStringNotContainsString('<script', $intention->text);

        $intention->forceFill(['status' => ModerationStatus::Approved, 'moderated_at' => now()])->save();

        $body = $this->getJson('/api/v1/intentions')->assertOk()->json();
        $this->assertStringNotContainsString('<script', json_encode($body));
    }

    public function test_api_never_exposes_sensitive_user_fields(): void
    {
        $user = $this->makeUser(attributes: ['google_id' => 'g-segredo-123', 'last_login_ip' => '203.0.113.7']);

        $json = $this->withHeaders($this->bearer($user))->getJson('/api/v1/me')->assertOk()->json();
        $flat = json_encode($json);

        // Valores sensíveis nunca aparecem no corpo.
        foreach ([$user->password, 'g-segredo-123', '203.0.113.7'] as $needle) {
            $this->assertStringNotContainsString((string) $needle, $flat, 'Valor sensível vazou.');
        }
        // Chaves sensíveis nunca são expostas.
        foreach (['role', 'status', 'google_id', 'last_login_ip', 'remember_token', 'app_authentication_secret'] as $key) {
            $this->assertArrayNotHasKey($key, $json, "Chave sensível exposta: {$key}");
        }
    }

    public function test_listing_and_search_limits_are_enforced(): void
    {
        // Paginação sem limite: pedido acima do teto é recusado (não derruba o servidor).
        $this->getJson('/api/v1/churches?limit=999999')->assertStatus(422);
        $this->getJson('/api/v1/churches?limit=500')->assertOk();
        // Busca exige no mínimo 3 letras (evita varredura ampla).
        $this->getJson('/api/v1/bible/search?q=ab')->assertStatus(422);
    }

    public function test_errors_do_not_leak_internals_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $body = $this->deleteJson('/api/v1/config')->assertStatus(405)->json();
        foreach (['exception', 'file', 'line', 'trace'] as $key) {
            $this->assertArrayNotHasKey($key, $body);
        }

        $html = $this->get('/pagina-que-nao-existe')->assertNotFound()->getContent();
        $this->assertStringNotContainsString('vendor', $html);
        $this->assertStringNotContainsString('Stack trace', $html);
    }
}
