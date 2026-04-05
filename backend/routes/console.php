<?php

use Illuminate\Support\Facades\Artisan;

Schedule::command('bot:assist')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();
// можно оставить пустым — главное, что команды автозагружаются из app/Console/Commands