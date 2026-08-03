<?php

namespace App\Jobs;

use App\Services\TournamentEloService;
use App\Services\TournamentOpenSkillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Полный пересчёт Elo + OpenSkill по всей истории турнирных матчей платформы.
 *
 * Elo/OpenSkill — общекарьерные (across всех турниров игрока) и path-dependent
 * (порядок матчей важен) — единственный корректный способ пересчитать их после
 * любого исправления результата (счёт матча, откат стадии, удаление стадии) —
 * полная переигровка ВСЕЙ платформы от базового значения, не одного турнира.
 * См. report_recalc_implementation_plan_2026-08-03.md.
 *
 * $triggeredByEventId используется только для логирования — сам пересчёт глобальный,
 * не зависит от того, какое событие его спровоцировало.
 */
class RecalculateTournamentRatingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(public ?int $triggeredByEventId = null)
    {
        $this->onQueue('default');
    }

    public function handle(TournamentEloService $elo, TournamentOpenSkillService $openSkill): void
    {
        Log::info('[Ratings] Full rebuild triggered', ['event_id' => $this->triggeredByEventId]);

        $elo->rebuildAll();
        $openSkill->rebuildAll();

        Log::info('[Ratings] Full rebuild finished', ['event_id' => $this->triggeredByEventId]);
    }
}
