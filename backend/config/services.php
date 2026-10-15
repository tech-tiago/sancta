<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', ''))))),
        'web_client_id' => env('GOOGLE_WEB_CLIENT_ID'),
    ],

    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'base_url' => 'https://api.mercadopago.com',
    ],

    'google_play' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME', 'com.maria.app_igreja'),
        'service_account' => env('GOOGLE_PLAY_SERVICE_ACCOUNT'),
        'rtdn_audience' => env('GOOGLE_PLAY_RTDN_AUDIENCE'),
        'rtdn_service_account_email' => env('GOOGLE_PLAY_RTDN_SERVICE_ACCOUNT_EMAIL'),
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'service_account' => env('FCM_SERVICE_ACCOUNT'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    // YouTube Data API v3 — detecção de transmissões ao vivo (sancta:live-sync). Opcional.
    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
    ],
];
