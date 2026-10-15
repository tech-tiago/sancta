<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pentest fase 3 — SEC-19 (informativo): a API deveria responder "Cache-Control: no-store", mas o
 * cabeçalho padrão do framework ("no-cache, private") já vinha preenchido e a regra nunca era aplicada.
 * Respostas com dados da conta (/me, /home logado, /me/subscription) podiam ficar gravadas em caches
 * locais. Agora toda resposta da API é no-store, exceto as marcadas como públicas de propósito
 * (ex.: lista de livros da Bíblia).
 */
class ApiCacheControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_and_home_responses_are_not_stored(): void
    {
        $user = $this->makeUser();

        foreach (['/api/v1/me', '/api/v1/me/subscription', '/api/v1/home', '/api/v1/config'] as $uri) {
            $cache = (string) $this->getJson($uri, $this->bearer($user))->assertOk()->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cache, $uri);
            $this->assertStringContainsString('private', $cache, $uri);
        }
    }

    public function test_explicitly_public_responses_keep_their_cache(): void
    {
        $this->seedMiniBible();

        $cache = (string) $this->getJson('/api/v1/bible/books')->assertOk()->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringNotContainsString('no-store', $cache);
    }
}
