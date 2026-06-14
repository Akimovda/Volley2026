<?php

namespace App\Services;

use App\Models\Event;
use App\Models\League;
use App\Models\PromotionHistory;
use App\Models\TournamentLeague;
use App\Models\TournamentLeagueTeam;
use App\Models\TournamentSeason;
use App\Models\TournamentSeasonEvent;
use App\Models\TournamentSeasonStats;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use App\Models\EventTeam;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\UserNotificationService;

class TournamentPromotionService
{
    public function __construct(
        private TournamentLeagueService $leagueService,
        private TournamentNotificationService $notificationService,
    ) {}

    // =========================================================================
    //  ПУБЛИЧНЫЙ API
    // =========================================================================

    /**
     * Применить промоушен/вылет после завершения тура.
     * Вызывается вручную организатором или автоматически (если auto_promotion=true).
     *
     * @return array{relegated: array, promoted: array, entered_from_queue: array, entered_from_feeder: array, errors: array}
     */
    public function process(
        TournamentSeason $season,
        int $occurrenceId,
        int $roundNumber,
        string $initiatedBy = self::INITIATED_SYSTEM
    ): array {
        $results = [
            'relegated'            => [],
            'promoted'             => [],
            'entered_from_queue'   => [],
            'entered_from_feeder'  => [],
            'errors'               => [],
        ];

        $league = $season->league;

        DB::transaction(function () use ($season, $league, $occurrenceId, $roundNumber, $initiatedBy, &$results) {

            // 1. ВЫЛЕТ — начинаем с нижних дивизионов (высокий level = ниже)
            foreach ($season->leagues()->orderByDesc('level')->get() as $division) {
                $eliminateCount = $division->getEliminateCount();
                if ($eliminateCount <= 0) continue;

                $standings = $this->getStandingsForRound($division, $occurrenceId);
                if ($standings->isEmpty()) {
                    $results['errors'][] = "Нет standings для дивизиона «{$division->name}» (occurrence #{$occurrenceId})";
                    continue;
                }

                $toEliminate = $standings->sortBy('rank')->reverse()->take($eliminateCount);

                foreach ($toEliminate as $standing) {
                    $result = $this->eliminateTeam(
                        $season, $division, $standing,
                        $occurrenceId, $roundNumber, $initiatedBy
                    );
                    if (isset($result['error'])) {
                        $results['errors'][] = $result['error'];
                    } else {
                        $results['relegated'][] = $result;
                    }
                }
            }

            // 2. ВНУТРЕННИЙ ПРОМОУШЕН (Lite → Hard и т.д.)
            foreach ($season->leagues()->orderByDesc('level')->get() as $division) {
                $promoteCount = $division->getPromoteCount();
                $promoteTo    = $division->getPromoteTo();
                if ($promoteCount <= 0 || !$promoteTo) continue;

                $targetDivision = match ($promoteTo) {
                    'upper_division' => $division->upperDivision(),
                    default          => null,
                };
                if (!$targetDivision) continue;

                $standings  = $this->getStandingsForRound($division, $occurrenceId);
                $toPromote  = $standings->sortBy('rank')->take($promoteCount);

                foreach ($toPromote as $standing) {
                    $result = $this->promoteTeam(
                        $season, $division, $targetDivision,
                        $standing, $occurrenceId, $roundNumber, $initiatedBy
                    );
                    if (isset($result['error'])) {
                        $results['errors'][] = $result['error'];
                    } else {
                        $results['promoted'][] = $result;
                    }
                }
            }

            // 3. ВХОД ИЗ ФИДЕРНОЙ ЛИГИ
            $feederSlots = $season->getFeederPromoteSlots();
            if ($feederSlots > 0 && $league && $league->hasFeeder()) {
                $feederLeague = $league->feederLeague;
                $feederSeason = $feederLeague->seasons()
                    ->where('status', TournamentSeason::STATUS_ACTIVE)
                    ->latest('starts_at')
                    ->first();

                if ($feederSeason) {
                    $feederDivision = $feederSeason->leagues()->orderBy('level')->first();
                    // Целевой дивизион — самый нижний в основной лиге
                    $targetDivision = $season->leagues()->orderByDesc('level')->first();

                    if ($feederDivision && $targetDivision) {
                        $feederStandings = $this->getStandingsForRound($feederDivision, $occurrenceId);
                        $toPromoteFromFeeder = $feederStandings->sortBy('rank')->take($feederSlots);

                        foreach ($toPromoteFromFeeder as $standing) {
                            $result = $this->promoteFromFeeder(
                                $season, $feederSeason, $feederDivision, $targetDivision,
                                $standing, $occurrenceId, $roundNumber, $initiatedBy
                            );
                            if (isset($result['error'])) {
                                $results['errors'][] = $result['error'];
                            } else {
                                $results['entered_from_feeder'][] = $result;
                            }
                        }
                    }
                }
            }

            // 4. ВХОД ИЗ ОЧЕРЕДИ (резерв дивизиона)
            if ($season->isQueueEntryEnabled()) {
                $queueSlots = $season->getQueueEntrySlots();

                foreach ($season->leagues as $division) {
                    $division->refresh();
                    $activeCount = $division->activeTeams()->count();
                    $max         = $division->max_teams ?? PHP_INT_MAX;
                    $freeSlots   = min($queueSlots, $max - $activeCount);

                    if ($freeSlots > 0) {
                        $fromQueue = $this->fillFromQueue(
                            $season, $division, $freeSlots,
                            $occurrenceId, $roundNumber, $initiatedBy
                        );
                        $results['entered_from_queue'] = array_merge(
                            $results['entered_from_queue'],
                            $fromQueue
                        );
                    }
                }
            }
        });

        return $results;
    }

