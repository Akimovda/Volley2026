<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Плановая чистка разрастающихся служебных таблиц (по умолчанию — записи старше 45 дней):
 *  - channel_publish_logs   — журнал публикаций анонсов (только пишется, нигде не читается);
 *  - notification_deliveries — история доставок уведомлений (повторы идут по записям моложе 6 ч, напоминания — по событиям);
 *  - page_views             — просмотры страниц; перед удалением считаются в page_view_totals, чтобы
 *                             «просмотры профиля за всё время» на дашборде игрока не уменьшались.
 * НЕ трогает: user_notifications (лента уведомлений пользователя), event_channel_messages (связь анонс ↔ сообщение).
 * Удаление идёт порциями (по умолчанию 5000), чтобы не держать долгих блокировок.
 */
class PruneOldRecords extends Command
{
    protected $signature = 'maintenance:prune-old-records
        {--days=45 : Delete records older than N days}
        {--chunk=5000 : Rows per batch}
        {--dry-run : Only count what would be deleted}';

    protected $description = 'Delete old rows from channel_publish_logs, notification_deliveries, page_views (page views are rolled up into page_view_totals).';

    public function handle(): int
    {
        $days   = max(7, (int) $this->option('days')); // нижняя граница — защита от случайного «--days=0»
        $chunk  = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        foreach (['channel_publish_logs', 'notification_deliveries', 'page_views'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'created_at')) {
                continue;
            }

            $total = (int) DB::table($table)->where('created_at', '<', $cutoff)->count();
            if ($dryRun || $total === 0) {
                $this->line(sprintf('%-26s старше %d дн.: %d%s', $table, $days, $total, $dryRun ? ' (dry-run)' : ''));
                continue;
            }

            $deleted = 0;
            do {
                $n = $table === 'page_views'
                    ? $this->pruneChunkWithRollup($cutoff, $chunk)
                    : $this->pruneChunk($table, $cutoff, $chunk);
                $deleted += $n;
            } while ($n === $chunk);

            $this->info(sprintf('%-26s удалено: %d', $table, $deleted));
        }

        return self::SUCCESS;
    }

    private function pruneChunk(string $table, $cutoff, int $chunk): int
    {
        $ids = DB::table($table)->where('created_at', '<', $cutoff)->orderBy('id')->limit($chunk)->pluck('id');

        return $ids->isEmpty() ? 0 : DB::table($table)->whereIn('id', $ids)->delete();
    }

    /** Порция page_views: сначала +N в page_view_totals по (entity_type, entity_id), потом удаление тех же строк — одной транзакцией. */
    private function pruneChunkWithRollup($cutoff, int $chunk): int
    {
        return (int) DB::transaction(function () use ($cutoff, $chunk) {
            $ids = DB::table('page_views')->where('created_at', '<', $cutoff)->orderBy('id')->limit($chunk)->pluck('id');
            if ($ids->isEmpty()) {
                return 0;
            }

            if (Schema::hasTable('page_view_totals')) {
                $groups = DB::table('page_views')->whereIn('id', $ids)
                    ->select('entity_type', 'entity_id', DB::raw('count(*) as c'))
                    ->groupBy('entity_type', 'entity_id')->get();

                foreach ($groups as $g) {
                    if ($g->entity_type === null || $g->entity_id === null) {
                        continue;
                    }
                    DB::statement(
                        'INSERT INTO page_view_totals (entity_type, entity_id, views, updated_at) VALUES (?, ?, ?, now())
                         ON CONFLICT (entity_type, entity_id) DO UPDATE SET views = page_view_totals.views + EXCLUDED.views, updated_at = now()',
                        [$g->entity_type, $g->entity_id, $g->c]
                    );
                }
            }

            return DB::table('page_views')->whereIn('id', $ids)->delete();
        });
    }
}
