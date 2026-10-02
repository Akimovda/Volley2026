<?php

/*
 * Версии нативных приложений (iOS/Android) для GET /api/app-version.
 * Меняются вручную при каждом релизе — правится одна строка в .env,
 * затем `php artisan config:cache`. min — минимальная версия, ниже которой
 * приложение показывает блокирующий алерт; по умолчанию = latest (никого
 * не блокируем).
 */
$iosLatest = env('APP_VERSION_IOS_LATEST', '1.1.9');
$androidLatest = env('APP_VERSION_ANDROID_LATEST', '1.1.5');

return [
    'ios' => [
        'latest' => $iosLatest,
        'min' => env('APP_VERSION_IOS_MIN', $iosLatest),
        'update_url' => env('APP_VERSION_IOS_URL', 'https://apps.apple.com/app/id6764748613'),
    ],
    'android' => [
        'latest' => $androidLatest,
        'min' => env('APP_VERSION_ANDROID_MIN', $androidLatest),
        'update_url' => env('APP_VERSION_ANDROID_URL', 'https://www.rustore.ru/catalog/app/club.volleyplay.app'),
    ],
];
