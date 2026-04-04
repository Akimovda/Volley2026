<?php

return [
    'token' => $_ENV['VK_TOKEN'] ?? '',
    'group_id' => (int)($_ENV['VK_GROUP_ID'] ?? 0),
    'api_version' => $_ENV['VK_API_VERSION'] ?? '5.199',
    'confirm_code' => $_ENV['VK_CONFIRM_CODE'] ?? '',
    'admin_id' => $_ENV['VK_ADMIN_ID'] ?? '',
    'log_path' => $_ENV['VK_LOG_PATH'] ?? (__DIR__ . '/../logs/bot.log'),

    'backend_url' => $_ENV['BACKEND_URL'] ?? 'https://volley-bot.store',
    'bind_secret' => $_ENV['BIND_WEBHOOK_SECRET'] ?? '',
];