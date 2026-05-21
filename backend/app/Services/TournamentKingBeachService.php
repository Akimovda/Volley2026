<?php

namespace App\Services;

use App\Models\KingBeachStanding;
use App\Models\PlayerCareerStats;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentKingBeachService
{
    /**
     * Ротации пар для 4 игроков [0,1,2,3]:
     * Партия 1: [0+1] vs [2+3]
     * Партия 2: [0+2] vs [1+3]
     * Партия 3: [0+3] vs [1+2]
     */
    private const ROTATIONS = [
        1 => ['home' => [0, 1], 'away' => [2, 3]],
        2 => ['home' => [0, 2], 'away' => [1, 3]],
        3 => ['home' => [0, 3], 'away' => [1, 2]],
    ];

    /**
     * Создать раунд «Короля пляжа»:
     * - Делит список игроков (user_id[]) на группы по 4
     * - Для каждой группы создаёт 3 матча с фиксированной ротацией пар
     */
    public function createRound(TournamentStage $stage, array $playerIds): void
    {
        $drawMode = $stage->configValue('draw_mode', 'random');

        if ($drawMode === 'seeded') {
            $playerIds = $this->sortByElo($playerIds, $stage->event);
        } else {
            shuffle($playerIds);
        }

        $chunks = array_chunk($playerIds, 4);

        DB::transaction(function () use ($stage, $chunks) {
            $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);

            foreach ($chunks as $i => $groupPlayers) {
                if (count($groupPlayers) < 4) {
                    continue;
                }

                $group = TournamentGroup::create([
                    'stage_id'   => $stage->id,
                    'name'       => 'Группа ' . chr(65 + $i),
                    'sort_order' => $i + 1,
                ]);

                $this->createGroupMatches($stage, $group, $groupPlayers);
                $this->initPlayerStandings($stage, $group, $groupPlayers);
            }
        });
    }

    /**
     * Создать 3 партии для группы из 4 игроков с ротацией пар.
     */
    public function createGroupMatches(TournamentStage $stage, TournamentGroup $group, array $playerIds): void
    {
        $matchNo = (int) ($stage->matches()->max('match_number') ?? 0);

        foreach (self::ROTATIONS as $rotation => $pairs) {
            $matchNo++;
            TournamentMatch::create([
                'stage_id'     => $stage->id,
                'group_id'     => $group->id,
                'round'        => $rotation,
                'match_number' => $matchNo,
                'status'       => TournamentMatch::STATUS_SCHEDULED,
                'meta'         => [
                    'king_beach'  => true,
                    'rotation'    => $rotation,
                    'home_players' => array_values(array_map(fn($idx) => (int) $playerIds[$idx], $pairs['home'])),
                    'away_players' => array_values(array_map(fn($idx) => (int) $playerIds[$idx], $pairs['away'])),
                ],
            ]);
        }
    }

    protected function initPlayerStandings(TournamentStage $stage, TournamentGroup $group, array $playerIds): void
    {
        foreach ($playerIds as $userId) {
            KingBeachStanding::firstOrCreate(
                ['stage_id' => $stage->id, 'group_id' => $group->id, 'user_id' => (int) $userId],
                ['total_points' => 0, 'rank' => 0],
            );
        }
    }

    /**
     * После ввода счёта партии — пересчитать индивидуальные очки игроков в группе.
     * Каждый игрок победившей пары получает счёт победителя, проигравшей — проигравшего.
     */
    public function recalculateGroupStandings(TournamentGroup $group): void
    {
        $standings = KingBeachStanding::where('group_id', $group->id)->get();

        $playerPoints = $standings->pluck('total_points', 'user_id')
            ->map(fn() => 0)
            ->toArray();

        $matches = TournamentMatch::where('group_id', $group->id)
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->get();

        foreach ($matches as $match) {
            $meta = $match->meta ?? [];
            if (!($meta['king_beach'] ?? false)) {
                continue;
            }

            $homePlayers = $meta['home_players'] ?? [];
            $awayPlayers = $meta['away_players'] ?? [];

            foreach ($homePlayers as $uid) {
                if (array_key_exists($uid, $playerPoints)) {
                    $playerPoints[$uid] += $match->total_points_home;
                }
            }
            foreach ($awayPlayers as $uid) {
                if (array_key_exists($uid, $playerPoints)) {
                    $playerPoints[$uid] += $match->total_points_away;
                }
            }
        }

        // Назначить ранги (сортировка DESC, одинаковые очки = одинаковый ранг)
        arsort($playerPoints);
        $rank = 1;
        $prevPoints = null;
        $sameRankCount = 0;

        foreach ($playerPoints as $userId => $points) {
            if ($prevPoints !== null && $points < $prevPoints) {
                $rank += $sameRankCount;
                $sameRankCount = 1;
            } else {
                $sameRankCount++;
            }
            $prevPoints = $points;

            KingBeachStanding::where('group_id', $group->id)
                ->where('user_id', $userId)
                ->update(['total_points' => $points, 'rank' => $rank]);
        }
    }

    /**
     * Вернуть двух (или N) лучших игроков группы по сумме очков.
     */
    public function getAdvancingPlayers(TournamentGroup $group, int $count = 2): array
    {
        return KingBeachStanding::where('group_id', $group->id)
            ->orderByDesc('total_points')
            ->limit($count)
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }

    /**
     * Создать следующий раунд из прошедших игроков всех групп.
     */
    public function advanceToNextRound(TournamentStage $stage): ?TournamentStage
    {
        $advanceCount = (int) $stage->configValue('advance_count', 2);

        $advancingPlayers = [];
        foreach ($stage->groups as $group) {
            $players = $this->getAdvancingPlayers($group, $advanceCount);
            $advancingPlayers = array_merge($advancingPlayers, $players);
        }

        if (count($advancingPlayers) < 4) {
            return null;
        }

        $roundNumber = ((int) $stage->configValue('round_number', 1)) + 1;
        $isFinal     = count($advancingPlayers) === 4;
        $stageName   = $isFinal
            ? __('tournaments.king_beach_final')
            : (__('tournaments.king_beach_round', ['n' => $roundNumber]));

        $nextStage = TournamentStage::create([
            'event_id'      => $stage->event_id,
            'occurrence_id' => $stage->occurrence_id,
            'type'          => TournamentStage::TYPE_KING_BEACH,
            'name'          => $stageName,
            'sort_order'    => $stage->sort_order + 1,
            'config'        => array_merge($stage->config ?? [], [
                'advance_count' => $advanceCount,
                'round_number'  => $roundNumber,
            ]),
            'status'        => TournamentStage::STATUS_PENDING,
        ]);

        $stage->update(['status' => TournamentStage::STATUS_COMPLETED]);

        $this->createRound($nextStage, $advancingPlayers);

        return $nextStage;
    }

    /**
     * Полный сброс стадии king_beach (для revert).
     */
    public function revertStage(TournamentStage $stage): void
    {
        DB::transaction(function () use ($stage) {
            $groupIds = $stage->groups()->pluck('id');

            KingBeachStanding::whereIn('group_id', $groupIds)->delete();
            $stage->matches()->delete();
            $stage->groups()->delete();
            $stage->update(['status' => TournamentStage::STATUS_PENDING]);
        });
    }

    /**
     * Загрузить standings группы с объектами пользователей.
     *
     * @return Collection<KingBeachStanding>
     */
    public function loadGroupStandings(TournamentGroup $group): Collection
    {
        return KingBeachStanding::where('group_id', $group->id)
            ->with('user')
            ->orderBy('rank')
            ->orderByDesc('total_points')
            ->get();
    }

    /**
     * Для каждой группы стадии вернуть карту user_id → очки по партиям.
     * Формат: [user_id => [rotation => points_scored]]
     */
    public function buildMatchPointsMap(TournamentGroup $group): array
    {
        $matches = TournamentMatch::where('group_id', $group->id)
            ->orderBy('round')
            ->get();

        $map = []; // user_id => [rotation => points]

        foreach ($matches as $match) {
            $meta = $match->meta ?? [];
            if (!($meta['king_beach'] ?? false)) {
                continue;
            }

            $rotation    = (int) ($meta['rotation'] ?? $match->round);
            $homePlayers = $meta['home_players'] ?? [];
            $awayPlayers = $meta['away_players'] ?? [];

            if ($match->status === TournamentMatch::STATUS_COMPLETED) {
                foreach ($homePlayers as $uid) {
                    $map[$uid][$rotation] = $match->total_points_home;
                }
                foreach ($awayPlayers as $uid) {
                    $map[$uid][$rotation] = $match->total_points_away;
                }
            } else {
                foreach (array_merge($homePlayers, $awayPlayers) as $uid) {
                    $map[$uid][$rotation] = null; // ещё не сыграно
                }
            }
        }

        return $map;
    }

    protected function sortByElo(array $userIds, $event): array
    {
        $direction = $event->tournament_settings?->direction ?? 'beach';

        $eloMap = PlayerCareerStats::whereIn('user_id', $userIds)
            ->where('direction', $direction)
            ->pluck('elo_rating', 'user_id');

        usort($userIds, fn($a, $b) => ($eloMap[$b] ?? 1500) <=> ($eloMap[$a] ?? 1500));

        return $userIds;
    }
}
