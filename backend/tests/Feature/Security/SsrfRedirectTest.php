<?php

namespace Tests\Feature\Security;

use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Services\Content\NewsFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pentest SEC-01 — SSRF por redirecionamento no leitor de RSS.
 * A URL cadastrada é pública, mas o servidor do feed responde 302 para um endereço interno
 * (metadados da nuvem, localhost, rede privada). Cada salto precisa ser validado de novo.
 */
class SsrfRedirectTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'http://93.184.216.34/feed.xml'; // IP público literal (sem DNS no teste)

    private const RSS = '<?xml version="1.0"?><rss version="2.0"><channel><title>t</title>'
        .'<item><title>Interno</title><link>https://exemplo.org/a</link><description>x</description></item>'
        .'</channel></rss>';

    private function source(): NewsSource
    {
        return NewsSource::query()->create(['name' => 'Feed', 'type' => 'rss', 'url' => self::FEED, 'is_active' => true, 'max_items' => 5]);
    }

    /** @return list<array{0: string}> */
    public static function internalTargets(): array
    {
        return [
            'metadados AWS' => ['http://169.254.169.254/latest/meta-data/'],
            'localhost' => ['http://127.0.0.1/admin'],
            'rede privada' => ['http://10.0.0.5/'],
            'IPv6 loopback' => ['http://[::1]/'],
            'decimal 127.0.0.1' => ['http://2130706433/'],
            'porta não permitida' => ['http://93.184.216.35:6379/'],
            'esquema file' => ['file:///etc/passwd'],
        ];
    }

    #[DataProvider('internalTargets')]
    public function test_redirect_to_internal_address_is_not_followed(string $target): void
    {
        Http::fake([
            self::FEED => Http::response('', 302, ['Location' => $target]),
            '*' => Http::response(self::RSS, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $source = $this->source();
        $imported = app(NewsFetcher::class)->fetch($source);

        $this->assertSame(0, $imported);
        $this->assertSame(0, NewsArticle::query()->count());
        Http::assertNotSent(fn (Request $request) => $request->url() !== self::FEED);
        $this->assertNotNull($source->fresh()->last_error);
    }

    public function test_redirect_to_another_public_feed_still_works(): void
    {
        $final = 'https://93.184.216.34/rss-final.xml';
        Http::fake([
            self::FEED => Http::response('', 301, ['Location' => $final]),
            $final => Http::response(self::RSS, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $this->assertSame(1, app(NewsFetcher::class)->fetch($this->source()));
    }
}
