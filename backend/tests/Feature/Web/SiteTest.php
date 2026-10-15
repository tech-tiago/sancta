<?php

namespace Tests\Feature\Web;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        $this->seedMiniBible();
        $this->makePlan();
        Church::query()->create(['name' => 'Paróquia Teste', 'city' => 'Salvador', 'state' => 'BA', 'latitude' => -12.9, 'longitude' => -38.5]);
        Page::query()->create(['slug' => 'politica-de-privacidade', 'title' => 'Privacidade', 'content' => '<p>LGPD</p>']);

        $this->get('/')->assertOk()->assertSee('Sua jornada de fé');
        $this->get('/planos')->assertOk()->assertSee('Anual');
        $this->get('/biblia')->assertOk()->assertSee('Gênesis');
        $this->get('/biblia/genesis/1')->assertOk()->assertSee('No princípio');
        $this->get('/biblia/busca?q=luz')->assertOk()->assertSee('<mark>luz</mark>', false);
        $this->get('/igrejas')->assertOk();
        $this->get('/igrejas/mapa.json')->assertOk()->assertJsonFragment(['n' => 'Paróquia Teste']);
        $this->get('/igrejas/paroquia-teste')->assertOk()->assertSee('Paróquia Teste');
        $this->get('/igrejas/cadastrar')->assertOk();
        $this->get('/p/politica-de-privacidade')->assertOk()->assertSee('LGPD');
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->get('/pagina-que-nao-existe')->assertNotFound();
    }

    public function test_web_register_login_and_account(): void
    {
        $this->post('/cadastrar', [
            'name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'Senha123segura',
            'password_confirmation' => 'Senha123segura', 'accept_terms' => '1',
        ])->assertRedirect('/minha-conta');

        $this->get('/minha-conta')->assertOk()->assertSee('Olá, Ana');
        $this->post('/sair')->assertRedirect('/');

        $this->post('/entrar', ['email' => 'ana@example.com', 'password' => 'errada'])->assertSessionHasErrors('email');
        $this->post('/entrar', ['email' => 'ana@example.com', 'password' => 'Senha123segura'])->assertRedirect('/minha-conta');
    }

    public function test_web_account_deletion(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/minha-conta/excluir', ['confirmation' => 'EXCLUIR', 'password' => 'Senha123segura'])->assertRedirect('/');
        $this->assertTrue(User::withTrashed()->find($user->id)->trashed());
    }

    public function test_staff_role_cannot_be_set_through_site_profile(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/minha-conta/perfil', ['name' => 'Novo', 'theme' => 'dark', 'role' => 'super_admin'])->assertRedirect();
        $this->assertSame(UserRole::User, $user->fresh()->role);
        $this->assertSame('dark', $user->fresh()->theme->value);
    }
}
