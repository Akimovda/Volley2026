<?php

namespace App\Console\Commands;

use App\Models\ActivitySession;
use App\Services\ActivitySessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupStaleActivitySessions extends Command
{
    protected $signature = 'activity:cleanup-stale-sessions {--dry-run : Показать кандидатов без изменений}';

    protected $description = 'Закрыть зависшие status=live сессии (started_at старше activity.sync_stale_hours): '
        . 'удалить пустые (0 сэмплов и 0 прыжков) или финализировать частичные по последним реальным данным';

    public function __construct(private readonly ActivitySessionService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours  = (int) config('activity.sync_stale_hours', 6);
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $sessions = ActivitySession::where('status', 'live')
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($sessions->isEmpty()) {
            $this->info("Зависших live-сессий не найдено (started_at < {$cutoff}).");
            return self::SUCCESS;
        }

        $deleted   = 0;
        $finalized = 0;

        foreach ($sessions as $session) {
            $isEmpty = ($session->samples_count ?? 0) === 0 && ($session->jump_count ?? 0) === 0;

            if ($dryRun) {
                $this->line(sprintf(
                    '  #%d user=%d started_at=%s action=%s samples=%d jumps=%d',
                    $session->id,
                    $session->user_id,
                    $session->started_at,
                    $isEmpty ? 'delete' : 'finalize',
                    $session->samples_count ?? 0,
                    $session->jump_count ?? 0,
                ));
                continue;
            }

            if ($isEmpty) {
                Log::info('[Activity] cleanup-stale-sessions: удалена пустая зависшая сессия', [
                    'session_id' => $session->id,
                    'user_id'    => $session->user_id,
                    'started_at' => $session->started_at,
                ]);

                // FK на activity_hr_samples/activity_jump_events — cascadeOnDelete, но их и так 0.
                $session->delete();
                $deleted++;
                continue;
            }

            // Частичные данные есть (сэмплы и/или прыжки дошли, но finalize() от клиента — нет).
            // Реальное время окончания неизвестно — берём момент последней дошедшей записи,
            // не now() (иначе duration_sec раздуется на часы простоя после обрыва связи).
            $lastSampleOffsetSec = DB::table('activity_hr_samples')
                ->where('session_id', $session->id)
                ->max('t_offset_sec');
            $lastJumpOffsetMs = DB::table('activity_jump_events')
                ->where('session_id', $session->id)
                ->max('t_offset_ms');

            $lastOffsetSec = max(
                $lastSampleOffsetSec !== null ? (int) $lastSampleOffsetSec : 0,
                $lastJumpOffsetMs !== null ? (int) round($lastJumpOffsetMs / 1000) : 0,
                1, // минимум 1с, чтобы duration_sec не оказался 0
            );

            $endedAtTs = $session->started_at->timestamp + $lastOffsetSec;

            Log::info('[Activity] cleanup-stale-sessions: финализирована зависшая сессия с частичными данными', [
                'session_id'    => $session->id,
                'user_id'       => $session->user_id,
                'samples_count' => $session->samples_count,
                'jump_count'    => $session->jump_count,
                'ended_at_ts'   => $endedAtTs,
            ]);

            $this->service->finalize($session, null, (float) $endedAtTs);
            $finalized++;
        }

        if ($dryRun) {
            $this->info("DRY RUN — кандидатов: {$sessions->count()} (started_at < {$cutoff}, порог {$hours}ч).");
            return self::SUCCESS;
        }

        $this->info("Готово: удалено {$deleted}, финализировано {$finalized} (started_at < {$cutoff}, порог {$hours}ч).");

        return self::SUCCESS;
    }
}
