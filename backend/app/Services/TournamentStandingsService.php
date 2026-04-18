<?php

namespace App\Services;

use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentStandingsService
{
    /** Обновление standings после завершённого матча. */
    public function updateAfterMatch(TournamentMatch $match): void
    {
        if (! $match->isCompleted() || ! $match->winner_team_id) return;

        $stage   = $match->stage;
        $groupId = $match->group_id;

        // Home team
        $this->applyMatchResult(
            $stage->id, $groupId, $match->team_home_id,
            $match->winner_team_id === $match->team_home_id,
            $match->sets_home, $match->sets_away,
            $match->total_points_home, $match->total_points_away,
            $stage->type,
        );

        // Away team
        $this->applyMatchResult(
            $stage->id, $groupId, $match->team_away_id,
            $match->winner_team_id === $match->team_away_id,
            $match->sets_away, $match->sets_home,
            $match->total_points_away, $match->total_points_home,
            $stage->type,
        );

        if ($groupId) {
            $this->recalcRanks($stage->id, $groupId);
        }
    }

    private function applyMatchResult(
        int $stageId, ?int $groupId, int $teamId,
        bool $isWinner, int $setsWon, int $setsLost,
        int $pointsScored, int $pointsConceded, string $stageType,
    ): void {
        $s = TournamentStanding::firstOrCreate(
            ['stage_id' => $stageId, 'group_id' => $groupId, 'team_id' => $teamId]
        );

        $s->played++;
        $s->sets_won        += $setsWon;
        $s->sets_lost       += $setsLost;
        $s->points_scored   += $pointsScored;
        $s->points_conceded += $pointsConceded;

        $rp = $this->calcRatingPoints($stageType, $isWinner, $setsWon, $setsLost);
        $s->rating_points += $rp;

        if ($isWinner) { $s->wins++; } else { $s->losses++; }

        $s->save();
    }

    /**
     * Швейцарская: 3/2/1/0. Остальные: 1/0.
     */
    private function calcRatingPoints(string $stageType, bool $isWinner, int $setsWon, int $setsLost): int
    {
        if ($stageType === TournamentStage::TYPE_SWISS) {
            if ($isWinner) {
                return $setsLost === 0 ? 3 : 2; // 2:0→3, 2:1→2
            }
            return $setsWon > 0 ? 1 : 0;         // 1:2→1, 0:2→0
        }
        return $isWinner ? 1 : 0;
    }

    /** Откат влияния матча из standings. */
    public function revertMatch(TournamentMatch $match): void
    {
        if (! $match->isCompleted()) return;

        $stage   = $match->stage;
        $groupId = $match->group_id;

        $sides = [
            ['team' => $match->team_home_id, 'won' => $match->sets_home, 'lost' => $match->sets_away,
             'scored' => $match->total_points_home, 'conceded' => $match->total_points_away,
             'isWinner' => $match->winner_team_id === $match->team_home_id],
            ['team' => $match->team_away_id, 'won' => $match->sets_away, 'lost' => $match->sets_home,
             'scored' => $match->total_points_away, 'conceded' => $match->total_points_home,
             'isWinner' => $match->winner_team_id === $match->team_away_id],
        ];

        foreach ($sides as $side) {
            $s = TournamentStanding::where('stage_id', $stage->id)
                ->where('group_id', $groupId)->where('team_id', $side['team'])->first();
            if (! $s) continue;

            $s->played          = max(0, $s->played - 1);
            $s->sets_won        = max(0, $s->sets_won - $side['won']);
            $s->sets_lost       = max(0, $s->sets_lost - $side['lost']);
            $s->points_scored   = max(0, $s->points_scored - $side['scored']);
            $s->points_conceded = max(0, $s->points_conceded - $side['conceded']);

            $rp = $this->calcRatingPoints($stage->type, $side['isWinner'], $side['won'], $side['lost']);
            $s->rating_points = max(0, $s->rating_points - $rp);

            if ($side['isWinner']) { $s->wins = max(0, $s->wins - 1); }
            else { $s->losses = max(0, $s->losses - 1); }

            $s->save();
        }

        if ($groupId) {
            $this->recalcRanks($match->stage_id, $groupId);
        }
    }

    /**
     * Пересчёт рангов: rating_points → matchWinRate → setDiff → pointDiff.
     */
    public function recalcRanks(int $stageId, int $groupId): void
    {
        $standings = TournamentStanding::where('stage_id', $stageId)
            ->where('group_id', $groupId)->get();

        // Загружаем h2h матчи для группы
        $h2h = $this->buildH2hMap($stageId, $groupId);

        $sorted = $standings->sortBy(function ($s) use ($h2h, $standings) {
            // Сортируем по: rating_points DESC, h2h, matchWinRate DESC, setDiff DESC, pointDiff DESC
            // Возвращаем массив для multi-sort (негативные = DESC)
            return [
                -$s->rating_points,
                -$s->matchWinRate(),
                -$s->setDiff(),
                -$s->pointDiff(),
            ];
        })->values();

        // Дополнительно: при равенстве rating_points проверяем h2h
        $sorted = $this->applyH2hTiebreak($sorted, $h2h);

        foreach ($sorted as $i => $s) {
            $s->update(['rank' => $i + 1]);
        }
    }

    /**
     * Построить карту head-to-head результатов в группе.
     * @return array<string, int>  "teamA-teamB" => +1 (A выиграл), -1 (B выиграл), 0 (ничья/не играли)
     */
    private function buildH2hMap(int $stageId, int $groupId): array
    {
        $matches = TournamentMatch::where('stage_id', $stageId)
            ->where('group_id', $groupId)
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->get();

        $map = [];
        foreach ($matches as $m) {
            if (!$m->winner_team_id) continue;
            $key = min($m->team_home_id, $m->team_away_id) . '-' . max($m->team_home_id, $m->team_away_id);
            $map[$key] = $m->winner_team_id === min($m->team_home_id, $m->team_away_id) ? 1 : -1;
        }

        return $map;
    }

    /**
     * Применить head-to-head тай-брейк для команд с одинаковыми rating_points.
     */
    private function applyH2hTiebreak(\Illuminate\Support\Collection $sorted, array $h2h): \Illuminate\Support\Collection
    {
        $result = $sorted->toArray();
        $n = count($result);

        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $result[$i];
                $b = $result[$j];

                // Только если rating_points и matchWinRate равны
                if ($a->rating_points !== $b->rating_points) continue;
                if (abs($a->matchWinRate() - $b->matchWinRate()) > 0.01) continue;

                // Проверяем h2h
                $key = min($a->team_id, $b->team_id) . '-' . max($a->team_id, $b->team_id);
                $h2hResult = $h2h[$key] ?? 0;

                // Если B выиграл h2h — меняем местами
                $bWon = ($h2hResult === 1 && $b->team_id === min($a->team_id, $b->team_id))
                     || ($h2hResult === -1 && $b->team_id === max($a->team_id, $b->team_id));

                if ($bWon) {
                    $result[$i] = $b;
                    $result[$j] = $a;
                }
            }
        }

        return collect($result);
    }

    /** Полный пересчёт standings стадии с нуля. */
    public function rebuildStandings(TournamentStage $stage): void
    {
        DB::transaction(function () use ($stage) {
            TournamentStanding::where('stage_id', $stage->id)->update([
                'played' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0,
                'sets_won' => 0, 'sets_lost' => 0,
                'points_scored' => 0, 'points_conceded' => 0,
                'rating_points' => 0, 'rank' => 0,
            ]);

            $matches = TournamentMatch::where('stage_id', $stage->id)
                ->where('status', TournamentMatch::STATUS_COMPLETED)->get();

            foreach ($matches as $match) {
                $this->updateAfterMatch($match);
            }
        });
    }

    /** Отсортированная таблица группы. */
    public function getGroupTable(int $stageId, int $groupId): Collection
    {
        return TournamentStanding::where('stage_id', $stageId)
            ->where('group_id', $groupId)
            ->with('team')->orderBy('rank')->get();
    }

    /** Команды, проходящие в плей-офф (top N). */
    public function getAdvancingTeams(int $stageId, int $groupId, int $advanceCount): Collection
    {
        return TournamentStanding::where('stage_id', $stageId)
            ->where('group_id', $groupId)
            ->where('rank', '<=', $advanceCount)
            ->orderBy('rank')->with('team')->get();
    }
}