    /**
     * Ручное перемещение командой организатором.
     */
    public function manualMove(
        TournamentSeason $season,
        TournamentLeagueTeam $leagueTeam,
        TournamentLeague $toDivision,
        string $newStatus,
        string $initiatedBy = self::INITIATED_ORGANIZER
    ): PromotionHistory {
        $fromDivision = $leagueTeam->league;

        $leagueTeam->league_id = $toDivision->id;
        $leagueTeam->status    = $newStatus;

        if ($newStatus === TournamentLeagueTeam::STATUS_RESERVE) {
            $leagueTeam->reserve_position = $toDivision->nextReservePosition();
        } else {
            $leagueTeam->reserve_position = null;
        }

        $leagueTeam->save();

        $ph = PromotionHistory::create([
            'season_id'       => $season->id,
            'league_team_id'  => $leagueTeam->id,
            'user_id'         => $leagueTeam->user_id,
            'team_id'         => $leagueTeam->team_id,
            'from_division_id' => $fromDivision->id,
            'to_division_id'  => $toDivision->id,
            'from_league_id'  => $season->league_id,
            'to_league_id'    => $season->league_id,
            'action'          => PromotionHistory::ACTION_MANUAL_MOVE,
            'status'          => PromotionHistory::STATUS_COMPLETED,
            'initiated_by'    => $initiatedBy,
            'notes'           => "Ручное перемещение: {$fromDivision->name} → {$toDivision->name}",
        ]);

        if ($leagueTeam->user_id && $initiatedBy !== self::INITIATED_USER) {
            $this->sendPromotionNotification(
                $leagueTeam->user_id,
                PromotionHistory::ACTION_MANUAL_MOVE,
                $fromDivision->name,
                $toDivision->name,
                $ph
            );
        }

        return $ph;
    }

