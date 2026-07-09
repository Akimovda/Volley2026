<?php

return [
    // Не ретраить доставки старше N часов — уведомление вида "игрок записался"
    // теряет смысл сутки спустя, даже если оно теоретически ещё retryable.
    'retry_max_age_hours' => (int) env('NOTIFICATIONS_RETRY_MAX_AGE_HOURS', 6),
];
