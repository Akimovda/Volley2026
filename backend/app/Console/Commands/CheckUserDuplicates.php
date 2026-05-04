<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckUserDuplicatesJob;
use Illuminate\Console\Command;

class CheckUserDuplicates extends Command
{
    protected $signature   = 'users:check-duplicates';
    protected $description = 'Найти дубли пользователей и уведомить в Telegram';

    public function handle(): int
    {
        dispatch_sync(new CheckUserDuplicatesJob());

        $this->info('Готово. Подробности в логах.');

        return self::SUCCESS;
    }
}
