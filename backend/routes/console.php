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
