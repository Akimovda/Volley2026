<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Login
    |--------------------------------------------------------------------------
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_name'  => env('TELEGRAM_BOT_NAME'),
        'redirect'  => env('TELEGRAM_LOGIN_REDIRECT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | VK Legacy OAuth (НЕ ИСПОЛЬЗОВАТЬ ДЛЯ ЛОГИНА)
    |--------------------------------------------------------------------------
    | Оставлено только для совместимости / старого кода
    */

    'vkontakte' => [
        'client_id'     => env('VKONTAKTE_CLIENT_ID'),
        'client_secret' => env('VKONTAKTE_CLIENT_SECRET'),
        'redirect'      => env('VKONTAKTE_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | VK ID (OAuth 2.1 + PKCE) — ОСНОВНОЙ СПОСОБ
    |--------------------------------------------------------------------------
    */

    'vkid' => [
        'client_id'     => env('VKID_CLIENT_ID'),
        'client_secret' => env('VKID_CLIENT_SECRET'),
        'redirect'      => env('VKID_REDIRECT_URI'),

        /*
         * VKID_SCOPES в .env:
         * VKID_SCOPES=email
         * VKID_SCOPES=email,phone (если когда-нибудь разрешат)
         */
        'scopes' => array_filter(
            array_map('trim', explode(',', env('VKID_SCOPES', 'email')))
        ),

        /*
         * PKCE code_verifier TTL (в минутах)
         */
        'pkce_ttl' => (int) env('VKID_PKCE_TTL', 10),

        /*
         * Где хранить PKCE verifier (redis РЕКОМЕНДОВАНО)
         */
        'cache_store'  => env('VKID_CACHE_STORE', 'redis'),
        'cache_prefix' => env('VKID_CACHE_PREFIX', 'socialite:vkid:pkce:'),
    ],

];

