<?php

namespace App\Services;

use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use App\Models\TournamentTiebreaker;

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

        // Тайбрейк-матчи не учитываются в таблице
        $matches = TournamentMatch::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->where(fn($q) => $q->whereNull('is_tiebreaker')->orWhere('is_tiebreaker', false))
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

            $home->sets_won  += $match->sets_home;
            $home->sets_lost += $match->sets_away;
            $away->sets_won  += $match->sets_away;
            $away->sets_lost += $match->sets_home;

            $home->points_scored   += $match->total_points_home;
            $home->points_conceded += $match->total_points_away;
            $away->points_scored   += $match->total_points_away;
            $away->points_conceded += $match->total_points_home;

            if ($match->winner_team_id === $homeId) {
                $home->wins++;
                $away->losses++;
            } else {
                $away->wins++;
                $home->losses++;
            }

            $matchFormat = $stage->configValue('match_format', 'bo3');
            if ($matchFormat === 'bo1') {
                if ($match->winner_team_id === $homeId) {
                    $home->rating_points += 1;
                } else {
                    $away->rating_points += 1;
                }
            } else {
                $home->rating_points += $this->swissPoints($match->sets_home, $match->sets_away);
                $away->rating_points += $this->swissPoints($match->sets_away, $match->sets_home);
            }
        }

        foreach ($standings as $standing) {
            $standing->save();
        }

        $this->rankGroup($stage, $group, $matches);
    }

    protected function swissPoints(int $setsWon, int $setsLost): int
    {
        if ($setsWon > $setsLost) {
            return $setsLost === 0 ? 3 : 2;
        }
        return $setsWon > 0 ? 1 : 0;
    }

    /**
     * Ранжирование:
     * 1. rating_points (победы) — desc
     * 2. Clean points_scored (без матчей против аутсайдеров) — desc
     * 3. Clean point diff — desc
     * 4. Личная встреча
     * 5. Жеребьёвка (resolved tiebreaker)
     *
     * Аутсайдер = команда с 0 побед при played > 0.
     */
    protected function rankGroup(TournamentStage $stage, TournamentGroup $group, $matches): void
    {
        $standings = TournamentStanding::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->get();

        $h2h = $this->buildHeadToHead($matches);

        $outsiderTeamIds = $standings
            ->filter(fn($s) => $s->played > 0 && $s->wins === 0)
            ->pluck('team_id')
            ->toArray();

        $cleanStats = $this->buildCleanStats($matches, $outsiderTeamIds);

        $resolvedTiebreakers = TournamentTiebreaker::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->where('status', 'resolved')
            ->whereNotNull('winner_team_id')
            ->get();

        $tiebreakerWinners = [];
        foreach ($resolvedTiebreakers as $tb) {
            $loserId = $tb->team_a_id === $tb->winner_team_id ? $tb->team_b_id : $tb->team_a_id;
            $tiebreakerWinners[$tb->winner_team_id][$loserId] = true;
        }

        $sorted = $standings->sort(function ($a, $b) use ($h2h, $cleanStats, $tiebreakerWinners) {
            if ($a->rating_points !== $b->rating_points) {
                return $b->rating_points <=> $a->rating_points;
            }

            $aScored = $cleanStats[$a->team_id]['points_scored'] ?? $a->points_scored;
            $bScored = $cleanStats[$b->team_id]['points_scored'] ?? $b->points_scored;
            if ($aScored !== $bScored) {
                return $bScored <=> $aScored;
            }

            $aPointDiff = ($cleanStats[$a->team_id]['points_scored'] ?? $a->points_scored)
                        - ($cleanStats[$a->team_id]['points_conceded'] ?? $a->points_conceded);
            $bPointDiff = ($cleanStats[$b->team_id]['points_scored'] ?? $b->points_scored)
                        - ($cleanStats[$b->team_id]['points_conceded'] ?? $b->points_conceded);
            if ($aPointDiff !== $bPointDiff) {
                return $bPointDiff <=> $aPointDiff;
            }

            $h2hResult = $this->headToHeadCompare($a->team_id, $b->team_id, $h2h);
            if ($h2hResult !== 0) {
                return $h2hResult;
            }

            // 5. Жеребьёвка
            if (isset($tiebreakerWinners[$a->team_id][$b->team_id])) {
                return -1;
            }
            if (isset($tiebreakerWinners[$b->team_id][$a->team_id])) {
                return 1;
            }

            return 0;
        });

        $rank = 1;
        foreach ($sorted->values() as $standing) {
            $standing->update(['rank' => $rank++]);
        }

        $this->syncPendingTiebreakers($stage, $group, $standings, $cleanStats, $h2h, $tiebreakerWinners);
    }

    /**
     * Создаёт pending tiebreaker для пар в ничью.
     * Удаляет pending tiebreaker для пар, которые больше не в ничью.
     */
    public function syncPendingTiebreakers(
        TournamentStage $stage,
        TournamentGroup $group,
        $standings,
        array $cleanStats,
        array $h2h,
        array $tiebreakerWinners
    ): void {
        $tiedPairs = $this->detectTiedPairs($standings, $cleanStats, $h2h, $tiebreakerWinners);

        $existing = TournamentTiebreaker::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->where('status', 'pending')
            ->get()
            ->keyBy(fn($tb) => $this->pairKey($tb->team_a_id, $tb->team_b_id));

        $tiedKeys = [];
        foreach ($tiedPairs as [$aId, $bId]) {
            $key = $this->pairKey($aId, $bId);
            $tiedKeys[] = $key;

            if (!$existing->has($key)) {
                TournamentTiebreaker::create([
                    'stage_id'  => $stage->id,
                    'group_id'  => $group->id,
                    'team_a_id' => $aId,
                    'team_b_id' => $bId,
                    'status'    => 'pending',
                ]);
            }
        }

        foreach ($existing as $key => $tb) {
            if (!in_array($key, $tiedKeys)) {
                $tb->delete();
            }
        }
    }

    /**
     * Возвращает все пары команд, у которых все 4 критерия равны (нужна жеребьёвка).
     */
    public function detectTiedPairs(
        $standings,
        array $cleanStats,
        array $h2h,
        array $tiebreakerWinners
    ): array {
        $pairs = [];
        $arr   = $standings->values()->all();

        for ($i = 0; $i < count($arr); $i++) {
            for ($j = $i + 1; $j < count($arr); $j++) {
                $a = $arr[$i];
                $b = $arr[$j];

                if ($a->rating_points !== $b->rating_points) continue;

                $aScored = $cleanStats[$a->team_id]['points_scored'] ?? $a->points_scored;
                $bScored = $cleanStats[$b->team_id]['points_scored'] ?? $b->points_scored;
                if ($aScored !== $bScored) continue;

                $aDiff = ($cleanStats[$a->team_id]['points_scored'] ?? $a->points_scored)
                       - ($cleanStats[$a->team_id]['points_conceded'] ?? $a->points_conceded);
                $bDiff = ($cleanStats[$b->team_id]['points_scored'] ?? $b->points_scored)
                       - ($cleanStats[$b->team_id]['points_conceded'] ?? $b->points_conceded);
                if ($aDiff !== $bDiff) continue;

                if ($this->headToHeadCompare($a->team_id, $b->team_id, $h2h) !== 0) continue;

                if (isset($tiebreakerWinners[$a->team_id][$b->team_id])) continue;
                if (isset($tiebreakerWinners[$b->team_id][$a->team_id])) continue;

                $pairs[] = [$a->team_id, $b->team_id];
            }
        }

        return $pairs;
    }

    public function pairKey(int $aId, int $bId): string
    {
        return min($aId, $bId) . '-' . max($aId, $bId);
    }

    protected function buildCleanStats($matches, array $outsiderTeamIds): array
    {
        $stats = [];

        foreach ($matches as $match) {
            if ($match->status !== TournamentMatch::STATUS_COMPLETED) continue;

            $homeId = $match->team_home_id;
            $awayId = $match->team_away_id;

            if (in_array($homeId, $outsiderTeamIds) || in_array($awayId, $outsiderTeamIds)) {
                continue;
            }

            foreach ([$homeId, $awayId] as $tid) {
                if (!isset($stats[$tid])) {
                    $stats[$tid] = ['sets_won' => 0, 'sets_lost' => 0, 'points_scored' => 0, 'points_conceded' => 0];
                }
            }

            $stats[$homeId]['sets_won']        += $match->sets_home;
            $stats[$homeId]['sets_lost']       += $match->sets_away;
            $stats[$homeId]['points_scored']   += $match->total_points_home;
            $stats[$homeId]['points_conceded'] += $match->total_points_away;

            $stats[$awayId]['sets_won']        += $match->sets_away;
            $stats[$awayId]['sets_lost']       += $match->sets_home;
            $stats[$awayId]['points_scored']   += $match->total_points_away;
            $stats[$awayId]['points_conceded'] += $match->total_points_home;
        }

        return $stats;
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
     * Получить топ-N команд из группы по рангу (для продвижения в плей-офф).
     */
    public function getAdvancingTeams(int $stageId, int $groupId, int $count): \Illuminate\Support\Collection
    {
        return \App\Models\TournamentStanding::where('stage_id', $stageId)
            ->where('group_id', $groupId)
            ->where('rank', '>', 0)
            ->orderBy('rank')
            ->limit($count)
            ->get();
    }

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