    /**
     * Отказ от перевода — команда уходит в конец резерва.
     */
    public function declineTransfer(
        TournamentLeagueTeam $leagueTeam,
        PromotionHistory $history
    ): void {
        $leagueTeam->status           = TournamentLeagueTeam::STATUS_RESERVE;
        $leagueTeam->reserve_position = $leagueTeam->league->nextReservePosition();
        $leagueTeam->save();

        $history->status = PromotionHistory::STATUS_DECLINED;
        $history->save();

        PromotionHistory::create([
            'season_id'       => $history->season_id,
            'league_team_id'  => $leagueTeam->id,
            'user_id'         => $leagueTeam->user_id,
            'team_id'         => $leagueTeam->team_id,
            'from_division_id' => $leagueTeam->league_id,
            'to_division_id'  => $leagueTeam->league_id,
            'from_league_id'  => $history->from_league_id,
            'to_league_id'    => $history->from_league_id,
            'action'          => PromotionHistory::ACTION_DECLINED,
            'status'          => PromotionHistory::STATUS_COMPLETED,
            'initiated_by'    => PromotionHistory::INITIATED_USER,
        ]);
    }

    /**
     * Проверка: можно ли ещё редактировать результаты тура N.
     * Запрещено если в следующем туре уже сыгран хоть один матч.
     */
    public function canEditRound(TournamentSeason $season, int $roundNumber): bool
    {
        $nextSeasonEvent = $season->seasonEvents()
            ->where('round_number', '>', $roundNumber)
            ->orderBy('round_number')
            ->first();

        if (!$nextSeasonEvent) {
            return true;
        }

        return !\App\Models\TournamentMatch::whereHas('stage', function ($q) use ($nextSeasonEvent) {
            $q->where('event_id', $nextSeasonEvent->event_id);
        })->whereIn('status', ['completed', 'live'])->exists();
    }

    // =========================================================================
    //  ОБРАТНАЯ СОВМЕСТИМОСТЬ — для несезонных турниров
    //  (вызывается из TournamentController::checkStageCompletion)
    // =========================================================================

    public function processEvent(Event $event): void
    {
        $seasonEvent = TournamentSeasonEvent::where('event_id', $event->id)->first();

        if (!$seasonEvent) {
            return;
        }

        $season = $seasonEvent->season;
        $league = $seasonEvent->league;

        if (!$season->isAutoPromotion()) {
            $seasonEvent->update(['status' => TournamentSeasonEvent::STATUS_COMPLETED]);
            $this->updateSeasonStats($event, $season, $league);
            return;
        }

        DB::transaction(function () use ($event, $season, $league, $seasonEvent) {
            $seasonEvent->update(['status' => TournamentSeasonEvent::STATUS_COMPLETED]);
            $this->updateSeasonStats($event, $season, $league);

            $classification = $this->getClassification($event);
            if ($classification->isEmpty()) {
                Log::warning("Promotion: пустая классификация для event #{$event->id}");
                return;
            }

            $rules       = $season->promotionRules();
            $leagueRules = $rules[$league->name] ?? $league->config ?? [];

            $promoteCount   = (int) ($leagueRules['promote_count'] ?? $league->promoteCount());
            $isLiteLeague   = stripos($league->name, 'lite') !== false || stripos($league->name, 'лайт') !== false;
            $eliminateCount = (int) ($leagueRules['eliminate_count'] ?? $league->eliminateCount() ?: ($isLiteLeague ? 2 : 0));

            if ($promoteCount > 0) {
                $promoteTo    = $leagueRules['promote_to'] ?? null;
                $targetLeague = $promoteTo
                    ? TournamentLeague::where('season_id', $season->id)->where('name', $promoteTo)->first()
                    : TournamentLeague::where('season_id', $season->id)->where('level', '<', $league->level)->orderByDesc('level')->first();

                if ($targetLeague) {
                    foreach ($classification->take($promoteCount) as $teamId) {
                        $lt = $this->findLeagueTeamByTeamId($league, $teamId);
                        if ($lt) {
                            $this->leagueService->transferToLeague($lt, $targetLeague, 'promoted');
                            Log::info("Promotion: team #{$teamId} promoted from {$league->name} to {$targetLeague->name}");
                            $this->tryNotifyPromotion($lt->team, $event, $league->name, $targetLeague->name);
                        }
                    }
                }
            }

            if ($eliminateCount > 0) {
                foreach ($classification->reverse()->take($eliminateCount) as $teamId) {
                    $lt = $this->findLeagueTeamByTeamId($league, $teamId);
                    if ($lt) {
                        $this->leagueService->eliminateToReserve($lt);
                        Log::info("Promotion: team #{$teamId} eliminated from {$league->name}");
                        $this->tryNotifyElimination($lt->team, $event, $league->name, $lt->fresh()->reserve_position ?? 0);
                    }
                }
            }

            $reserveAbsorb = (bool) ($leagueRules['reserve_absorb'] ?? $league->cfg('reserve_absorb', true));
            if ($reserveAbsorb && $eliminateCount > 0) {
                foreach ($this->leagueService->fillFromReserve($league, $eliminateCount) as $lt) {
                    Log::info("Promotion: reserve #{$lt->id} activated in {$league->name}");
                    $this->tryNotifyActivated($lt->team, $league->name);
                }
            }
        });
    }

