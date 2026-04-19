<?php

namespace App\Services;

use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;

class TournamentStandingsService
{
    /**
     * Полный пересчёт standings группы по всем завершённым матчам.
     */
    public function recalculateGroup(TournamentStage $stage, TournamentGroup $group): void
    {
        $standings = TournamentStanding::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->get()
            ->keyBy('team_id');

        // Сбрасываем счётчики
        foreach ($standings as $standing) {
            $standing->fill([
                'played'          => 0,
                'wins'            => 0,
                'losses'          => 0,
                'draws'           => 0,
                'sets_won'        => 0,
                'sets_lost'       => 0,
                'points_scored'   => 0,
                'points_conceded' => 0,
                'rating_points'   => 0,
            ]);
        }

        // Считаем по матчам
        $matches = TournamentMatch::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->whereIn('status', [TournamentMatch::STATUS_COMPLETED, TournamentMatch::STATUS_FORFEIT])
            ->get();

        $isSwiss = $stage->type === TournamentStage::TYPE_SWISS;

        foreach ($matches as $match) {
            $homeId = $match->team_home_id;
            $awayId = $match->team_away_id;

            $home = $standings->get($homeId);
            $away = $standings->get($awayId);

            if (!$home || !$away) {
                continue;
            }

            $home->played++;
            $away->played++;

            if ($match->status === TournamentMatch::STATUS_FORFEIT) {
                if ($match->winner_team_id === $homeId) {
                    $home->wins++;
                    $away->losses++;
                    $home->rating_points += $isSwiss ? 3 : 1;
                } else {
                    $away->wins++;
                    $home->losses++;
                    $away->rating_points += $isSwiss ? 3 : 1;
                }
                continue;
            }

            // Сеты
            $home->sets_won  += $match->sets_home;
            $home->sets_lost += $match->sets_away;
            $away->sets_won  += $match->sets_away;
            $away->sets_lost += $match->sets_home;

            // Очки
            $home->points_scored   += $match->total_points_home;
            $home->points_conceded += $match->total_points_away;
            $away->points_scored   += $match->total_points_away;
            $away->points_conceded += $match->total_points_home;

            // Победы / поражения
            if ($match->winner_team_id === $homeId) {
                $home->wins++;
                $away->losses++;
            } else {
                $away->wins++;
                $home->losses++;
            }

            // Rating points
            if ($isSwiss) {
                $home->rating_points += $this->swissPoints($match->sets_home, $match->sets_away);
                $away->rating_points += $this->swissPoints($match->sets_away, $match->sets_home);
            } else {
                $home->rating_points = $home->wins;
                $away->rating_points = $away->wins;
            }
        }

        // Сохраняем и ранжируем
        foreach ($standings as $standing) {
            $standing->save();
        }

        $this->rankGroup($stage, $group, $matches);
    }

    /**
     * Очки швейцарской системы.
     */
    protected function swissPoints(int $setsWon, int $setsLost): int
    {
        if ($setsWon > $setsLost) {
            return $setsLost === 0 ? 3 : 2;
        }
        return $setsWon > 0 ? 1 : 0;
    }

    /**
     * Ранжирование с тай-брейками:
     * rating_points → head-to-head → set diff → point diff
     */
    protected function rankGroup(TournamentStage $stage, TournamentGroup $group, $matches): void
    {
        $standings = TournamentStanding::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->get();

        $h2h = $this->buildHeadToHead($matches);

        $sorted = $standings->sort(function ($a, $b) use ($h2h) {
            // 1. Rating points (desc)
            if ($a->rating_points !== $b->rating_points) {
                return $b->rating_points <=> $a->rating_points;
            }

            // 2. Head-to-head
            $h2hResult = $this->headToHeadCompare($a->team_id, $b->team_id, $h2h);
            if ($h2hResult !== 0) {
                return $h2hResult;
            }

            // 3. Set diff (desc)
            $aSetDiff = $a->sets_won - $a->sets_lost;
            $bSetDiff = $b->sets_won - $b->sets_lost;
            if ($aSetDiff !== $bSetDiff) {
                return $bSetDiff <=> $aSetDiff;
            }

            // 4. Point diff (desc)
            $aPointDiff = $a->points_scored - $a->points_conceded;
            $bPointDiff = $b->points_scored - $b->points_conceded;
            return $bPointDiff <=> $aPointDiff;
        });

        $rank = 1;
        foreach ($sorted->values() as $standing) {
            $standing->update(['rank' => $rank++]);
        }
    }

    protected function buildHeadToHead($matches): array
    {
        $h2h = [];

        foreach ($matches as $match) {
            if (!$match->winner_team_id) {
                continue;
            }

            $h2h[$match->winner_team_id][$match->loserId()] =
                ($h2h[$match->winner_team_id][$match->loserId()] ?? 0) + 1;
        }

        return $h2h;
    }

    protected function headToHeadCompare(int $aId, int $bId, array $h2h): int
    {
        $aWins = $h2h[$aId][$bId] ?? 0;
        $bWins = $h2h[$bId][$aId] ?? 0;

        if ($aWins > $bWins) return -1;
        if ($bWins > $aWins) return 1;
        return 0;
    }

    /**
     * Полный пересчёт standings всей стадии.
     */
    public function recalculateStage(TournamentStage $stage): void
    {
        $groups = $stage->groups;

        if ($groups->isEmpty()) {
            return;
        }

        foreach ($groups as $group) {
            $this->recalculateGroup($stage, $group);
        }
    }
}
