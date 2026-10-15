<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pop-ups do painel: métricas de visualização/clique sem "reeditar" o aviso. */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_interactions_count_without_touching_updated_at(): void
    {
        $this->travelTo(now()->subDay());
        $announcement = Announcement::query()->create(['title' => 'Novena de Aparecida', 'frequency' => 'once']);
        $this->travelBack();

        $before = $this->getJson('/api/v1/announcements')->assertOk()->json('0.updated_at');

        foreach (['view', 'click', 'dismiss'] as $action) {
            $this->postJson("/api/v1/announcements/{$announcement->id}/interactions", ['action' => $action])->assertOk();
        }

        // O app reexibe o pop-up quando updated_at muda; contar visualizações não pode mudá-lo.
        $this->assertSame($before, $this->getJson('/api/v1/announcements')->json('0.updated_at'));

        $announcement->refresh();
        $this->assertSame(1, $announcement->views_count);
        $this->assertSame(1, $announcement->clicks_count);
        $this->assertDatabaseCount('announcement_interactions', 3);
    }

    public function test_invalid_action_is_rejected(): void
    {
        $announcement = Announcement::query()->create(['title' => 'Aviso']);

        $this->postJson("/api/v1/announcements/{$announcement->id}/interactions", ['action' => 'delete'])
            ->assertUnprocessable();
    }
}
