<?php
// config/event_timezones.php
return [
    'groups' => [
        'Россия (11 часовых поясов)' => [
            'Europe/Kaliningrad'   => 'Калининград (UTC+2) — Europe/Kaliningrad',
            'Europe/Moscow'        => 'Москва (UTC+3) — Europe/Moscow',
            'Europe/Samara'        => 'Самара (UTC+4) — Europe/Samara',
            'Asia/Yekaterinburg'   => 'Екатеринбург (UTC+5) — Asia/Yekaterinburg',
            'Asia/Omsk'            => 'Омск (UTC+6) — Asia/Omsk',
            'Asia/Krasnoyarsk'     => 'Красноярск (UTC+7) — Asia/Krasnoyarsk',
            'Asia/Irkutsk'         => 'Иркутск (UTC+8) — Asia/Irkutsk',
            'Asia/Yakutsk'         => 'Якутск (UTC+9) — Asia/Yakutsk',
            'Asia/Vladivostok'     => 'Владивосток (UTC+10) — Asia/Vladivostok',
            'Asia/Magadan'         => 'Магадан (UTC+11) — Asia/Magadan',
            'Asia/Kamchatka'       => 'Камчатка (UTC+12) — Asia/Kamchatka',
        ],
        'СНГ / рядом' => [
            'Europe/Minsk'         => 'Минск (UTC+3) — Europe/Minsk',
            'Europe/Kyiv'          => 'Киев (UTC+2/UTC+3 DST) — Europe/Kyiv',
            'Asia/Almaty'          => 'Алматы (UTC+5) — Asia/Almaty',
            'Asia/Aqtau'           => 'Актау (UTC+5) — Asia/Aqtau',
            'Asia/Aqtobe'          => 'Актобе (UTC+5) — Asia/Aqtobe',
            'Asia/Atyrau'          => 'Атырау (UTC+5) — Asia/Atyrau',
            'Asia/Qostanay'        => 'Костанай (UTC+5) — Asia/Qostanay',
            'Asia/Qyzylorda'       => 'Кызылорда (UTC+5) — Asia/Qyzylorda',
            'Asia/Tashkent'        => 'Ташкент (UTC+5) — Asia/Tashkent',
            'Asia/Bishkek'         => 'Бишкек (UTC+6) — Asia/Bishkek',
            'Asia/Yerevan'         => 'Ереван (UTC+4) — Asia/Yerevan',
            'Asia/Baku'            => 'Баку (UTC+4) — Asia/Baku',
            'Asia/Tbilisi'         => 'Тбилиси (UTC+4) — Asia/Tbilisi',
        ],
        'Другое' => [
            'Europe/Berlin'        => 'Берлин (UTC+1/UTC+2 DST) — Europe/Berlin',
            'Europe/Istanbul'      => 'Турция (UTC+3) — Europe/Istanbul',
            'Asia/Dubai'           => 'Дубай (UTC+4) — Asia/Dubai',
            'Asia/Bangkok'         => 'Таиланд (UTC+7) — Asia/Bangkok',
            'UTC'                  => 'UTC (UTC+0) — UTC',
        ],
    ],
    // дефолт, если не выбрано
    'default' => 'Europe/Moscow',
];
