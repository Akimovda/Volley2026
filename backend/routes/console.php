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

Schedule::command('events:expand-recurring --horizon=90 --chunk=200 --maxCreates=500')
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
