<?php

return [
    // Функция «Трансляция» в статусе Beta: один флаг снимает бейдж и пометки «в тестировании» (тексты — lang stream.beta*)
    'beta' => (bool) env('BROADCAST_BETA', true),

    // Канал обратной связи проекта (тот же, что в футере сайта)
    'feedback_url' => env('BROADCAST_FEEDBACK_URL', 'https://t.me/+YAwpAjUdXR0zY2Ji'),
];
