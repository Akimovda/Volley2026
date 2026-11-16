<?php

/**
 * GeoNames Web Services — поиск города для «+ Добавить город» (events/create и др.).
 * Бесплатная учётная запись: https://www.geonames.org/login → подтвердить e-mail → на странице аккаунта
 * включить «Free Web Services». Логин (не пароль!) — в .env: GEONAMES_USERNAME=...
 * Лимиты бесплатного аккаунта: 20 000 запросов/день, 1000/час. Без логина виджет сообщает «не настроено».
 */
return [
    'username'  => env('GEONAMES_USERNAME', ''),
    'base_url'  => rtrim((string) env('GEONAMES_BASE_URL', 'https://secure.geonames.org'), '/'),
    'timeout'   => 8,
    // лимит добавлений города на одного пользователя в сутки (поиск ограничен throttle на маршруте)
    'daily_additions_per_user' => (int) env('GEONAMES_DAILY_ADDITIONS', 20),
];
