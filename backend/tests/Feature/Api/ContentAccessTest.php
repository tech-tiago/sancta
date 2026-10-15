<?php

namespace Tests\Feature\Api;

use App\Models\BibleAnnotation;
use App\Models\Church;
use App\Models\Journey;
use App\Models\Prayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Bloqueio de conteúdo Sancta+ no servidor e isolamento de dados entre usuários (IDOR). */
class ContentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function premiumPrayer(): Prayer
    {
        return Prayer::query()->create([
            'title' => 'Oração exclusiva',
            'body' => '<p>Parágrafo 1</p><p>Parágrafo 2</p><p>Parágrafo 3 secreto</p>',
            'audio_url' => 'https://cdn.example.com/audio.mp3',
            'is_premium' => true,
            'status' => 'published',
        ]);
    }

    public function test_premium_prayer_is_locked_for_guests_and_free_users(): void
    {
        $prayer = $this->premiumPrayer();

        $guest = $this->getJson("/api/v1/prayers/{$prayer->slug}")->assertOk();
        $guest->assertJsonPath('locked', true)->assertJsonPath('audio_url', null);
        $this->assertCount(2, $guest->json('blocks'));
        $this->assertStringNotContainsString('secreto', json_encode($guest->json()));

        $free = $this->makeUser();
        $this->withHeaders($this->bearer($free))->getJson("/api/v1/prayers/{$prayer->slug}")
            ->assertJsonPath('locked', true)->assertJsonPath('audio_url', null);
    }

    public function test_premium_prayer_is_unlocked_for_subscribers(): void
    {
        $prayer = $this->premiumPrayer();
        $user = $this->makeUser();
        $this->makePremium($user);

        $response = $this->withHeaders($this->bearer($user))->getJson("/api/v1/prayers/{$prayer->slug}")->assertOk();
        $response->assertJsonPath('locked', false)->assertJsonPath('audio_url', 'https://cdn.example.com/audio.mp3');
        $this->assertCount(3, $response->json('blocks'));
    }

    public function test_draft_prayers_are_not_exposed(): void
    {
        $draft = Prayer::query()->create(['title' => 'Rascunho', 'body' => '<p>x</p>', 'status' => 'draft']);

        $this->getJson("/api/v1/prayers/{$draft->slug}")->assertNotFound();
        $this->assertStringNotContainsString('Rascunho', $this->getJson('/api/v1/prayers')->getContent());
    }

    public function test_premium_journey_only_exposes_first_step_to_free_users(): void
    {
        $journey = Journey::query()->create(['title' => 'Plano premium', 'type' => 'reading_plan', 'is_premium' => true, 'status' => 'published']);
        $journey->steps()->create(['position' => 1, 'title' => 'Dia 1', 'content' => '<p>Aberto</p>']);
        $journey->steps()->create(['position' => 2, 'title' => 'Dia 2', 'content' => '<p>Fechado secreto</p>']);

        $response = $this->getJson("/api/v1/journeys/{$journey->slug}")->assertOk();
        $this->assertStringNotContainsString('Fechado secreto', $response->getContent());
        $response->assertJsonPath('steps.1.locked', true);

        $user = $this->makeUser();
        $this->withHeaders($this->bearer($user))->postJson("/api/v1/journeys/{$journey->slug}/steps/2/complete")->assertStatus(402);
        $this->withHeaders($this->bearer($user))->postJson("/api/v1/journeys/{$journey->slug}/steps/1/complete")->assertOk();
    }

    public function test_completing_steps_updates_streak(): void
    {
        $journey = Journey::query()->create(['title' => 'Novena', 'type' => 'novena', 'status' => 'published']);
        $journey->steps()->create(['position' => 1, 'title' => 'Dia 1', 'duration_seconds' => 300]);
        $user = $this->makeUser();

        $this->withHeaders($this->bearer($user))->postJson("/api/v1/journeys/{$journey->slug}/steps/1/complete")
            ->assertOk()->assertJsonPath('finished', true);

        $this->assertSame(1, $user->fresh()->current_streak);
        $this->assertSame(300, (int) $user->fresh()->total_prayer_seconds);
    }

    public function test_ai_explanation_requires_subscription(): void
    {
        $book = $this->seedMiniBible();
        $verseId = $book->verses()->value('id');
        $user = $this->makeUser();

        $this->withHeaders($this->bearer($user))->postJson('/api/v1/bible/explain', ['verse_ids' => [$verseId]])
            ->assertStatus(402)->assertJsonPath('paywall', true);
    }

    public function test_ai_explanation_uses_server_side_key_and_cache(): void
    {
        config(['services.gemini.api_key' => 'chave-servidor']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Explicação católica.']]]]]])]);

        $book = $this->seedMiniBible();
        $verseId = $book->verses()->value('id');
        $user = $this->makeUser();
        $this->makePremium($user);

        $this->withHeaders($this->bearer($user))->postJson('/api/v1/bible/explain', ['verse_ids' => [$verseId]])
            ->assertOk()->assertJsonPath('explanation', 'Explicação católica.');
        $this->withHeaders($this->bearer($user))->postJson('/api/v1/bible/explain', ['verse_ids' => [$verseId]])->assertOk();

        Http::assertSentCount(1); // segunda chamada veio do cache
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'chave-servidor') && ! str_contains($request->url(), 'key='));
    }

    public function test_bible_annotations_are_isolated_per_user(): void
    {
        $book = $this->seedMiniBible();
        $verseId = $book->verses()->value('id');
        $alice = $this->makeUser();
        $bob = $this->makeUser();

        $this->withHeaders($this->bearer($alice))->putJson('/api/v1/me/bible/annotations', [
            'verse_ids' => [$verseId], 'highlight' => 'gold', 'note' => '<script>alert(1)</script>Minha nota',
        ])->assertOk();

        $this->assertSame('alert(1)Minha nota', BibleAnnotation::query()->where('user_id', $alice->id)->value('note'));

        // Cada requisição real começa sem usuário em cache; simulamos isso entre os dois usuários.
        $this->app['auth']->forgetGuards();

        $bobView = $this->withHeaders($this->bearer($bob))->getJson('/api/v1/bible/books/genesis/chapters/1')->assertOk();
        $this->assertNull($bobView->json('verses.0.highlight'));
        $this->assertSame(0, count($this->withHeaders($this->bearer($bob))->getJson('/api/v1/me/bible/annotations')->json('data')));
    }

    public function test_invalid_highlight_color_is_rejected(): void
    {
        $book = $this->seedMiniBible();
        $user = $this->makeUser();

        $this->withHeaders($this->bearer($user))->putJson('/api/v1/me/bible/annotations', [
            'verse_ids' => [$book->verses()->value('id')], 'highlight' => 'red;drop table',
        ])->assertUnprocessable();
    }

    public function test_bible_search_escapes_wildcards(): void
    {
        $this->seedMiniBible();

        $this->getJson('/api/v1/bible/search?q=luz')->assertOk()->assertJsonCount(1, 'results');
        $this->getJson('/api/v1/bible/search?q=%25%25%25')->assertOk()->assertJsonCount(0, 'results');
    }

    public function test_hidden_churches_are_not_listed_nor_favoritable(): void
    {
        $hidden = Church::query()->create(['name' => 'Igreja Oculta', 'city' => 'X', 'state' => 'BA', 'status' => 'hidden']);
        $user = $this->makeUser();

        $this->assertStringNotContainsString('Igreja Oculta', $this->getJson('/api/v1/churches')->getContent());
        $this->getJson("/api/v1/churches/{$hidden->uuid}")->assertNotFound();
        $this->withHeaders($this->bearer($user))->postJson("/api/v1/churches/{$hidden->uuid}/favorite")->assertNotFound();
    }

    public function test_public_endpoints_respond(): void
    {
        $this->seedMiniBible();
        Http::fake(['liturgia.up.railway.app/*' => Http::response([
            'liturgia' => 'Sábado da 26ª Semana do Tempo Comum', 'cor' => 'Verde', 'dia' => 'Oração do dia',
            'primeiraLeitura' => ['referencia' => 'Jó 42,1-3', 'titulo' => 'Leitura', 'texto' => 'Texto'],
            'salmo' => ['referencia' => 'Sl 118', 'refrao' => 'Refrão', 'texto' => 'Texto'],
            'evangelho' => ['referencia' => 'Lc 10,17-24', 'titulo' => 'Evangelho', 'texto' => 'Texto'],
        ])]);

        $this->getJson('/api/v1/config')->assertOk()->assertJsonStructure(['app' => ['min_version'], 'features', 'premium']);
        $this->getJson('/api/v1/home')->assertOk()->assertJsonStructure(['greeting', 'liturgy', 'featured_prayers']);
        $this->getJson('/api/v1/liturgy')->assertOk()->assertJsonPath('color', 'Verde')->assertJsonCount(3, 'readings');
        $this->getJson('/api/v1/bible/books')->assertOk()->assertJsonCount(1, 'books');
        $this->getJson('/api/v1/bible/books/genesis/chapters/1')->assertOk()->assertJsonCount(3, 'verses');
        $this->getJson('/api/v1/bible/books/genesis/chapters/99')->assertNotFound();
        $this->getJson('/api/v1/plans')->assertOk();
        $this->getJson('/api/v1/rota-inexistente')->assertNotFound()->assertJsonPath('message', 'Não encontrado.');
    }
}
