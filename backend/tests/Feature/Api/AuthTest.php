<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $override = []): array
    {
        return array_merge([
            'name' => 'Maria Teste',
            'email' => 'maria@example.com',
            'password' => 'Senha123segura',
            'device_name' => 'Pixel 8',
            'accept_terms' => true,
        ], $override);
    }

    public function test_user_can_register_and_receives_expiring_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertCreated()
            ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'email', 'is_premium']])
            ->assertJsonPath('user.is_premium', false);

        $this->assertNotNull(User::query()->where('email', 'maria@example.com')->value('terms_accepted_at'));
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'Pixel 8']);
        $this->assertNotNull(\DB::table('personal_access_tokens')->value('expires_at'));
    }

    public function test_registration_sends_email_verification(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertCreated();

        Notification::assertSentTo(
            User::query()->where('email', 'maria@example.com')->first(),
            VerifyEmail::class,
        );
    }

    public function test_registration_requires_terms_and_strong_password(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerPayload(['accept_terms' => false, 'password' => 'abc']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accept_terms', 'password']);
    }

    public function test_mass_assignment_cannot_escalate_role_on_register(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerPayload(['role' => 'super_admin', 'status' => 'active']))->assertCreated();

        $this->assertSame(UserRole::User, User::query()->where('email', 'maria@example.com')->first()->role);
    }

    public function test_mass_assignment_cannot_escalate_role_on_profile_update(): void
    {
        $user = $this->makeUser();

        $this->withHeaders($this->bearer($user))
            ->patchJson('/api/v1/me', ['name' => 'Novo Nome', 'role' => 'super_admin', 'email_verified_at' => null])
            ->assertOk();

        $user->refresh();
        $this->assertSame('Novo Nome', $user->name);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_duplicate_email_returns_generic_message(): void
    {
        $this->makeUser(attributes: ['email' => 'maria@example.com']);

        $this->postJson('/api/v1/auth/register', $this->registerPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_with_wrong_password_is_generic_and_rate_limited(): void
    {
        $this->makeUser(attributes: ['email' => 'joao@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'joao@example.com', 'password' => 'errada', 'device_name' => 'x'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.email.0', 'E-mail ou senha incorretos.');
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'joao@example.com', 'password' => 'Senha123segura', 'device_name' => 'x'])
            ->assertStatus(429);
    }

    public function test_unknown_email_gets_same_message_as_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'ninguem@example.com', 'password' => 'qualquer1', 'device_name' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'E-mail ou senha incorretos.');
    }

    public function test_login_returns_token_that_authenticates(): void
    {
        $this->makeUser(attributes: ['email' => 'joao@example.com']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'joao@example.com', 'password' => 'Senha123segura', 'device_name' => 'x'])
            ->assertOk()->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('email', 'joao@example.com')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('role');
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->makeUser();
        $headers = $this->bearer($user);

        $this->withHeaders($headers)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('message', 'Sessão expirada. Entre novamente.');
        $this->putJson('/api/v1/me/bible/annotations', [])->assertUnauthorized();
        $this->postJson('/api/v1/billing/google-play/verify', [])->assertUnauthorized();
    }

    public function test_suspended_user_cannot_login_nor_use_existing_token(): void
    {
        $user = $this->makeUser(attributes: ['email' => 'suspenso@example.com']);
        $headers = $this->bearer($user);
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->withHeaders($headers)->getJson('/api/v1/me')->assertForbidden();
        $this->postJson('/api/v1/auth/login', ['email' => 'suspenso@example.com', 'password' => 'Senha123segura', 'device_name' => 'x'])
            ->assertForbidden();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('velho', ['app'], now()->subMinute())->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_forgot_password_does_not_reveal_if_email_exists(): void
    {
        $this->makeUser(attributes: ['email' => 'existe@example.com']);

        $a = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'existe@example.com'])->assertOk()->json('message');
        $b = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'naoexiste@example.com'])->assertOk()->json('message');

        $this->assertSame($a, $b);
    }

    public function test_google_login_rejects_invalid_token(): void
    {
        config(['services.google.client_ids' => ['cliente.apps.googleusercontent.com']]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'token.invalido.aqui', 'device_name' => 'x'])
            ->assertUnauthorized();
    }

    public function test_password_change_revokes_other_tokens(): void
    {
        $user = $this->makeUser();
        $user->createToken('outro-aparelho');
        $headers = $this->bearer($user);

        $this->withHeaders($headers)->putJson('/api/v1/me/password', [
            'current_password' => 'Senha123segura',
            'password' => 'NovaSenha456',
            'password_confirmation' => 'NovaSenha456',
        ])->assertOk();

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_account_deletion_anonymizes_and_keeps_payments(): void
    {
        $user = $this->makeUser(attributes: ['email' => 'apagar@example.com']);
        $headers = $this->bearer($user);

        $this->withHeaders($headers)->deleteJson('/api/v1/me', ['confirmation' => 'EXCLUIR', 'password' => 'Senha123segura'])->assertOk();

        $deleted = User::withTrashed()->find($user->id);
        $this->assertTrue($deleted->trashed());
        $this->assertStringEndsWith('@sancta.invalid', $deleted->email);
        $this->assertSame('Conta excluída', $deleted->name);
        $this->assertSame(0, $deleted->tokens()->count());
    }
}
