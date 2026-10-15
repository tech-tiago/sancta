<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Ordem importa: Bíblia antes do versículo do dia; orações antes das jornadas.
     * Dados de demonstração só em ambiente local.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            PlanSeeder::class,
            BibleSeeder::class,
            PrayerSeeder::class,
            JourneySeeder::class,
            DailyVerseSeeder::class,
            SaintSeeder::class,
            ChurchSeeder::class,
            MediaSeeder::class,
            SiteContentSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
