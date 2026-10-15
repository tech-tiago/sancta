<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pentest SEC-02 — Envenenamento do link de redefinição de senha (cabeçalho Host).
 * O atacante pede "esqueci a senha" para o e-mail da vítima com Host: atacante.example;
 * se o link do e-mail usar o Host da requisição, o token chega ao domínio do atacante.
 */
class PasswordResetPoisoningTest extends TestCase
{
    use RefreshDatabase;

    private const APP_URL = 'https://sancta.example';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => self::APP_URL]);
        // Aqui o objetivo é provar que o LINK ignora o Host; em produção o trustHosts ainda recusaria
        // esse Host antes (400). A lista do Symfony é estática: garante que nenhum teste anterior a deixou definida.
        Request::setTrustedHosts([]);
    }

    private function resetUrlSentTo(User $user): string
    {
        $url = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, &$url) {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });

        return (string) $url;
    }

    public function test_site_reset_link_ignores_forged_host_header(): void
    {
        Notification::fake();
        $victim = $this->makeUser(attributes: ['email' => 'vitima@exemplo.com']);

        $this->post('http://atacante.example/esqueci-senha', ['email' => 'vitima@exemplo.com'])->assertRedirect();

        $url = $this->resetUrlSentTo($victim);
        $this->assertStringStartsWith(self::APP_URL.'/redefinir-senha/', $url);
        $this->assertStringNotContainsString('atacante.example', $url);
    }

    public function test_production_rejects_requests_for_foreign_hosts(): void
    {
        $this->app['env'] = 'production';
        $this->beforeApplicationDestroyed(fn () => Request::setTrustedHosts([]));

        $this->get('http://atacante.example/')->assertStatus(400);
        $this->get('https://sancta.example/up')->assertOk();
    }

    public function test_api_reset_link_ignores_forged_host_header(): void
    {
        Notification::fake();
        $victim = $this->makeUser(attributes: ['email' => 'vitima2@exemplo.com']);

        $this->withHeaders(['X-Forwarded-Host' => 'atacante2.example'])
            ->postJson('http://atacante.example/api/v1/auth/forgot-password', ['email' => 'vitima2@exemplo.com'])
            ->assertOk();

        $url = $this->resetUrlSentTo($victim);
        $this->assertStringStartsWith(self::APP_URL.'/redefinir-senha/', $url);
        $this->assertStringNotContainsString('atacante', $url);
    }
}
