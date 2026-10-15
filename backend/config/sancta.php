<?php

/*
|--------------------------------------------------------------------------
| Sancta — configurações da plataforma
|--------------------------------------------------------------------------
| Textos, recursos e links ficam na tabela `settings` (painel → Configurações).
| Credenciais das integrações (Mercado Pago, Google, Firebase, Gemini, SMTP) e a
| segurança do painel são definidas no painel → Integrações e segurança: ficam
| criptografadas no banco e têm prioridade sobre os valores do .env, que servem
| de padrão (ver App\Services\Integrations).
*/

return [

    'admin' => [
        'path' => trim((string) env('SANCTA_ADMIN_PATH', 'painel'), '/') ?: 'painel',
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('SANCTA_ADMIN_IPS', ''))))),
        'require_mfa' => (bool) env('SANCTA_ADMIN_REQUIRE_MFA', false),
        'seed' => [
            'name' => env('SANCTA_ADMIN_NAME', 'Administrador'),
            'email' => env('SANCTA_ADMIN_EMAIL', 'admin@sancta.test'),
            'password' => env('SANCTA_ADMIN_PASSWORD'),
        ],
    ],

    'api' => [
        'token_ttl_days' => (int) env('SANCTA_API_TOKEN_TTL_DAYS', 60),
        // Quantos dispositivos (tokens) simultâneos um usuário pode manter
        'max_tokens_per_user' => 10,
    ],

    'security' => [
        'csp_report_only' => (bool) env('SANCTA_CSP_REPORT_ONLY', false),
        // Tamanho máximo de uploads de imagem enviados por usuários (KB)
        'max_user_upload_kb' => 4096,
    ],

    'payments' => [
        // O gateway simulado NUNCA é usado em produção (ver PaymentGatewayManager)
        'fake_enabled' => (bool) env('SANCTA_FAKE_PAYMENTS', false),
        'currency' => 'BRL',
        // Formas de pagamento aceitas no site (ligadas/desligadas no painel)
        'pix_enabled' => true,
        'card_enabled' => true,
        // Validade do QR Code Pix (minutos)
        'pix_expiration_minutes' => 30,
    ],

    'ai' => [
        // Limite diário de explicações por assinante
        'daily_limit' => 30,
    ],

    'maps' => [
        // Para alto volume comercial, use um provedor com chave (MapTiler, Stadia, Mapbox).
        // Padrão: OpenStreetMap (sem chave; política de uso justo). No tema escuro o
        // site/app aplica um filtro de cor sobre os mesmos tiles.
        'tiles_light' => env('SANCTA_MAP_TILES_LIGHT', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'tiles_dark' => env('SANCTA_MAP_TILES_DARK', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'dark_filter' => (bool) env('SANCTA_MAP_DARK_FILTER', true),
        'attribution' => env('SANCTA_MAP_ATTRIBUTION', '&copy; colaboradores do OpenStreetMap'),
    ],

    'liturgy' => [
        'api_url' => env('LITURGIA_API_URL', 'https://liturgia.up.railway.app/'),
    ],

    'news' => [
        // Limites de segurança para o leitor de RSS
        'max_feed_bytes' => 2 * 1024 * 1024,
        'timeout_seconds' => 15,
        'keep_days' => 90,
    ],
];
