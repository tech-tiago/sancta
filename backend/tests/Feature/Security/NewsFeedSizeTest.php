<?php

namespace Tests\Feature\Security;

use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Services\Content\NewsFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Leitor de notícias: um feed acima do limite é recusado sem ser lido inteiro para a memória
 * (mesma leitura limitada do Ao vivo). Feeds dentro do limite continuam funcionando.
 */
class NewsFeedSizeTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'http://93.184.216.34/feed.xml'; // IP público literal (sem DNS no teste)

    private const RSS = '<?xml version="1.0"?><rss version="2.0"><channel><title>t</title>'
        .'<item><title>Notícia</title><link>https://exemplo.org/a</link><description>x</description></item>'
        .'</channel></rss>';

    private function source(): NewsSource
    {
        return NewsSource::query()->create(['name' => 'Feed', 'type' => 'rss', 'url' => self::FEED, 'is_active' => true, 'max_items' => 5]);
    }

    public function test_feed_larger_than_the_limit_is_rejected(): void
    {
        config(['sancta.news.max_feed_bytes' => 1024]);
        Http::fake([self::FEED => Http::response(str_pad(self::RSS, 4096, ' '), 200, ['Content-Type' => 'application/rss+xml'])]);

        $source = $this->source();

        $this->assertSame(0, app(NewsFetcher::class)->fetch($source));
        $this->assertSame(0, NewsArticle::query()->count());
        $this->assertStringContainsString('tamanho máximo', (string) $source->fresh()->last_error);
    }

    public function test_feed_within_the_limit_is_imported(): void
    {
        Http::fake([self::FEED => Http::response(self::RSS, 200, ['Content-Type' => 'application/rss+xml'])]);

        $this->assertSame(1, app(NewsFetcher::class)->fetch($this->source()));
    }
}
