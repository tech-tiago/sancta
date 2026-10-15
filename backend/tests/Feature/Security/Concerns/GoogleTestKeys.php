<?php

namespace Tests\Feature\Security\Concerns;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;

/**
 * Chaves RSA geradas no próprio teste para simular o Google (JWKS) sem acessar a rede.
 * Um par "oficial" (publicado no JWKS falso) e um par "do atacante" (fora do JWKS).
 */
trait GoogleTestKeys
{
    /** @var array<string, array{private: string, n: string, e: string}> */
    private static array $keys = [];

    protected function rsaKey(string $name): array
    {
        if (isset(self::$keys[$name])) {
            return self::$keys[$name];
        }

        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $resource = @openssl_pkey_new($options);
        if ($resource === false) {
            // Windows/XAMPP: o OpenSSL do PHP precisa do openssl.cnf empacotado.
            foreach ([getenv('OPENSSL_CONF'), dirname(PHP_BINARY).'/extras/ssl/openssl.cnf'] as $config) {
                if ($config && is_file($config) && ($resource = @openssl_pkey_new($options + ['config' => $config])) !== false) {
                    break;
                }
            }
        }
        if ($resource === false) {
            $this->markTestSkipped('OpenSSL sem suporte a geração de chaves neste ambiente.');
        }

        openssl_pkey_export($resource, $private, null, isset($config) && $config ? ['config' => $config] : []);
        $details = openssl_pkey_get_details($resource);
        $b64 = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        return self::$keys[$name] = ['private' => $private, 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])];
    }

    /** Publica a chave "oficial" no JWKS falso do Google. */
    protected function fakeGoogleJwks(): array
    {
        Cache::forget('google.jwks');
        $key = $this->rsaKey('google');

        return ['www.googleapis.com/oauth2/v3/certs' => ['keys' => [['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'kid-google', 'n' => $key['n'], 'e' => $key['e']]]]];
    }

    protected function googleJwt(array $claims, string $keyName = 'google'): string
    {
        return JWT::encode($claims + ['iat' => time(), 'exp' => time() + 600], $this->rsaKey($keyName)['private'], 'RS256', 'kid-google');
    }

    protected function serviceAccountJson(): string
    {
        return json_encode([
            'type' => 'service_account', 'project_id' => 'teste', 'client_email' => 'play@teste.iam.gserviceaccount.com',
            'private_key' => $this->rsaKey('service-account')['private'],
        ]);
    }
}
