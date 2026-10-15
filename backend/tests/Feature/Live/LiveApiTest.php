<?php

namespace Tests\Feature\Live;

use App\Enums\UserRole;
use App\Filament\Resources\LiveChannels\Pages\ManageLiveChannels;
use App\Models\LiveChannel;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Contrato da API "Ao vivo", destaque na tela Hoje, página /ao-vivo e painel. */
class LiveApiTest extends TestCase
{
    use RefreshDatabase;

    private LiveChannel $quiet;

    private LiveChannel $live;

    private LiveChannel $scheduled;

    protected function setUp(): void
    {
        parent::setUp();

        // Domingo, 04/10/2026, 10:00 em Brasília.
        $this->travelTo(CarbonImmutable::parse('2026-10-04 10:00:00', 'America/Sao_Paulo'));

        $this->quiet = LiveChannel::query()->create([
            'name' => 'Canal Tranquilo', 'category' => 'oracao', 'youtube_url' => 'https://www.youtube.com/@tranquilo',
            'fallback_url' => 'https://tranquilo.example.org', 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->quiet->schedules()->create(['weekday' => 1, 'starts_at' => '07:00', 'duration_minutes' => 45, 'title' => 'Oração da manhã']);

        $this->live = LiveChannel::query()->create([
            'name' => 'TV Ao Vivo', 'category' => 'missa', 'youtube_channel_id' => 'UCabcdefghijklmnopqrstuv', 'is_active' => true, 'sort_order' => 2,
        ]);
        $this->live->forceFill([
            'live_status' => 'live', 'live_video_id' => 'LIVEvideo01', 'live_title' => 'Santa Missa',
            'live_started_at' => now()->subMinutes(20), 'live_checked_at' => now()->subMinute(),
        ])->save();

        $this->scheduled = LiveChannel::query()->create([
            'name' => 'Terço Diário', 'category' => 'terco', 'youtube_url' => 'https://www.youtube.com/@terco', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->scheduled->schedules()->create(['weekday' => null, 'starts_at' => '09:30:00', 'duration_minutes' => 60, 'title' => 'Terço da manhã']);

        LiveChannel::query()->create(['name' => 'Inativo', 'youtube_url' => 'https://www.youtube.com/@inativo', 'is_active' => false]);
    }

    public function test_live_channels_contract_and_order(): void
    {
        $response = $this->getJson('/api/v1/live-channels')->assertOk()->assertJsonCount(3);

        // Ao vivo → no horário → ordem do painel.
        $this->assertSame([$this->live->id, $this->scheduled->id, $this->quiet->id], array_column($response->json(), 'id'));

        $response->assertJsonPath('0.is_live', true)
            ->assertJsonPath('0.category', 'missa')
            ->assertJsonPath('0.watch_url', 'https://www.youtube.com/watch?v=LIVEvideo01')
            ->assertJsonPath('0.youtube_url', 'https://www.youtube.com/channel/UCabcdefghijklmnopqrstuv')
            ->assertJsonPath('0.live.video_id', 'LIVEvideo01')
            ->assertJsonPath('0.live.title', 'Santa Missa')
            ->assertJsonPath('0.live.started_at', '2026-10-04T09:40:00-03:00')
            ->assertJsonPath('0.live.thumbnail_url', 'https://i.ytimg.com/vi/LIVEvideo01/hqdefault.jpg')
            ->assertJsonPath('0.upcoming', null)
            ->assertJsonPath('0.topic', 'sancta_live_'.$this->live->id)
            // Compatibilidade com o app 3.0
            ->assertJsonPath('0.url', 'https://www.youtube.com/watch?v=LIVEvideo01')
            ->assertJsonPath('0.thumbnail_url', 'https://i.ytimg.com/vi/LIVEvideo01/hqdefault.jpg');

        $response->assertJsonPath('1.is_live', false)
            ->assertJsonPath('1.on_schedule_now', true)
            ->assertJsonPath('1.live', null)
            ->assertJsonPath('1.watch_url', 'https://www.youtube.com/@terco')
            ->assertJsonPath('1.schedule', [['weekday' => null, 'time' => '09:30', 'duration_minutes' => 60, 'title' => 'Terço da manhã']])
            ->assertJsonPath('1.next_at', '2026-10-05T09:30:00-03:00');

        $response->assertJsonPath('2.on_schedule_now', false)
            ->assertJsonPath('2.next_at', '2026-10-05T07:00:00-03:00')
            ->assertJsonPath('2.fallback_url', 'https://tranquilo.example.org');

        $keys = ['id', 'name', 'description', 'category', 'cover_url', 'logo_url', 'youtube_url', 'watch_url', 'is_live', 'live', 'upcoming',
            'on_schedule_now', 'schedule', 'next_at', 'topic', 'type', 'url', 'video_id', 'fallback_url', 'thumbnail_url'];
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $response->json('2'), "Campo {$key} ausente");
        }
    }

    public function test_upcoming_is_exposed(): void
    {
        $this->quiet->forceFill([
            'live_status' => 'upcoming', 'live_video_id' => 'UPCvideo003', 'live_title' => 'Adoração',
            'live_scheduled_at' => now()->addHours(3), 'live_checked_at' => now(),
        ])->save();

        $channel = collect($this->getJson('/api/v1/live-channels')->json())->firstWhere('id', $this->quiet->id);

        $this->assertFalse($channel['is_live']);
        $this->assertSame(['video_id' => 'UPCvideo003', 'title' => 'Adoração', 'scheduled_at' => '2026-10-04T13:00:00-03:00'], $channel['upcoming']);
    }

    public function test_schedule_crossing_midnight_and_weekday(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 00:20:00', 'America/Sao_Paulo')); // segunda
        $this->quiet->schedules()->create(['weekday' => 0, 'starts_at' => '23:30', 'duration_minutes' => 90, 'title' => 'Vigília']);

        $this->assertTrue($this->quiet->fresh()->isOnScheduleNow());
    }

    public function test_home_exposes_live_now(): void
    {
        Http::fake(['*' => Http::response([], 404)]); // liturgia externa: sem rede nos testes

        $response = $this->getJson('/api/v1/home')->assertOk();

        $this->assertSame([$this->live->id, $this->scheduled->id], array_column($response->json('live_now'), 'id'));
        $response->assertJsonPath('live_now.0.is_live', true)
            ->assertJsonPath('live_now.0.title', 'Santa Missa')
            ->assertJsonPath('live_now.0.watch_url', 'https://www.youtube.com/watch?v=LIVEvideo01')
            ->assertJsonPath('live_now.1.on_schedule_now', true)
            ->assertJsonPath('live_now.1.title', 'Terço da manhã')
            // campos antigos de live_channels continuam
            ->assertJsonPath('live_channels.0.url', 'https://www.youtube.com/watch?v=LIVEvideo01')
            ->assertJsonPath('live_channels.0.is_live', true);
        $this->assertArrayHasKey('thumbnail_url', $response->json('live_channels.0'));
    }

    public function test_live_area_can_be_turned_off(): void
    {
        app(Settings::class)->set('features.live', false);
        Http::fake(['*' => Http::response([], 404)]);

        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('live_now', [])->assertJsonPath('live_channels', []);
        $this->get('/ao-vivo')->assertNotFound();
    }

    public function test_live_page_renders_with_safe_links(): void
    {
        $this->get('/ao-vivo')
            ->assertOk()
            ->assertSee('Ao vivo agora')
            ->assertSee('Agenda de hoje')
            ->assertSee('TV Ao Vivo')
            ->assertSee('Terço da manhã')
            ->assertSee('https://www.youtube.com/watch?v=LIVEvideo01', false)
            ->assertSee('target="_blank" rel="noopener"', false)
            ->assertDontSee('Inativo');

        $this->get('/')->assertOk()->assertSee(route('live'), false);
    }

    public function test_channel_id_format(): void
    {
        $this->assertTrue(LiveChannel::isValidChannelId('UCabcdefghijklmnopqrstuv'));
        $this->assertTrue(LiveChannel::isValidChannelId('UC_x5XG1OV2P6uZZ5FSM9Ttw'));
        $this->assertFalse(LiveChannel::isValidChannelId('UCcurto'));
        $this->assertFalse(LiveChannel::isValidChannelId('XXabcdefghijklmnopqrstuv'));
        $this->assertFalse(LiveChannel::isValidChannelId('UCabcdefghijklmnopqrst/v'));
    }

    public function test_panel_lists_channels_and_validates_channel_id(): void
    {
        $editor = $this->makeUser(UserRole::Editor);
        $this->actingAs($editor)->get('/'.config('sancta.admin.path').'/live-channels')->assertOk()->assertSee('TV Ao Vivo');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $undo = Repeater::fake();

        Livewire::test(ManageLiveChannels::class)
            ->callAction('create', data: ['name' => 'Canal X', 'category' => 'missa', 'type' => 'live', 'youtube_channel_id' => 'UCinvalido'])
            ->assertHasActionErrors(['youtube_channel_id']);

        Livewire::test(ManageLiveChannels::class)
            ->callAction('create', data: [
                'name' => 'Canal Novo', 'category' => 'adoracao', 'type' => 'live', 'youtube_channel_id' => 'UC1234567890123456789012',
                'is_active' => true, 'sort_order' => 5,
                'schedules' => [['weekday' => 0, 'starts_at' => '18:00', 'duration_minutes' => 60, 'title' => 'Adoração']],
            ])
            ->assertHasNoActionErrors();

        $undo();

        $created = LiveChannel::query()->where('name', 'Canal Novo')->firstOrFail();
        $this->assertSame('https://www.youtube.com/channel/UC1234567890123456789012', $created->youtube_url);
        $this->assertSame(1, $created->schedules()->count());
        $this->assertSame('18:00', $created->schedules()->first()->time());
    }

    public function test_moderator_cannot_edit_channels_and_check_now_works_for_editor(): void
    {
        $this->assertFalse($this->makeUser(UserRole::Moderator)->can('update', $this->live));

        config(['services.youtube.api_key' => 'AIzaTeste']);
        Http::fake([
            'www.youtube.com/feeds/videos.xml*' => Http::response('<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"></feed>'),
            'www.googleapis.com/*' => Http::response(['items' => []]),
        ]);

        $this->actingAs($this->makeUser(UserRole::Editor));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageLiveChannels::class)
            ->callTableAction('check_now', $this->live)
            ->assertNotified();

        $this->assertSame('none', $this->live->fresh()->live_status);
    }
}
