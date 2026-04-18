<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\TournamentStage;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentStanding;
use App\Models\TournamentMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentSetupService
{
    /**
     * Создание стадии турнира.
     *
     * @param  array{type: string, name: string, sort_order?: int, config?: array} $data
     */
    public function createStage(Event $event, array $data): TournamentStage
    {
        return TournamentStage::create([
            'event_id'   => $event->id,
            'type'       => $data['type'],
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? 1,
            'config'     => $data['config'] ?? [],
            'status'     => TournamentStage::STATUS_PENDING,
        ]);
    }

    /* ── Группы ── */

    /**
     * @param  string[] $names  ["Группа A", "Группа B", ...]
     */
    public function createGroups(TournamentStage $stage, array $names): Collection
    {
        $groups = collect();
        foreach ($names as $i => $name) {
            $groups->push(TournamentGroup::create([
                'stage_id'   => $stage->id,
                'name'       => $name,
                'sort_order' => $i + 1,
            ]));
        }
        return $groups;
    }

    public function createGroupsAuto(TournamentStage $stage, int $count): Collection
    {
        $names = [];
        for ($i = 0; $i < $count; $i++) {
            $names[] = 'Группа ' . chr(ord('A') + $i);
        }
        return $this->createGroups($stage, $names);
    }

    /* ── Жеребьёвка ── */

    /** Случайная жеребьёвка — равномерно раскидать команды по группам. */
    public function drawRandom(Collection $groups, Collection $teams): void
    {
        $shuffled = $teams->shuffle()->values();
        $groupCount = $groups->count();

        DB::transaction(function () use ($shuffled, $groups, $groupCount) {
            foreach ($shuffled as $i => $team) {
                $group = $groups[$i % $groupCount];
                $seed  = intdiv($i, $groupCount) + 1;

                TournamentGroupTeam::create([
                    'group_id' => $group->id,
                    'team_id'  => $team->id,
                    'seed'     => $seed,
                ]);
            }
        });
    }

    /** Жеребьёвка «змейкой» по посеву. Команды должны быть отсортированы по силе. */
    public function drawSeeded(Collection $groups, Collection $seededTeams): void
    {
        $groupCount = $groups->count();

        DB::transaction(function () use ($seededTeams, $groups, $groupCount) {
            $direction = 1;
            $gi = 0;
            $seed = 1;

            foreach ($seededTeams as $team) {
                TournamentGroupTeam::create([
                    'group_id' => $groups[$gi]->id,
                    'team_id'  => $team->id,
                    'seed'     => $seed,
                ]);

                $gi += $direction;

                if ($gi >= $groupCount) {
                    $gi = $groupCount - 1;
                    $direction = -1;
                    $seed++;
                } elseif ($gi < 0) {
                    $gi = 0;
                    $direction = 1;
                    $seed++;
                }
            }
        });
    }

    /**
     * Ручная расстановка.
     * @param  array<int, array{team_id: int, seed?: int}[]> $assignments  group_id => [{team_id, seed?}]
     */
    public function drawManual(array $assignments): void
    {
        DB::transaction(function () use ($assignments) {
            foreach ($assignments as $groupId => $teams) {
                foreach ($teams as $i => $item) {
                    TournamentGroupTeam::create([
                        'group_id' => $groupId,
                        'team_id'  => $item['team_id'],
                        'seed'     => $item['seed'] ?? ($i + 1),
                    ]);
                }
            }
        });
    }

    /* ── Standings init ── */

    public function initStandings(TournamentStage $stage, TournamentGroup $group): void
    {
        $teamIds = TournamentGroupTeam::where('group_id', $group->id)->pluck('team_id');

        foreach ($teamIds as $teamId) {
            TournamentStanding::firstOrCreate([
                'stage_id' => $stage->id,
                'group_id' => $group->id,
                'team_id'  => $teamId,
            ]);
        }
    }

    public function initAllStandings(TournamentStage $stage): void
    {
        foreach ($stage->groups as $group) {
            $this->initStandings($stage, $group);
        }
    }

    /* ── Генерация Round Robin матчей (circle method) ── */

    public function generateRoundRobinMatches(TournamentStage $stage, TournamentGroup $group): Collection
    {
        $teamIds = TournamentGroupTeam::where('group_id', $group->id)
            ->orderBy('seed')->pluck('team_id')->toArray();

        $n = count($teamIds);
        $matches = collect();
        $matchNum = 1;

        $ids = $teamIds;
        if ($n % 2 !== 0) {
            $ids[] = null; // BYE
            $n++;
        }

        $rounds = $n - 1;
        $half   = $n / 2;

        for ($round = 0; $round < $rounds; $round++) {
            for ($i = 0; $i < $half; $i++) {
                $home = $ids[$i];
                $away = $ids[$n - 1 - $i];

                if ($home === null || $away === null) continue;

                $match = TournamentMatch::create([
                    'stage_id'     => $stage->id,
                    'group_id'     => $group->id,
                    'round'        => $round + 1,
                    'match_number' => $matchNum++,
                    'team_home_id' => $home,
                    'team_away_id' => $away,
                    'status'       => TournamentMatch::STATUS_SCHEDULED,
                ]);

                $matches->push($match);
            }

            // Ротация: фиксируем первый, остальные сдвигаем
            $last = array_pop($ids);
            array_splice($ids, 1, 0, [$last]);
        }

        return $matches;
    }
}
