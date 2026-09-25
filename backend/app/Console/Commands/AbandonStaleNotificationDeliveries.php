<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Одноразовая команда — пометить многомесячный бэклог notification_deliveries
 * (pending/failed старше 24ч, которые notifications:retry-failed всё равно
 * никогда не подхватит — там потолок config('notifications.retry_max_age_hours'),
 * по умолчанию 6ч) как 'abandoned'. error не трогаем — история ошибки нужна
 * для будущего разбора. Удалить класс отдельным коммитом после применения
 * на проде.
 */
class AbandonStaleNotificationDeliveries extends Command
{
    protected $signature = 'notifications:abandon-stale
        {--force : Применить (без флага — только показать, что будет сделано)}';

    protected $description = 'Пометить pending/failed доставки старше 24 часов как abandoned';

    public function handle(): int
    {
        $force  = (bool) $this->option('force');
        $cutoff = now()->subHours(24);

        $query = fn () => DB::table('notification_deliveries')
            ->whereIn('status', ['pending', 'failed'])
            ->where('created_at', '<', $cutoff);

        $byStatusChannel = (clone $query())
            ->select('status', 'channel', DB::raw('count(*) as cnt'))
            ->groupBy('status', 'channel')
            ->orderBy('status')->orderBy('channel')
            ->get();

        $total = $byStatusChannel->sum('cnt');

        if ($total === 0) {
            $this->info('Нечего помечать — кандидатов не найдено.');
            return self::SUCCESS;
        }

        foreach ($byStatusChannel as $row) {
            $this->line("{$row->status} | {$row->channel} | {$row->cnt}");
        }
        $this->info("Итого кандидатов: {$total}");

        if (!$force) {
            $this->warn('DRY RUN — ничего не изменено. Повторите с --force для применения.');
            return self::SUCCESS;
        }

        $updated = $query()->update([
            'status'     => 'abandoned',
            'updated_at' => now(),
        ]);

        $this->info("Обновлено: {$updated}");

        return self::SUCCESS;
    }
}
