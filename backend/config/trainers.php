<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Просьба оценить тренера после завершения тура
    |--------------------------------------------------------------------------
    | Команда trainers:notify-rating-request шлёт уведомление каждому
    | confirmed-игроку тура без его оценки эффективного тренера этого occurrence.
    | Выключено по умолчанию — тексты уведомления (selling/public) должны быть
    | согласованы с заказчиком ДО включения на бою. Команда безопасна при
    | флаге=false: она сама выходит без побочных эффектов (см. Console/Commands/
    | NotifyTrainerRatingRequestsCommand).
    */
    'rating_request_notify_enabled' => env('TRAINER_RATING_REQUEST_NOTIFY_ENABLED', false),

    // Не уведомлять по occurrences, завершившимся раньше этого числа часов назад
    // (защита от массовой рассылки по старым турам при первом включении фичи).
    'rating_request_max_age_hours' => env('TRAINER_RATING_REQUEST_MAX_AGE_HOURS', 6),

];
