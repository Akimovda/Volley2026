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
     * Ротации пар для 4 игроков [0,1,2,3]: каждый раунд — 1 матч (2 пары):
     * Партия 1: [0+1] vs [2+3]
     * Партия 2: [0+2] vs [1+3]
     * Партия 3: [0+3] vs [1+2]
     */
    private const ROTATIONS_4 = [
        ['home' => [0, 1], 'away' => [2, 3]],
        ['home' => [0, 2], 'away' => [1, 3]],
        ['home' => [0, 3], 'away' => [1, 2]],
    ];

    /**
     * Для 6 игроков [0..5]: 5 раундов партнёрств — 1-факторизация K6, каждая
     * пара партнёров встречается ровно 1 раз за все 5 раундов. Внутри каждого
     * раунда 3 непересекающиеся пары играют друг с другом round-robin —
     * 3 матча на раунд (P0vP1, P0vP2, P1vP2), итого 5×3=15 матчей на группу.
     * Каждый игрок сыграет 10 из 15 матчей (сидит "вне игры" только когда его
     * пара не участвует в конкретном матче своего раунда).
     */
    private const PARTNER_ROUNDS_6 = [
        [[0, 1], [2, 3], [4, 5]],
        [[0, 2], [1, 4], [3, 5]],
        [[0, 3], [1, 5], [2, 4]],
        [[0, 4], [1, 3], [2, 5]],
        [[0, 5], [1, 2], [3, 4]],
    ];

    public const GROUP_SIZES = [4, 6];

    /**
     * Создать раунд «Короля пляжа»:
     * - Делит список игроков (user_id[]) на группы по 4
     * - Для каждой группы создаёт 3 матча с фиксированной ротацией пар
     */
    public function createRound(TournamentStage $stage, array $playerIds): void
    {
        $this->distributeIntoGroups($stage, $playerIds);
    }

    /**
     * Разбивает переданных игроков на группы по configValue('group_size', 4)
     * (случайно или по ELO — см. config.draw_mode стадии) и создаёт для каждой
     * матчи+standings. Нумерация групп продолжается с уже существующих (можно
     * вызывать повторно — например, "Распределить случайно" для тех, кто остался
     * после ручного создания части групп). Игроки, которых не хватило на полную
     * группу, НЕ создают группу — возвращаются в 'leftover', чтобы вызывающий код
     * мог сообщить об этом организатору (раньше остаток молча терялся).
     *
     * @return array{groups: array<TournamentGroup>, leftover: array<int>}
     */
    public function distributeIntoGroups(TournamentStage $stage, array $playerIds): array
    {
        $drawMode = $stage->configValue('draw_mode', 'random');
        $groupSize = (int) $stage->configValue('group_size', 4);

        if ($drawMode === 'seeded') {
            $playerIds = $this->sortByElo($playerIds, $stage->event);
        } else {
            shuffle($playerIds);
        }

        $chunks = array_chunk($playerIds, $groupSize);
        $courts = array_values(array_filter((array) $stage->configValue('courts', [])));

        $createdGroups = [];
        $leftover = [];

        DB::transaction(function () use ($stage, $chunks, $groupSize, $courts, &$createdGroups, &$leftover) {
            if ($stage->isPending()) {
                $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
            }

            foreach ($chunks as $chunk) {
                if (count($chunk) < $groupSize) {
                    $leftover = array_merge($leftover, $chunk);
                    continue;
                }

                $groupIndex = $stage->groups()->count();
                $groupCourts = !empty($courts) ? [$courts[$groupIndex % count($courts)]] : null;

                $createdGroups[] = $this->createOneGroup($stage, array_values($chunk), $groupCourts);
            }
        });

        return ['groups' => $createdGroups, 'leftover' => array_values($leftover)];
    }

    /**
     * Ручное создание ОДНОЙ группы ровно из configValue('group_size', 4) игроков
     * (без авто-распределения остальных). $name — если задан (например, из таблицы
     * ручного распределения, где организатор сам вписал ярлык группы), используется
     * вместо авто "Группа A/B/...".
     */
    public function createManualGroup(TournamentStage $stage, array $playerIds, ?array $courts = null, ?string $name = null): TournamentGroup
    {
        $groupSize = (int) $stage->configValue('group_size', 4);

        if (count($playerIds) !== $groupSize) {
            throw new \InvalidArgumentException("Группа \"Король пляжа\" должна содержать ровно {$groupSize} игроков.");
        }

        return DB::transaction(function () use ($stage, $playerIds, $courts, $name) {
            if ($stage->isPending()) {
                $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
            }

            return $this->createOneGroup($stage, array_values($playerIds), $courts, $name);
        });
    }

    private function createOneGroup(TournamentStage $stage, array $playerIds, ?array $courts = null, ?string $name = null): TournamentGroup
    {
        $index = $stage->groups()->count();

        $group = TournamentGroup::create([
            'stage_id'   => $stage->id,
            'name'       => $name ?: ('Группа ' . chr(65 + $index)),
            'sort_order' => $index + 1,
            'courts'     => $courts,
        ]);

        $this->createGroupMatches($stage, $group, $playerIds);
        $this->initPlayerStandings($stage, $group, $playerIds);

        return $group;
    }

    /**
     * Создать партии для группы (4 или 6 игроков) с ротацией пар.
     * Для 4 — 3 матча (ROTATIONS_4). Для 6 — 15 матчей (5 раундов
     * партнёрств × 3 матча round-robin внутри раунда, см. PARTNER_ROUNDS_6).
     */
    public function createGroupMatches(TournamentStage $stage, TournamentGroup $group, array $playerIds): void
    {
        $matchNo = (int) ($stage->matches()->max('match_number') ?? 0);
        $pairsList = $this->buildRotationSchedule(count($playerIds));

        foreach ($pairsList as $pairs) {
            $matchNo++;
            TournamentMatch::create([
                'stage_id'     => $stage->id,
                'group_id'     => $group->id,
                'round'        => $matchNo,
                'match_number' => $matchNo,
                'status'       => TournamentMatch::STATUS_SCHEDULED,
                'meta'         => [
                    'king_beach'  => true,
                    'rotation'    => $matchNo,
                    'home_players' => array_values(array_map(fn($idx) => (int) $playerIds[$idx], $pairs['home'])),
                    'away_players' => array_values(array_map(fn($idx) => (int) $playerIds[$idx], $pairs['away'])),
                ],
            ]);
        }
    }

    /**
     * Список матчей (['home' => [idx,idx], 'away' => [idx,idx]]) по индексам
     * игрока внутри группы (0-based), для поддерживаемого размера группы.
     */
    private function buildRotationSchedule(int $groupSize): array
    {
        if ($groupSize === 4) {
            return self::ROTATIONS_4;
        }

        if ($groupSize === 6) {
            $schedule = [];
            foreach (self::PARTNER_ROUNDS_6 as $round) {
                [$p0, $p1, $p2] = $round;
                $schedule[] = ['home' => $p0, 'away' => $p1];
                $schedule[] = ['home' => $p0, 'away' => $p2];
                $schedule[] = ['home' => $p1, 'away' => $p2];
            }
            return $schedule;
        }

        throw new \InvalidArgumentException("Неподдерживаемый размер группы King of the Beach: {$groupSize}");
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
     * После завершения группового этапа — распределяет ВСЕХ игроков (не только
     * "проходящих") по новым дивизионам Hard/Medium/Lite по силе (как formDivisions()
     * для командных турниров, но по индивидуальным KingBeachStanding и единственной
     * метрике total_points). Создаёт по одной новой king_beach стадии на дивизион,
     * внутри каждой сразу дробит игроков на группы по 4 через distributeIntoGroups().
     *
     * @return array<string, TournamentStage> имя дивизиона => созданная стадия
     */
    public function formDivisions(TournamentStage $stage, int $advancePerGroup = 2): array
    {
        $groups = $stage->groups;
        $groupsCount = $groups->count();

        if ($groupsCount < 2) {
            throw new \InvalidArgumentException('Нужно минимум 2 группы для распределения по дивизионам.');
        }

        $byRank = [];
        foreach ($groups as $group) {
            $standings = KingBeachStanding::where('group_id', $group->id)->orderBy('rank')->get();
            foreach ($standings as $s) {
                $byRank[$s->rank][] = ['user_id' => (int) $s->user_id, 'points' => (int) $s->total_points];
            }
        }
        foreach ($byRank as &$players) {
            usort($players, fn($a, $b) => $b['points'] <=> $a['points']);
        }
        unset($players);

        $divisionPlayerIds = [];

        if ($groupsCount === 2) {
            $hardIds = [];
            $liteIds = [];
            foreach ($groups as $group) {
                $standings = KingBeachStanding::where('group_id', $group->id)->orderBy('rank')->get()->values();
                foreach ($standings as $i => $s) {
                    if ($i < $advancePerGroup) {
                        $hardIds[] = (int) $s->user_id;
                    } else {
                        $liteIds[] = (int) $s->user_id;
                    }
                }
            }
            $divisionPlayerIds = ['Hard' => $hardIds, 'Lite' => $liteIds];
        } elseif ($groupsCount === 3) {
            $hardIds = array_column($byRank[1] ?? [], 'user_id');
            $mediumIds = [];
            $liteIds = [];

            $seconds = $byRank[2] ?? [];
            if (count($seconds) > 0) {
                $hardIds[] = $seconds[0]['user_id'];
                for ($i = 1; $i < count($seconds); $i++) {
                    $mediumIds[] = $seconds[$i]['user_id'];
                }
            }

            $thirds = $byRank[3] ?? [];
            $thirdToMedium = min(2, count($thirds));
            for ($i = 0; $i < count($thirds); $i++) {
                if ($i < $thirdToMedium) {
                    $mediumIds[] = $thirds[$i]['user_id'];
                } else {
                    $liteIds[] = $thirds[$i]['user_id'];
                }
            }

            foreach ($byRank as $rank => $players) {
                if ($rank <= 3) {
                    continue;
                }
                foreach ($players as $p) {
                    $liteIds[] = $p['user_id'];
                }
            }

            $divisionPlayerIds = ['Hard' => $hardIds, 'Medium' => $mediumIds, 'Lite' => $liteIds];
        } else {
            // 4+ группы: сортируем всех по рангу+очкам и режем на равные куски —
            // каждый кусок становится отдельным дивизионом (Hard, Medium-1, Medium-2, ..., Lite).
            // В отличие от командной версии — НЕ лупим средние дивизионы в один список
            // (там это давало дублирование состава между Medium-N стадиями).
            $allByQuality = [];
            foreach ($byRank as $players) {
                foreach ($players as $p) {
                    $allByQuality[] = $p;
                }
            }

            $perDiv = (int) ceil(count($allByQuality) / $groupsCount);
            $chunks = array_chunk($allByQuality, max(1, $perDiv));

            $names = array_merge(
                ['Hard'],
                array_map(fn($i) => 'Medium-' . $i, range(1, max(0, count($chunks) - 2))),
                ['Lite']
            );

            foreach ($chunks as $i => $chunk) {
                $name = $names[$i] ?? ('Medium-' . $i);
                $divisionPlayerIds[$name] = array_column($chunk, 'user_id');
            }
        }

        $result = [];
        $sortOrderBase = (int) $stage->sort_order;

        DB::transaction(function () use ($stage, $divisionPlayerIds, &$result, $sortOrderBase) {
            $i = 1;
            foreach ($divisionPlayerIds as $name => $playerIds) {
                if (empty($playerIds)) {
                    continue;
                }

                $newStage = TournamentStage::create([
                    'event_id'      => $stage->event_id,
                    'occurrence_id' => $stage->occurrence_id,
                    'type'          => TournamentStage::TYPE_KING_BEACH,
                    'name'          => $name,
                    'sort_order'    => $sortOrderBase + $i,
                    'config'        => $stage->config,
                    'status'        => TournamentStage::STATUS_PENDING,
                ]);

                $this->distributeIntoGroups($newStage, $playerIds);

                $result[$name] = $newStage;
                $i++;
            }

            $stage->update(['status' => TournamentStage::STATUS_COMPLETED]);
        });

        return $result;
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
