<?php

namespace App\Services;

use App\Models\TournamentStage;
use App\Models\TournamentMatch;
use App\Models\TournamentStanding;
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
        // Standings для всех
        foreach ($teamIds as $teamId) {
            TournamentStanding::firstOrCreate([
                'stage_id' => $stage->id,
                'group_id' => null,
                'team_id'  => $teamId,
            ]);
        }

        $stage->update([
            'status' => TournamentStage::STATUS_IN_PROGRESS,
            'config' => array_merge($stage->config ?? [], [
                'king_team_id'  => null,
                'queue'         => $teamIds,
                'current_round' => 0,
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

        // Бонусные очки king за удержание
        $standing = TournamentStanding::where('stage_id', $stage->id)
            ->where('team_id', $match->winner_team_id)->first();
        if ($standing) {
            $standing->increment('rating_points'); // +1 за удержание
        }
    }
}
