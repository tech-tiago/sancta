<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\LiveChannel;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dados FICTÍCIOS para demonstrar o painel (gráficos, assinantes, pagamentos).
 * Executado apenas em ambiente local — nunca em produção.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        // Agenda de exemplo dos canais ao vivo (roda mesmo se os usuários de demonstração já existirem).
        $this->seedLiveSchedules();

        if (User::query()->where('email', 'like', '%@demo.sancta.test')->exists()) {
            return;
        }

        $plans = Plan::query()->get()->keyBy('slug');
        $names = ['Ana', 'Bruno', 'Carla', 'Daniel', 'Elisa', 'Fábio', 'Gabriela', 'Henrique', 'Isabela', 'João', 'Larissa', 'Marcos', 'Natália', 'Otávio', 'Paula', 'Rafael', 'Sofia', 'Tiago', 'Vitória', 'William'];
        $surnames = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Lima', 'Costa', 'Ferreira', 'Almeida'];

        for ($i = 1; $i <= 60; $i++) {
            $created = now()->subDays(random_int(0, 120))->subMinutes(random_int(0, 1440));
            $name = $names[array_rand($names)].' '.$surnames[array_rand($surnames)];

            $user = User::query()->create([
                'name' => $name,
                'email' => Str::slug($name, '.').".{$i}@demo.sancta.test",
                'password' => Str::password(24),
            ]);
            $user->forceFill([
                'created_at' => $created,
                'email_verified_at' => $created,
                'terms_accepted_at' => $created,
                'current_streak' => random_int(0, 30),
                'longest_streak' => random_int(5, 60),
                'total_prayer_seconds' => random_int(600, 60000),
                'last_login_at' => now()->subHours(random_int(1, 400)),
                'source' => random_int(0, 3) ? 'app' : 'site',
            ])->save();

            // ~40% assinantes
            if (random_int(1, 100) > 40) {
                continue;
            }

            $plan = $plans[['mensal', 'semestral', 'anual', 'anual'][array_rand([0, 1, 2, 3])]];
            $start = $created->copy()->addDays(random_int(0, 10));
            $viaPlay = random_int(0, 1) === 1;
            $method = $viaPlay ? PaymentMethod::GooglePlay : (random_int(0, 1) ? PaymentMethod::Pix : PaymentMethod::CreditCard);

            $subscription = new Subscription([
                'plan_id' => $plan->id,
                'provider' => $viaPlay ? PaymentProvider::GooglePlay : PaymentProvider::MercadoPago,
                'status' => SubscriptionStatus::Active,
                'auto_renew' => $viaPlay,
                'starts_at' => $start,
                'ends_at' => $plan->periodEndFrom($start),
                'notes' => 'Dados de demonstração',
            ]);
            $subscription->user()->associate($user);
            $subscription->save();

            $payment = new Payment;
            $payment->forceFill([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
                'provider' => $viaPlay ? PaymentProvider::GooglePlay : PaymentProvider::MercadoPago,
                'method' => $method,
                'status' => PaymentStatus::Approved,
                'amount_cents' => $plan->price_cents,
                'currency' => 'BRL',
                'installments' => $method === PaymentMethod::CreditCard ? random_int(1, $plan->max_installments) : 1,
                'provider_payment_id' => 'DEMO-'.Str::upper(Str::random(12)),
                'paid_at' => $start,
                'created_at' => $start,
                'updated_at' => $start,
            ])->save();
        }

        $this->command?->info('Dados de demonstração criados (60 usuários fictícios).');
    }

    /**
     * Horários FICTÍCIOS (somente local) para visualizar "No horário da transmissão" e "Agenda de hoje".
     * Não cadastra IDs de canais do YouTube — isso é feito no painel com os IDs reais.
     */
    private function seedLiveSchedules(): void
    {
        $examples = [
            'TV Canção Nova' => [
                ['weekday' => null, 'starts_at' => '07:00', 'duration_minutes' => 60, 'title' => 'Santa Missa (exemplo)'],
                ['weekday' => null, 'starts_at' => '15:00', 'duration_minutes' => 30, 'title' => 'Terço da Misericórdia (exemplo)'],
            ],
            'TV Aparecida' => [
                ['weekday' => 0, 'starts_at' => '08:00', 'duration_minutes' => 90, 'title' => 'Missa de domingo (exemplo)'],
                ['weekday' => null, 'starts_at' => '18:00', 'duration_minutes' => 30, 'title' => 'Terço (exemplo)'],
            ],
            'Frei Gilson' => [
                ['weekday' => null, 'starts_at' => '21:00', 'duration_minutes' => 45, 'title' => 'Oração da noite (exemplo)'],
            ],
        ];

        foreach ($examples as $name => $schedules) {
            $channel = LiveChannel::query()->where('name', $name)->first();
            if (! $channel || $channel->schedules()->exists()) {
                continue;
            }

            foreach ($schedules as $i => $schedule) {
                $channel->schedules()->create($schedule + ['sort_order' => $i]);
            }
        }
    }
}
