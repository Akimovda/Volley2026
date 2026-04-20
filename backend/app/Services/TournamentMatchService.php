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
        protected TournamentStandingsService $standingsService,
    ) {}


    /**
     * Записать счёт матча (API контроллера).
     *
     * @param  array  $sets  [[25,23], [21,25], [15,11]]
     */
    public function recordScore(
        TournamentMatch $match,
        array $sets,
        ?User $scorer = null,
    ): TournamentMatch {
        $scoreHome = array_map(fn($s) => (int) $s[0], $sets);
        $scoreAway = array_map(fn($s) => (int) $s[1], $sets);

        return $this->submitScore($match, $scoreHome, $scoreAway, $scorer);
    }

    /**
     * Ввести счёт матча (внутренний).
     *
     * @param  array  $scoreHome  [25, 21, 15] — очки по сетам
     * @param  array  $scoreAway  [23, 25, 11]
     */
    public function submitScore(
        TournamentMatch $match,
        array $scoreHome,
        array $scoreAway,
        ?User $scorer = null,
    ): TournamentMatch {
        if (!$match->hasTeams()) {
            throw new InvalidArgumentException('Матч не имеет обеих команд.');
        }

        if ($match->isCompleted()) {
            throw new InvalidArgumentException('Матч уже завершён.');
        }

        $stage  = $match->stage;
        $format = $stage->matchFormat();

        $this->validateScore($scoreHome, $scoreAway, $format, $stage);

        $setsHome = 0;
        $setsAway = 0;
        foreach ($scoreHome as $i => $pts) {
            $awayPts = $scoreAway[$i] ?? 0;
            if ($pts > $awayPts) {
                $setsHome++;
            } else {
                $setsAway++;
            }
        }

        $winnerId = $setsHome > $setsAway
            ? $match->team_home_id
            : $match->team_away_id;

        DB::transaction(function () use ($match, $scoreHome, $scoreAway, $setsHome, $setsAway, $winnerId, $scorer) {
            $match->update([
                'score_home'        => $scoreHome,
                'score_away'        => $scoreAway,
                'sets_home'         => $setsHome,
                'sets_away'         => $setsAway,
                'total_points_home' => array_sum($scoreHome),
                'total_points_away' => array_sum($scoreAway),
                'winner_team_id'    => $winnerId,
                'status'            => TournamentMatch::STATUS_COMPLETED,
                'scored_by_user_id' => $scorer?->id,
                'scored_at'         => now(),
            ]);

            if ($match->group_id) {
                $this->standingsService->recalculateGroup(
                    $match->stage,
                    $match->group,
                );
            }

            $this->advanceWinner($match, $winnerId);
            $this->advanceLoser($match);
        });

        return $match->fresh();
    }

    /**
     * Отменить счёт (откат результата матча).
     */
    public function resetScore(TournamentMatch $match): TournamentMatch
    {
        DB::transaction(function () use ($match) {
            $winnerId = $match->winner_team_id;

            if ($match->next_match_id && $winnerId) {
                $slot = $match->next_match_slot;
                TournamentMatch::where('id', $match->next_match_id)->update([
                    "team_{$slot}_id" => null,
                ]);
            }

            if ($match->loser_next_match_id) {
                $slot = $match->loser_next_match_slot;
                TournamentMatch::where('id', $match->loser_next_match_id)->update([
                    "team_{$slot}_id" => null,
                ]);
            }

            $match->update([
                'score_home'        => null,
                'score_away'        => null,
                'sets_home'         => 0,
                'sets_away'         => 0,
                'total_points_home' => 0,
                'total_points_away' => 0,
                'winner_team_id'    => null,
                'status'            => TournamentMatch::STATUS_SCHEDULED,
                'scored_by_user_id' => null,
                'scored_at'         => null,
            ]);

            if ($match->group_id) {
                $this->standingsService->recalculateGroup(
                    $match->stage,
                    $match->group,
                );
            }
        });

        return $match->fresh();
    }

    /**
     * Записать forfeit (техническое поражение).
     */
    public function forfeit(TournamentMatch $match, int $loserTeamId, ?User $scorer = null): TournamentMatch
    {
        if (!$match->hasTeams()) {
            throw new InvalidArgumentException('Матч не имеет обеих команд.');
        }

        $winnerId = ($loserTeamId === $match->team_home_id)
            ? $match->team_away_id
            : $match->team_home_id;

        DB::transaction(function () use ($match, $winnerId, $scorer) {
            $match->update([
                'winner_team_id'    => $winnerId,
                'status'            => TournamentMatch::STATUS_FORFEIT,
                'scored_by_user_id' => $scorer?->id,
                'scored_at'         => now(),
            ]);

            if ($match->group_id) {
                $this->standingsService->recalculateGroup(
                    $match->stage,
                    $match->group,
                );
            }

            $this->advanceWinner($match, $winnerId);
            $this->advanceLoser($match);
        });

        return $match->fresh();
    }

    protected function advanceWinner(TournamentMatch $match, int $winnerId): void
    {
        if (!$match->next_match_id) {
            return;
        }

        $slot = $match->next_match_slot;
        TournamentMatch::where('id', $match->next_match_id)->update([
            "team_{$slot}_id" => $winnerId,
        ]);
    }

    protected function advanceLoser(TournamentMatch $match): void
    {
        if (!$match->loser_next_match_id || !$match->winner_team_id) {
            return;
        }

        $loserId = $match->loserId();
        if (!$loserId) {
            return;
        }

        $slot = $match->loser_next_match_slot;
        TournamentMatch::where('id', $match->loser_next_match_id)->update([
            "team_{$slot}_id" => $loserId,
        ]);
    }

    /**
     * Валидация введённого счёта.
     */
    protected function validateScore(
        array $scoreHome,
        array $scoreAway,
        string $format,
        TournamentStage $stage,
    ): void {
        $setCount = count($scoreHome);

        if ($setCount !== count($scoreAway)) {
            throw new InvalidArgumentException('Количество сетов home и away должно совпадать.');
        }

        $maxSets = match ($format) {
            'bo1' => 1,
            'bo3' => 3,
            'bo5' => 5,
            default => 3,
        };

        $setsToWin = match ($format) {
            'bo1' => 1,
            'bo3' => 2,
            'bo5' => 3,
            default => 2,
        };

        if ($setCount < 1 || $setCount > $maxSets) {
            throw new InvalidArgumentException("Для формата {$format} допустимо от 1 до {$maxSets} сетов.");
        }

        $setPoints      = $stage->setPoints();
        $decidingSetPts = $stage->decidingSetPoints();

        $homeWins = 0;
        $awayWins = 0;

        foreach ($scoreHome as $i => $h) {
            $a = $scoreAway[$i];
            // Решающий сет только для Bo3/Bo5 (не Bo1)
            $isDecidingSet = $maxSets > 1 && (($i + 1 === $maxSets) || ($homeWins === $setsToWin - 1 && $awayWins === $setsToWin - 1));
            $target = $isDecidingSet ? $decidingSetPts : $setPoints;

            $this->validateSet($h, $a, $target, $i + 1);

            if ($h > $a) {
                $homeWins++;
            } else {
                $awayWins++;
            }
        }

        if ($homeWins !== $setsToWin && $awayWins !== $setsToWin) {
            throw new InvalidArgumentException("Матч не завершён: ни одна команда не набрала {$setsToWin} сета(-ов).");
        }

        if ($homeWins > $setsToWin || $awayWins > $setsToWin) {
            throw new InvalidArgumentException('Слишком много сетов — матч уже должен был завершиться.');
        }
    }

    protected function validateSet(int $home, int $away, int $targetPoints, int $setNumber): void
    {
        if ($home < 0 || $away < 0) {
            throw new InvalidArgumentException("Сет {$setNumber}: очки не могут быть отрицательными.");
        }

        $winner = max($home, $away);
        $loser  = min($home, $away);

        if ($winner < $targetPoints) {
            throw new InvalidArgumentException("Сет {$setNumber}: победитель должен набрать минимум {$targetPoints} очков (сейчас {$winner}).");
        }

        if ($winner - $loser < 2) {
            throw new InvalidArgumentException("Сет {$setNumber}: разница должна быть минимум 2 очка ({$home}:{$away}).");
        }

        if ($loser >= $targetPoints - 1 && $winner - $loser !== 2) {
            throw new InvalidArgumentException("Сет {$setNumber}: при тай-брейке разница должна быть ровно 2 ({$home}:{$away}).");
        }

        if ($loser < $targetPoints - 1 && $winner !== $targetPoints) {
            throw new InvalidArgumentException("Сет {$setNumber}: победитель должен набрать ровно {$targetPoints} (не {$winner}), если проигравший набрал {$loser}.");
        }
    }
}
