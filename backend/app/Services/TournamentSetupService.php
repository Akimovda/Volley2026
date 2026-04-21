<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\TournamentStage;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentMatch;
use App\Models\TournamentStanding;
use App\Models\PlayerCareerStats;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TournamentSetupService
{
    /*
    |----------------------------------------------------------------------
    | Stage CRUD
    |----------------------------------------------------------------------
    */

    /**
     * Создать стадию турнира.
     *
     * config JSON:
     * {
     *   "groups_count": 2,
     *   "teams_per_group": 4,
     *   "advance_count": 2,
     *   "rounds_count": 3,
     *   "match_format": "bo3",
     *   "set_points": 25,
     *   "deciding_set_points": 15,
     *   "third_place_match": false,
     *   "courts": ["Корт 1", "Корт 2"],
     *   "draw_mode": "seeded",
     *   "draw_seed_by": "elo",
     *   "draw_source_stage_id": null,
     *   "draw_advance_count": 2,
     *   "draw_source_league_id": null
     * }
     */
    public function createStage(Event $event, array $data): TournamentStage
    {
        $maxOrder = $event->tournamentStages()->max('sort_order') ?? 0;

        return TournamentStage::create([
            'event_id'      => $event->id,
            'occurrence_id' => $data['occurrence_id'] ?? null,
            'type'          => $data['type'],
            'name'          => $data['name'],
            'sort_order'    => $data['sort_order'] ?? ($maxOrder + 1),
            'config'        => $data['config'] ?? [],
            'status'        => TournamentStage::STATUS_PENDING,
        ]);
    }

    /**
     * Создать группы для стадии.
     */
    public function createGroups(TournamentStage $stage, int $count, ?array $names = null): Collection
    {
        $groups = collect();

        for ($i = 0; $i < $count; $i++) {
            $name = $names[$i] ?? 'Группа ' . chr(65 + $i);

            $groups->push(TournamentGroup::create([
                'stage_id'   => $stage->id,
                'name'       => $name,
                'sort_order' => $i + 1,
            ]));
        }

        return $groups;
    }

    /*
    |----------------------------------------------------------------------
    | Жеребьёвка — единый entry point
    |----------------------------------------------------------------------
    */

    /**
     * Провести жеребьёвку для стадии.
     *
     * Режим берётся из config.draw_mode стадии.
     *
     * @param  TournamentStage  $stage
     * @param  Collection|null  $teams  Команды (для seeded/stage_advance/league_carry)
     * @param  array|null  $manualAssignment  [group_id => [team_id, ...]] (для manual)
     */
    public function drawTeams(
        TournamentStage $stage,
        ?Collection $teams = null,
        ?array $manualAssignment = null,
    ): void {
        $groups = $stage->groups;

        if ($groups->isEmpty()) {
            throw new InvalidArgumentException('Стадия не содержит групп. Сначала создайте группы.');
        }

        $mode = $stage->configValue('draw_mode', 'seeded');

        // Manual — отдельная ветка
        if ($mode === 'manual') {
            if (empty($manualAssignment)) {
                throw new InvalidArgumentException('Для ручной жеребьёвки передайте $manualAssignment [group_id => [team_id, ...]].');
            }
            $this->drawManual($manualAssignment);
            return;
        }

        // Собираем команды если не переданы
        $teams = $teams ?? match ($mode) {
            'stage_advance' => $this->collectFromPreviousStage($stage),
            'league_carry'  => $this->collectFromLeague($stage),
            default         => $this->collectEventTeams($stage->event),
        };

        if ($teams->isEmpty()) {
            throw new InvalidArgumentException('Нет команд для жеребьёвки.');
        }

        // Сортируем
        $sorted = match ($mode) {
            'seeded'        => $this->sortByRating($teams, $stage),
            'stage_advance' => $teams,
            'league_carry'  => $teams,
            default         => $teams,
        };

        // Раскладываем по группам snake-методом
        $this->distributeSnake($groups, $sorted);
    }

    /*
    |----------------------------------------------------------------------
    | Режим 1: Посев по рейтингу (Seeded)
    |----------------------------------------------------------------------
    */

    public function sortByRating(Collection $teams, TournamentStage $stage): Collection
    {
        $seedBy    = $stage->configValue('draw_seed_by', 'elo');
        $event     = $stage->event;
        $direction = $event->tournament_settings->direction ?? 'classic';

        return $teams->sortByDesc(function (EventTeam $team) use ($seedBy, $direction) {
            return match ($seedBy) {
                'elo'            => $this->teamAverageElo($team, $direction),
                'match_win_rate' => $this->teamAverageWinRate($team),
                'rating_points'  => $this->teamTotalRatingPoints($team),
                default          => 0,
            };
        })->values();
    }

    protected function teamAverageElo(EventTeam $team, string $direction): float
    {
        $members = $team->members ?? $team->members()->get();
        if ($members->isEmpty()) {
            return 1500;
        }

        $total = 0;
        $count = 0;

        foreach ($members as $member) {
            $userId = $member->user_id ?? $member->id;
            $career = PlayerCareerStats::where('user_id', $userId)
                ->where('direction', $direction)
                ->first();

            $total += $career?->elo_rating ?? 1500;
            $count++;
        }

        return $count > 0 ? $total / $count : 1500;
    }

    protected function teamAverageWinRate(EventTeam $team): float
    {
        $members = $team->members ?? $team->members()->get();
        if ($members->isEmpty()) {
            return 0;
        }

        $total = 0;
        $count = 0;

        foreach ($members as $member) {
            $userId = $member->user_id ?? $member->id;
            $career = PlayerCareerStats::where('user_id', $userId)->first();

            $total += $career?->match_win_rate ?? 0;
            $count++;
        }

        return $count > 0 ? $total / $count : 0;
    }

    protected function teamTotalRatingPoints(EventTeam $team): int
    {
        $standing = TournamentStanding::where('team_id', $team->id)
            ->orderByDesc('updated_at')
            ->first();

        return $standing?->rating_points ?? 0;
    }

    /*
    |----------------------------------------------------------------------
    | Режим 2: По результатам предыдущей стадии (Stage Advance)
    |----------------------------------------------------------------------
    */

    protected function collectFromPreviousStage(TournamentStage $stage): Collection
    {
        $sourceStageId = $stage->configValue('draw_source_stage_id');

        if (!$sourceStageId) {
            $sourceStage = TournamentStage::where('event_id', $stage->event_id)
                ->where('sort_order', '<', $stage->sort_order)
                ->orderByDesc('sort_order')
                ->first();

            if (!$sourceStage) {
                throw new InvalidArgumentException('Не найдена предыдущая стадия для stage_advance.');
            }

            $sourceStageId = $sourceStage->id;
        }

        $advanceCount = (int) $stage->configValue('draw_advance_count', 2);

        $standings = TournamentStanding::where('stage_id', $sourceStageId)
            ->whereNotNull('group_id')
            ->where('rank', '<=', $advanceCount)
            ->orderBy('group_id')
            ->orderBy('rank')
            ->get();

        if ($standings->isEmpty()) {
            throw new InvalidArgumentException('В предыдущей стадии нет standings. Завершите стадию перед продвижением.');
        }

        $teamIds = collect();
        for ($rank = 1; $rank <= $advanceCount; $rank++) {
            $rankStandings = $standings->where('rank', $rank);
            foreach ($rankStandings as $s) {
                $teamIds->push($s->team_id);
            }
        }

        return EventTeam::whereIn('id', $teamIds)
            ->get()
            ->sortBy(fn($t) => $teamIds->search($t->id))
            ->values();
    }

    /*
    |----------------------------------------------------------------------
    | Режим 3: По составу лиги сезона (League Carry)
    |----------------------------------------------------------------------
    */

    protected function collectFromLeague(TournamentStage $stage): Collection
    {
        $leagueId = $stage->configValue('draw_source_league_id');

        if (!$leagueId) {
            throw new InvalidArgumentException('Не указан draw_source_league_id для league_carry.');
        }

        $leagueTeams = DB::table('tournament_league_teams')
            ->where('league_id', $leagueId)
            ->where('status', 'active')
            ->orderBy('reserve_position')
            ->pluck('team_id')
            ->filter();

        if ($leagueTeams->isEmpty()) {
            throw new InvalidArgumentException('В лиге нет активных команд.');
        }

        $teams = EventTeam::whereIn('id', $leagueTeams)->get();

        $seasonId = DB::table('tournament_leagues')
            ->where('id', $leagueId)
            ->value('season_id');

        if ($seasonId) {
            $statsMap = DB::table('tournament_season_stats')
                ->where('season_id', $seasonId)
                ->where('league_id', $leagueId)
                ->pluck('match_win_rate', 'user_id');

            $teams = $teams->sortByDesc(function (EventTeam $team) use ($statsMap) {
                $members = $team->members ?? $team->members()->get();
                if ($members->isEmpty()) {
                    return 0;
                }

                $total = 0;
                $count = 0;
                foreach ($members as $m) {
                    $userId = $m->user_id ?? $m->id;
                    $total += $statsMap[$userId] ?? 0;
                    $count++;
                }

                return $count > 0 ? $total / $count : 0;
            })->values();
        }

        return $teams;
    }

    /*
    |----------------------------------------------------------------------
    | Распределение snake-методом
    |----------------------------------------------------------------------
    */

    public function distributeSnake(Collection $groups, Collection $teams): void
    {
        $groupCount = $groups->count();
        $direction  = 1;
        $groupIdx   = 0;
        $seedInGroup = [];

        foreach ($teams as $team) {
            $group = $groups[$groupIdx];

            if (!isset($seedInGroup[$group->id])) {
                $seedInGroup[$group->id] = 0;
            }
            $seedInGroup[$group->id]++;

            TournamentGroupTeam::create([
                'group_id' => $group->id,
                'team_id'  => $team->id,
                'seed'     => $seedInGroup[$group->id],
            ]);

            $groupIdx += $direction;
            if ($groupIdx >= $groupCount) {
                $groupIdx = $groupCount - 1;
                $direction = -1;
            } elseif ($groupIdx < 0) {
                $groupIdx = 0;
                $direction = 1;
            }
        }
    }

    /*
    |----------------------------------------------------------------------
    | Режим 4: Ручное распределение (Manual)
    |----------------------------------------------------------------------
    */

    public function drawManual(array $assignment): void
    {
        foreach ($assignment as $groupId => $teamIds) {
            foreach (array_values($teamIds) as $seed => $item) {
                // Поддерживаем и plain int, и ['team_id' => int]
                $teamId = is_array($item) ? (int) ($item['team_id'] ?? $item[0] ?? 0) : (int) $item;
                if ($teamId <= 0) continue;

                TournamentGroupTeam::create([
                    'group_id' => (int) $groupId,
                    'team_id'  => $teamId,
                    'seed'     => $seed + 1,
                ]);
            }
        }
    }

    protected function collectEventTeams(Event $event): Collection
    {
        return $event->teams()->get();
    }

    /*
    |----------------------------------------------------------------------
    | Генерация расписания: Round Robin
    |----------------------------------------------------------------------
    */

    public function generateRoundRobinMatches(TournamentStage $stage, TournamentGroup $group): Collection
    {
        $teamIds = $group->groupTeams()->pluck('team_id')->toArray();
        $n       = count($teamIds);

        if ($n < 2) {
            return collect();
        }

        if ($n % 2 !== 0) {
            $teamIds[] = null;
            $n++;
        }

        $rounds  = $n - 1;
        $half    = $n / 2;
        $matches = collect();
        $matchNo = TournamentMatch::where('stage_id', $stage->id)->max('match_number') ?? 0;

        $fixed    = $teamIds[0];
        $rotating = array_slice($teamIds, 1);

        for ($round = 1; $round <= $rounds; $round++) {
            $current = array_merge([$fixed], $rotating);

            for ($i = 0; $i < $half; $i++) {
                $home = $current[$i];
                $away = $current[$n - 1 - $i];

                if ($home === null || $away === null) {
                    continue;
                }

                $matchNo++;
                $matches->push(TournamentMatch::create([
                    'stage_id'     => $stage->id,
                    'group_id'     => $group->id,
                    'round'        => $round,
                    'match_number' => $matchNo,
                    'team_home_id' => $home,
                    'team_away_id' => $away,
                    'status'       => TournamentMatch::STATUS_SCHEDULED,
                ]));
            }

            $last = array_pop($rotating);
            array_unshift($rotating, $last);
        }

        $this->initStandings($stage, $group);

        return $matches;
    }

    /*
    |----------------------------------------------------------------------
    | Генерация сетки: Single Elimination (Олимпийка)
    |----------------------------------------------------------------------
    */

    public function generateSingleElimBracket(
        TournamentStage $stage,
        Collection $teams,
        bool $thirdPlaceMatch = false,
    ): Collection {
        $n = $teams->count();

        $bracketSize = 1;
        while ($bracketSize < $n) {
            $bracketSize *= 2;
        }

        $totalRounds = (int) log($bracketSize, 2);
        $matches     = collect();
        $matchNo     = 0;

        $matchesByRound = [];

        for ($round = $totalRounds; $round >= 1; $round--) {
            $matchesInRound = $bracketSize / pow(2, $round);
            $matchesByRound[$round] = [];

            for ($pos = 0; $pos < $matchesInRound; $pos++) {
                $matchNo++;
                $match = TournamentMatch::create([
                    'stage_id'     => $stage->id,
                    'round'        => $round,
                    'match_number' => $matchNo,
                    'status'       => TournamentMatch::STATUS_SCHEDULED,
                ]);

                $matchesByRound[$round][] = $match;
                $matches->push($match);
            }
        }

        for ($round = 1; $round < $totalRounds; $round++) {
            foreach ($matchesByRound[$round] as $i => $match) {
                $nextIdx  = intdiv($i, 2);
                $nextSlot = ($i % 2 === 0) ? 'home' : 'away';

                $match->update([
                    'next_match_id'   => $matchesByRound[$round + 1][$nextIdx]->id,
                    'next_match_slot' => $nextSlot,
                ]);
            }
        }

        if ($thirdPlaceMatch && $totalRounds >= 2) {
            $matchNo++;
            $thirdPlace = TournamentMatch::create([
                'stage_id'         => $stage->id,
                'round'            => $totalRounds,
                'match_number'     => $matchNo,
                'status'           => TournamentMatch::STATUS_SCHEDULED,
                'bracket_position' => 'third_place',
            ]);

            foreach ($matchesByRound[$totalRounds - 1] as $i => $semi) {
                $slot = ($i === 0) ? 'home' : 'away';
                $semi->update([
                    'loser_next_match_id'   => $thirdPlace->id,
                    'loser_next_match_slot' => $slot,
                ]);
            }

            $matches->push($thirdPlace);
        }

        $seeded = $this->seedTeamsForBracket($teams, $bracketSize);

        foreach ($matchesByRound[1] as $i => $match) {
            $homeIdx = $i * 2;
            $awayIdx = $i * 2 + 1;

            $match->update([
                'team_home_id' => $seeded[$homeIdx] ?? null,
                'team_away_id' => $seeded[$awayIdx] ?? null,
            ]);

            if ($match->team_home_id && !$match->team_away_id) {
                $this->autoAdvanceBye($match, $match->team_home_id);
            } elseif (!$match->team_home_id && $match->team_away_id) {
                $this->autoAdvanceBye($match, $match->team_away_id);
            }
        }

        return $matches;
    }

    protected function seedTeamsForBracket(Collection $teams, int $bracketSize): array
    {
        $ids    = $teams->pluck('id')->toArray();
        $result = array_fill(0, $bracketSize, null);

        $positions = $this->generateSeedPositions($bracketSize);

        foreach ($ids as $seed => $teamId) {
            $result[$positions[$seed]] = $teamId;
        }

        return $result;
    }

    protected function generateSeedPositions(int $size): array
    {
        if ($size === 1) {
            return [0];
        }

        $prev   = $this->generateSeedPositions($size / 2);
        $result = [];

        foreach ($prev as $pos) {
            $result[] = $pos * 2;
            $result[] = $size - 1 - $pos * 2;
        }

        return $result;
    }

    protected function autoAdvanceBye(TournamentMatch $match, int $winnerId): void
    {
        $match->update([
            'winner_team_id' => $winnerId,
            'status'         => TournamentMatch::STATUS_COMPLETED,
            'sets_home'      => 0,
            'sets_away'      => 0,
        ]);

        if ($match->next_match_id) {
            $slot = $match->next_match_slot;
            TournamentMatch::where('id', $match->next_match_id)->update([
                "team_{$slot}_id" => $winnerId,
            ]);
        }
    }

    /*
    |----------------------------------------------------------------------
    | Standings / Revert
    |----------------------------------------------------------------------
    */

    public function initStandings(TournamentStage $stage, TournamentGroup $group): void
    {
        $teamIds = $group->groupTeams()->pluck('team_id');

        foreach ($teamIds as $teamId) {
            TournamentStanding::firstOrCreate([
                'stage_id' => $stage->id,
                'group_id' => $group->id,
                'team_id'  => $teamId,
            ]);
        }
    }


    /*
    |----------------------------------------------------------------------
    | Legacy API (вызывается из TournamentController)
    |----------------------------------------------------------------------
    */

    /**
     * Случайная жеребьёвка (legacy API).
     */
    public function drawRandom(Collection $groups, Collection $teams): void
    {
        $shuffled = $teams->shuffle()->values();
        $this->distributeSnake($groups, $shuffled);
    }

    /**
     * Посев по рейтингу (legacy API).
     */
    public function drawSeeded(Collection $groups, Collection $teams): void
    {
        // Без стадии — просто сортируем по id (fallback)
        $this->distributeSnake($groups, $teams);
    }

    /**
     * Создать группы автоматически (legacy API).
     */
    public function createGroupsAuto(TournamentStage $stage, int $count): Collection
    {
        return $this->createGroups($stage, $count);
    }

    public function revertStage(TournamentStage $stage): void
    {
        DB::transaction(function () use ($stage) {
            $stage->matches()->delete();
            $stage->standings()->delete();

            foreach ($stage->groups as $group) {
                $group->groupTeams()->delete();
            }

            $stage->update(['status' => TournamentStage::STATUS_PENDING]);
        });
    }
}
