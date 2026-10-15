<?php

namespace App\Services\Google;

use Throwable;

/**
 * Valida o id_token do "Entrar com Google" enviado pelo app.
 * Checa assinatura (JWKS), emissor, audiência (nossos client IDs), expiração e e-mail verificado.
 */
class GoogleIdTokenVerifier
{
    public function __construct(private readonly GoogleJwks $jwks) {}

    /** @return array{sub: string, email: string, name: ?string, picture: ?string}|null */
    public function verify(string $idToken): ?array
    {
        // Client IDs aceitos + o client ID Web (o app pede o id_token para ele).
        $audiences = array_values(array_filter([...(array) config('services.google.client_ids', []), config('services.google.web_client_id')], 'filled'));
        if ($audiences === []) {
            return null;
        }

        try {
            $payload = $this->jwks->decode($idToken);
        } catch (Throwable) {
            return null;
        }

        $issuerOk = in_array($payload->iss ?? null, ['accounts.google.com', 'https://accounts.google.com'], true);
        $audienceOk = in_array($payload->aud ?? null, $audiences, true);
        $emailOk = ! empty($payload->email) && filter_var($payload->email_verified ?? false, FILTER_VALIDATE_BOOL);

        if (! $issuerOk || ! $audienceOk || ! $emailOk || empty($payload->sub)) {
            return null;
        }

        return [
            'sub' => (string) $payload->sub,
            'email' => mb_strtolower((string) $payload->email),
            'name' => isset($payload->name) ? (string) $payload->name : null,
            'picture' => isset($payload->picture) && str_starts_with((string) $payload->picture, 'https://') ? (string) $payload->picture : null,
        ];
    }
}
