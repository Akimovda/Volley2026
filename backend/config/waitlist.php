<?php

return [
    // Через сколько дней после occurrence.starts_at запись occurrence_waitlist
    // считается устаревшей и безопасной для удаления (waitlist:cleanup-expired).
    // Запас в днях — на случай разбора спорных ситуаций по прошедшему туру.
    'cleanup_expired_days' => (int) env('WAITLIST_CLEANUP_EXPIRED_DAYS', 7),

    // Сколько минут даётся на оплату игроку, автоматически записанному из листа ожидания
    // на платное мероприятие (не оплатил — выписывается, место уходит следующему).
    'payment_hold_minutes' => (int) env('WAITLIST_PAYMENT_HOLD_MINUTES', 30),
];
