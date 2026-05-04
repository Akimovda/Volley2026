<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PurgeInactiveUsersJob;
use Illuminate\Console\Command;

class PurgeInactiveUsers extends Command
{
    protected $signature   = 'users:purge-inactive {--dry-run : Показать кандидатов без удаления}';
    protected $description = 'Мягко удалить аккаунты без заполненного профиля старше 14 дней';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN — удаления не будет.');
        }

        dispatch_sync(new PurgeInactiveUsersJob($dryRun));

        $this->info('Готово. Подробности в логах.');

        return self::SUCCESS;
    }
}
