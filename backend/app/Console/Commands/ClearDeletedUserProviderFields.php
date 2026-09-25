<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Одноразовая команда — закрывает исторический хвост: у части мягко удалённых
 * пользователей (до фикса AccountDeleteRequestController/UserMergeService,
 * 2026-09-25) остались незанулённые provider id и/или поля бот-уведомлений.
 * См. report/kernel_cleanup_recon.md. Удалить класс отдельным коммитом после
 * применения на проде.
 */
class ClearDeletedUserProviderFields extends Command
{
    protected $signature = 'users:clear-deleted-provider-fields
        {--force : Применить (без флага — только показать, что будет сделано)}';

    protected $description = 'Обнулить provider id и поля бот-уведомлений у мягко удалённых пользователей';

    private const FIELDS = [
        'telegram_id', 'vk_id', 'yandex_id', 'apple_id', 'google_id',
        'telegram_notify_chat_id', 'telegram_notify_linked_at',
        'vk_notify_user_id', 'vk_notify_linked_at',
        'max_chat_id', 'max_linked_at',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $affected = fn () => DB::table('users')
            ->whereNotNull('deleted_at')
            ->where(function ($w) {
                foreach (self::FIELDS as $f) {
                    $w->orWhereNotNull($f);
                }
            });

        foreach (self::FIELDS as $f) {
            $cnt = DB::table('users')->whereNotNull('deleted_at')->whereNotNull($f)->count();
            $this->line("{$f}: {$cnt}");
        }

        $total = $affected()->count();

        if ($total === 0) {
            $this->info('Нечего обнулять — кандидатов не найдено.');
            return self::SUCCESS;
        }

        $this->info("Итого пользователей с хотя бы одним заполненным полем: {$total}");

        if (!$force) {
            $this->warn('DRY RUN — ничего не изменено. Повторите с --force для применения.');
            return self::SUCCESS;
        }

        $updated = $affected()->update(array_merge(
            array_fill_keys(self::FIELDS, null),
            ['updated_at' => now()]
        ));

        $this->info("Обновлено: {$updated}");

        return self::SUCCESS;
    }
}
