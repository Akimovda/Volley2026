<?php

return [
    'recording_open'   => env('ACTIVITY_RECORDING_OPEN', false),
    'consent_version'  => env('ACTIVITY_CONSENT_VERSION', '2026-06-21'),

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
];
