<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
            \App\Console\Commands\SendPendingNotifications::class,
            \App\Console\Commands\SendEventRegistrationReminders::class,
            \App\Console\Commands\CancelEventsByQuorum::class,
            \App\Console\Commands\BotAssistCommand::class,
        ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('events:send-registration-reminders')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cron-events-reminders.log'));
            
        $schedule->command('events:cancel-by-quorum --limit=200')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cron-events-quorum.log'));
            
        $schedule->command('notifications:send-pending --limit=100')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cron-notifications.log'));

        $schedule->command('users:check-duplicates')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cron-user-duplicates.log'));

        $schedule->command('users:purge-inactive')
            ->dailyAt('04:30')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cron-user-purge.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}