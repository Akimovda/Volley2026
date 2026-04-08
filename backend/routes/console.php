<?php

use Illuminate\Support\Facades\Schedule;

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
