<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PublishOccurrenceRegistrationOpenJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublishPendingAnnouncements extends Command
{
    protected $signature = 'events:publish-pending-announcements
        {--limit=100 : Max occurrences to dispatch per run}
        {--dry-run : Only show what would be dispatched}';

    protected $description = 'Dispatch channel announcement jobs (registration_open) for occurrences whose registration window has just opened.';

    public function handle(): int
    {
        if (!Schema::hasTable('event_occurrences')
            || !Schema::hasTable('event_notification_channels')
            || !Schema::hasTable('event_channel_messages')
        ) {
            $this->warn('Required tables missing — skip');
            return self::SUCCESS;
        }

        $limit  = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $now    = now('UTC');

        // Берём DISTINCT occurrence_id у которых хотя бы один привязанный канал
        // ещё не получил анонс. Сервис publish() сам обойдёт все каналы и отправит
        // тем, у кого нет записи в event_channel_messages (для остальных hash_check).
        $rows = DB::table('event_occurrences as eo')
            ->join('event_notification_channels as enc', 'enc.event_id', '=', 'eo.event_id')
            ->leftJoin('event_channel_messages as ecm', function ($j) {
                $j->on('ecm.event_id', '=', 'eo.event_id')
                  ->on('ecm.occurrence_id', '=', 'eo.id')
                  ->on('ecm.channel_id', '=', 'enc.channel_id')
                  ->where('ecm.notification_type', 'registration_open');
            })
            ->whereNull('ecm.id') // канал ещё не получил анонс этой occurrence
            ->where('enc.notification_type', 'registration_open')
            ->where('eo.starts_at', '>', $now)
            ->whereNull('eo.cancelled_at')
            ->whereRaw('(eo.is_cancelled IS NULL OR eo.is_cancelled = false)')
            ->whereNotNull('eo.registration_starts_at')
            ->where('eo.registration_starts_at', '<=', $now)
            ->select('eo.id')
            ->distinct()
            ->orderBy('eo.id')
            ->limit($limit)
            ->pluck('id');

        $count = $rows->count();
        $this->info("Found {$count} occurrences pending announcement");

        if ($count === 0 || $dryRun) {
            if ($dryRun) {
                $this->line('Dry-run: ' . $rows->implode(','));
            }
            return self::SUCCESS;
        }

        // Диспатчим в очередь — реальную публикацию делает worker асинхронно.
        // PublishOccurrenceAnnouncementService внутри сам пропускает уже отправленные
        // (по hash в event_channel_messages), поэтому повторный dispatch безопасен.
        foreach ($rows as $occId) {
            PublishOccurrenceRegistrationOpenJob::dispatch((int) $occId)->onQueue('default');
        }

        $this->info("Dispatched {$count} jobs to queue");

        return self::SUCCESS;
    }
}
