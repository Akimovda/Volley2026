<?php

namespace App\Services;

use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\TournamentTiebreaker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Запись итогового счёта командного матча и все последствия — без редиректов и без привязки к экрану
 * организатора. Один код для TournamentController::score() и для ввода по токен-ссылке ведущего.
 */
class MatchFinalizeService
{
    public function __construct(
        private TournamentMatchService $matchService,
        private TournamentStandingsService $standingsService,
        private StageCompletionService $stageCompletion,
    ) {}

    /** Совпадает ли счёт по сетам завершённого матча с присланным ($sets = [[home, away], ...]). */
    public function sameSetScores(TournamentMatch $match, array $sets): bool
    {
        $home = array_map('intval', (array) $match->score_home);
        $away = array_map('intval', (array) $match->score_away);
        $inHome = array_map(fn ($s) => (int) $s[0], $sets);
        $inAway = array_map(fn ($s) => (int) $s[1], $sets);

        return $home === array_values($inHome) && $away === array_values($inAway);
    }

    /**
     * Под lock матча: двойной сабмит того же счёта — успех без повторных побочных эффектов;
     * другой счёт у уже завершённого матча — InvalidArgumentException.
     *
     * @param array<int, array{0:int,1:int}> $sets
     * @return array{already:bool, friendly:bool}
     */
    public function finalize(TournamentMatch $match, array $sets, ?User $by): array
    {
        $stage = $match->stage;
        $event = $stage->event;

        $already = DB::transaction(function () use ($match, $sets, $by) {
            $locked = TournamentMatch::whereKey($match->id)->lockForUpdate()->first();
            if ($locked->isCompleted()) {
                if ($this->sameSetScores($locked, $sets)) {
                    return true;
                }
                throw new InvalidArgumentException('Матч уже завершён.');
            }
            $this->matchService->recordScore($locked, $sets, $by);
            return false;
        });

        // «Игра со статистикой» (обычное мероприятие): никаких турнирных последствий.
        if ($stage->type === TournamentStage::TYPE_FRIENDLY) {
            // Рейтинговое мероприятие (events.stats_rated): результаты идут в общие рейтинги
            // тем же путём, что и турнирные (полный пересчёт события + Elo/OpenSkill в очереди).
            if (!$already && $event->stats_rated) {
                try {
                    \App\Jobs\RecalculateTournamentStatsJob::dispatch($event->id)->afterCommit();
                } catch (\Throwable $e) {
                    \Log::warning('Friendly game rating dispatch failed: ' . $e->getMessage());
                }
            }

            return ['already' => $already, 'friendly' => true];
        }

        if (!$already) {
            // Standings затронутой группы пересчитаны синхронно внутри recordScore(); тяжёлый хвост
            // (статистика события, career, сезон) — в очередь.
            try {
                \App\Jobs\RecalculateTournamentStatsJob::dispatch($event->id)->afterCommit();
            } catch (\Throwable $e) {
                \Log::warning('Stats rebuild dispatch failed: ' . $e->getMessage());
            }

            // Тайбрейк-матч: разрезолвим тайбрейкер и пересчитаем standings (только организатор — нужен $by)
            $fresh = $match->fresh();
            if ($fresh->is_tiebreaker && $fresh->winner_team_id && $by) {
                $tb = TournamentTiebreaker::where('match_id', $fresh->id)->first();
                if ($tb && $tb->status !== 'resolved') {
                    $tb->update([
                        'winner_team_id'      => $fresh->winner_team_id,
                        'resolved_by_user_id' => $by->id,
                        'resolved_at'         => now(),
                        'status'              => 'resolved',
                    ]);
                    if ($fresh->group_id) {
                        $group = TournamentGroup::find($fresh->group_id);
                        if ($group) {
                            $this->standingsService->recalculateGroup($stage, $group);
                        }
                    }
                }
            }

            $this->stageCompletion->check($stage);
        }

        return ['already' => $already, 'friendly' => false];
    }
}
