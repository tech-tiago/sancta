<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\ChurchSubmission;
use App\Models\Page;
use App\Models\Prayer;
use App\Services\Community\ChurchSubmissionService;
use App\Support\Html;
use App\Support\NetworkGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Cabeçalhos, XSS, SSRF, CSRF e anti-spam. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_on_site(): void
    {
        $response = $this->get('/entrar');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", explode(';', $csp)[1]);
    }

    public function test_security_headers_on_api(): void
    {
        $this->getJson('/api/v1/config')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    public function test_html_sanitizer_removes_scripts_and_event_handlers(): void
    {
        $dirty = '<p onclick="steal()">Olá</p><script>alert(1)</script><img src="javascript:alert(1)"><a href="javascript:alert(2)">x</a><iframe src="https://evil"></iframe>';
        $clean = Html::sanitize($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringContainsString('Olá', $clean);
    }

    public function test_stored_html_is_sanitized_on_save_and_render(): void
    {
        $page = Page::query()->create(['slug' => 'teste', 'title' => 'Teste', 'content' => '<h2>Título</h2><script>alert(1)</script><p>Ok</p>']);
        $prayer = Prayer::query()->create(['title' => 'X', 'body' => '<p>Ave</p><img src=x onerror=alert(1)>', 'status' => 'published']);

        $this->assertStringNotContainsString('<script', $page->fresh()->content);
        $this->assertStringNotContainsString('onerror', $prayer->fresh()->body);
        $this->get('/p/teste')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('Título');
    }

    public function test_html_to_blocks_for_app(): void
    {
        $blocks = Html::toBlocks('<h2>Título</h2><p>Texto</p><ul><li>A</li><li>B</li></ul><blockquote>Citação</blockquote>');

        $this->assertSame(['heading', 'paragraph', 'list', 'quote'], array_column($blocks, 'type'));
        $this->assertSame(['A', 'B'], $blocks[2]['items']);
    }

    public function test_ssrf_guard_blocks_internal_addresses(): void
    {
        foreach (['http://localhost/feed', 'http://127.0.0.1/x', 'http://10.0.0.5/x', 'http://192.168.0.1/x', 'http://169.254.169.254/latest/meta-data', 'file:///etc/passwd', 'ftp://example.com/x', 'http://user:pass@example.com/', 'gopher://x'] as $url) {
            $this->assertFalse(NetworkGuard::isPublicHttpUrl($url), $url);
        }
        $this->assertTrue(NetworkGuard::isPublicHttpUrl('https://8.8.8.8/feed.xml'));
    }

    public function test_csrf_is_enforced_on_site_forms(): void
    {
        // O kernel de testes desativa o CSRF por padrão; reativamos explicitamente.
        $this->withMiddleware()->app->instance('env', 'production-like');
        $response = $this->call('POST', '/entrar', ['email' => 'a@b.com', 'password' => 'x'], [], [], ['HTTP_ACCEPT' => 'text/html']);

        $this->assertContains($response->getStatusCode(), [419, 302]);
    }

    public function test_church_suggestion_honeypot_and_timing_block_bots(): void
    {
        $base = ['name' => 'Paróquia Bot', 'address' => 'Rua X', 'city' => 'Salvador', 'state' => 'BA', 'submitter_name' => 'Bot', 'submitter_email' => 'bot@example.com', 'consent' => '1'];

        // Honeypot preenchido
        $this->post('/igrejas/cadastrar', $base + ['website_confirm' => 'http://spam', '_started' => encrypt(now()->subMinute()->timestamp)])
            ->assertSessionHasErrors('name');

        // Envio rápido demais
        $this->post('/igrejas/cadastrar', $base + ['_started' => encrypt(now()->timestamp)])
            ->assertSessionHasErrors('name');

        // Token de tempo adulterado
        $this->post('/igrejas/cadastrar', $base + ['_started' => 'nao-criptografado'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('church_submissions', 0);

        // Humano
        $this->post('/igrejas/cadastrar', $base + ['_started' => encrypt(now()->subMinute()->timestamp)])
            ->assertRedirect('/igrejas');
        $this->assertDatabaseCount('church_submissions', 1);
    }

    public function test_submission_requires_review_before_appearing_on_map(): void
    {
        $user = $this->makeUser();

        $this->withHeaders($this->bearer($user))->postJson('/api/v1/church-submissions', [
            'name' => '<b>Paróquia Nova</b>', 'address' => 'Rua A, 10', 'city' => 'Ilhéus', 'state' => 'BA',
            'latitude' => -14.79, 'longitude' => -39.04, 'mass_times' => 'Domingo 8h',
        ])->assertCreated();

        $submission = ChurchSubmission::query()->firstOrFail();
        $this->assertSame('Paróquia Nova', $submission->payload['name']); // tags removidas
        $this->assertStringNotContainsString('Paróquia Nova', $this->getJson('/api/v1/churches')->getContent());

        $admin = $this->makeUser(UserRole::Moderator);
        $church = app(ChurchSubmissionService::class)->approve($submission, $admin);

        $this->assertInstanceOf(Church::class, $church);
        $this->getJson('/api/v1/churches')->assertOk()->assertJsonFragment(['name' => 'Paróquia Nova']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'church_submission.approved']);
    }

    public function test_correction_only_updates_target_church(): void
    {
        $church = Church::query()->create(['name' => 'Matriz', 'city' => 'Porto Seguro', 'state' => 'BA', 'phone' => '111']);
        $user = $this->makeUser();

        $this->withHeaders($this->bearer($user))->postJson('/api/v1/church-submissions', [
            'church_uuid' => $church->uuid, 'phone' => '(73) 99999-0000', 'message' => 'Telefone mudou',
        ])->assertCreated();

        app(ChurchSubmissionService::class)->approve(ChurchSubmission::query()->firstOrFail(), $this->makeUser(UserRole::Moderator));

        $this->assertSame('(73) 99999-0000', $church->fresh()->phone);
        $this->assertSame('Matriz', $church->fresh()->name);
        $this->assertDatabaseCount('churches', 1);
    }

    public function test_avatar_upload_rejects_non_images(): void
    {
        $user = $this->makeUser();
        $fake = UploadedFile::fake()->createWithContent('avatar.jpg', '<?php echo "shell"; ?>');

        $this->withHeaders($this->bearer($user))->post('/api/v1/me/avatar', ['avatar' => $fake], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }
}
