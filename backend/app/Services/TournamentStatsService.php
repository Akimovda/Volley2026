<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use App\Models\PlayerTournamentStats;
use App\Models\PlayerCareerStats;
use Illuminate\Support\Facades\DB;

class TournamentStatsService
{
    /**
     * Обновить статистику всех игроков после завершённого матча.
     */
    public function updateAfterMatch(TournamentMatch $match): void
    {
        if (! $match->isCompleted() || ! $match->winner_team_id) return;

        $stage = $match->stage;
        $event = $stage->event;

        // Обновляем stats для обеих команд
        foreach ([
            ['team_id' => $match->team_home_id, 'won' => $match->sets_home, 'lost' => $match->sets_away,
             'scored' => $match->total_points_home, 'conceded' => $match->total_points_away,
             'isWinner' => $match->winner_team_id === $match->team_home_id],
            ['team_id' => $match->team_away_id, 'won' => $match->sets_away, 'lost' => $match->sets_home,
             'scored' => $match->total_points_away, 'conceded' => $match->total_points_home,
             'isWinner' => $match->winner_team_id === $match->team_away_id],
        ] as $side) {
            $this->updateTeamPlayersStats($event, $side['team_id'], $side);
        }
    }

    /**
     * Откатить статистику игроков для матча.
     */
    public function revertMatch(TournamentMatch $match): void
    {
        if (! $match->isCompleted()) return;

        $stage = $match->stage;
        $event = $stage->event;

        foreach ([
            ['team_id' => $match->team_home_id, 'won' => $match->sets_home, 'lost' => $match->sets_away,
             'scored' => $match->total_points_home, 'conceded' => $match->total_points_away,
             'isWinner' => $match->winner_team_id === $match->team_home_id],
            ['team_id' => $match->team_away_id, 'won' => $match->sets_away, 'lost' => $match->sets_home,
             'scored' => $match->total_points_away, 'conceded' => $match->total_points_home,
             'isWinner' => $match->winner_team_id === $match->team_away_id],
        ] as $side) {
            $this->revertTeamPlayersStats($event, $side['team_id'], $side);
        }
    }

    /**
     * Обновить stats для всех игроков команды.
     */
    private function updateTeamPlayersStats(Event $event, int $teamId, array $side): void
    {
        $memberUserIds = DB::table('event_team_members')
            ->where('event_team_id', $teamId)
            ->where('confirmation_status', 'confirmed')
            ->pluck('user_id');

        foreach ($memberUserIds as $userId) {
            $stat = PlayerTournamentStats::firstOrCreate([
                'event_id' => $event->id,
                'user_id'  => $userId,
                'team_id'  => $teamId,
            ]);

            $stat->matches_played++;
            if ($side['isWinner']) $stat->matches_won++;
            $stat->sets_won        += $side['won'];
            $stat->sets_lost       += $side['lost'];
            $stat->points_scored   += $side['scored'];
            $stat->points_conceded += $side['conceded'];

            $stat->recalcRates()->save();
        }
    }

    /**
     * Откат stats для всех игроков команды.
     */
    private function revertTeamPlayersStats(Event $event, int $teamId, array $side): void
    {
        $memberUserIds = DB::table('event_team_members')
            ->where('event_team_id', $teamId)
            ->where('confirmation_status', 'confirmed')
            ->pluck('user_id');

        foreach ($memberUserIds as $userId) {
            $stat = PlayerTournamentStats::where('event_id', $event->id)
                ->where('user_id', $userId)
                ->where('team_id', $teamId)
                ->first();

            if (! $stat) continue;

            $stat->matches_played = max(0, $stat->matches_played - 1);
            if ($side['isWinner']) $stat->matches_won = max(0, $stat->matches_won - 1);
            $stat->sets_won        = max(0, $stat->sets_won - $side['won']);
            $stat->sets_lost       = max(0, $stat->sets_lost - $side['lost']);
            $stat->points_scored   = max(0, $stat->points_scored - $side['scored']);
            $stat->points_conceded = max(0, $stat->points_conceded - $side['conceded']);

            $stat->recalcRates()->save();
        }
    }

    /**
     * Пересчитать career stats для игрока по всем его турнирам.
     */
    public function rebuildCareerStats(int $userId): void
    {
        foreach (['classic', 'beach'] as $direction) {
            $tournamentStats = PlayerTournamentStats::where('user_id', $userId)
                ->whereHas('event', fn($q) => $q->where('direction', $direction))
                ->get();

            if ($tournamentStats->isEmpty()) {
                PlayerCareerStats::where('user_id', $userId)
                    ->where('direction', $direction)
                    ->delete();
                continue;
            }

            $career = PlayerCareerStats::firstOrCreate([
                'user_id'   => $userId,
                'direction' => $direction,
            ]);

            $career->total_tournaments    = $tournamentStats->groupBy('event_id')->count();
            $career->total_matches        = $tournamentStats->sum('matches_played');
            $career->total_wins           = $tournamentStats->sum('matches_won');
            $career->total_sets_won       = $tournamentStats->sum('sets_won');
            $career->total_sets_lost      = $tournamentStats->sum('sets_lost');
            $career->total_points_scored  = $tournamentStats->sum('points_scored');
            $career->total_points_conceded = $tournamentStats->sum('points_conceded');

            $career->recalcRates()->save();
        }
    }

