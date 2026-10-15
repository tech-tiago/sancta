<?php

namespace App\Services\Google;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use stdClass;

/**
 * Verificação de JWTs emitidos pelo Google (login com Google e notificações Pub/Sub)
 * com as chaves públicas oficiais (JWKS), em cache.
 */
class GoogleJwks
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public function decode(string $jwt): stdClass
    {
        JWT::$leeway = 60;

        return JWT::decode($jwt, JWK::parseKeySet($this->keys(), 'RS256'));
    }

    private function keys(): array
    {
        return Cache::remember('google.jwks', 3600, function (): array {
            $response = Http::timeout(10)->retry(2, 200)->get(self::JWKS_URL);
            if ($response->failed() || ! is_array($response->json('keys'))) {
                throw new RuntimeException('Não foi possível obter as chaves públicas do Google.');
            }

            return $response->json();
        });
    }
}
