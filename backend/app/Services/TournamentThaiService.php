<?php

namespace App\Services;

use App\Models\TournamentStage;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentMatch;
use App\Models\TournamentStanding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentThaiService
{
    /**
     * Инициализация тайского формата.
     *
     * 2 параллельные игры на 1 корте → группы → группы (золото/серебро).
     * Фаза 1: Round Robin в мини-группах.
     * Фаза 2: Лучшие → Gold Group, остальные → Silver Division.
     *
     * @param  int[] $teamIds
     * @param  int   $groupsCount  Кол-во начальных групп
     */
    public function initialize(
        TournamentStage $stage,
        array $teamIds,
        int $groupsCount = 2,
    ): void {
        // Создаём начальные группы
        $groups = collect();
        for ($i = 0; $i < $groupsCount; $i++) {
            $groups->push(TournamentGroup::create([
                'stage_id'   => $stage->id,
                'name'       => 'Группа ' . chr(ord('A') + $i),
                'sort_order' => $i + 1,
            ]));
        }

        // Распределяем команды по группам
        $shuffled = collect($teamIds)->shuffle()->values();
        foreach ($shuffled as $i => $teamId) {
            $group = $groups[$i % $groupsCount];
            $seed = intdiv($i, $groupsCount) + 1;

            TournamentGroupTeam::create([
                'group_id' => $group->id,
                'team_id'  => $teamId,
                'seed'     => $seed,
            ]);

            TournamentStanding::firstOrCreate([
                'stage_id' => $stage->id,
                'group_id' => $group->id,
                'team_id'  => $teamId,
            ]);
        }

        // Генерируем RR матчи для каждой группы
        $setupService = app(TournamentSetupService::class);
        foreach ($groups as $group) {
            $setupService->generateRoundRobinMatches($stage, $group);
        }

        $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
    }

    /**
     * Создать группы после групповой фазы.
     *
     * @param  int $advanceToGold  Сколько лучших из каждой группы → золотую группу
     */
    public function createDivisions(
        TournamentStage $groupStage,
        TournamentStage $goldStage,
        TournamentStage $silverStage,
        int $advanceToGold = 2,
    ): void {
        $standingsService = app(TournamentStandingsService::class);
        $setupService = app(TournamentSetupService::class);

        $goldTeams = [];
        $silverTeams = [];

        foreach ($groupStage->groups as $group) {
            $standings = $standingsService->getGroupTable($groupStage->id, $group->id);

            foreach ($standings as $s) {
                if ($s->rank <= $advanceToGold) {
                    $goldTeams[] = $s->team_id;
                } else {
                    $silverTeams[] = $s->team_id;
                }
            }
        }

        // Золотая группа
        if (count($goldTeams) >= 2) {
            $goldGroup = TournamentGroup::create([
                'stage_id'   => $goldStage->id,
                'name'       => 'Золотая группа',
                'sort_order' => 1,
            ]);
            foreach ($goldTeams as $i => $tid) {
                TournamentGroupTeam::create(['group_id' => $goldGroup->id, 'team_id' => $tid, 'seed' => $i + 1]);
                TournamentStanding::firstOrCreate(['stage_id' => $goldStage->id, 'group_id' => $goldGroup->id, 'team_id' => $tid]);
            }
            $setupService->generateRoundRobinMatches($goldStage, $goldGroup);
            $goldStage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
        }

        // Серебряная группа
        if (count($silverTeams) >= 2) {
            $silverGroup = TournamentGroup::create([
                'stage_id'   => $silverStage->id,
                'name'       => 'Серебряная группа',
                'sort_order' => 1,
            ]);
            foreach ($silverTeams as $i => $tid) {
                TournamentGroupTeam::create(['group_id' => $silverGroup->id, 'team_id' => $tid, 'seed' => $i + 1]);
                TournamentStanding::firstOrCreate(['stage_id' => $silverStage->id, 'group_id' => $silverGroup->id, 'team_id' => $tid]);
            }
            $setupService->generateRoundRobinMatches($silverStage, $silverGroup);
            $silverStage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
        }
    }
}
