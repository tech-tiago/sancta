<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Filament\Pages\PlatformSettings;
use App\Models\Category;
use App\Models\Prayer;
use App\Services\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `/home` → `period_prayers`: a oração de manhã/tarde/noite do cartão principal da tela Hoje e do
 * widget. Configurável no painel (Configurações → Aplicativo); padrão = heurística no servidor.
 */
class PeriodPrayersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 503)]); // liturgia externa indisponível: /home segue sem ela
    }

    private function prayer(string $title, array $attributes = []): Prayer
    {
        return Prayer::query()->create($attributes + ['title' => $title, 'body' => '<p>Texto</p>', 'status' => 'published', 'duration_seconds' => 300]);
    }

    private function seedPrayers(): array
    {
        $night = Category::query()->create(['slug' => 'para-dormir', 'name' => 'Para dormir', 'is_active' => true]);

        return [
            'pai' => $this->prayer('Pai-Nosso', ['is_featured' => true, 'sort_order' => 1]),
            'manha' => $this->prayer('Oração da manhã', ['is_featured' => true, 'sort_order' => 2]),
            'angelus' => $this->prayer('Angelus', ['sort_order' => 3]),
            'noite_premium' => $this->prayer('Oração da noite guiada', ['is_premium' => true, 'sort_order' => 4]),
            'noite' => $this->prayer('Oração da noite', ['sort_order' => 5]),
            'dormir' => $this->prayer('Salmo do descanso', ['category_id' => $night->id, 'sort_order' => 6]),
        ];
    }

    public function test_default_heuristic_picks_one_free_prayer_per_period(): void
    {
        $p = $this->seedPrayers();

        $home = $this->getJson('/api/v1/home')->assertOk()
            ->assertJsonStructure(['period_prayers' => ['morning' => ['id', 'slug', 'title', 'duration_minutes', 'locked', 'category'], 'afternoon', 'night']]);

        $this->assertSame($p['manha']->slug, $home->json('period_prayers.morning.slug'));
        $this->assertSame($p['angelus']->slug, $home->json('period_prayers.afternoon.slug'));
        // Entre duas orações "da noite", prefere a gratuita.
        $this->assertSame($p['noite']->slug, $home->json('period_prayers.night.slug'));
        $this->assertFalse($home->json('period_prayers.night.locked'));
        $this->assertSame(5, $home->json('period_prayers.morning.duration_minutes'));
    }

    public function test_category_is_used_when_no_title_matches(): void
    {
        $p = $this->seedPrayers();
        $p['noite']->update(['status' => 'draft']);
        $p['noite_premium']->delete();

        $this->getJson('/api/v1/home')->assertJsonPath('period_prayers.night.slug', $p['dormir']->slug);
    }

    public function test_without_matching_prayers_the_keys_are_present_and_null(): void
    {
        $this->prayer('Pai-Nosso');

        $this->getJson('/api/v1/home')->assertOk()
            ->assertJsonPath('period_prayers', ['morning' => null, 'afternoon' => null, 'night' => null]);
    }

    public function test_admin_chooses_the_prayers_in_the_panel(): void
    {
        $p = $this->seedPrayers();
        $this->actingAs($this->makeUser(UserRole::Admin));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(PlatformSettings::class)
            ->fillForm(['home' => [
                'prayer_morning' => $p['pai']->id,
                'prayer_afternoon' => null,
                'prayer_night' => $p['noite_premium']->id,
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($p['pai']->id, app(Settings::class)->get('home.prayer_morning'));
        auth()->logout();

        $guest = $this->getJson('/api/v1/home');
        $guest->assertJsonPath('period_prayers.morning.slug', $p['pai']->slug)
            ->assertJsonPath('period_prayers.afternoon.slug', $p['angelus']->slug) // vazio = automático
            ->assertJsonPath('period_prayers.night.slug', $p['noite_premium']->slug)
            ->assertJsonPath('period_prayers.night.locked', true);

        // Assinante: a mesma escolha, liberada.
        $subscriber = $this->makeUser();
        $this->makePremium($subscriber);
        $this->withHeaders($this->bearer($subscriber))->getJson('/api/v1/home')
            ->assertJsonPath('period_prayers.night.slug', $p['noite_premium']->slug)
            ->assertJsonPath('period_prayers.night.locked', false);
    }

    public function test_chosen_prayer_that_is_no_longer_published_falls_back_to_the_heuristic(): void
    {
        $p = $this->seedPrayers();
        $draft = $this->prayer('Oração antiga da manhã', ['status' => 'draft']);
        app(Settings::class)->set('home.prayer_morning', $draft->id);

        $this->getJson('/api/v1/home')->assertJsonPath('period_prayers.morning.slug', $p['manha']->slug);
        $this->assertStringNotContainsString($draft->slug, $this->getJson('/api/v1/home')->getContent());
    }

    public function test_panel_only_accepts_published_prayers(): void
    {
        $this->seedPrayers();
        $draft = $this->prayer('Rascunho da tarde', ['status' => 'draft']);
        $this->actingAs($this->makeUser(UserRole::Admin));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(PlatformSettings::class)
            ->fillForm(['home' => ['prayer_afternoon' => $draft->id]])
            ->call('save')
            ->assertHasFormErrors(['home.prayer_afternoon']);
    }
}
