<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Tests\TestCase;

/**
 * Pentest SEC-04 — Restrição de IP do painel ignorada nas requisições do Livewire.
 * As páginas do painel respondem 404 fora da lista de IPs, mas as ações dos componentes
 * (salvar, excluir, testar) chegam por POST no endpoint do Livewire, que precisa aplicar
 * a mesma restrição (middleware persistente).
 */
class AdminIpLivewireTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED = '10.20.30.40';

    private const OUTSIDE = '203.0.113.9';

    /** Abre a página a partir de um IP permitido e devolve o snapshot do componente principal. */
    private function snapshotFromAllowedIp(string $path): string
    {
        $html = $this->withServerVariables(['REMOTE_ADDR' => self::ALLOWED])->get($path)->assertOk()->getContent();

        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        $names = [];
        foreach ($matches[1] as $raw) {
            $snapshot = html_entity_decode($raw, ENT_QUOTES);
            $name = (string) data_get(json_decode($snapshot, true), 'memo.name');
            $names[] = $name;
            if (str_contains(strtolower($name), 'integration')) {
                return $snapshot;
            }
        }

        $this->fail('Snapshot do componente não encontrado. Encontrados: '.implode(', ', $names));
    }

    private function livewireUpdate(string $snapshot, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['X-Livewire' => '1'])
            ->postJson(app(HandleRequests::class)->getUpdateUri(), [
                'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]]]],
            ]);
    }

    public function test_livewire_actions_respect_the_panel_ip_allowlist(): void
    {
        config(['sancta.admin.allowed_ips' => [self::ALLOWED]]);
        $this->actingAs($this->makeUser(UserRole::SuperAdmin));

        $snapshot = $this->snapshotFromAllowedIp('/'.config('sancta.admin.path').'/integracoes');

        $this->livewireUpdate($snapshot, self::ALLOWED)->assertOk();

        // Em produção cada requisição é um processo novo; no teste, limpa o estado do Livewire
        // (ele memoriza "middleware já aplicado" até o fim da requisição).
        Livewire::flushState();

        $this->livewireUpdate($snapshot, self::OUTSIDE)->assertNotFound();
    }
}
