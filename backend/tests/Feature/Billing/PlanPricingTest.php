<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Equivalente mensal e % de economia calculados no servidor (por canal: app × site). */
class PlanPricingTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlans(): void
    {
        $this->makePlan(['slug' => 'mensal', 'name' => 'Mensal', 'interval' => 'month', 'interval_count' => 1, 'price_cents' => 1990, 'sort_order' => 3]);
        $this->makePlan(['slug' => 'semestral', 'name' => 'Semestral', 'interval' => 'month', 'interval_count' => 6, 'price_cents' => 8990, 'sort_order' => 2]);
        $this->makePlan(['slug' => 'anual', 'name' => 'Anual', 'interval' => 'year', 'interval_count' => 1, 'price_cents' => 14990, 'trial_days' => 7, 'badge' => 'Mais popular', 'is_featured' => true, 'sort_order' => 1]);
        $this->makePlan(['slug' => 'vitalicio', 'name' => 'Vitalício', 'interval' => 'lifetime', 'interval_count' => 1, 'price_cents' => 49990, 'sort_order' => 4]);
    }

    public function test_api_plans_expose_monthly_equivalent_and_savings(): void
    {
        $this->seedPlans();

        $plans = collect($this->getJson('/api/v1/plans')->assertOk()->json())->keyBy('slug');

        $this->assertSame(1990, $plans['mensal']['monthly_equivalent_cents']);
        $this->assertNull($plans['mensal']['savings_percent']);

        $this->assertSame(1498, $plans['semestral']['monthly_equivalent_cents']); // 89,90 / 6
        $this->assertSame(24, $plans['semestral']['savings_percent']);          // 24,7% → 24 (nunca arredonda para cima)

        $this->assertSame(1249, $plans['anual']['monthly_equivalent_cents']);    // 149,90 / 12
        $this->assertSame(37, $plans['anual']['savings_percent']);
        $this->assertSame(7, $plans['anual']['trial_days']);
        $this->assertSame('Mais popular', $plans['anual']['badge']);
        $this->assertTrue($plans['anual']['is_featured']);

        $this->assertNull($plans['vitalicio']['monthly_equivalent_cents']);
        $this->assertNull($plans['vitalicio']['savings_percent']);
    }

    public function test_savings_use_the_monthly_plan_of_the_same_channel(): void
    {
        $this->seedPlans();
        // Mensal mais caro vendido só no site: no site, a economia do anual é maior.
        Plan::query()->where('slug', 'mensal')->update(['available_on_web' => false]);
        $this->makePlan(['slug' => 'mensal-site', 'name' => 'Mensal site', 'interval' => 'month', 'interval_count' => 1, 'price_cents' => 2490, 'available_on_app' => false]);

        $app = collect($this->getJson('/api/v1/plans')->json())->keyBy('slug');
        $this->assertSame(37, $app['anual']['savings_percent']);
        $this->assertArrayNotHasKey('mensal-site', $app->all());

        // Site: (24,90 × 12 − 149,90) / (24,90 × 12) = 49,8% → 49
        $this->get('/planos')->assertOk()->assertSee('Economize 49%');
    }

    public function test_no_monthly_plan_means_no_savings(): void
    {
        $this->makePlan(['slug' => 'anual', 'interval' => 'year', 'price_cents' => 14990]);
        $this->makePlan(['slug' => 'mensal-inativo', 'interval' => 'month', 'interval_count' => 1, 'price_cents' => 1990, 'is_active' => false]);

        $this->getJson('/api/v1/plans')->assertOk()->assertJsonPath('0.savings_percent', null)->assertJsonPath('0.monthly_equivalent_cents', 1249);
    }

    public function test_cheapest_monthly_is_the_reference_and_math_is_exact(): void
    {
        $monthly = new Plan(['interval' => 'month', 'interval_count' => 1, 'price_cents' => 1000, 'currency' => 'BRL', 'is_active' => true]);
        $cheaper = new Plan(['interval' => 'month', 'interval_count' => 1, 'price_cents' => 900, 'currency' => 'BRL', 'is_active' => true]);
        $quarter = new Plan(['interval' => 'month', 'interval_count' => 3, 'price_cents' => 2100, 'currency' => 'BRL', 'is_active' => true]);
        $pricey = new Plan(['interval' => 'month', 'interval_count' => 3, 'price_cents' => 3000, 'currency' => 'BRL', 'is_active' => true]);

        Plan::withPricing(collect([$monthly, $cheaper, $quarter, $pricey]));

        $this->assertSame($cheaper, $quarter->pricingReference);
        $this->assertSame(22, $quarter->savingsPercent()); // 1 − 2100/2700 = 22,2%
        $this->assertNull($pricey->savingsPercent());       // mais caro que o mensal: sem "economia"
        $this->assertNull($cheaper->savingsPercent());       // o próprio mensal
    }

    public function test_plans_page_shows_monthly_equivalent_and_savings(): void
    {
        $this->seedPlans();

        $this->get('/planos')->assertOk()
            ->assertSee('equivale a R$')
            ->assertSee('Economize 37%')
            ->assertSee('Economize 24%');
    }
}
