<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Filament\Pages\IntegrationSettings;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Billing\CheckoutService;
use App\Services\Google\ServiceAccountToken;
use App\Services\Integrations;
use App\Services\IntegrationTester;
use App\Services\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/** Credenciais e segurança editáveis no painel (Integrações e segurança). */
class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'APP_USR-1234567890123456-100426-0123456789abcdef0123456789abcdef-123456789';

    private function integrations(): Integrations
    {
        return app(Integrations::class);
    }

    public function test_only_super_admin_opens_the_page_and_secrets_never_reach_the_html(): void
    {
        $this->integrations()->save(['mercadopago.access_token' => self::TOKEN]);
        $base = '/'.config('sancta.admin.path').'/integracoes';

        $this->actingAs($this->makeUser(UserRole::Admin))->get($base)->assertForbidden();

        // Nova sessão: o painel invalida a sessão quando o usuário autenticado muda.
        $this->flushSession();
        $this->actingAs($this->makeUser(UserRole::SuperAdmin))->get($base)
            ->assertOk()
            ->assertDontSee(self::TOKEN)
            ->assertSee('termina em 6789');
    }

    public function test_secret_is_encrypted_at_rest_and_applied_to_config(): void
    {
        $changed = $this->integrations()->save(['mercadopago.access_token' => self::TOKEN, 'mercadopago.webhook_secret' => 'segredo-do-webhook']);

        $this->assertSame(['mercadopago.access_token', 'mercadopago.webhook_secret'], $changed);
        $raw = Setting::query()->where('key', 'integrations.mercadopago.access_token')->value('value');
        $this->assertStringNotContainsString(self::TOKEN, (string) $raw);
        $this->assertSame(self::TOKEN, config('services.mercadopago.access_token'));
        $this->assertSame('segredo-do-webhook', config('services.mercadopago.webhook_secret'));
    }

    public function test_blank_secret_keeps_value_and_forget_falls_back_to_env(): void
    {
        $integrations = $this->integrations();
        $integrations->save(['gemini.api_key' => 'chave-do-painel']);

        $integrations->save(['gemini.api_key' => '']);
        $this->assertSame('chave-do-painel', config('services.gemini.api_key'));
        $this->assertSame('panel', $integrations->source('gemini.api_key'));

        $integrations->forget('gemini.api_key');
        $this->assertSame($integrations->defaultValue('gemini.api_key'), config('services.gemini.api_key'));
        $this->assertNotSame('panel', $integrations->source('gemini.api_key'));
    }

    public function test_values_already_in_use_are_not_copied_from_env_to_panel(): void
    {
        $env = config('services.google_play.package_name');

        $this->assertSame([], $this->integrations()->save(['google_play.package_name' => $env, 'payments.pix_enabled' => true]));
        $this->assertSame(0, Setting::query()->where('key', 'like', 'integrations.%')->count());
    }

    public function test_saving_requires_the_current_password_and_audits_only_field_names(): void
    {
        $this->actingAs($this->makeUser(UserRole::SuperAdmin));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(IntegrationSettings::class)
            ->fillForm(['mercadopago' => ['access_token' => self::TOKEN], 'confirm_password' => 'senha-errada1'])
            ->call('save')
            ->assertHasFormErrors(['confirm_password']);
        $this->assertNotSame(self::TOKEN, config('services.mercadopago.access_token'));
        $this->assertSame(0, Setting::query()->where('key', 'like', 'integrations.%')->count());

        Livewire::test(IntegrationSettings::class)
            ->fillForm(['mercadopago' => ['access_token' => self::TOKEN], 'confirm_password' => 'Senha123segura'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(self::TOKEN, config('services.mercadopago.access_token'));
        $log = AuditLog::query()->where('event', 'integrations.updated')->latest('id')->first();
        $this->assertSame(['mercadopago.access_token'], $log->new_values['campos']);
        $this->assertStringNotContainsString('APP_USR', json_encode(AuditLog::query()->get()->toArray()));
    }

    public function test_ip_allowlist_cannot_lock_out_the_admin_who_is_saving(): void
    {
        $this->actingAs($this->makeUser(UserRole::SuperAdmin));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(IntegrationSettings::class)
            ->fillForm(['admin' => ['allowed_ips' => ['200.200.200.200']], 'confirm_password' => 'Senha123segura'])
            ->call('save')
            ->assertHasFormErrors(['admin.allowed_ips']);

        Livewire::test(IntegrationSettings::class)
            ->fillForm(['admin' => ['allowed_ips' => ['isso-nao-e-ip']], 'confirm_password' => 'Senha123segura'])
            ->call('save')
            ->assertHasFormErrors(['admin.allowed_ips']);

        Livewire::test(IntegrationSettings::class)
            ->fillForm(['admin' => ['allowed_ips' => ['127.0.0.0/8']], 'confirm_password' => 'Senha123segura'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['127.0.0.0/8'], config('sancta.admin.allowed_ips'));
    }

    public function test_mfa_turned_on_in_the_panel_is_enforced_on_the_next_request(): void
    {
        $base = '/'.config('sancta.admin.path');
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($admin)->get($base)->assertOk();

        $this->integrations()->save(['admin.require_mfa' => true]);

        $this->actingAs($admin)->get($base)->assertRedirectContains('multi-factor-authentication');
    }

    public function test_panel_cannot_disable_mfa_required_by_the_server(): void
    {
        config(['sancta.admin.require_mfa' => true]);
        $integrations = new Integrations(app(Settings::class));
        $integrations->apply();

        $integrations->save(['admin.require_mfa' => false]);

        $this->assertTrue(config('sancta.admin.require_mfa'));
    }

    public function test_unlock_command_removes_panel_restrictions(): void
    {
        $this->integrations()->save(['admin.allowed_ips' => ['10.0.0.1'], 'admin.require_mfa' => true]);

        $this->artisan('sancta:admin-unlock')->assertSuccessful();

        $this->assertSame([], config('sancta.admin.allowed_ips'));
        $this->assertFalse(config('sancta.admin.require_mfa'));
    }

    public function test_disabled_payment_method_is_hidden_and_rejected(): void
    {
        $this->integrations()->save(['payments.card_enabled' => false]);
        $user = $this->makeUser();
        $plan = $this->makePlan();

        $this->actingAs($user)->get(route('checkout.show', $plan))
            ->assertOk()->assertSee('value="pix"', false)->assertDontSee('value="credit_card"', false);

        $this->expectException(RuntimeException::class);
        app(CheckoutService::class)->start($user, $plan, PaymentMethod::CreditCard, null);
    }

    public function test_service_account_can_be_the_json_content_or_a_file_path(): void
    {
        $json = json_encode(['type' => 'service_account', 'client_email' => 'sancta@projeto.iam.gserviceaccount.com', 'private_key' => "-----BEGIN PRIVATE KEY-----\nteste\n-----END PRIVATE KEY-----\n", 'project_id' => 'projeto']);

        $this->integrations()->save(['fcm.service_account' => $json]);

        $this->assertSame('sancta@projeto.iam.gserviceaccount.com', ServiceAccountToken::parse((string) config('services.fcm.service_account'))['client_email']);
        $this->assertSame('sancta@projeto.iam.gserviceaccount.com', $this->integrations()->secretHint('fcm.service_account'));
        $this->assertStringNotContainsString('PRIVATE KEY', (string) Setting::query()->where('key', 'integrations.fcm.service_account')->value('value'));

        $path = tempnam(sys_get_temp_dir(), 'sa');
        file_put_contents($path, $json);
        $this->assertSame('projeto', ServiceAccountToken::parse($path)['project_id']);
        unlink($path);

        $this->assertNull(ServiceAccountToken::parse('{"type":"outra-coisa"}'));
        $this->assertNull(ServiceAccountToken::parse('/caminho/que/nao/existe.json'));
    }

    public function test_mercado_pago_check_reports_account_and_mode(): void
    {
        Http::fake(['api.mercadopago.com/users/me' => Http::response(['nickname' => 'PAROQUIA', 'id' => 1])]);
        $this->integrations()->save(['mercadopago.access_token' => self::TOKEN]);

        $this->assertStringContainsString('PAROQUIA (modo PRODUÇÃO)', app(IntegrationTester::class)->mercadoPago());
    }

    public function test_app_config_exposes_public_ids_but_never_secrets(): void
    {
        $this->makePlan(['google_play_product_id' => 'sancta_plus']);
        $this->integrations()->save([
            'google.web_client_id' => '123-abc.apps.googleusercontent.com',
            'mercadopago.access_token' => self::TOKEN,
            'gemini.api_key' => 'chave-secreta-gemini',
        ]);

        $response = $this->getJson('/api/v1/config')->assertOk()
            ->assertJsonPath('auth.google_server_client_id', '123-abc.apps.googleusercontent.com')
            ->assertJsonPath('billing.product_ids', ['sancta_plus']);

        $body = $response->getContent();
        $this->assertStringNotContainsString(self::TOKEN, $body);
        $this->assertStringNotContainsString('chave-secreta-gemini', $body);
    }

    public function test_valid_ip_entries(): void
    {
        $this->assertTrue(Integrations::isValidIpEntry('200.10.0.1'));
        $this->assertTrue(Integrations::isValidIpEntry('200.10.0.0/24'));
        $this->assertTrue(Integrations::isValidIpEntry('2804:14c::/32'));
        $this->assertFalse(Integrations::isValidIpEntry('200.10.0.0/33'));
        $this->assertFalse(Integrations::isValidIpEntry('meu-ip'));
    }
}
