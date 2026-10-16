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

        // Proxies na frente do servidor, para ler o IP do visitante (X-Forwarded-For) e o HTTPS
        // (X-Forwarded-Proto): IPs/CIDRs separados por vírgula; "cloudflare" inclui as faixas da
        // Cloudflare. Vazio = acesso direto, sem proxy (ver App\Http\Middleware\TrustProxies).
        'trusted_proxies' => (string) env('TRUSTED_PROXIES', ''),
        // Lista atualizada no servidor (deploy/cloudpanel/cloudflare-ips.sh); sem ela, vale a abaixo.
        'cloudflare_ips_file' => storage_path('app/cloudflare-ips.txt'),
        // https://www.cloudflare.com/ips-v4 e /ips-v6 (outubro de 2026)
        'cloudflare_ips' => [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
            '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
            '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
            '2a06:98c0::/29', '2c0f:f248::/32',
        ],
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
