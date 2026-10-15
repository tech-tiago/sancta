<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Planos Sancta+ (inspirados no modelo do Hallow: mensal, semestral e anual com teste grátis).
 * No Google Play: um produto de assinatura "sancta_plus" com três planos-base.
 * Os preços podem ser alterados a qualquer momento no painel.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            'Todas as orações guiadas e em áudio',
            'Jornadas, novenas e planos de leitura completos',
            'Reflexão diária do Evangelho',
            'Explicação de versículos com IA',
            'Eventos e retiros exclusivos',
        ];

        $plans = [
            [
                'slug' => 'mensal',
                'name' => 'Mensal',
                'description' => 'Flexível: cancele quando quiser.',
                'interval' => 'month',
                'interval_count' => 1,
                'price_cents' => 1990,
                'trial_days' => 0,
                'max_installments' => 1,
                'google_play_base_plan_id' => 'mensal',
                'badge' => null,
                'is_featured' => false,
                'sort_order' => 3,
            ],
            [
                'slug' => 'semestral',
                'name' => 'Semestral',
                'description' => 'Seis meses para criar um hábito de oração.',
                'interval' => 'month',
                'interval_count' => 6,
                'price_cents' => 8990,
                'compare_at_price_cents' => 11940,
                'trial_days' => 0,
                'max_installments' => 6,
                'google_play_base_plan_id' => 'semestral',
                'badge' => 'Economize 24%',
                'is_featured' => false,
                'sort_order' => 2,
            ],
            [
                'slug' => 'anual',
                'name' => 'Anual',
                'description' => 'O melhor valor — 7 dias grátis para experimentar.',
                'interval' => 'year',
                'interval_count' => 1,
                'price_cents' => 14990,
                'compare_at_price_cents' => 23880,
                'trial_days' => 7,
                'max_installments' => 12,
                'google_play_base_plan_id' => 'anual',
                'badge' => 'Mais popular',
                'is_featured' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan + [
                'currency' => 'BRL',
                'google_play_product_id' => 'sancta_plus',
                'features' => $features,
                'is_active' => true,
                'available_on_web' => true,
                'available_on_app' => true,
            ]);
        }
    }
}
