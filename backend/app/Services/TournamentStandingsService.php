<?php

namespace App\Services;

use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use App\Models\TournamentTiebreaker;
use App\Models\TournamentTiebreakerSet;

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
     * Список аутсайдеров группы (0 побед при played > 0).
     */
    public function getOutsiderTeamIds($standings): array
    {
        return $standings
            ->filter(fn($s) => $s->played > 0 && $s->wins === 0)
            ->pluck('team_id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->toArray();
    }

    /**
     * Чистая статистика (без матчей с аутсайдерами) — публично для UI.
     */
    public function computeCleanStats(TournamentStage $stage, TournamentGroup $group): array
    {
        $matches = TournamentMatch::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->where(fn($q) => $q->whereNull('is_tiebreaker')->orWhere('is_tiebreaker', false))
            ->whereIn('status', [TournamentMatch::STATUS_COMPLETED])
            ->get();

        $standings = TournamentStanding::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->get();

        $outsiders = $this->getOutsiderTeamIds($standings);

        return $this->buildCleanStats($matches, $outsiders);
    }

    /**
     * Ранжирование с учётом разрешённых tiebreaker sets.
     *
     * Порядок:
     * 1. rating_points — desc
     * 2. clean points_scored — desc
     * 3. clean point diff — desc
     * 4. h2h среди tied tuple, если транзитивно
     * 5. resolved_order из TournamentTiebreakerSet, если есть
     * 6. legacy: попарный TournamentTiebreaker resolved (старые данные)
     * 7. Иначе — все команды tied tuple получают одинаковый rank, создаётся pending set
     */
    protected function rankGroup(TournamentStage $stage, TournamentGroup $group, $matches): void
    {
        $standings = TournamentStanding::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->get();

        $h2h = $this->buildHeadToHead($matches);

        $outsiderTeamIds = $this->getOutsiderTeamIds($standings);

        $cleanStats = $this->buildCleanStats($matches, $outsiderTeamIds);

        // Загрузить разрешённые tiebreaker sets
        $resolvedSets = TournamentTiebreakerSet::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->where('status', 'resolved')
            ->whereNotNull('resolved_order')
            ->get();

        $resolvedOrderByTeam = []; // team_id => position в resolved_order (0-based)
        $resolvedSetByTeam   = []; // team_id => set_id (для определения принадлежности)
        foreach ($resolvedSets as $rset) {
            $order = $rset->resolved_order ?: [];
            foreach ($order as $idx => $tid) {
                $resolvedOrderByTeam[(int) $tid] = $idx;
                $resolvedSetByTeam[(int) $tid]   = $rset->id;
            }
        }

        // Legacy: пары
        $legacyResolved = TournamentTiebreaker::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->where('status', 'resolved')
            ->whereNotNull('winner_team_id')
            ->get();

        $legacyWinners = [];
        foreach ($legacyResolved as $tb) {
            $loserId = $tb->team_a_id === $tb->winner_team_id ? $tb->team_b_id : $tb->team_a_id;
            $legacyWinners[$tb->winner_team_id][$loserId] = true;
        }

        // Сгруппировать по тройке (rating, clean_ps, clean_diff)
        $tuples = []; // key => list of standings
        foreach ($standings as $s) {
            $cps = $cleanStats[$s->team_id]['points_scored'] ?? $s->points_scored;
            $cpc = $cleanStats[$s->team_id]['points_conceded'] ?? $s->points_conceded;
            $key = $s->rating_points . ':' . $cps . ':' . ($cps - $cpc);
            $tuples[$key][] = ['standing' => $s, 'cps' => $cps, 'cdiff' => $cps - $cpc];
        }

        // Отсортировать ключи tuples desc по rating, cps, cdiff
        $sortedKeys = array_keys($tuples);
        usort($sortedKeys, function ($a, $b) {
            [$ra, $pa, $da] = explode(':', $a);
            [$rb, $pb, $db] = explode(':', $b);
            if ((int) $ra !== (int) $rb) return (int) $rb <=> (int) $ra;
            if ((int) $pa !== (int) $pb) return (int) $pb <=> (int) $pa;
            return (int) $db <=> (int) $da;
        });

        $rank = 1;
        $finalOrder = []; // team_id => rank
        $ambiguousSets = []; // [[team_ids], ...]

        foreach ($sortedKeys as $key) {
            $bucket = $tuples[$key];
            $teamIds = array_map(fn($r) => (int) $r['standing']->team_id, $bucket);

            if (count($bucket) === 1) {
                $finalOrder[$teamIds[0]] = $rank++;
                continue;
            }

            // 1. Если есть resolved_order для этого набора — применить
            $allResolved = true;
            foreach ($teamIds as $tid) {
                if (!isset($resolvedOrderByTeam[$tid])) {
                    $allResolved = false;
                    break;
                }
            }

            if ($allResolved) {
                $sortedTeams = $teamIds;
                usort($sortedTeams, fn($a, $b) => $resolvedOrderByTeam[$a] <=> $resolvedOrderByTeam[$b]);
                foreach ($sortedTeams as $tid) {
                    $finalOrder[$tid] = $rank++;
                }
                continue;
            }

            // 2. Попробовать h2h транзитивно
            $h2hOrder = $this->resolveByHeadToHead($teamIds, $h2h);
            if ($h2hOrder !== null) {
                foreach ($h2hOrder as $tid) {
                    $finalOrder[$tid] = $rank++;
                }
                continue;
            }

            // 3. Legacy пары (если все пары в наборе разрешены попарно — построить порядок)
            $legacyOrder = $this->resolveByLegacyPairs($teamIds, $legacyWinners);
            if ($legacyOrder !== null) {
                foreach ($legacyOrder as $tid) {
                    $finalOrder[$tid] = $rank++;
                }
                continue;
            }

            // 4. Неоднозначно — все команды получают одинаковый rank, регистрируем pending set
            $startRank = $rank;
            foreach ($teamIds as $tid) {
                $finalOrder[$tid] = $startRank;
            }
            $rank += count($teamIds);
            $ambiguousSets[] = $teamIds;
        }

        foreach ($standings as $standing) {
            $r = $finalOrder[$standing->team_id] ?? null;
            if ($r !== null) {
                $standing->update(['rank' => $r]);
            }
        }

        $this->syncPendingTiebreakerSets($stage, $group, $ambiguousSets);
    }

    /**
     * Если h2h-подграф среди $teamIds полностью транзитивен (уникальные wins-counts) —
     * вернёт упорядоченный массив team_ids от 1-го к последнему. Иначе null.
     */
    protected function resolveByHeadToHead(array $teamIds, array $h2h): ?array
    {
        $winsAmong = [];
        foreach ($teamIds as $tid) {
            $winsAmong[$tid] = 0;
        }
        foreach ($teamIds as $a) {
            foreach ($teamIds as $b) {
                if ($a === $b) continue;
                $winsAmong[$a] += $h2h[$a][$b] ?? 0;
            }
        }

        $values = array_values($winsAmong);
        if (count($values) !== count(array_unique($values))) {
            return null;
        }

        $sorted = $teamIds;
        usort($sorted, fn($a, $b) => $winsAmong[$b] <=> $winsAmong[$a]);
        return $sorted;
    }

    /**
     * Восстановление порядка через legacy попарные тайбрейки.
     * Возвращает упорядоченный массив, если граф полностью разрешим, иначе null.
     */
    protected function resolveByLegacyPairs(array $teamIds, array $legacyWinners): ?array
    {
        $wins = array_fill_keys($teamIds, 0);
        $covered = 0;

        for ($i = 0; $i < count($teamIds); $i++) {
            for ($j = $i + 1; $j < count($teamIds); $j++) {
                $a = $teamIds[$i];
                $b = $teamIds[$j];
                if (isset($legacyWinners[$a][$b])) {
                    $wins[$a]++;
                    $covered++;
                } elseif (isset($legacyWinners[$b][$a])) {
                    $wins[$b]++;
                    $covered++;
                }
            }
        }

        $expectedPairs = count($teamIds) * (count($teamIds) - 1) / 2;
        if ($covered < $expectedPairs) {
            return null;
        }

        $values = array_values($wins);
        if (count($values) !== count(array_unique($values))) {
            return null;
        }

        $sorted = $teamIds;
        usort($sorted, fn($a, $b) => $wins[$b] <=> $wins[$a]);
        return $sorted;
    }

    /**
     * Создаёт/удаляет pending tiebreaker sets для группы.
     */
    public function syncPendingTiebreakerSets(
        TournamentStage $stage,
        TournamentGroup $group,
        array $ambiguousSets
    ): void {
        $existing = TournamentTiebreakerSet::where('stage_id', $stage->id)
            ->where('group_id', $group->id)
            ->where('status', 'pending')
            ->get()
            ->keyBy('team_ids_key');

        $newKeys = [];
        foreach ($ambiguousSets as $teamIds) {
            $key = TournamentTiebreakerSet::buildKey($teamIds);
            $newKeys[] = $key;

            if (!$existing->has($key)) {
                $sortedIds = array_map('intval', $teamIds);
                sort($sortedIds);
                TournamentTiebreakerSet::create([
                    'stage_id'     => $stage->id,
                    'group_id'     => $group->id,
                    'team_ids'     => $sortedIds,
                    'team_ids_key' => $key,
                    'status'       => 'pending',
                ]);
            }
        }

        foreach ($existing as $key => $tbs) {
            if (!in_array($key, $newKeys, true)) {
                $tbs->delete();
            }
        }
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