    // =========================================================================
    //  ПРИВАТНЫЕ МЕТОДЫ
    // =========================================================================

    private function eliminateTeam(
        TournamentSeason $season,
        TournamentLeague $fromDivision,
        TournamentStanding $standing,
        int $occurrenceId,
        int $roundNumber,
        string $initiatedBy
    ): array {
        $leagueTeam = $this->findLeagueTeamByTeamId($fromDivision, $standing->team_id);
        if (!$leagueTeam) {
            return ['error' => "team_id={$standing->team_id} не найдена в дивизионе «{$fromDivision->name}»"];
        }

        $eliminateTo    = $fromDivision->getEliminateTo();
        $league         = $season->league;
        $targetDivision = null;
        $targetLeague   = null;
        $action         = PromotionHistory::ACTION_RELEGATED_RESERVE;

        switch ($eliminateTo) {
            case 'feeder':
                if ($league && $league->hasFeeder()) {
                    $targetLeague = $league->feederLeague;
                    $feederSeason = $targetLeague->seasons()
                        ->where('status', TournamentSeason::STATUS_ACTIVE)
                        ->latest('starts_at')
                        ->first();
                    if ($feederSeason) {
                        $targetDivision = $feederSeason->leagues()->orderBy('level')->first();
                        $action = PromotionHistory::ACTION_RELEGATED_FEEDER;
                    }
                }
                break;

            case 'lower_division':
                $targetDivision = $fromDivision->lowerDivision();
                $action = $targetDivision
                    ? PromotionHistory::ACTION_RELEGATED_LOWER
                    : PromotionHistory::ACTION_RELEGATED_RESERVE;
                break;

            case 'reserve':
            default:
                $action = PromotionHistory::ACTION_RELEGATED_RESERVE;
                break;
        }

        if ($targetDivision && $action !== PromotionHistory::ACTION_RELEGATED_RESERVE) {
            // Перевод в другой дивизион / фидерную лигу
            $this->leagueService->transferToLeague($leagueTeam, $targetDivision, 'relegated');
        } else {
            // В резерв текущего дивизиона
            $leagueTeam->status           = TournamentLeagueTeam::STATUS_RESERVE;
            $leagueTeam->reserve_position = $fromDivision->nextReservePosition();
            $leagueTeam->left_at          = now();

            $penalty = $season->getRelegationPenalty();
            if ($penalty) {
                $leagueTeam->confirmation_expires_at = $this->parsePenaltyDate($penalty);
            }

            $leagueTeam->save();
        }

        $ph = PromotionHistory::create([
            'season_id'        => $season->id,
            'occurrence_id'    => $occurrenceId,
            'round_number'     => $roundNumber,
            'league_team_id'   => $leagueTeam->id,
            'user_id'          => $leagueTeam->user_id,
            'team_id'          => $leagueTeam->team_id,
            'from_division_id' => $fromDivision->id,
            'to_division_id'   => $targetDivision?->id,
            'from_league_id'   => $season->league_id,
            'to_league_id'     => $targetLeague?->id ?? $season->league_id,
            'action'           => $action,
            'status'           => PromotionHistory::STATUS_COMPLETED,
            'initiated_by'     => $initiatedBy,
        ]);

        if ($leagueTeam->user_id) {
            $this->sendPromotionNotification(
                $leagueTeam->user_id,
                $action,
                $fromDivision->name,
                $targetDivision?->name ?? ($targetLeague?->name ?? __('tournaments.reserve')),
                $ph
            );
        }

        return [
            'user_id' => $leagueTeam->user_id,
            'team_id' => $leagueTeam->team_id,
            'action'  => $action,
            'from'    => $fromDivision->name,
            'to'      => $targetDivision?->name ?? 'reserve',
        ];
    }

