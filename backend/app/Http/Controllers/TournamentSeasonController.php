<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\League;
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
        ]);

        // Доступные события организатора (не привязанные к сезону)
        $availableEvents = Event::where('organizer_id', $request->user()->id)
            ->whereDoesntHave('seasonEvent')
            ->where('format', 'tournament')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('seasons.edit', compact('season', 'availableEvents'));
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

        $this->seasonService->updateSeason($season, $validated);

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

    public function activateLeagueTeam(Request $request, TournamentLeagueTeam $leagueTeam)
    {
        $this->authorizeSeason($request, $leagueTeam->league->season);

        $leagueTeam->activateFromReserve();

        return back()->with('success', 'Команда активирована.');
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

        // Загружаем occurrences основного event
        $sourceEvent = $season->seasonEvents->first()?->event;
        $occurrences = collect();
        if ($sourceEvent) {
            $occurrences = $sourceEvent->occurrences()
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
