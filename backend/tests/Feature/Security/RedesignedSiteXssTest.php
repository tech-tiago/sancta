<?php

namespace Tests\Feature\Security;

use App\Models\LiveChannel;
use App\Models\Testimonial;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pentest fase 3 — site redesenhado (resistiu): textos editáveis no painel (paywall, depoimentos,
 * planos, canais ao vivo) chegam escapados ao HTML; todo <script> do site carrega o nonce da CSP
 * e a CSP do site não tem 'unsafe-inline' nem 'unsafe-eval' para scripts.
 */
class RedesignedSiteXssTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '<img src=x onerror=alert(document.domain)>';

    public function test_panel_texts_are_escaped_on_public_pages(): void
    {
        $p = self::PAYLOAD;
        app(Settings::class)->setMany([
            'features.live' => true,
            'premium.headline' => "Chamada {$p}",
            'premium.subheadline' => "Sub {$p}",
            'premium.social_proof' => "Prova {$p}",
            'premium.guarantee' => "Garantia {$p}",
            'premium.benefits' => [['icon' => 'star', 'title' => "Benefício {$p}", 'description' => "Descrição {$p}"]],
            'premium.faq' => [['question' => "Pergunta {$p}", 'answer' => "Resposta {$p}"]],
            'app.slogan' => "Slogan {$p}",
        ]);
        Testimonial::query()->create(['author' => "Autor {$p}", 'content' => "Depoimento {$p}", 'rating' => 5, 'is_active' => true]);
        $this->makePlan(['slug' => 'anual', 'name' => "Anual {$p}", 'description' => "Plano {$p}", 'badge' => "Selo {$p}", 'features' => ["Recurso {$p}"]]);
        $channel = LiveChannel::query()->create(['name' => "Canal {$p}", 'description' => "Desc {$p}", 'youtube_url' => 'https://www.youtube.com/@canal', 'is_active' => true]);
        $channel->forceFill(['live_status' => 'live', 'live_video_id' => 'abcdefghijk', 'live_title' => "Live {$p}", 'live_checked_at' => now(), 'live_started_at' => now()])->saveQuietly();

        foreach (['/', '/planos', '/presentear', '/ao-vivo', '/entrar'] as $uri) {
            $html = $this->get($uri)->assertOk()->getContent();
            $this->assertStringNotContainsString('<img src=x', $html, $uri);
        }
    }

    public function test_every_site_script_carries_the_csp_nonce(): void
    {
        foreach (['/', '/planos', '/presentear', '/igrejas', '/entrar'] as $uri) {
            $response = $this->get($uri)->assertOk();
            $csp = (string) $response->headers->get('Content-Security-Policy');
            preg_match("/'nonce-([^']+)'/", $csp, $m);

            $this->assertNotEmpty($m[1] ?? null, $uri);
            $this->assertStringNotContainsString("'unsafe-inline'", str($csp)->after('script-src')->before(';')->toString(), $uri);
            $this->assertStringNotContainsString("'unsafe-eval'", $csp, $uri);

            preg_match_all('/<script\b[^>]*>/i', $response->getContent(), $scripts);
            foreach ($scripts[0] as $tag) {
                $this->assertStringContainsString('nonce="'.$m[1].'"', $tag, "{$uri}: {$tag}");
            }
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $response->getContent(), "{$uri}: manipulador inline");
        }
    }
}