    private function promoteTeam(
        TournamentSeason $season,
        TournamentLeague $fromDivision,
        TournamentLeague $toDivision,
        TournamentStanding $standing,
        int $occurrenceId,
        int $roundNumber,
        string $initiatedBy
    ): array {
        $leagueTeam = $this->findLeagueTeamByTeamId($fromDivision, $standing->team_id);
        if (!$leagueTeam) {
            return ['error' => "team_id={$standing->team_id} не найдена в дивизионе «{$fromDivision->name}»"];
        }

        $this->leagueService->transferToLeague($leagueTeam, $toDivision, 'promoted');

        $ph = PromotionHistory::create([
            'season_id'        => $season->id,
            'occurrence_id'    => $occurrenceId,
            'round_number'     => $roundNumber,
            'league_team_id'   => $leagueTeam->id,
            'user_id'          => $leagueTeam->user_id,
            'team_id'          => $leagueTeam->team_id,
            'from_division_id' => $fromDivision->id,
            'to_division_id'   => $toDivision->id,
            'from_league_id'   => $season->league_id,
            'to_league_id'     => $season->league_id,
            'action'           => PromotionHistory::ACTION_PROMOTED_UPPER,
            'status'           => PromotionHistory::STATUS_COMPLETED,
            'initiated_by'     => $initiatedBy,
        ]);

        if ($leagueTeam->user_id) {
            $this->sendPromotionNotification(
                $leagueTeam->user_id,
                PromotionHistory::ACTION_PROMOTED_UPPER,
                $fromDivision->name,
                $toDivision->name,
                $ph
            );
        }

        return [
            'user_id' => $leagueTeam->user_id,
            'team_id' => $leagueTeam->team_id,
            'action'  => PromotionHistory::ACTION_PROMOTED_UPPER,
            'from'    => $fromDivision->name,
            'to'      => $toDivision->name,
        ];
    }

