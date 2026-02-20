protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
{
    $schedule->command('events:expand-recurring --horizon=90 --chunk=200 --maxCreates=500')
        ->dailyAt('03:10')
        ->withoutOverlapping();

    $schedule->command('events:send-reminders')
        ->everyMinute()
        ->withoutOverlapping();
}
