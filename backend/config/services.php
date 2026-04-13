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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bind' => [
        'secret' => env('BIND_WEBHOOK_SECRET'),
    ],

    'vk' => [
        'token'       => env('VK_TOKEN'),
        'version'     => env('VK_API_VERSION', '5.199'),
        'bot_link'    => env('VK_BOT_LINK'),
        'bot_api_url' => env('VK_BOT_API_URL'),
    ],

    'max' => [
        'bot_api_url' => env('MAX_BOT_API_URL'),
        'bot_link'    => env('MAX_BOT_LINK'),
        'bot_id'      => env('MAX_BOT_ID'),
        'bot_token'   => env('MAX_BOT_TOKEN'),
    ],

    'telegram' => [
        'bot_token'      => env('TELEGRAM_BOT_TOKEN'),
        'bot_username'   => env('TELEGRAM_BOT_USERNAME'),
        'admin_chat_id'  => env('TELEGRAM_ADMIN_CHAT_ID'),
    ],

    'vkid' => [
        'client_id'      => env('VKID_CLIENT_ID'),
        'client_secret'  => env('VKID_CLIENT_SECRET'),
        'redirect'       => env('VKID_REDIRECT_URI'),
        'admin_token'    => env('VK_ADMIN_TOKEN'),
        'admin_peer_id'  => env('VK_ADMIN_PEER_ID'),
    ],

    'yandex_maps' => [
        'key' => env('YANDEX_MAPS_API_KEY'),
    ],

    'yandex' => [
        'client_id'     => env('YANDEX_CLIENT_ID'),
        'client_secret' => env('YANDEX_CLIENT_SECRET'),
        'redirect'      => env('YANDEX_REDIRECT_URI'),
    ],
];
