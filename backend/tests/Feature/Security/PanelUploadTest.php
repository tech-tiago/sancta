<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Models\Event;
use App\Rules\NoActiveContentUpload;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pentest SEC-03 — Upload de SVG (conteúdo ativo) pelo painel.
 * Campos ->image() do Filament aceitam "image/*", inclusive SVG. O arquivo vai para o disco
 * público, no mesmo domínio do painel: aberto diretamente, o script do SVG roda com a sessão
 * de quem abriu (ex.: um editor mirando o super administrador).
 */
class PanelUploadTest extends TestCase
{
    use RefreshDatabase;

    private function svg(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('capa.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');
    }

    public function test_editor_cannot_store_svg_through_panel_image_field(): void
    {
        Storage::fake('public');
        $this->actingAs($this->makeUser(UserRole::Editor));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Evento', 'starts_at' => now()->addDay()->format('Y-m-d H:i:s'), 'status' => 'published'])
            ->set('data.cover_image', [$this->svg()])
            ->call('create')
            ->assertHasFormErrors(['cover_image']);

        $this->assertSame(0, Event::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles('events'));
    }

    public function test_raster_images_are_still_accepted(): void
    {
        Storage::fake('public');
        $this->actingAs($this->makeUser(UserRole::Editor));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Evento', 'starts_at' => now()->addDay()->format('Y-m-d H:i:s'), 'status' => 'published'])
            ->set('data.cover_image', [UploadedFile::fake()->image('capa.png', 20, 20)])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Event::query()->count());
    }

    public function test_rule_blocks_active_content_by_type_and_extension(): void
    {
        $rule = ['f' => [new NoActiveContentUpload]];

        $this->assertTrue(Validator::make(['f' => $this->svg()], $rule)->fails());
        $this->assertTrue(Validator::make(['f' => UploadedFile::fake()->createWithContent('pagina.html', '<p>oi</p>')], $rule)->fails());
        $this->assertTrue(Validator::make(['f' => UploadedFile::fake()->createWithContent('foto.png.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')], $rule)->fails());
        $this->assertFalse(Validator::make(['f' => UploadedFile::fake()->image('foto.jpg')], $rule)->fails());
        $this->assertFalse(Validator::make(['f' => UploadedFile::fake()->createWithContent('conta.json', '{"a":1}')], $rule)->fails());
    }
}
