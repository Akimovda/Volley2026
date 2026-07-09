<?php

namespace App\Console\Commands;

use App\Models\OccurrenceWaitlist;
use Illuminate\Console\Command;

class CleanupExpiredWaitlist extends Command
{
    protected $signature = 'waitlist:cleanup-expired {--dry-run : Показать кандидатов на удаление без удаления}';
    protected $description = 'Удалить occurrence_waitlist для occurrences, прошедших более N дней назад (config waitlist.cleanup_expired_days)';

    public function handle(): int
    {
        $days   = (int) config('waitlist.cleanup_expired_days', 7);
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $query = fn () => OccurrenceWaitlist::query()
            ->whereHas('occurrence', fn ($q) => $q->where('starts_at', '<', $cutoff));

        $count = $query()->count();

        if ($count === 0) {
            $this->info("Устаревших записей листа ожидания не найдено (starts_at < {$cutoff}).");
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("DRY RUN — будет удалено: {$count} (occurrence.starts_at < {$cutoff}, запас {$days} дн.)");

            $query()
                ->with('occurrence:id,event_id,starts_at')
                ->get(['id', 'occurrence_id', 'user_id'])
                ->each(function (OccurrenceWaitlist $row) {
                    $this->line(sprintf(
                        '  #%d occurrence=%d event=%d starts_at=%s user=%d',
                        $row->id,
                        $row->occurrence_id,
                        $row->occurrence->event_id ?? 0,
                        optional($row->occurrence)->starts_at,
                        $row->user_id
                    ));
                });

            return self::SUCCESS;
        }

        $deleted = $query()->delete();
        $this->info("Удалено {$deleted} устаревших записей листа ожидания.");

        return self::SUCCESS;
    }
}
