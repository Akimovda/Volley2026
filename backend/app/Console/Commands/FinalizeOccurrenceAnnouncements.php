<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MarkAnnouncementFinalizedJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinalizeOccurrenceAnnouncements extends Command
{
    protected $signature = 'events:finalize-announcements
        {--limit=100 : Max occurrences to dispatch per run}
        {--dry-run : Only show what would be dispatched}';

    protected $description = 'Edit channel announcements (registration_open) for occurrences that just finished: "Записаться!" button/text -> "Мероприятие завершено".';

    public function handle(): int
    {
        if (!Schema::hasTable('event_occurrences') || !Schema::hasTable('event_channel_messages')) {
            $this->warn('Required tables missing — skip');
            return self::SUCCESS;
        }

        $limit      = max(1, (int) $this->option('limit'));
        $dryRun     = (bool) $this->option('dry-run');
        $maxAgeHours = (int) config('channels.finalize_announcement_max_age_hours', 6);
        $now        = now('UTC');
        $cutoff     = $now->copy()->subHours($maxAgeHours);

        // Occurrences, у которых хотя бы один канал получил анонс (registration_open),
        // ещё не финализирован, occurrence реально завершилась (starts_at+duration_sec < now)
        // не раньше $cutoff (защита от массового редактирования старых постов при деплое),
        // и occurrence не отменена (у отмены своя семантика — markCancelled).
        $rows = DB::table('event_occurrences as eo')
            ->join('event_channel_messages as ecm', 'ecm.occurrence_id', '=', 'eo.id')
            ->where('ecm.notification_type', 'registration_open')
            ->whereNotNull('ecm.external_message_id')
            ->whereNull('ecm.announcement_finalized_at')
            ->whereNotNull('eo.duration_sec')
            ->whereNull('eo.cancelled_at')
            ->whereRaw('(eo.is_cancelled IS NULL OR eo.is_cancelled = false)')
            ->whereRaw("eo.starts_at + make_interval(secs => eo.duration_sec) <= ?", [$now])
            ->whereRaw("eo.starts_at + make_interval(secs => eo.duration_sec) >= ?", [$cutoff])
            ->select('eo.id')
            ->distinct()
            ->orderBy('eo.id')
            ->limit($limit)
            ->pluck('id');

        $count = $rows->count();
        $this->info("Found {$count} occurrences pending finalization");

        if ($count === 0 || $dryRun) {
            if ($dryRun) {
                $this->line('Dry-run: ' . $rows->implode(','));
            }
            return self::SUCCESS;
        }

        foreach ($rows as $occId) {
            MarkAnnouncementFinalizedJob::dispatch((int) $occId)->onQueue('default');
        }

        $this->info("Dispatched {$count} jobs to queue");

        return self::SUCCESS;
    }
}
