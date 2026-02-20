<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('events:expand-recurring --horizon=90 --chunk=200 --maxCreates=500')
    ->dailyAt('03:10')
    ->withoutOverlapping();

// на время отладки можно так:
// ->everyMinute();
