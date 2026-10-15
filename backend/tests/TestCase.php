<?php

namespace Tests;

use App\Enums\PaymentProvider;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Models\BibleVersion;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function makeUser(UserRole $role = UserRole::User, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role->value, 'password' => 'Senha123segura'], $attributes));
    }

    protected function makePlan(array $attributes = []): Plan
    {
        return Plan::query()->create(array_merge([
            'slug' => 'anual-'.uniqid(),
            'name' => 'Anual',
            'interval' => 'year',
            'interval_count' => 1,
            'price_cents' => 14990,
            'currency' => 'BRL',
            'max_installments' => 12,
            'google_play_product_id' => 'sancta_plus',
            'google_play_base_plan_id' => 'anual',
            'is_active' => true,
            'available_on_web' => true,
            'available_on_app' => true,
        ], $attributes));
    }

    protected function makePremium(User $user): Subscription
    {
        $subscription = new Subscription([
            'plan_id' => $this->makePlan()->id,
            'provider' => PaymentProvider::Manual,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        $subscription->user()->associate($user);
        $subscription->save();
        $user->forgetPremiumCache();

        return $subscription;
    }

    /** Bíblia mínima para testes (Gênesis 1,1-3). */
    protected function seedMiniBible(): BibleBook
    {
        $version = BibleVersion::query()->create(['name' => 'Teste', 'abbreviation' => 'TST', 'is_default' => true, 'is_active' => true]);
        cache()->forget('bible.default_version');

        $book = BibleBook::query()->create([
            'bible_version_id' => $version->id, 'testament' => 'AT', 'position' => 1,
            'name' => 'Gênesis', 'abbreviation' => 'Gn', 'slug' => 'genesis', 'chapters_count' => 1,
        ]);

        foreach (['No princípio, Deus criou o céu e a terra.', 'A terra estava sem forma e vazia.', 'Deus disse: Faça-se a luz! E a luz foi feita.'] as $i => $text) {
            BibleVerse::query()->create(['bible_book_id' => $book->id, 'chapter' => 1, 'verse' => $i + 1, 'text' => $text]);
        }

        return $book;
    }

    protected function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('teste', ['app'], now()->addDay())->plainTextToken];
    }
}
