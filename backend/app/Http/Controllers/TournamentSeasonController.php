<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\League;
use App\Models\PromotionHistory;
use App\Models\TournamentSeason;
use App\Models\TournamentLeague;
use App\Models\TournamentLeagueTeam;
use App\Models\TournamentSeasonEvent;
use App\Services\TournamentSeasonService;
use App\Services\TournamentLeagueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentSeasonController extends Controller
{
    public function __construct(
        private TournamentSeasonService $seasonService,
        private TournamentLeagueService $leagueService,
    ) {}

    /* ================================================================
     *  Список сезонов организатора
     * ================================================================ */

    public function index(Request $request)
    {
        return redirect()->route('leagues.index');
    }

    /* ================================================================
     *  Создание сезона — форма
     * ================================================================ */

    public function create(Request $request, League $league)
    {
        $this->authorizeLeague($request, $league);
        return view('seasons.create', compact('league'));
    }

    /* ================================================================
     *  Создание сезона — сохранение
     * ================================================================ */

    public function store(Request $request, League $league)
    {
        $this->authorizeLeague($request, $league);

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',
        ]);

        // direction берём из лиги
        $validated['direction'] = $league->direction;
        $validated['league_id'] = $league->id;

        $season = $this->seasonService->createSeason($request->user(), $validated);

        return redirect()
            ->route('seasons.edit', $season)
            ->with('success', 'Сезон создан. Добавьте дивизионы.');
    }

    /* ================================================================
     *  Редактирование сезона (+ управление лигами, привязка турниров)
     * ================================================================ */

    public function edit(Request $request, TournamentSeason $season)
    {
        $this->authorizeSeason($request, $season);

        $season->load([
            'leagues.activeTeams.team.captain',
            'leagues.activeTeams.user',
            'leagues.reserveTeams.team.captain',
            'leagues.reserveTeams.user',
            'seasonEvents.event',
            'seasonEvents.league',
            'league.feederLeague',
        ]);

        // Доступные события организатора (не привязанные к сезону)
        $availableEvents = Event::where('organizer_id', $request->user()->id)
            ->whereDoesntHave('seasonEvent')
            ->where('format', 'tournament')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $promotionHistory = \App\Models\PromotionHistory::where('season_id', $season->id)
            ->with(['user', 'fromDivision', 'toDivision'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('seasons.edit', compact('season', 'availableEvents', 'promotionHistory'));
    }

    /* ================================================================
     *  Обновление сезона
     * ================================================================ */

    public function update(Request $request, TournamentSeason $season)
    {
        $this->authorizeSeason($request, $season);

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'direction' => 'required|in:classic,beach',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',
            'config'    => 'nullable|array',
        ]);

        // Обработка config промоушена
        $promotionConfigFields = [
            'bool'   => ['auto_promotion', 'queue_entry_enabled', 'auto_sync_after_promotion'],
            'int'    => ['queue_entry_slots', 'feeder_promote_slots', 'auto_sync_delay_hours'],
            'string' => ['promotion_trigger', 'relegation_penalty'],
        ];
        $config = $season->config ?? [];
        foreach ($promotionConfigFields as $type => $fields) {
            foreach ($fields as $field) {
                if ($request->has("config.{$field}")) {
                    $val = $request->input("config.{$field}");
                    $config[$field] = match ($type) {
                        'bool'   => (bool) $val,
                        'int'    => (int) $val,
                        default  => ($val === '' ? null : $val),
                    };
                } elseif ($type === 'bool') {
                    // Чекбоксы не отправляются если unchecked
                    $config[$field] = false;
                }
            }
        }
        $validated['config'] = $config;

        $this->seasonService->updateSeason($season, $validated);

        // Сохранение настроек дивизионов
        if ($request->has('divisions')) {
            foreach ($request->input('divisions') as $divisionId => $data) {
                $division = \App\Models\TournamentLeague::find((int) $divisionId);
                if (!$division || $division->season_id !== $season->id) continue;

                if (isset($data['name']) && trim($data['name']) !== '') {
                    $division->name = trim($data['name']);
                }
                if (isset($data['max_teams'])) {
                    $division->max_teams = $data['max_teams'] !== '' ? (int) $data['max_teams'] : null;
                }
                if (isset($data['level'])) {
                    $division->level = (int) $data['level'];
                }

                $divConfig = $division->config ?? [];
                foreach (['eliminate_count', 'promote_count'] as $intField) {
                    if (isset($data['config'][$intField])) {
                        $divConfig[$intField] = (int) $data['config'][$intField];
                    }
                }
                foreach (['eliminate_to', 'promote_to'] as $strField) {
                    if (isset($data['config'][$strField])) {
                        $divConfig[$strField] = $data['config'][$strField] ?: null;
                    }
                }
                $division->config = $divConfig;
                $division->save();
            }
        }

        return back()->with('success', 'Сезон обновлён.');
    }

    /* ================================================================
     *  Активировать / Завершить сезон
     * ================================================================ */

    public function activate(Request $request, TournamentSeason $season)
    {
        $this->authorizeSeason($request, $season);

        try {
            $this->seasonService->activate($season);
            return back()->with('success', 'Сезон активирован.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(Request $request, TournamentSeason $season)
    {
        $this->authorizeSeason($request, $season);

        $this->seasonService->complete($season);
        return back()->with('success', 'Сезон завершён.');
    }

    /* ================================================================
     *  ЛИГИ — CRUD
     * ================================================================ */

    public function storeLeague(Request $request, TournamentSeason $season)
    {
        $this->authorizeSeason($request, $season);

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'max_teams' => 'nullable|integer|min:2',
            'config'    => 'nullable|array',
            'config.promote_count'  => 'nullable|integer|min:0',
            'config.relegate_count' => 'nullable|integer|min:0',
            'config.eliminate_count' => 'nullable|integer|min:0',
            'config.promote_to'     => 'nullable|string',
            'config.reserve_absorb' => 'nullable|boolean',
        ]);

        $this->leagueService->createLeague($season, $validated);

        return back()->with('success', "Лига «{$validated['name']}» создана.");
    }

    public function updateLeague(Request $request, TournamentLeague $league)
    {
        $this->authorizeSeason($request, $league->season);

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'max_teams' => 'nullable|integer|min:2',
            'config'    => 'nullable|array',
        ]);

        $this->leagueService->updateLeague($league, $validated);

        return back()->with('success', 'Лига обновлена.');
    }

    public function destroyLeague(Request $request, TournamentLeague $league)
    {
        $this->authorizeSeason($request, $league->season);

        try {
            $this->leagueService->deleteLeague($league);
            return back()->with('success', 'Лига удалена.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* ================================================================
     *  КОМАНДЫ В ЛИГЕ
     * ================================================================ */

    public function addTeamToLeague(Request $request, TournamentLeague $league)
    {
        $this->authorizeSeason($request, $league->season);

        $validated = $request->validate([
            'team_id' => 'nullable|exists:event_teams,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        try {
            $team = isset($validated['team_id'])
                ? \App\Models\EventTeam::find($validated['team_id'])
                : null;
            $user = isset($validated['user_id'])
                ? \App\Models\User::find($validated['user_id'])
                : null;

            $entry = $this->leagueService->addTeam($league, $team, $user);

            $statusMsg = $entry->isReserve()
                ? "Добавлено в резерв (позиция #{$entry->reserve_position})."
                : 'Команда добавлена в лигу.';

            return back()->with('success', $statusMsg);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function createAndAddToLeague(Request $request, TournamentLeague $league)
    {
        $this->authorizeSeason($request, $league->season);

        $validated = $request->validate([
            'captain_user_id' => 'required|exists:users,id',
            'partner_user_id' => 'nullable|exists:users,id|different:captain_user_id',
            'name'            => 'nullable|string|max:255',
            'target_status'   => 'required|in:active,reserve',
            'occurrence_id'   => 'nullable|integer|exists:event_occurrences,id',
        ]);

        $occurrenceId = !empty($validated['occurrence_id']) ? (int) $validated['occurrence_id'] : null;

        // Находим event через occurrence (точнее), fallback — первый event сезона
        $seasonEvent = $occurrenceId
            ? \App\Models\TournamentSeasonEvent::where('occurrence_id', $occurrenceId)->first()
            : \App\Models\TournamentSeasonEvent::where('season_id', $league->season_id)->first();
        $event = $seasonEvent ? \App\Models\Event::find($seasonEvent->event_id) : null;

        if (!$event) {
            return back()->with('error', 'Событие не найдено.');
        }

        $captain = \App\Models\User::findOrFail($validated['captain_user_id']);

        $teamName = trim($validated['name'] ?? '');
        if (empty($teamName)) {
            $teamName = 'Команда ' . ($captain->last_name ?: $captain->first_name ?: $captain->name);
        }

        try {
            $teamService = app(\App\Services\TournamentTeamService::class);
            $team = $teamService->createTeam(
                event: $event,
                captain: $captain,
                name: $teamName,
                occurrenceId: $occurrenceId,
                autoApprove: true,
            );

            // Добавить партнёра (пляжный формат)
            if (!empty($validated['partner_user_id'])) {
                $partner = \App\Models\User::find($validated['partner_user_id']);
                if ($partner) {
                    \App\Models\EventTeamMember::create([
                        'event_team_id'        => $team->id,
                        'user_id'              => $partner->id,
                        'role_code'            => 'player',
                        'team_role'            => 'player',
                        'confirmation_status'  => 'confirmed',
                        'position_order'       => 2,
                        'invited_by_user_id'   => $request->user()->id,
                        'joined_at'            => now(),
                        'responded_at'         => now(),
                        'confirmed_at'         => now(),
                    ]);
                    // Обновляем is_complete после добавления партнёра
                    $team = $teamService->refreshTeamState($team->fresh());
                }
            }

            if ($validated['target_status'] === 'active' && $league->max_teams && !$league->hasCapacity()) {
                $current = $league->activeTeams()->count();
                throw new \InvalidArgumentException(
                    "Дивизион заполнен ({$current}/{$league->max_teams} команд в основном составе). Добавьте в резерв."
                );
            }

            $entry = $this->leagueService->addTeam($league, $team, forceStatus: $validated['target_status']);

            $msg = $entry->isReserve()
                ? "Команда «{$team->name}» добавлена в резерв (позиция #{$entry->reserve_position})."
                : "Команда «{$team->name}» добавлена в основной состав.";

            $setupUrl = route('tournament.setup', $event)
                . ($occurrenceId ? '?occurrence_id=' . $occurrenceId : '');
            return redirect($setupUrl)->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function removeTeamFromLeague(Request $request, TournamentLeagueTeam $leagueTeam)
    {
        $this->authorizeSeason($request, $leagueTeam->league->season);

        $this->leagueService->removeTeam($leagueTeam);

        return back()->with('success', 'Команда убрана из лиги.');
    }

    /* ================================================================
     *  УПРАВЛЕНИЕ СТАТУСОМ КОМАНДЫ В ЛИГЕ
     * ================================================================ */

    public function toReserve(Request $request, TournamentLeagueTeam $leagueTeam)
    {
        $this->authorizeSeason($request, $leagueTeam->league->season);

        $nextPos = $leagueTeam->league->nextReservePosition();
        $leagueTeam->update([
            'status'           => TournamentLeagueTeam::STATUS_RESERVE,
            'left_at'          => now(),
            'reserve_position' => $nextPos,
        ]);

        return back()->with('success', 'Команда переведена в резерв.');
    }

    public function moveReserve(Request $request, TournamentLeagueTeam $leagueTeam)
    {
        $this->authorizeSeason($request, $leagueTeam->league->season);

        if ($leagueTeam->status !== 'reserve') {
            return back();
        }

        $direction = $request->input('direction'); // 'up' | 'down'
        $op        = $direction === 'up' ? '<' : '>';
        $order     = $direction === 'up' ? 'desc' : 'asc';

        $neighbor = TournamentLeagueTeam::where('league_id', $leagueTeam->league_id)
            ->where('status', 'reserve')
            ->where('reserve_position', $op, $leagueTeam->reserve_position)
            ->orderBy('reserve_position', $order)
            ->first();

        if (!$neighbor) {
            return back();
        }

        $myPos            = $leagueTeam->reserve_position;
        $neighborPos      = $neighbor->reserve_position;
        $leagueTeam->reserve_position = $neighborPos;
        $neighbor->reserve_position   = $myPos;
        $leagueTeam->save();
        $neighbor->save();

        return back();
    }

    public function activateLeagueTeam(Request $request, TournamentLeagueTeam $leagueTeam)
    {
        $this->authorizeSeason($request, $leagueTeam->league->season);

        $leagueTeam->activateFromReserve();

        // Привязываем к текущему туру: создаём EventTeam если нужно
        $occurrenceId = (int) $request->input('occurrence_id', 0);
        if ($occurrenceId > 0) {
            $this->ensureEventTeamForOccurrence($leagueTeam, $occurrenceId);
        }

        return back()->with('success', 'Команда активирована.');
    }

    private function ensureEventTeamForOccurrence(TournamentLeagueTeam $leagueTeam, int $occurrenceId): void
    {
        $occurrence = \App\Models\EventOccurrence::find($occurrenceId);
        if (!$occurrence) return;

        $existingTeam = $leagueTeam->team;

        // Уже правильный тур — ничего не делать
        if ($existingTeam && (int) $existingTeam->occurrence_id === $occurrenceId) return;

        // Определяем параметры новой команды
        $captainId = $existingTeam?->captain_user_id ?? $leagueTeam->user_id;
        if (!$captainId) return;

        $captain = \App\Models\User::find($captainId);
        if (!$captain) return;

        $name = $existingTeam?->name
            ?? ($captain->last_name ?: ($captain->first_name ?: $captain->name));

        // Ищем уже существующую команду этого капитана для этого тура
        $teamForOcc = \App\Models\EventTeam::where('event_id', $occurrence->event_id)
            ->where('occurrence_id', $occurrenceId)
            ->where('captain_user_id', $captainId)
            ->first();

        if (!$teamForOcc) {
            // Создаём новую команду для тура
            $baseName = $name;
            $finalName = $baseName;
            $i = 2;
            while (\App\Models\EventTeam::where('event_id', $occurrence->event_id)
                ->where('occurrence_id', $occurrenceId)
                ->where('name', $finalName)->exists()) {
                $finalName = $baseName . ' ' . $i++;
            }

            $teamForOcc = \App\Models\EventTeam::create([
                'event_id'        => $occurrence->event_id,
                'occurrence_id'   => $occurrenceId,
                'captain_user_id' => $captainId,
                'name'            => $finalName,
                'team_kind'       => $existingTeam?->team_kind ?? 'beach_pair',
                'status'          => 'approved',
                'invite_code'     => \Illuminate\Support\Str::random(8),
                'is_complete'     => false,
                'last_checked_at' => now(),
                'confirmed_at'    => now(),
            ]);
        }

        // Обновляем ссылку в league team
        $leagueTeam->update(['team_id' => $teamForOcc->id]);
    }

    /* ================================================================
     *  ПРИВЯЗКА ТУРНИРОВ К СЕЗОНУ
     * ================================================================ */

    public function attachEvent(Request $request, TournamentSeason $season)
    {
        $this->authorizeSeason($request, $season);

        $validated = $request->validate([
            'event_id'     => 'required|exists:events,id',
            'league_id'    => 'required|exists:tournament_leagues,id',
            'round_number' => 'nullable|integer|min:1',
        ]);

        $event  = Event::findOrFail($validated['event_id']);
        $league = TournamentLeague::findOrFail($validated['league_id']);

        try {
            $this->seasonService->attachEvent($season, $league, $event, $validated['round_number'] ?? null);
            return back()->with('success', 'Турнир привязан к сезону.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function detachEvent(Request $request, TournamentSeason $season, Event $event)
    {
        $this->authorizeSeason($request, $season);

        $this->seasonService->detachEvent($season, $event);
        return back()->with('success', 'Турнир отвязан от сезона.');
    }

    /* ================================================================
     *  Публичная страница сезона
     * ================================================================ */

    public function show(TournamentSeason $season)
    {
        $season->load([
            'organizer',
            'league',
            'leagues.activeTeams.team.captain',
            'leagues.activeTeams.team.members.user',
            'leagues.activeTeams.user',
            'leagues.reserveTeams.team.captain',
            'leagues.reserveTeams.team.members.user',
            'seasonEvents.event',
            'seasonEvents.league',
            'stats' => fn($q) => $q->orderByDesc('match_win_rate'),
        ]);

        // Загружаем только те occurrences, которые привязаны к данному сезону
        $sourceEvent = $season->seasonEvents->first()?->event;
        $occurrences = collect();
        if ($sourceEvent) {
            $seasonOccurrenceIds = $season->seasonEvents->pluck('occurrence_id')->filter();
            $occurrences = $sourceEvent->occurrences()
                ->whereIn('id', $seasonOccurrenceIds)
                ->whereNull('cancelled_at')
                ->orderBy('starts_at')
                ->get();
        }

        return view('seasons.show', compact('season', 'occurrences', 'sourceEvent'));
    }

    /**
     * Публичная страница по slug.
     */
    public function showBySlug(string $leagueSlug, string $slug)
    {
        $season = TournamentSeason::where('slug', $slug)->firstOrFail();
        return $this->show($season);
    }

    /* ================================================================
     *  Удаление сезона (только draft)
     * ================================================================ */

    public function destroy(Request $request, TournamentSeason $season)
    {
        $this->authorizeSeason($request, $season);

        try {
            $this->seasonService->deleteSeason($season);
            return redirect()->route('leagues.index')->with('success', 'Сезон удалён.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* ================================================================
     *  Ручное управление командами (вылет / перевод / активация)
     * ================================================================ */

    public function relegateTeam(Request $request, TournamentSeason $season, \App\Models\TournamentLeagueTeam $leagueTeam)
    {
        $this->authorizeSeason($request, $season);
        abort_unless($leagueTeam->league->season_id === $season->id, 403);

        $target = $request->input('target', 'reserve'); // 'reserve' | 'feeder' | 'lower_division'

        $toDivision = match ($target) {
            'lower_division' => $leagueTeam->league->lowerDivision() ?? $leagueTeam->league,
            'feeder'         => $this->getFeederDivision($season) ?? $leagueTeam->league,
            default          => $leagueTeam->league,
        };
        $newStatus = 'reserve';

        app(\App\Services\TournamentPromotionService::class)->manualMove(
            $season, $leagueTeam, $toDivision, $newStatus, 'organizer'
        );

        return back()->with('success', __('tournaments.team_relegated'));
    }

    public function transferTeam(Request $request, TournamentSeason $season, \App\Models\TournamentLeagueTeam $leagueTeam)
    {
        $this->authorizeSeason($request, $season);
        abort_unless($leagueTeam->league->season_id === $season->id, 403);

        $request->validate(['to_division_id' => 'required|integer']);
        $toDivision = \App\Models\TournamentLeague::where('season_id', $season->id)
            ->findOrFail((int) $request->input('to_division_id'));

        app(\App\Services\TournamentPromotionService::class)->manualMove(
            $season, $leagueTeam, $toDivision, 'active', 'organizer'
        );

        return back()->with('success', __('tournaments.team_transferred'));
    }

    public function activateTeam(Request $request, TournamentSeason $season, \App\Models\TournamentLeagueTeam $leagueTeam)
    {
        $this->authorizeSeason($request, $season);
        abort_unless($leagueTeam->league->season_id === $season->id, 403);

        $leagueTeam->status                  = \App\Models\TournamentLeagueTeam::STATUS_ACTIVE;
        $leagueTeam->reserve_position        = null;
        $leagueTeam->confirmation_expires_at = null;
        $leagueTeam->joined_at               = now();
        $leagueTeam->save();

        return back()->with('success', __('tournaments.team_activated'));
    }

    /* ================================================================
     *  Выполнить промоушен вручную
     * ================================================================ */

    public function executePromotion(Request $request, TournamentSeason $season)
    {
        $this->authorizeSeason($request, $season);

        $request->validate([
            'occurrence_id' => 'required|integer',
            'round_number'  => 'required|integer|min:1',
        ]);

        $roundNumber  = (int) $request->input('round_number');
        $occurrenceId = (int) $request->input('occurrence_id');

        $results = app(\App\Services\TournamentPromotionService::class)->process(
            $season,
            $occurrenceId,
            $roundNumber,
            'organizer'
        );

        $totalMoves = count($results['relegated'])
            + count($results['promoted'])
            + count($results['entered_from_queue'])
            + count($results['entered_from_feeder']);

        if (!empty($results['errors'])) {
            \Illuminate\Support\Facades\Log::warning('Promotion errors', $results['errors']);
        }

        // Автосинхронизация состава дивизионов в следующий тур
        if ($season->isAutoSync() && $totalMoves > 0) {
            $delayHours = $season->getAutoSyncDelayHours();
            \App\Jobs\SyncLeagueTeamsToNextOccurrenceJob::dispatch($season->id, $roundNumber)
                ->delay(now()->addHours($delayHours));
        }

        return back()->with('success', __('tournaments.promotion_completed', ['count' => $totalMoves]));
    }

    /* ================================================================
     *  Отказ от перевода (игрок)
     * ================================================================ */

    public function declineTransfer(Request $request, PromotionHistory $promotionHistory)
    {
        $user = $request->user();
        abort_unless((int) $promotionHistory->user_id === (int) $user->id, 403);

        abort_unless(in_array($promotionHistory->action, [
            PromotionHistory::ACTION_RELEGATED_FEEDER,
            PromotionHistory::ACTION_PROMOTED_UPPER,
            PromotionHistory::ACTION_PROMOTED_PARENT,
        ], true), 422, 'Этот перевод нельзя отменить.');

        abort_unless($promotionHistory->status === PromotionHistory::STATUS_COMPLETED, 422, 'Статус перевода не позволяет отказаться.');

        abort_unless(
            $promotionHistory->created_at->diffInDays(now()) <= 7,
            422,
            __('tournaments.decline_expired')
        );

        $leagueTeam = $promotionHistory->leagueTeam;
        abort_unless($leagueTeam, 404);

        app(\App\Services\TournamentPromotionService::class)->declineTransfer($leagueTeam, $promotionHistory);

        return back()->with('success', __('tournaments.transfer_declined'));
    }

    /* ================================================================
     *  Вспомогательный метод — получить первый дивизион фидерной лиги
     * ================================================================ */

    private function getFeederDivision(TournamentSeason $season): ?\App\Models\TournamentLeague
    {
        $league = $season->league;
        if (!$league || !$league->hasFeeder()) return null;

        $feederSeason = $league->feederLeague->seasons()
            ->where('status', 'active')
            ->latest('starts_at')
            ->first();

        return $feederSeason?->leagues()->orderBy('level')->first();
    }

    /* ================================================================
     *  Auth helper
     * ================================================================ */

    private function authorizeSeason(Request $request, TournamentSeason $season): void
    {
        $user = $request->user();
        if ($season->organizer_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Вы не являетесь организатором этого сезона.');
        }
    }

    private function authorizeLeague(Request $request, League $league): void
    {
        $user = $request->user();
        if ($league->organizer_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Вы не являетесь владельцем этой лиги.');
        }
    }
}
