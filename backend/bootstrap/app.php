<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'yookassa/webhook',
        ]);
        $middleware->alias([
            'user.restricted' => \App\Http\Middleware\EnsureUserNotRestricted::class,
            'track.view'      => \App\Http\Middleware\TrackPageView::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Reminders: каждую минуту
        $schedule->command('events:send-registration-reminders')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cron-events-reminders.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();