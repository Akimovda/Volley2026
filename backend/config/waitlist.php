<?php

return [
    // Через сколько дней после occurrence.starts_at запись occurrence_waitlist
    // считается устаревшей и безопасной для удаления (waitlist:cleanup-expired).
    // Запас в днях — на случай разбора спорных ситуаций по прошедшему туру.
    'cleanup_expired_days' => (int) env('WAITLIST_CLEANUP_EXPIRED_DAYS', 7),
];
