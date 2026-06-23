<?php

return [
    'recording_open'   => env('ACTIVITY_RECORDING_OPEN', false),
    'consent_version'  => env('ACTIVITY_CONSENT_VERSION', '2026-06-21'),

    // Allowlist user_id для доступа к записи без recording_open и без прав admin.
    // Задаётся через .env: ACTIVITY_RECORDING_ALLOWLIST=415,416
    // После ревью Apple — убрать из .env (не из кода).
    'recording_allowlist' => array_values(array_filter(
        array_map('intval', explode(',', env('ACTIVITY_RECORDING_ALLOWLIST', ''))),
        fn($id) => $id > 0
    )),

    // Что умеет собирать каждый тип источника данных.
    // ble_hrp = стандартный BLE HR Profile (нагрудный пояс) — только пульс.
    // healthkit/polar_sdk/health_connect — могут измерять прыжки через акселерометр.
    'device_capabilities' => [
        'ble_hrp'        => ['hr'],
        'healthkit'      => ['hr', 'jumps'],
        'polar_sdk'      => ['hr', 'jumps'],
        'health_connect' => ['hr', 'jumps'],
    ],
    'default_capabilities' => ['hr'],

    // Точность измерения прыжков по протоколу.
    // none     = только пульс, прыжки недоступны
    // relative = прыжки через акселерометр телефона/часов (приблизительно)
    // better   = датчик у корпуса (Polar/нагрудный), повышенная точность
    'device_accuracy' => [
        'ble_hrp'        => 'none',
        'healthkit'      => 'relative',
        'polar_sdk'      => 'better',
        'health_connect' => 'relative',
    ],
    'default_accuracy' => 'none',

    // Коэффициент конвертации «ускорение → высота прыжка» по протоколу устройства.
    // null = используем дефолт; личный коэффициент атлета (athlete_profiles.jump_height_coeff) имеет приоритет.
    'jump_height_coeff' => [
        'healthkit'      => 0.533,
        'health_connect' => 0.533,
        'polar_sdk'      => null,   // откалибруем на реальном железе
    ],
    'jump_height_coeff_default' => 0.55,

    // Планировщик пушей «Записать активность?»
    // prompt_after_min  — через сколько минут после старта occurrence слать пуш
    // prompt_grace_min  — окно (от now()-grace до now()-after); защита от накопленных occurrence при простое планировщика
    'prompt_after_min' => 5,
    'prompt_grace_min' => 35,
];
