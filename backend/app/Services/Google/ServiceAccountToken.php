<?php

namespace App\Services\Google;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Obtém access tokens OAuth2 de uma conta de serviço Google (JWT bearer flow),
 * usados pela API do Google Play e pelo Firebase Cloud Messaging.
 *
 * A credencial pode ser o CONTEÚDO do arquivo JSON (enviado no painel, guardado
 * criptografado) ou o CAMINHO do arquivo (.env) — que deve ficar fora da pasta pública.
 */
class ServiceAccountToken
{
    public function get(string $credentials, string $scope): string
    {
        $key = 'google.sa_token.'.md5($credentials.$scope);

        return Cache::remember($key, 3000, function () use ($credentials, $scope): string {
            $account = static::parse($credentials);
            if ($account === null) {
                throw new RuntimeException('Credencial da conta de serviço não encontrada ou inválida.');
            }

            $now = time();
            $assertion = JWT::encode([
                'iss' => $account['client_email'],
                'scope' => $scope,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $account['private_key'], 'RS256');

            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if ($response->failed() || ! $response->json('access_token')) {
                throw new RuntimeException('Falha ao autenticar a conta de serviço Google.');
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * Lê a credencial (conteúdo JSON ou caminho do arquivo).
     *
     * @return array{client_email: string, private_key: string, project_id: ?string}|null
     */
    public static function parse(?string $credentials): ?array
    {
        $credentials = trim((string) $credentials);
        if ($credentials === '') {
            return null;
        }

        if (! str_starts_with($credentials, '{')) {
            if (! is_file($credentials) || ! is_readable($credentials)) {
                return null;
            }
            $credentials = (string) file_get_contents($credentials);
        }

        $data = json_decode($credentials, true);
        if (! is_array($data) || ($data['type'] ?? null) !== 'service_account'
            || empty($data['client_email']) || empty($data['private_key'])) {
            return null;
        }

        return [
            'client_email' => (string) $data['client_email'],
            'private_key' => (string) $data['private_key'],
            'project_id' => isset($data['project_id']) ? (string) $data['project_id'] : null,
        ];
    }
}
