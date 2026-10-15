<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Filament\Pages\PlatformSettings;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Services\PremiumContent;
use App\Services\Settings;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Conteúdo do paywall Sancta+ editável no painel e exposto em /api/v1/config e /planos. */
class PaywallConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_exposes_premium_block_with_compatible_benefits(): void
    {
        Testimonial::query()->create(['author' => 'Maria', 'location' => 'Salvador, BA', 'content' => 'Rezo todos os dias com o app.', 'rating' => 5, 'is_active' => true, 'sort_order' => 1]);
        Testimonial::query()->create(['author' => 'Inativo', 'content' => 'Não deve aparecer', 'rating' => 4, 'is_active' => false]);

        $premium = $this->getJson('/api/v1/config')->assertOk()->json('premium');

        $this->assertSame('Sancta+', $premium['name']);
        $this->assertSame('Aprofunde sua vida de oração', $premium['headline']);
        $this->assertNotEmpty($premium['subheadline']);
        $this->assertSame('Cancele quando quiser', $premium['guarantee']);
        $this->assertNull($premium['social_proof']); // vazio por padrão: nada de números inventados

        // App atual: lista de strings. Novo: lista estruturada.
        $this->assertContainsOnly('string', $premium['benefits']);
        $this->assertSame('Todas as orações guiadas, jornadas e novenas', $premium['benefits'][0]);
        $this->assertSame(['icon' => 'headphones', 'title' => 'Todas as orações guiadas, jornadas e novenas', 'description' => 'Reze com calma, passo a passo, onde estiver.'], $premium['benefit_items'][0]);
        $this->assertSame(array_column($premium['benefit_items'], 'title'), $premium['benefits']);

        $this->assertArrayHasKey('question', $premium['faq'][0]);
        $this->assertArrayHasKey('answer', $premium['faq'][0]);

        // Depoimentos: só ativos e só nome exibido, texto e nota (sem cidade/dados pessoais).
        $this->assertSame([['author' => 'Maria', 'content' => 'Rezo todos os dias com o app.', 'rating' => 5]], $premium['testimonials']);
    }

    public function test_legacy_string_benefits_are_normalized(): void
    {
        app(Settings::class)->set('premium.benefits', ['Orações guiadas em áudio', 'Leitura da Bíblia', '<b>Algo</b> novo']);

        $premium = $this->getJson('/api/v1/config')->assertOk()->json('premium');

        $this->assertSame(['Orações guiadas em áudio', 'Leitura da Bíblia', 'Algo novo'], $premium['benefits']);
        $this->assertSame('headphones', $premium['benefit_items'][0]['icon']);
        $this->assertSame('book', $premium['benefit_items'][1]['icon']);
        $this->assertSame('star', $premium['benefit_items'][2]['icon']);
    }

    public function test_unknown_icons_and_html_are_sanitized(): void
    {
        $items = PremiumContent::normalizeBenefits([
            ['icon' => 'javascript:alert(1)', 'title' => '<script>x</script>Título', 'description' => '<img src=x onerror=alert(1)>Texto'],
            ['icon' => 'sun', 'title' => '   '],
            'Texto simples',
        ]);

        $this->assertSame([
            ['icon' => 'star', 'title' => 'xTítulo', 'description' => 'Texto'],
            ['icon' => 'star', 'title' => 'Texto simples', 'description' => null],
        ], $items);
    }

    public function test_migration_converts_stored_legacy_benefits(): void
    {
        Setting::withoutEvents(fn () => Setting::query()->create(['key' => 'premium.benefits', 'value' => ['Reflexão diária do Evangelho', 'Eventos e retiros exclusivos']]));

        $migration = require database_path('migrations/2026_10_04_100200_convert_premium_benefits_setting.php');
        $migration->up();

        $stored = Setting::query()->where('key', 'premium.benefits')->first()->value;
        $this->assertSame([
            ['icon' => 'sun', 'title' => 'Reflexão diária do Evangelho', 'description' => null],
            ['icon' => 'calendar', 'title' => 'Eventos e retiros exclusivos', 'description' => null],
        ], $stored);

        $migration->down();
        $this->assertSame(['Reflexão diária do Evangelho', 'Eventos e retiros exclusivos'], Setting::query()->where('key', 'premium.benefits')->first()->value);
    }

    public function test_admin_edits_paywall_content_in_settings(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $undo = Repeater::fake();

        Livewire::test(PlatformSettings::class)
            ->fillForm([
                'premium' => [
                    'headline' => 'Reze todos os dias',
                    'guarantee' => 'Sem fidelidade',
                    'social_proof' => 'Feito com carinho para a sua oração',
                    'benefits' => [['icon' => 'moon', 'title' => 'Oração da noite', 'description' => 'Para terminar o dia em paz.']],
                    'faq' => [['question' => 'Tem teste grátis?', 'answer' => 'Sim, no plano anual.']],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $undo();

        $premium = $this->getJson('/api/v1/config')->json('premium');
        $this->assertSame('Reze todos os dias', $premium['headline']);
        $this->assertSame('Sem fidelidade', $premium['guarantee']);
        $this->assertSame('Feito com carinho para a sua oração', $premium['social_proof']);
        $this->assertSame([['icon' => 'moon', 'title' => 'Oração da noite', 'description' => 'Para terminar o dia em paz.']], $premium['benefit_items']);
        $this->assertSame(['Oração da noite'], $premium['benefits']);
        $this->assertSame([['question' => 'Tem teste grátis?', 'answer' => 'Sim, no plano anual.']], $premium['faq']);
    }

    public function test_plans_page_renders_paywall_content(): void
    {
        $this->makePlan();
        Testimonial::query()->create(['author' => 'João', 'content' => 'Mudou minha rotina de oração.', 'rating' => 5, 'is_active' => true]);

        $this->get('/planos')->assertOk()
            ->assertSee('Aprofunde sua vida de oração')
            ->assertSee('Reflexão diária do Evangelho e homilia')
            ->assertSee('Cancele quando quiser')
            ->assertSee('Mudou minha rotina de oração.')
            ->assertSee('Posso cancelar quando quiser?')
            ->assertSee(route('gift.index'), false);
    }
}
