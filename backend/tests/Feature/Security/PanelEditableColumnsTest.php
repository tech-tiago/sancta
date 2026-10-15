<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Filament\Resources\LiveChannels\Pages\ManageLiveChannels;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Models\LiveChannel;
use App\Models\Testimonial;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pentest fase 3 — SEC-10: colunas editáveis do painel (chaves "Ativo"/"Exibir"/"Destaque" nas
 * listagens) gravavam direto pelo Livewire sem consultar a política do recurso. Um moderador
 * (perfil só de leitura para conteúdo) conseguia publicar/despublicar canais ao vivo, depoimentos
 * do paywall, avisos etc. Agora toda coluna editável fica desabilitada sem permissão de "update".
 */
class PanelEditableColumnsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_moderator_cannot_toggle_live_channel_visibility(): void
    {
        $channel = LiveChannel::query()->create(['name' => 'Canal', 'youtube_url' => 'https://www.youtube.com/@canal', 'is_active' => false]);

        $this->actingAs($this->makeUser(UserRole::Moderator));
        $this->assertTrue(auth()->user()->can('viewAny', LiveChannel::class), 'Moderador visualiza a lista');
        $this->assertFalse(auth()->user()->can('update', $channel));

        Livewire::test(ManageLiveChannels::class)->call('updateTableColumnState', 'is_active', (string) $channel->getKey(), true);

        $this->assertFalse($channel->fresh()->is_active, 'Moderador não pode publicar o canal pela chave da listagem');
    }

    public function test_moderator_cannot_toggle_paywall_testimonials(): void
    {
        $testimonial = Testimonial::query()->forceCreate(['author' => 'Maria', 'content' => 'Rezo todos os dias', 'rating' => 5, 'is_active' => false]);

        $this->actingAs($this->makeUser(UserRole::Moderator));
        Livewire::test(ManageTestimonials::class)->call('updateTableColumnState', 'is_active', (string) $testimonial->getKey(), true);

        $this->assertFalse((bool) $testimonial->fresh()->is_active);
    }

    public function test_editor_still_toggles(): void
    {
        $channel = LiveChannel::query()->create(['name' => 'Canal', 'youtube_url' => 'https://www.youtube.com/@canal', 'is_active' => false]);

        $this->actingAs($this->makeUser(UserRole::Editor));
        Livewire::test(ManageLiveChannels::class)->call('updateTableColumnState', 'is_active', (string) $channel->getKey(), true);

        $this->assertTrue($channel->fresh()->is_active);
    }
}
