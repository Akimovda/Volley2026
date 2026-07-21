<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('premium:expire')->hourly();

Schedule::command('bot:assist')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('events:cancel-by-quorum')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('events:expand-recurring --days=90 --chunk=200 --maxCreates=500')
    ->dailyAt('03:10')
    ->withoutOverlapping();

Schedule::command('channels:verify-bots')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Освобождение просроченных резервов оплаты
Schedule::job(new \App\Jobs\ReleaseExpiredPaymentsJob())
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Проверка истёкших абонементов и купонов
Schedule::job(new \App\Jobs\CheckExpiredSubscriptions())
    ->dailyAt('01:00')
    ->withoutOverlapping();

// Автозапись и снятие неподтверждённых
Schedule::job(new \App\Jobs\AutoUnconfirmBookingJob())
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Автозапись по абонементам
Schedule::command('subscriptions:auto-booking')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Недельная сводка для Premium (каждый понедельник в 09:00)
Schedule::command('premium:weekly-digest')
    ->weeklyOn(1, '09:00')
    ->withoutOverlapping();

// Уведомления о предстоящих матчах турнира (каждые 5 минут)
Schedule::command('tournament:notify-upcoming --minutes=15')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Ежемесячная турнирная сводка (1-е число в 02:00)
Schedule::command('tournament:monthly-summary')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping();

// Генерация sitemap.xml (ежедневно в 04:00)
Schedule::command('sitemap:generate')
    ->dailyAt('04:00')
    ->withoutOverlapping();

// Публикация анонсов в каналы (registration_open) — каждую минуту:
// находит occurrences с открытой регистрацией без отправленного анонса и публикует.
Schedule::command('events:publish-pending-announcements --limit=100')
    ->everyMinute()
    ->withoutOverlapping();

// Финализация анонсов в каналах (каждые 5 минут):
// находит occurrences, которые только что завершились (starts_at+duration_sec < now,
// в пределах config('channels.finalize_announcement_max_age_hours') — не будим
// редактированием старые посты), ещё не помечены announcement_finalized_at,
// и правит уже опубликованный пост: кнопка «Записаться!» -> «🏁 Мероприятие завершено».
Schedule::command('events:finalize-announcements --limit=100')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Автозапись из waitlist при открытии гендерного окна (каждые 5 минут):
// находит occurrences где gender_limited_reg_starts_days_before прошёл и
// запускает autoBookNext для ожидающих пользователей ограничиваемого пола.
Schedule::command('waitlist:process-gender-windows')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Уборка устаревшего листа ожидания (ежедневно в 04:15):
// удаляет occurrence_waitlist для occurrences, прошедших более N дней назад
// (config('waitlist.cleanup_expired_days'), по умолчанию 7 — запас на случай
// разбора спорных ситуаций по прошедшему туру).
Schedule::command('waitlist:cleanup-expired')
    ->dailyAt('04:15')
    ->withoutOverlapping();

// Ретрай неудачных доставок уведомлений (каждые 5 минут):
// только транзиентные ошибки (is_retryable=true, cURL/сеть/5xx), не старше
// config('notifications.retry_max_age_hours') (по умолчанию 6ч) и не более
// 3 попыток (attempts<3) с backoff 1/5/30 минут между попытками.
Schedule::command('notifications:retry-failed')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Автоотклонение неполных заявок команд (каждые 30 минут):
// находит EventTeamApplication со статусом 'incomplete' у которых
// дедлайн ближайшего occurrence (registration_ends_at) уже наступил.
Schedule::command('tournaments:auto-reject-incomplete-applications')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// Авто-завершение сезонов по ends_at (ежедневно в 03:20):
// переводит active-сезоны в completed когда ends_at < сегодня.
Schedule::call(function () {
    $service = app(\App\Services\TournamentSeasonService::class);
    \App\Models\TournamentSeason::where('status', \App\Models\TournamentSeason::STATUS_ACTIVE)
        ->whereNotNull('ends_at')
        ->where('ends_at', '<', now()->startOfDay())
        ->each(fn ($season) => $service->complete($season));
})->dailyAt('03:20')->name('seasons:auto-complete');

// Перепривязка туров турниров к сезонам по датам (ежедневно в 03:30):
// проверяет все recurring-турниры с season_id и раскладывает
// occurrences по нужным сезонам лиги на основе дат.
Schedule::command('tournaments:sync-season-routing')
    ->dailyAt('03:30')
    ->withoutOverlapping();

// Пуш «Записать активность?» — через 5 мин после старта occurrence
Schedule::command('activity:prompt-recording')
    ->everyMinute()
    ->withoutOverlapping();

// Зависшие status=live сессии (см. report_activity_ghost_duplicates_2026-07-21.md) — старше
// activity.sync_stale_hours данные с устройства уже не придут: пустые удаляются, частичные
// финализируются по последним реальным сэмплам/прыжкам.
Schedule::command('activity:cleanup-stale-sessions')
    ->hourly()
    ->withoutOverlapping();

// Club module: истечение неоплаченных броней кортов (TTL 30 минут, каждые 5 минут):
// pending-брони с payment_mode=prepaid, у которых наступил expires_at, переводятся
// в expired — освобождают слот, чтобы им мог воспользоваться другой организатор.
// Проходим по одной (не массовый update) — нужно уведомить арендатора о каждой.
Schedule::call(function () {
    $notificationService = app(\App\Services\UserNotificationService::class);

    \App\Models\CourtBooking::where('status', 'pending')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->with('court.direction.location')
        ->each(function (\App\Models\CourtBooking $booking) use ($notificationService) {
            $booking->status = 'expired';
            $booking->cancelled_by = 'system';
            $booking->save();

            if ($booking->user_id) {
                $notificationService->createCourtBookingExpiredNotification($booking->user_id, $booking);
            }
        });
})->everyFiveMinutes()->name('expire-court-bookings');

// Club module: напоминания о брони корта за 24ч и за 2ч до начала (каждые 15 минут).
// Окно ±7.5 мин вокруг целевого момента — половина шага расписания, чтобы каждая
// бронь попала ровно в один прогон. reminded_24h_at/reminded_2h_at — защита от дублей.
Schedule::call(function () {
    $notificationService = app(\App\Services\UserNotificationService::class);

    $remind = function (int $hoursBefore, string $flagColumn) use ($notificationService) {
        $target = now()->addHours($hoursBefore);
        \App\Models\CourtBooking::whereIn('status', \App\Models\CourtBooking::ACTIVE_STATUSES)
            ->whereNull($flagColumn)
            ->whereBetween('starts_at', [$target->copy()->subMinutes(7.5), $target->copy()->addMinutes(7.5)])
            ->with('court.direction.location')
            ->each(function (\App\Models\CourtBooking $booking) use ($notificationService, $hoursBefore, $flagColumn) {
                if ($booking->user_id) {
                    $notificationService->createCourtBookingReminderNotification($booking->user_id, $booking, $hoursBefore);
                }
                $booking->update([$flagColumn => now()]);
            });
    };

    $remind(24, 'reminded_24h_at');
    $remind(2, 'reminded_2h_at');
})->everyFifteenMinutes()->name('remind-court-bookings');
