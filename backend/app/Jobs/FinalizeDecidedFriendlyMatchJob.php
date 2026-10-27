<?php

namespace App\Jobs;

use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Services\Broadcast\CourtStateBroadcaster;
use App\Services\MatchFinalizeService;
use App\Services\MatchRallyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Автозавершение матча «Игры со статистикой», когда он решён по очкам (последний сет сыгран), а секретарь/организатор
 * не нажал «Записать счёт». Без этого матч оставался live с sets 0:0 и не попадал ни в статистику, ни в рейтинги.
 * Диспетчеризуется с задержкой после очка, которое решило матч; срабатывает только если с тех пор не было ни очка, ни
 * отмены (last_seq не изменился), матч ещё не завершён и по-прежнему решён. Турнирные матчи не трогает.
 */
class FinalizeDecidedFriendlyMatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $matchId, public int $expectedSeq)
    {
    }

    public function handle(MatchRallyService $rally, MatchFinalizeService $finalizer): void
    {
        $match = TournamentMatch::with('stage')->find($this->matchId);
        if (!$match || $match->isCompleted() || $match->stage?->type !== TournamentStage::TYPE_FRIENDLY) {
            return;
        }
        if ((int) $match->last_seq !== $this->expectedSeq || !$rally->isMatchReadyToFinalize($match)) {
            return; // было новое очко/отмена или матч уже не решён
        }

        try {
            $finalizer->finalize($match, $rally->buildFinalSets($match), null);
            Log::info('friendly.auto_finalize', ['match_id' => $match->id, 'seq' => $this->expectedSeq]);
            app(CourtStateBroadcaster::class)->forMatch($match->id);
        } catch (\Throwable $e) {
            Log::warning('friendly.auto_finalize_failed', ['match_id' => $match->id, 'error' => $e->getMessage()]);
        }
    }
}