    private function promoteFromFeeder(
        TournamentSeason $parentSeason,
        TournamentSeason $feederSeason,
        TournamentLeague $feederDivision,
        TournamentLeague $targetDivision,
        TournamentStanding $standing,
        int $occurrenceId,
        int $roundNumber,
        string $initiatedBy
    ): array {
        $feederTeam = $this->findLeagueTeamByTeamId($feederDivision, $standing->team_id);
        if (!$feederTeam) {
            return ['error' => "team_id={$standing->team_id} не найдена в фидерном дивизионе «{$feederDivision->name}»"];
        }

        // Помечаем в фидерной лиге как promoted
        $feederTeam->status  = TournamentLeagueTeam::STATUS_PROMOTED;
        $feederTeam->left_at = now();
        $feederTeam->save();

        // Добавляем в основную лигу
        $newLeagueTeam = TournamentLeagueTeam::create([
            'league_id' => $targetDivision->id,
            'team_id'   => $feederTeam->team_id,
            'user_id'   => $feederTeam->user_id,
            'status'    => $targetDivision->hasCapacity()
                ? TournamentLeagueTeam::STATUS_ACTIVE
                : TournamentLeagueTeam::STATUS_RESERVE,
            'joined_at' => now(),
            'reserve_position' => $targetDivision->hasCapacity() ? null : $targetDivision->nextReservePosition(),
        ]);

        $ph = PromotionHistory::create([
            'season_id'        => $parentSeason->id,
            'occurrence_id'    => $occurrenceId,
            'round_number'     => $roundNumber,
            'league_team_id'   => $newLeagueTeam->id,
            'user_id'          => $feederTeam->user_id,
            'team_id'          => $feederTeam->team_id,
            'from_division_id' => $feederDivision->id,
            'to_division_id'   => $targetDivision->id,
            'from_league_id'   => $feederSeason->league_id,
            'to_league_id'     => $parentSeason->league_id,
            'action'           => PromotionHistory::ACTION_PROMOTED_PARENT,
            'status'           => PromotionHistory::STATUS_COMPLETED,
            'initiated_by'     => $initiatedBy,
        ]);

        if ($feederTeam->user_id) {
            $this->sendPromotionNotification(
                $feederTeam->user_id,
                PromotionHistory::ACTION_PROMOTED_PARENT,
                $feederDivision->name,
                $targetDivision->name,
                $ph
            );
        }

        return [
            'user_id' => $feederTeam->user_id,
            'team_id' => $feederTeam->team_id,
            'action'  => PromotionHistory::ACTION_PROMOTED_PARENT,
            'from'    => $feederDivision->name,
            'to'      => $targetDivision->name,
        ];
    }

    private function fillFromQueue(
        TournamentSeason $season,
        TournamentLeague $division,
        int $slots,
        int $occurrenceId,
        int $roundNumber,
        string $initiatedBy
    ): array {
        $results = [];

        $queueTeams = TournamentLeagueTeam::where('league_id', $division->id)
            ->where('status', TournamentLeagueTeam::STATUS_RESERVE)
            ->where(function ($q) {
                $q->whereNull('confirmation_expires_at')
                  ->orWhere('confirmation_expires_at', '<=', now());
            })
            ->orderBy('reserve_position')
            ->limit($slots)
            ->get();

        foreach ($queueTeams as $team) {
            $team->status                    = TournamentLeagueTeam::STATUS_PENDING_CONFIRMATION;
            $team->confirmation_expires_at   = now()->addHours(2);
            $team->confirmation_token        = Str::random(48);
            $team->save();

            PromotionHistory::create([
                'season_id'        => $season->id,
                'occurrence_id'    => $occurrenceId,
                'round_number'     => $roundNumber,
                'league_team_id'   => $team->id,
                'user_id'          => $team->user_id,
                'team_id'          => $team->team_id,
                'from_division_id' => $division->id,
                'to_division_id'   => $division->id,
                'from_league_id'   => $season->league_id,
                'to_league_id'     => $season->league_id,
                'action'           => PromotionHistory::ACTION_ENTERED_QUEUE,
                'status'           => PromotionHistory::STATUS_PENDING,
                'initiated_by'     => $initiatedBy,
            ]);

            if ($team->user_id) {
                $this->sendPromotionNotification(
                    $team->user_id,
                    PromotionHistory::ACTION_ENTERED_QUEUE,
                    null,
                    $division->name
                );
            }

            $results[] = [
                'user_id'  => $team->user_id,
                'team_id'  => $team->team_id,
                'action'   => PromotionHistory::ACTION_ENTERED_QUEUE,
                'division' => $division->name,
            ];
        }

        return $results;
    }

