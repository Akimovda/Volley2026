<?php

namespace App\Services;

use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TournamentMatchService
{
    public function __construct(
        private TournamentStandingsService $standingsService,
        private TournamentStatsService $statsService,
        private TournamentNotificationService $notificationService,
    ) {}

    /**
     * Записать счёт матча.
     *
     * @param  int[][] $sets  [[25,23],[21,25],[15,11]]
     */
    public function recordScore(TournamentMatch $match, array $sets, ?User $scoredBy = null): TournamentMatch
    {
        if (! $match->hasTeams()) {
            throw new InvalidArgumentException('Невозможно ввести счёт: команды не определены.');
        }

        if ($match->isCompleted()) {
            throw new InvalidArgumentException('Матч уже завершён. Используйте correctScore().');
        }

        $stage  = $match->stage;
        $format = $stage->matchFormat();

        $this->validateSets($sets, $format, $stage);

        $scoreHome = array_column($sets, 0);
        $scoreAway = array_column($sets, 1);

        $setsHome = $setsAway = $totalHome = $totalAway = 0;

        foreach ($sets as [$h, $a]) {
            $totalHome += $h;
            $totalAway += $a;
            if ($h > $a) { $setsHome++; } else { $setsAway++; }
        }

        $winnerId = $setsHome > $setsAway ? $match->team_home_id : $match->team_away_id;

        return DB::transaction(function () use ($match, $scoreHome, $scoreAway, $setsHome, $setsAway, $totalHome, $totalAway, $winnerId, $scoredBy) {
            $match->update([
                'score_home'        => $scoreHome,
                'score_away'        => $scoreAway,
                'sets_home'         => $setsHome,
                'sets_away'         => $setsAway,
                'total_points_home' => $totalHome,
                'total_points_away' => $totalAway,
                'winner_team_id'    => $winnerId,
                'status'            => TournamentMatch::STATUS_COMPLETED,
                'scored_by_user_id' => $scoredBy?->id,
                'scored_at'         => now(),
            ]);

            $this->standingsService->updateAfterMatch($match);
            $this->statsService->updateAfterMatch($match);
            $this->propagateWinner($match);

            // Уведомляем участников
            try {
                $this->notificationService->notifyMatchResult($match);
            } catch (\Throwable $e) {
                \Log::warning('Tournament notification failed: ' . $e->getMessage());
            }

            // King of the Court: обновляем king и очередь
            if ($match->stage->type === 'king_of_court') {
                try {
                    app(\App\Services\TournamentKingService::class)->afterMatch($match->stage, $match);
                } catch (\Throwable $e) {
                    \Log::warning('King afterMatch failed: ' . $e->getMessage());
                }
            }

            return $match->fresh();
        });
    }

    /** Исправление счёта уже завершённого матча. */
    public function correctScore(TournamentMatch $match, array $sets, ?User $scoredBy = null): TournamentMatch
    {
        if (! $match->isCompleted()) {
            throw new InvalidArgumentException('Матч ещё не завершён.');
        }

        return DB::transaction(function () use ($match, $sets, $scoredBy) {
            $this->standingsService->revertMatch($match);
            $this->statsService->revertMatch($match);
            $match->update(['status' => TournamentMatch::STATUS_SCHEDULED, 'winner_team_id' => null]);
            return $this->recordScore($match, $sets, $scoredBy);
        });
    }

    /** Bracket propagation: победитель → next_match, проигравший → loser_next (double elim). */
    private function propagateWinner(TournamentMatch $match): void
    {
        if (! $match->winner_team_id) return;

        if ($match->next_match_id && $match->next_match_slot) {
            $field = $match->next_match_slot === 'home' ? 'team_home_id' : 'team_away_id';
            TournamentMatch::where('id', $match->next_match_id)->update([$field => $match->winner_team_id]);
        }

        if ($match->loser_next_match_id && $match->loser_next_match_slot) {
            $loserId = $match->loserId();
            $field   = $match->loser_next_match_slot === 'home' ? 'team_home_id' : 'team_away_id';
            TournamentMatch::where('id', $match->loser_next_match_id)->update([$field => $loserId]);
        }
    }

    /** Валидация сетов. */
    private function validateSets(array $sets, string $format, TournamentStage $stage): void
    {
        $setsToWin = match ($format) {
            'bo1' => 1, 'bo3' => 2, 'bo5' => 3, default => 2,
        };
        $maxSets = $setsToWin * 2 - 1;

        if (count($sets) < 1 || count($sets) > $maxSets) {
            throw new InvalidArgumentException("Сетов должно быть от 1 до {$maxSets} для {$format}.");
        }

        $setPoints      = $stage->setPoints();
        $decidingPoints = $stage->decidingSetPoints();
        $homeWon = $awayWon = 0;

        foreach ($sets as $i => [$h, $a]) {
            if (! is_int($h) || ! is_int($a) || $h < 0 || $a < 0) {
                throw new InvalidArgumentException('Некорректный счёт в сете ' . ($i + 1));
            }

            $isDeciding = ($i + 1) === $maxSets;
            $target     = $isDeciding ? $decidingPoints : $setPoints;
            $winner     = max($h, $a);
            $loser      = min($h, $a);

            if ($winner < $target) {
                throw new InvalidArgumentException("Сет " . ($i + 1) . ": никто не набрал {$target}.");
            }
            if ($winner - $loser < 2) {
                throw new InvalidArgumentException("Сет " . ($i + 1) . ": разница должна быть >= 2.");
            }
            if ($winner > $target && $loser < $target - 1) {
                throw new InvalidArgumentException("Сет " . ($i + 1) . ": некорректный счёт (при > {$target} проигравший >= " . ($target - 1) . ").");
            }

            if ($h > $a) { $homeWon++; } else { $awayWon++; }
        }

        if ($homeWon !== $setsToWin && $awayWon !== $setsToWin) {
            throw new InvalidArgumentException("Матч не завершён: нужно выиграть {$setsToWin} сетов.");
        }
        if ($homeWon > $setsToWin || $awayWon > $setsToWin) {
            throw new InvalidArgumentException("Лишние сеты: одна сторона уже выиграла матч.");
        }
    }
}
