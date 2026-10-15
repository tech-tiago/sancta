<?php

namespace Tests\Feature\Push;

use App\Enums\Audience;
use App\Models\PushCampaign;
use App\Services\Google\ServiceAccountToken;
use App\Services\Push\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Formato da mensagem FCM: canal Android padrão "Avisos e novidades" (sancta_avisos), cor Azul Manto
 * e ícone do Portal — o app cria os mesmos canais e o Manifest aponta o mesmo canal padrão.
 */
class PushServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.fcm.project_id' => 'sancta-teste', 'services.fcm.service_account' => '{"fake":true}']);
        $this->mock(ServiceAccountToken::class, fn ($mock) => $mock->shouldReceive('get')->andReturn('token-de-teste'));
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/sancta-teste/messages/1'])]);
    }

    public function test_campaign_uses_default_channel_brand_color_and_portal_icon(): void
    {
        $campaign = PushCampaign::query()->create([
            'title' => 'Novidade no Sancta',
            'body' => 'Conheça o widget Oração do dia.',
            'image_url' => 'https://sancta.app.br/storage/push/capa.jpg',
            'deep_link' => 'sancta://hoje',
            'audience' => Audience::All,
        ]);

        $this->assertTrue(app(PushService::class)->sendCampaign($campaign));

        Http::assertSent(function (Request $request): bool {
            $message = $request->data()['message'] ?? [];

            return str_contains($request->url(), '/projects/sancta-teste/messages:send')
                && $request->hasHeader('Authorization', 'Bearer token-de-teste')
                && ($message['topic'] ?? null) === 'sancta_all'
                && data_get($message, 'android.notification.channel_id') === 'sancta_avisos'
                && data_get($message, 'android.notification.color') === '#1F3D9C'
                && data_get($message, 'android.notification.icon') === 'ic_notification'
                && data_get($message, 'notification.image') === 'https://sancta.app.br/storage/push/capa.jpg'
                && data_get($message, 'data.deep_link') === 'sancta://hoje';
        });
        $this->assertSame('sent', $campaign->fresh()->status);
    }

    public function test_live_push_keeps_its_own_channel(): void
    {
        $ok = app(PushService::class)->notifyTopic('sancta_live_3', 'Ao vivo agora: TV', 'Santa Missa', 'sancta://ao-vivo', 'sancta_ao_vivo', null, ['type' => 'live']);

        $this->assertTrue($ok);
        Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'message.topic') === 'sancta_live_3'
            && data_get($request->data(), 'message.android.notification.channel_id') === 'sancta_ao_vivo'
            && data_get($request->data(), 'message.android.notification.color') === '#1F3D9C'
            && data_get($request->data(), 'message.data.type') === 'live');
    }

    public function test_user_notification_falls_back_to_default_channel(): void
    {
        $user = $this->makeUser();
        $user->devices()->create(['fcm_token' => str_repeat('a', 40), 'platform' => 'android', 'notifications_enabled' => true]);

        app(PushService::class)->notifyUser($user, 'Pagamento aprovado', 'Seu Sancta+ está ativo.', 'sancta://perfil');

        Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'message.token') === str_repeat('a', 40)
            && data_get($request->data(), 'message.android.notification.channel_id') === PushService::DEFAULT_CHANNEL
            && data_get($request->data(), 'message.android.notification.color') === PushService::ACCENT_COLOR);
    }
}
