<?php

return [
    // Не ретраить доставки старше N часов — уведомление вида "игрок записался"
    // теряет смысл сутки спустя, даже если оно теоретически ещё retryable.
    'retry_max_age_hours' => (int) env('NOTIFICATIONS_RETRY_MAX_AGE_HOURS', 6),

    // Аварийный гейт (по паттерну ACTIVITY_RECORDING_OPEN): рассылка «новое мероприятие
    // в городе» жителям — массовая, ошибка дорогая. Выключается без деплоя.
    'new_event_city_notify_enabled' => (bool) env('NEW_EVENT_CITY_NOTIFY_ENABLED', true),

    // Максимум 1 уведомление этого типа на пользователя за N часов (rate-limit).
    'new_event_city_notify_rate_limit_hours' => (int) env('NEW_EVENT_CITY_NOTIFY_RATE_LIMIT_HOURS', 24),

    // Размер чанка получателей на одну job (цепочка job-ов, не одна большая).
    'new_event_city_notify_chunk_size' => (int) env('NEW_EVENT_CITY_NOTIFY_CHUNK_SIZE', 75),
];