    /**
     * Пересчитать career stats для всех игроков турнира.
     */
    public function rebuildAllCareerStatsForEvent(Event $event): void
    {
        $userIds = PlayerTournamentStats::where('event_id', $event->id)
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            $this->rebuildCareerStats($userId);
        }
    }

    /**
     * Полный пересчёт player_tournament_stats для турнира с нуля.
     */
    public function rebuildTournamentStats(Event $event): void
    {
        // Очищаем
        PlayerTournamentStats::where('event_id', $event->id)->delete();

        // Проходим все завершённые матчи
        $matches = TournamentMatch::whereHas('stage', fn($q) => $q->where('event_id', $event->id))
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->get();

        foreach ($matches as $match) {
            $this->updateAfterMatch($match);
        }
    }

    /**
     * Получить топ игроков турнира по match_win_rate.
     */
    public function getTopPlayers(int $eventId, int $limit = 10): \Illuminate\Support\Collection
    {
        return PlayerTournamentStats::where('event_id', $eventId)
            ->where('matches_played', '>', 0)
            ->with('user', 'team')
            ->orderByDesc('match_win_rate')
            ->orderByDesc('point_diff')
            ->limit($limit)
            ->get();
    }

    /**
     * Определить итоговую классификацию (1–N место) после завершения турнира.
     *
     * Логика:
     * 1. Если есть single_elim/double_elim стадия → победитель финала = 1 место, проигравший = 2
     * 2. Если есть матч за 3-е → его победитель = 3
     * 3. Остальные — по standings последней групповой стадии
     *
     * @return array<int, array{place: int, team_id: int, team_name: string}>
     */
    public function calculateFinalClassification(Event $event): array
    {
        $stages = $event->tournamentStages()->orderBy('sort_order')->get();
        $classification = [];
        $place = 1;
        $assignedTeams = [];

        // Если есть дивизионы — классификация по дивизионам (Hard первый, потом Lite)
        $divisionStages = $stages->filter(fn($s) => str_starts_with($s->name, 'Дивизион'));
        if ($divisionStages->isNotEmpty()) {
            // Сортируем: Hard первый, Medium потом, Lite последний
            $sorted = $divisionStages->sortBy(function($s) {
                if (str_contains($s->name, 'Hard')) return 0;
                if (str_contains($s->name, 'Medium')) return 1;
                return 2; // Lite
            });

            foreach ($sorted as $divStage) {
                $divStandings = TournamentStanding::where('stage_id', $divStage->id)
                    ->with('team')
                    ->orderBy('rank')
                    ->get();

                foreach ($divStandings as $s) {
                    if (in_array($s->team_id, $assignedTeams)) continue;
                    $classification[] = [
                        'place' => $place++,
                        'team_id' => $s->team_id,
                        'team_name' => $s->team->name ?? '?',
                        'division' => str_replace('Дивизион ', '', $divStage->name),
                    ];
                    $assignedTeams[] = $s->team_id;
                }
            }

            return $classification;
        }

        // 1. Bracket стадия (single/double elim) — финал определяет 1-2 место
        $bracketStage = $stages->whereIn('type', ['single_elim', 'double_elim'])->last();
        if ($bracketStage) {
            $finalMatch = $bracketStage->matches()
                ->where('status', TournamentMatch::STATUS_COMPLETED)
                ->orderByDesc('round')
                ->orderByDesc('match_number')
                ->first();

            if ($finalMatch && $finalMatch->winner_team_id) {
                // Проверяем что это не матч за 3 место
                $isThirdPlace = $finalMatch->court === '3rd place';

                if (!$isThirdPlace) {
                    $winner = EventTeam::find($finalMatch->winner_team_id);
                    $loser = EventTeam::find($finalMatch->loserId());

                    if ($winner) {
                        $classification[] = ['place' => $place++, 'team_id' => $winner->id, 'team_name' => $winner->name];
                        $assignedTeams[] = $winner->id;
                    }
                    if ($loser) {
                        $classification[] = ['place' => $place++, 'team_id' => $loser->id, 'team_name' => $loser->name];
                        $assignedTeams[] = $loser->id;
                    }
                }

                // Матч за 3 место
                $thirdMatch = $bracketStage->matches()
                    ->where('status', TournamentMatch::STATUS_COMPLETED)
                    ->where('court', '3rd place')
                    ->first();

                if ($thirdMatch && $thirdMatch->winner_team_id) {
                    $third = EventTeam::find($thirdMatch->winner_team_id);
                    $fourth = EventTeam::find($thirdMatch->loserId());

                    if ($third && !in_array($third->id, $assignedTeams)) {
                        $classification[] = ['place' => $place++, 'team_id' => $third->id, 'team_name' => $third->name];
                        $assignedTeams[] = $third->id;
                    }
                    if ($fourth && !in_array($fourth->id, $assignedTeams)) {
                        $classification[] = ['place' => $place++, 'team_id' => $fourth->id, 'team_name' => $fourth->name];
                        $assignedTeams[] = $fourth->id;
                    }
                }
            }
        }

        // 2. Оставшиеся команды — по standings последней стадии с группами
        $groupStage = $stages->filter(fn($s) => $s->groups->isNotEmpty())->last();
        if ($groupStage) {
            $standings = TournamentStanding::where('stage_id', $groupStage->id)
                ->with('team')
                ->orderBy('rank')
                ->get();

            foreach ($standings as $s) {
                if (in_array($s->team_id, $assignedTeams)) continue;
                $classification[] = ['place' => $place++, 'team_id' => $s->team_id, 'team_name' => $s->team->name ?? '?'];
                $assignedTeams[] = $s->team_id;
            }
        }

        // 3. Все оставшиеся команды
        $allTeamIds = DB::table('event_teams')
            ->where('event_id', $event->id)
            ->where('status', 'submitted')
            ->pluck('id');

        foreach ($allTeamIds as $tid) {
            if (in_array($tid, $assignedTeams)) continue;
            $team = EventTeam::find($tid);
            $classification[] = ['place' => $place++, 'team_id' => $tid, 'team_name' => $team->name ?? '?'];
        }

        return $classification;
    }

}
