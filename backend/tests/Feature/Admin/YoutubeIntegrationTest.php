<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Services\Integrations;
use App\Services\IntegrationTester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** Chave da YouTube Data API em Integrações e segurança → Conteúdo (mesmo padrão dos outros segredos). */
class YoutubeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'AIzaSyChaveDeTesteDoYoutube0000004321';

    public function test_key_is_encrypted_applied_and_never_shown(): void
    {
        $changed = app(Integrations::class)->save(['youtube.api_key' => self::KEY]);

        $this->assertSame(['youtube.api_key'], $changed);
        $this->assertSame(self::KEY, config('services.youtube.api_key'));
        $this->assertStringNotContainsString(self::KEY, (string) Setting::query()->where('key', 'integrations.youtube.api_key')->value('value'));
        $this->assertSame('termina em 4321', app(Integrations::class)->secretHint('youtube.api_key'));

        $this->actingAs($this->makeUser(UserRole::SuperAdmin))
            ->get('/'.config('sancta.admin.path').'/integracoes?tab=conteudo')
            ->assertOk()
            ->assertSee('YouTube — transmissões ao vivo')
            ->assertSee('termina em 4321')
            ->assertDontSee(self::KEY);

        // Nunca vai para o app.
        $this->assertStringNotContainsString(self::KEY, $this->getJson('/api/v1/config')->getContent());
    }

    public function test_tester_reports_valid_key_using_header(): void
    {
        config(['services.youtube.api_key' => self::KEY]);
        Http::fake(['www.googleapis.com/youtube/v3/i18nRegions*' => Http::response(['items' => [['id' => 'BR']]])]);

        $this->assertStringContainsString('Chave válida', app(IntegrationTester::class)->youtube());
        Http::assertSent(fn (Request $r) => $r->hasHeader('x-goog-api-key', self::KEY) && ! str_contains($r->url(), self::KEY));
    }

    public function test_tester_explains_common_errors(): void
    {
        config(['services.youtube.api_key' => self::KEY]);
        $tester = app(IntegrationTester::class);

        Http::fake(['*' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'accessNotConfigured']]]], 403)]);
        try {
            $tester->youtube();
            $this->fail('Deveria falhar');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('não está ativada', $e->getMessage());
        }

        config(['services.youtube.api_key' => null]);
        $this->expectExceptionMessage('Informe a chave');
        $tester->youtube();
    }
}
