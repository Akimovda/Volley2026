<?php

namespace App\Services;

use App\Models\TournamentStage;
use App\Models\TournamentMatch;
use App\Models\TournamentStanding;
use App\Models\TournamentGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentKingService
{
    /**
     * Инициализация King of the Court.
     *
     * Логика: все команды в пуле. Победитель остаётся на корте (king),
     * проигравший уходит в очередь. Очки за удержание корта.
     *
     * @param  int[] $teamIds
     */
    public function initialize(TournamentStage $stage, array $teamIds): void
    {
        // Обёрточная TournamentGroup — без неё standings/matches никогда не
        // попадут в рендер $group->standings в setup.blade.php (та рендерит
        // турнирную таблицу только через $stage->groups, см.
        // report/kotc_deps_recon_2026-08-21.md, п.5).
        $group = TournamentGroup::create([
            'stage_id'   => $stage->id,
            'name'       => 'King of the Court',
            'sort_order' => 1,
        ]);

        // Standings для всех
        foreach ($teamIds as $teamId) {
            TournamentStanding::firstOrCreate([
                'stage_id' => $stage->id,
                'group_id' => $group->id,
                'team_id'  => $teamId,
            ]);
        }

        // rounds_count — сколько матчей сыграть всего, до завершения стадии.
        // Организатор задаёт при создании стадии (форма setup.blade.php); если
        // не задал — дефолт 2 матча на команду (каждая успевает хотя бы раз
        // побывать королём и хотя бы раз челленджером).
        $config = $stage->config ?? [];
        $roundsCount = (int) ($config['rounds_count'] ?? 0);
        if ($roundsCount < 1) {
            $roundsCount = max(1, count($teamIds) * 2);
        }

        $stage->update([
            'status' => TournamentStage::STATUS_IN_PROGRESS,
            'config' => array_merge($config, [
                'king_team_id'  => null,
                'queue'         => $teamIds,
                'current_round' => 0,
                'king_group_id' => $group->id,
                'rounds_count'  => $roundsCount,
            ]),
        ]);
    }

    /**
     * Сгенерировать следующий матч King of the Court.
     */
    public function generateNextMatch(TournamentStage $stage): ?TournamentMatch
    {
        $config = $stage->config ?? [];
        $queue = $config['queue'] ?? [];
        $kingId = $config['king_team_id'] ?? null;
        $round = ($config['current_round'] ?? 0) + 1;
        $groupId = $config['king_group_id'] ?? null;
        $roundsCount = (int) ($config['rounds_count'] ?? 0);

        // Лимит матчей (rounds_count) исчерпан — не плодим матчи сверх него.
        // 0 — legacy-защита для стадий без rounds_count в config (не должно
        // случаться после initialize(), тот всегда проставляет дефолт).
        if ($roundsCount > 0 && ($config['current_round'] ?? 0) >= $roundsCount) {
            return null;
        }

        if (count($queue) < 1) {
            return null; // Турнир окончен
        }

        // Первый матч: берём двух первых из очереди
        if (!$kingId) {
            if (count($queue) < 2) return null;
            $homeId = array_shift($queue);
            $awayId = array_shift($queue);
        } else {
            $homeId = $kingId; // King
            $awayId = array_shift($queue);
        }

        $matchNum = ($stage->matches()->max('match_number') ?? 0) + 1;

        $match = TournamentMatch::create([
            'stage_id'     => $stage->id,
            'group_id'     => $groupId,
            'round'        => $round,
            'match_number' => $matchNum,
            'team_home_id' => $homeId,
            'team_away_id' => $awayId,
            'status'       => TournamentMatch::STATUS_SCHEDULED,
        ]);

        // Обновляем config
        $config['queue'] = $queue;
        $config['current_round'] = $round;
        $stage->update(['config' => $config]);

        return $match;
    }

    /**
     * После завершения матча: победитель = новый king, проигравший → конец очереди.
     *
     * Чистая стейт-машина — rating_points сюда не пишем: при group_id на
     * standings/matches (см. initialize()/generateNextMatch()) штатный путь
     * submitScore() сам вызывает StandingsService::recalculateGroup() сразу
     * после этого метода (см. report/kotc_deps_recon_2026-08-21.md, п.1).
     *
     * Идемпотентность: НЕ полная — повторный вызов на одном и том же матче
     * задвоил бы проигравшего в очереди (queue[] += $loserId второй раз).
     * На практике повтор невозможен: хук вызывается ровно один раз из
     * submitScore() (сразу после проставления winner_team_id), а рескор
     * завершённого KotC-матча запрещён guardKotcRescore() — см. коммит 5.
     */
    public function afterMatch(TournamentStage $stage, TournamentMatch $match): void
    {
        if (!$match->winner_team_id) return;

        $config = $stage->config ?? [];
        $queue = $config['queue'] ?? [];

        $loserId = $match->loserId();

        // Победитель = king
        $config['king_team_id'] = $match->winner_team_id;

        // Проигравший → конец очереди
        if ($loserId) {
            $queue[] = $loserId;
        }

        $config['queue'] = $queue;
        $stage->update(['config' => $config]);
    }
}
