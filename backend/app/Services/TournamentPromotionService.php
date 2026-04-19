<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TournamentSeason;
use App\Models\TournamentLeague;
use App\Models\TournamentLeagueTeam;
use App\Models\TournamentSeasonEvent;
use App\Models\TournamentSeasonStats;
use App\Models\TournamentStanding;
use App\Models\TournamentStage;
use App\Models\EventTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\TournamentNotificationService;
use Illuminate\Support\Facades\Log;

class TournamentPromotionService
{
    public function __construct(
        private TournamentLeagueService $leagueService,
        private TournamentNotificationService $notificationService,
    ) {}

    /**
     * Обработать завершение турнира в контексте сезона.
     * Вызывается после того, как все стадии турнира завершены.
     */
    public function process(Event $event): void
    {
        $seasonEvent = TournamentSeasonEvent::where('event_id', $event->id)->first();

        if (!$seasonEvent) {
            return; // Турнир не привязан к сезону
        }

        $season = $seasonEvent->season;
        $league = $seasonEvent->league;

        if (!$season->isAutoPromotion()) {
            // Просто отмечаем тур завершённым и обновляем статистику
            $seasonEvent->update(['status' => TournamentSeasonEvent::STATUS_COMPLETED]);
            $this->updateSeasonStats($event, $season, $league);
            return;
        }

        DB::transaction(function () use ($event, $season, $league, $seasonEvent) {
            // 1. Отмечаем тур завершённым
            $seasonEvent->update(['status' => TournamentSeasonEvent::STATUS_COMPLETED]);

            // 2. Обновляем сезонную статистику
            $this->updateSeasonStats($event, $season, $league);

            // 3. Получаем итоговую классификацию
            $classification = $this->getClassification($event);

            if ($classification->isEmpty()) {
                Log::warning("Promotion: пустая классификация для event #{$event->id}");
                return;
            }

            // 4. Применяем правила продвижения
            $rules = $season->promotionRules();
            $leagueRules = $rules[$league->name] ?? $league->config ?? [];

            $promoteCount  = (int) ($leagueRules['promote_count'] ?? $league->promoteCount());
            $eliminateCount = (int) ($leagueRules['eliminate_count'] ?? $league->eliminateCount());

            // 5. Promote — лучшие уходят в высшую лигу
            if ($promoteCount > 0) {
                $promoteTo = $leagueRules['promote_to'] ?? null;
                $targetLeague = $promoteTo
                    ? TournamentLeague::where('season_id', $season->id)->where('name', $promoteTo)->first()
                    : TournamentLeague::where('season_id', $season->id)->where('level', '<', $league->level)->orderByDesc('level')->first();

                if ($targetLeague) {
                    $topTeams = $classification->take($promoteCount);
                    foreach ($topTeams as $teamId) {
                        $leagueTeam = $this->findLeagueTeam($league, $teamId);
                        if ($leagueTeam) {
                            $this->leagueService->transferToLeague($leagueTeam, $targetLeague, 'promoted');
                            Log::info("Promotion: team #{$teamId} promoted from {$league->name} to {$targetLeague->name}");
                            $team = EventTeam::find($teamId);
                            if ($team) {
                                try {
                                    $this->notificationService->notifyPromotion($team, $event, $league->name, $targetLeague->name);
                                } catch (\Throwable $e) {
                                    Log::warning("Promotion notification failed: " . $e->getMessage());
                                }
                            }
                        }
                    }
                }
            }

            // 6. Eliminate — худшие уходят в резерв
            if ($eliminateCount > 0) {
                $bottomTeams = $classification->reverse()->take($eliminateCount);
                foreach ($bottomTeams as $teamId) {
                    $leagueTeam = $this->findLeagueTeam($league, $teamId);
                    if ($leagueTeam) {
                        $this->leagueService->eliminateToReserve($leagueTeam);
                        Log::info("Promotion: team #{$teamId} eliminated from {$league->name} to reserve");
                            $team = EventTeam::find($teamId);
                            if ($team) {
                                $pos = $leagueTeam->fresh()->reserve_position ?? 0;
                                try {
                                    $this->notificationService->notifyElimination($team, $event, $league->name, $pos);
                                } catch (\Throwable $e) {
                                    Log::warning("Elimination notification failed: " . $e->getMessage());
                                }
                            }
                    }
                }
            }

            // 7. Подтягиваем из резерва если есть свободные места
            $reserveAbsorb = (bool) ($leagueRules['reserve_absorb'] ?? $league->cfg('reserve_absorb', true));
            if ($reserveAbsorb) {
                $freeSlots = $eliminateCount; // столько же сколько выбыло
                if ($freeSlots > 0) {
                    $activated = $this->leagueService->fillFromReserve($league, $freeSlots);
                    foreach ($activated as $lt) {
                        Log::info("Promotion: reserve #{$lt->id} activated in {$league->name}");
                        $activatedTeam = $lt->team;
                        if ($activatedTeam) {
                            try {
                                $this->notificationService->notifyActivatedFromReserve($activatedTeam, $league->name);
                            } catch (\Throwable $e) {
                                Log::warning("Reserve activation notification failed: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Получить итоговую классификацию турнира (упорядоченный список team_id).
     * Берём из standings последней стадии, или из StatsService.
     */
    protected function getClassification(Event $event): Collection
    {
        // Берём последнюю завершённую стадию
        $lastStage = $event->tournamentStages()
            ->where('status', TournamentStage::STATUS_COMPLETED)
            ->orderByDesc('sort_order')
            ->first();

        if (!$lastStage) {
            return collect();
        }

        // Если есть bracket (single/double elim) — берём порядок по матчам
        if (in_array($lastStage->type, ['single_elim', 'double_elim'])) {
            return $this->classificationFromBracket($lastStage);
        }

        // Иначе берём из standings
        return TournamentStanding::where('stage_id', $lastStage->id)
            ->orderBy('rank')
            ->pluck('team_id');
    }

    /**
     * Классификация из bracket — финалист, победитель полуфиналов и т.д.
     */
    protected function classificationFromBracket(TournamentStage $stage): Collection
    {
        $matches = $stage->matches()
            ->where('status', 'completed')
            ->orderByDesc('round')
            ->orderBy('match_number')
            ->get();

        $result = collect();

        foreach ($matches as $match) {
            if ($match->winner_team_id && !$result->contains($match->winner_team_id)) {
                $result->push($match->winner_team_id);
            }
            $loserId = $match->loserId();
            if ($loserId && !$result->contains($loserId)) {
                $result->push($loserId);
            }
        }

        return $result;
    }

    /**
     * Найти запись команды в лиге.
     */
    protected function findLeagueTeam(TournamentLeague $league, int $teamId): ?TournamentLeagueTeam
    {
        return TournamentLeagueTeam::where('league_id', $league->id)
            ->where('team_id', $teamId)
            ->where('status', TournamentLeagueTeam::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Обновить сезонную статистику по результатам турнира.
     */
    protected function updateSeasonStats(Event $event, TournamentSeason $season, TournamentLeague $league): void
    {
        // Собираем все player_tournament_stats для этого события
        $playerStats = \App\Models\PlayerTournamentStats::where('event_id', $event->id)->get();

        foreach ($playerStats as $ps) {
            $seasonStat = TournamentSeasonStats::firstOrCreate(
                [
                    'season_id' => $season->id,
                    'league_id' => $league->id,
                    'user_id'   => $ps->user_id,
                ],
                ['elo_season' => 1500],
            );

            $seasonStat->increment('rounds_played', 1);
            $seasonStat->increment('matches_played', $ps->matches_played);
            $seasonStat->increment('matches_won', $ps->matches_won);
            $seasonStat->increment('sets_won', $ps->sets_won);
            $seasonStat->increment('sets_lost', $ps->sets_lost);
            $seasonStat->increment('points_scored', $ps->points_scored);
            $seasonStat->increment('points_conceded', $ps->points_conceded);

            // Пересчитываем winrate
            $totalPlayed = $seasonStat->matches_played;
            $totalWon    = $seasonStat->matches_won;

            $seasonStat->update([
                'match_win_rate' => $totalPlayed > 0 ? round($totalWon / $totalPlayed * 100, 2) : 0,
                'set_win_rate'   => ($seasonStat->sets_won + $seasonStat->sets_lost) > 0
                    ? round($seasonStat->sets_won / ($seasonStat->sets_won + $seasonStat->sets_lost) * 100, 2)
                    : 0,
            ]);

            // Streak
            if ($ps->matches_won > 0 && $ps->matches_played === $ps->matches_won) {
                $seasonStat->increment('current_streak', $ps->matches_won);
            } else {
                $seasonStat->update(['current_streak' => 0]);
            }

            // Best placement
            $classification = $this->getClassification($event);
            $teamIdx = $classification->search($ps->team_id);
            if ($teamIdx !== false) {
                $placement = $teamIdx + 1;
                if (!$seasonStat->best_placement || $placement < $seasonStat->best_placement) {
                    $seasonStat->update(['best_placement' => $placement]);
                }
            }
        }
    }
}
