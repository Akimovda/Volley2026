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
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth / Socialite providers
    |--------------------------------------------------------------------------
    */

    // Telegram Login Widget (не Socialite)
    'telegram' => [
        'bot_token'    => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'), // именно username бота для виджета
    ],

    // VK ID via SocialiteProviders (driver: vkid)
    // Socialite::driver('vkid')
    'vkid' => [
        'client_id'     => env('VKID_CLIENT_ID'),
        'client_secret' => env('VKID_CLIENT_SECRET'),
        'redirect'      => env('VKID_REDIRECT_URI'),
    ],

    // Yandex via SocialiteProviders (driver: yandex)
    // Socialite::driver('yandex')
    'yandex' => [
        'client_id'     => env('YANDEX_CLIENT_ID'),
        'client_secret' => env('YANDEX_CLIENT_SECRET'),
        'redirect'      => env('YANDEX_REDIRECT_URI'),
    ],
];
