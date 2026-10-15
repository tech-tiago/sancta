<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Security\Concerns\GoogleTestKeys;
use Tests\TestCase;

/**
 * Pentest — autenticação: tentativas que o sistema RESISTIU (evidências de regressão).
 * Força bruta com troca de IP por cabeçalho, reuso de token de redefinição, token de outro e-mail,
 * id_token do Google forjado/audiência errada/e-mail não verificado, RTDN com token indevido.
 */
class AuthAttackTest extends TestCase
{
    use GoogleTestKeys, RefreshDatabase;

    private const CLIENT_ID = '123-app.apps.googleusercontent.com';

    public function test_login_rate_limit_is_not_bypassed_by_spoofed_forwarding_headers(): void
    {
        $this->makeUser(attributes: ['email' => 'alvo@exemplo.com']);

        foreach (range(1, 5) as $i) {
            $this->withHeaders(['X-Forwarded-For' => "198.51.100.{$i}", 'X-Real-IP' => "198.51.100.{$i}", 'Client-IP' => "198.51.100.{$i}"])
                ->postJson('/api/v1/auth/login', ['email' => 'alvo@exemplo.com', 'password' => "errada{$i}x", 'device_name' => 't'])
                ->assertUnprocessable();
        }

        // A senha CORRETA também é recusada: o limite vale para e-mail+IP real, não para o cabeçalho.
        $this->withHeaders(['X-Forwarded-For' => '198.51.100.99'])
            ->postJson('/api/v1/auth/login', ['email' => 'alvo@exemplo.com', 'password' => 'Senha123segura', 'device_name' => 't'])
            ->assertStatus(429);
    }

    public function test_reset_token_cannot_be_reused_nor_used_for_another_email(): void
    {
        $victim = $this->makeUser(attributes: ['email' => 'vitima@exemplo.com']);
        $attacker = $this->makeUser(attributes: ['email' => 'atacante@exemplo.com']);
        $token = Password::broker()->createToken($attacker);

        // Token do atacante aplicado ao e-mail da vítima: recusado.
        $this->post('/redefinir-senha', ['token' => $token, 'email' => 'vitima@exemplo.com', 'password' => 'NovaSenha123', 'password_confirmation' => 'NovaSenha123'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(password_verify('Senha123segura', $victim->fresh()->password));

        // Uso legítimo e, depois, reuso do mesmo token: recusado.
        $this->post('/redefinir-senha', ['token' => $token, 'email' => 'atacante@exemplo.com', 'password' => 'NovaSenha123', 'password_confirmation' => 'NovaSenha123'])
            ->assertRedirect('/entrar');
        $this->post('/redefinir-senha', ['token' => $token, 'email' => 'atacante@exemplo.com', 'password' => 'OutraSenha456', 'password_confirmation' => 'OutraSenha456'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(password_verify('NovaSenha123', $attacker->fresh()->password));
    }

    public function test_password_reset_revokes_existing_api_tokens(): void
    {
        $user = $this->makeUser(attributes: ['email' => 'u@exemplo.com']);
        $headers = $this->bearer($user);
        $token = Password::broker()->createToken($user);

        $this->post('/redefinir-senha', ['token' => $token, 'email' => 'u@exemplo.com', 'password' => 'NovaSenha123', 'password_confirmation' => 'NovaSenha123']);

        $this->assertSame(0, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withHeaders($headers)->getJson('/api/v1/me')->assertUnauthorized();
    }

    private function googleLogin(string $idToken)
    {
        return $this->postJson('/api/v1/auth/google', ['id_token' => $idToken, 'device_name' => 'teste']);
    }

    public function test_google_login_rejects_forged_wrong_audience_and_unverified_tokens(): void
    {
        config(['services.google.web_client_id' => self::CLIENT_ID, 'services.google.client_ids' => []]);
        Http::fake($this->fakeGoogleJwks());
        $this->makeUser(attributes: ['email' => 'dono@exemplo.com']);
        $base = ['iss' => 'https://accounts.google.com', 'aud' => self::CLIENT_ID, 'sub' => 'g-1', 'email' => 'dono@exemplo.com', 'email_verified' => true];

        $this->googleLogin($this->googleJwt($base, 'atacante'))->assertUnauthorized();                         // assinado por chave fora do JWKS
        $this->googleLogin($this->googleJwt(['aud' => 'outro.apps.googleusercontent.com'] + $base))->assertUnauthorized(); // audiência de outro app
        $this->googleLogin($this->googleJwt(['email_verified' => false] + $base))->assertUnauthorized();      // e-mail não verificado
        $this->googleLogin($this->googleJwt(['iss' => 'https://evil.example'] + $base))->assertUnauthorized(); // emissor falso
        $this->googleLogin($this->googleJwt(['exp' => time() - 3600] + $base))->assertUnauthorized();         // expirado
        $this->assertSame(0, User::query()->where('email', 'dono@exemplo.com')->first()->tokens()->count());

        // Controle: token legítimo funciona (prova que o teste exercita o caminho real).
        $this->googleLogin($this->googleJwt($base))->assertOk()->assertJsonStructure(['token']);
    }

    public function test_rtdn_rejects_tokens_with_wrong_audience_or_sender(): void
    {
        config([
            'services.google_play.rtdn_audience' => 'https://sancta.example/api/webhooks/google-play',
            'services.google_play.rtdn_service_account_email' => 'pubsub@teste.iam.gserviceaccount.com',
            'services.google_play.package_name' => 'com.maria.app_igreja',
        ]);
        Http::fake($this->fakeGoogleJwks());
        $claims = ['iss' => 'https://accounts.google.com', 'aud' => 'https://sancta.example/api/webhooks/google-play', 'email' => 'pubsub@teste.iam.gserviceaccount.com', 'email_verified' => true];
        $body = ['message' => ['messageId' => 'm-1', 'data' => base64_encode(json_encode(['packageName' => 'com.maria.app_igreja', 'testNotification' => ['version' => '1.0']]))]];

        $send = fn (string $jwt) => $this->withHeader('Authorization', 'Bearer '.$jwt)->postJson('/api/webhooks/google-play', $body);

        $send($this->googleJwt(['aud' => 'https://outro.example'] + $claims))->assertUnauthorized();
        $send($this->googleJwt(['email' => 'qualquer@gmail.com'] + $claims))->assertUnauthorized();
        $send($this->googleJwt(['email_verified' => false] + $claims))->assertUnauthorized();
        $send($this->googleJwt($claims, 'atacante'))->assertUnauthorized();

        $send($this->googleJwt($claims))->assertOk();
    }
}