    /**
     * Найти активную запись команды в дивизионе по team_id.
     */
    private function findLeagueTeamByTeamId(TournamentLeague $league, ?int $teamId): ?TournamentLeagueTeam
    {
        if (!$teamId) return null;

        return TournamentLeagueTeam::where('league_id', $league->id)
            ->where('team_id', $teamId)
            ->where('status', TournamentLeagueTeam::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Получить standings тура для конкретного дивизиона (через occurrence_id).
     * Берёт standings из последней стадии соответствующего event.
     *
     * @return Collection<TournamentStanding>
     */
    private function getStandingsForRound(TournamentLeague $division, int $occurrenceId): Collection
    {
        $seasonEvent = TournamentSeasonEvent::where('league_id', $division->id)
            ->where('occurrence_id', $occurrenceId)
            ->first();

        if (!$seasonEvent) {
            return collect();
        }

        $lastStage = TournamentStage::where('event_id', $seasonEvent->event_id)
            ->orderByDesc('sort_order')
            ->first();

        if (!$lastStage) {
            return collect();
        }

        return TournamentStanding::where('stage_id', $lastStage->id)
            ->orderBy('rank')
            ->get();
    }

    /**
     * Парсинг строки штрафного дедлайна: "saturday_07:00"
     */
    private function parsePenaltyDate(string $penalty): Carbon
    {
        [$day, $time]     = explode('_', $penalty, 2);
        [$hours, $minutes] = explode(':', $time, 2);

        return now()->next(ucfirst($day))->setTime((int) $hours, (int) $minutes);
    }

    // =========================================================================
    //  МЕТОДЫ ИЗ СТАРОГО СЕРВИСА (используются processEvent)
    // =========================================================================

    protected function getClassification(Event $event): Collection
    {
        $lastStage = $event->tournamentStages()
            ->where('status', TournamentStage::STATUS_COMPLETED)
            ->orderByDesc('sort_order')
            ->first();

        if (!$lastStage) return collect();

        if (in_array($lastStage->type, ['single_elim', 'double_elim'])) {
            return $this->classificationFromBracket($lastStage);
        }

        return TournamentStanding::where('stage_id', $lastStage->id)
            ->orderBy('rank')
            ->pluck('team_id');
    }

    protected function classificationFromBracket(TournamentStage $stage): Collection
    {
        $result = collect();

        foreach ($stage->matches()->where('status', 'completed')->orderByDesc('round')->orderBy('match_number')->get() as $match) {
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

    protected function updateSeasonStats(Event $event, TournamentSeason $season, TournamentLeague $league): void
    {
        $playerStats = \App\Models\PlayerTournamentStats::where('event_id', $event->id)->get();

        foreach ($playerStats as $ps) {
            $seasonStat = TournamentSeasonStats::firstOrCreate(
                ['season_id' => $season->id, 'league_id' => $league->id, 'user_id' => $ps->user_id],
                ['elo_season' => 1500],
            );

            $seasonStat->increment('rounds_played', 1);
            $seasonStat->increment('matches_played', $ps->matches_played);
            $seasonStat->increment('matches_won', $ps->matches_won);
            $seasonStat->increment('sets_won', $ps->sets_won);
            $seasonStat->increment('sets_lost', $ps->sets_lost);
            $seasonStat->increment('points_scored', $ps->points_scored);
            $seasonStat->increment('points_conceded', $ps->points_conceded);

            $totalPlayed = $seasonStat->matches_played;
            $totalWon    = $seasonStat->matches_won;
            $seasonStat->update([
                'match_win_rate' => $totalPlayed > 0 ? round($totalWon / $totalPlayed * 100, 2) : 0,
                'set_win_rate'   => ($seasonStat->sets_won + $seasonStat->sets_lost) > 0
                    ? round($seasonStat->sets_won / ($seasonStat->sets_won + $seasonStat->sets_lost) * 100, 2)
                    : 0,
            ]);

            if ($ps->matches_won > 0 && $ps->matches_played === $ps->matches_won) {
                $seasonStat->increment('current_streak', $ps->matches_won);
            } else {
                $seasonStat->update(['current_streak' => 0]);
            }

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

    private function tryNotifyPromotion(?EventTeam $team, Event $event, string $from, string $to): void
    {
        if (!$team) return;
        try {
            $this->notificationService->notifyPromotion($team, $event, $from, $to);
        } catch (\Throwable $e) {
            Log::warning("Promotion notification failed: " . $e->getMessage());
        }
    }

    private function tryNotifyElimination(?EventTeam $team, Event $event, string $leagueName, int $pos): void
    {
        if (!$team) return;
        try {
            $this->notificationService->notifyElimination($team, $event, $leagueName, $pos);
        } catch (\Throwable $e) {
            Log::warning("Elimination notification failed: " . $e->getMessage());
        }
    }

    private function tryNotifyActivated(?EventTeam $team, string $leagueName): void
    {
        if (!$team) return;
        try {
            $this->notificationService->notifyActivatedFromReserve($team, $leagueName);
        } catch (\Throwable $e) {
            Log::warning("Reserve activation notification failed: " . $e->getMessage());
        }
    }

    // =========================================================================
    //  УВЕДОМЛЕНИЯ ИГРОКАМ
    // =========================================================================

    private function sendPromotionNotification(
        int $userId,
        string $action,
        ?string $fromName,
        ?string $toName,
        ?PromotionHistory $history = null
    ): void {
        try {
            [$title, $body] = match ($action) {
                PromotionHistory::ACTION_RELEGATED_FEEDER  => [
                    __('tournaments.notification_relegated_feeder', ['league' => $toName ?? '']),
                    __('tournaments.notification_relegated_feeder', ['league' => $toName ?? ''])
                        . "\n" . __('tournaments.you_were_relegated', ['to' => $toName ?? ''])
                        . "\n" . __('tournaments.decline_transfer') . ' — ' . __('tournaments.confirm_decline'),
                ],
                PromotionHistory::ACTION_RELEGATED_LOWER   => [
                    __('tournaments.notification_relegated_reserve'),
                    __('tournaments.you_were_relegated', ['to' => $toName ?? __('tournaments.to_lower_division')]),
                ],
                PromotionHistory::ACTION_RELEGATED_RESERVE => [
                    __('tournaments.notification_relegated_reserve'),
                    __('tournaments.you_were_relegated', ['to' => __('tournaments.reserve')])
                        . ($toName ? " ({$toName})" : ''),
                ],
                PromotionHistory::ACTION_PROMOTED_UPPER    => [
                    __('tournaments.notification_promoted_upper', ['division' => $toName ?? '']),
                    __('tournaments.you_were_promoted', ['to' => $toName ?? '']),
                ],
                PromotionHistory::ACTION_PROMOTED_PARENT   => [
                    __('tournaments.notification_promoted_parent', ['league' => $toName ?? '']),
                    __('tournaments.you_were_promoted', ['to' => $toName ?? '']),
                ],
                PromotionHistory::ACTION_ENTERED_QUEUE     => [
                    __('tournaments.notification_from_queue'),
                    __('tournaments.you_were_promoted', ['to' => $toName ?? '']),
                ],
                default                                    => [
                    __('tournaments.notification_manual_move'),
                    __('tournaments.you_were_relegated', ['to' => $toName ?? '']),
                ],
            };

            $payload = [
                'action'           => $action,
                'from_division'    => $fromName,
                'to_division'      => $toName,
                'promotion_history_id' => $history?->id,
            ];

            app(UserNotificationService::class)->create(
                userId: $userId,
                type: 'promotion',
                title: $title,
                body: $body,
                payload: $payload,
                channels: ['in_app', 'telegram', 'vk', 'max']
            );
        } catch (\Throwable $e) {
            Log::warning("Promotion notification failed for user #{$userId}: " . $e->getMessage());
        }
    }

    // =========================================================================
    //  КОНСТАНТЫ ИНИЦИАТОРОВ (для удобства вызывающего кода)
    // =========================================================================

    public const INITIATED_SYSTEM    = 'system';
    public const INITIATED_ORGANIZER = 'organizer';
    public const INITIATED_ADMIN     = 'admin';
    public const INITIATED_USER      = 'user';
}
