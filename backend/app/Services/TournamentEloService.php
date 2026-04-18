<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TournamentMatch;
use App\Models\PlayerCareerStats;
use App\Models\PlayerTournamentStats;
use Illuminate\Support\Facades\DB;

class TournamentEloService
{
    private const K_FACTOR = 32;
    private const DEFAULT_ELO = 1500;

    /**
     * Пересчитать Elo для всех игроков после завершения турнира.
     *
     * Логика:
     * - Проходим все матчи турнира в хронологическом порядке
     * - Для каждого матча считаем средний Elo команд
     * - Обновляем Elo каждого игрока по формуле
     */
    public function recalculateForEvent(Event $event): void
    {
        $direction = $event->direction ?? 'classic';

        $matches = TournamentMatch::whereHas('stage', fn($q) => $q->where('event_id', $event->id))
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->whereNotNull('winner_team_id')
            ->orderBy('scored_at')
            ->get();

        foreach ($matches as $match) {
            $this->processMatch($match, $direction);
        }
    }

    /**
     * Обработать один матч: обновить Elo всех игроков обеих команд.
     */
    private function processMatch(TournamentMatch $match, string $direction): void
    {
        $homePlayerIds = $this->getTeamPlayerIds($match->team_home_id);
        $awayPlayerIds = $this->getTeamPlayerIds($match->team_away_id);

        if (empty($homePlayerIds) || empty($awayPlayerIds)) return;

        $homeAvgElo = $this->getAverageElo($homePlayerIds, $direction);
        $awayAvgElo = $this->getAverageElo($awayPlayerIds, $direction);

        $homeWon = $match->winner_team_id === $match->team_home_id;

        // Expected scores
        $expectedHome = 1 / (1 + pow(10, ($awayAvgElo - $homeAvgElo) / 400));
        $expectedAway = 1 - $expectedHome;

        $actualHome = $homeWon ? 1 : 0;
        $actualAway = $homeWon ? 0 : 1;

        // Корректировка по счёту сетов (бонус за чистую победу)
        $setBonus = 1.0;
        if ($homeWon && $match->sets_away === 0) $setBonus = 1.2;
        elseif (!$homeWon && $match->sets_home === 0) $setBonus = 1.2;

        $deltaHome = round(self::K_FACTOR * $setBonus * ($actualHome - $expectedHome));
        $deltaAway = round(self::K_FACTOR * $setBonus * ($actualAway - $expectedAway));

        // Обновляем Elo каждого игрока
        foreach ($homePlayerIds as $userId) {
            $this->updatePlayerElo($userId, $direction, $deltaHome);
        }
        foreach ($awayPlayerIds as $userId) {
            $this->updatePlayerElo($userId, $direction, $deltaAway);
        }
    }

    private function getTeamPlayerIds(int $teamId): array
    {
        return DB::table('event_team_members')
            ->where('event_team_id', $teamId)
            ->where('confirmation_status', 'confirmed')
            ->pluck('user_id')
            ->toArray();
    }

    private function getAverageElo(array $userIds, string $direction): float
    {
        $elos = PlayerCareerStats::whereIn('user_id', $userIds)
            ->where('direction', $direction)
            ->pluck('elo_rating')
            ->toArray();

        // Для игроков без записи — используем дефолт
        $missing = count($userIds) - count($elos);
        for ($i = 0; $i < $missing; $i++) {
            $elos[] = self::DEFAULT_ELO;
        }

        return count($elos) > 0 ? array_sum($elos) / count($elos) : self::DEFAULT_ELO;
    }

    private function updatePlayerElo(int $userId, string $direction, int $delta): void
    {
        $career = PlayerCareerStats::firstOrCreate(
            ['user_id' => $userId, 'direction' => $direction],
            ['elo_rating' => self::DEFAULT_ELO]
        );

        $career->elo_rating = max(100, $career->elo_rating + $delta);
        $career->save();
    }

    /**
     * Сбросить Elo всех игроков до дефолта.
     */
    public function resetAll(string $direction = null): int
    {
        $query = PlayerCareerStats::query();
        if ($direction) $query->where('direction', $direction);

        return $query->update(['elo_rating' => self::DEFAULT_ELO]);
    }
}
