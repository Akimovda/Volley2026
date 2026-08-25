<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\TournamentSeasonEvent;
use App\Models\TournamentStage;
use App\Models\TournamentMatch;
use App\Models\TournamentStanding;
use App\Models\PlayerTournamentStats;
use App\Services\PlayerMatchStatsService;
use App\Services\MatchProgressService;
use Illuminate\Http\Request;

class TournamentPublicController extends Controller
{
    /**
     * Live-фрагмент ленты "ход матча" одного матча — рендерит тот же партиал,
     * что и первичная загрузка show(), для честной подмены innerHTML на клиенте
     * по WebSocket-сигналу TournamentMatchRallyUpdated (см. tournaments/public/show.blade.php).
     */
    public function matchProgressFragment(Event $event, TournamentMatch $match)
    {
        abort_unless($match->stage->event_id === $event->id, 404);

        $matchProgress = app(MatchProgressService::class)->build($match->id);

        return view('tournaments._partials.match_progress_fragment', [
            'matchProgress' => $matchProgress,
            'match'         => $match,
            'event'         => $event,
        ]);
    }

    /**
     * Главная публичная страница турнира (табы).
     */
    public function show(Request $request, Event $event)
    {
        $tab = $request->query('tab', 'overview');

        // Occurrence selector — для сезонных турниров и для несезонных (в т.ч.
        // повторяющихся серий) одинаково: чтение ?occurrence_id= раньше было
        // заперто внутри if(season_id), из-за чего несезонные турниры (напр.
        // event 561: 105 occurrences, tournament_stages есть только у 3) не
        // могли переключать тур вообще — $stages всегда тянул ВСЕ стадии всех
        // туров сразу на каждую вкладку (см. report/tabs-fix-recon-A.md).
        $occurrences = collect();
        $selectedOccurrence = null;
        $currentSeason = null;
        if ($event->season_id) {
            $occurrences = $event->occurrences()->orderBy('starts_at')->get();

            // Фильтр по сезону (когда ссылка пришла со страницы конкретного сезона)
            $seasonIdFilter = (int) $request->query('season_id');
            if ($seasonIdFilter) {
                $seasonOccIds = TournamentSeasonEvent::where('season_id', $seasonIdFilter)
                    ->where('event_id', $event->id)
                    ->pluck('occurrence_id');
                $occurrences = $occurrences->whereIn('id', $seasonOccIds->all())->values();
                $currentSeason = \App\Models\TournamentSeason::find($seasonIdFilter);
            }
        } else {
            // Несезонный турнир: список туров строим ТОЛЬКО из occurrences, где
            // реально есть tournament_stages — у повторяющейся серии могут быть
            // десятки/сотни будущих occurrences без жеребьёвки, показывать/
            // выбирать их бессмысленно (и дефолт "последний occurrence события"
            // упёрся бы в пустой тур без единой стадии).
            // pluck()->unique() вместо SQL DISTINCT — tournamentStages() несёт
            // встроенный orderBy('sort_order') (Event::tournamentStages()),
            // а PostgreSQL требует ORDER BY-колонку в SELECT при DISTINCT.
            $stageOccurrenceIds = $event->tournamentStages()
                ->whereNotNull('occurrence_id')
                ->pluck('occurrence_id')
                ->unique();
            if ($stageOccurrenceIds->isNotEmpty()) {
                $occurrences = $event->occurrences()
                    ->whereIn('id', $stageOccurrenceIds->all())
                    ->orderBy('starts_at')
                    ->get();
            }
        }

        $occId = $request->query('occurrence_id');
        if ($occId) {
            $selectedOccurrence = $occurrences->firstWhere('id', $occId);
        }
        if (!$selectedOccurrence && $occurrences->isNotEmpty()) {
            // Сезонные — как и раньше, первый тур серии (список туров там —
            // ВСЕ occurrences, не только со стадиями, порядок не менялся).
            // Несезонные — последний СО стадиями ($occurrences уже отфильтрован
            // выше, list отсортирован по starts_at asc → last() = самый свежий
            // сыгранный/играемый тур), не последний occurrence события.
            $selectedOccurrence = $event->season_id ? $occurrences->first() : $occurrences->last();
        }

        // Определяем сезон по occurrence если season_id не передан явно
        if ($event->season_id && !$currentSeason && $selectedOccurrence) {
            $se = TournamentSeasonEvent::where('event_id', $event->id)
                ->where('occurrence_id', $selectedOccurrence->id)
                ->first();
            if ($se) {
                $currentSeason = \App\Models\TournamentSeason::find($se->season_id);
            }
        }

        $stages = $event->tournamentStages()
            ->when($selectedOccurrence, fn($q) => $q->where('occurrence_id', $selectedOccurrence->id))
            ->with([
                'groups.teams',
                'groups.standings' => fn($q) => $q->with('team.members.user', 'team.captain')->orderBy('rank'),
                'matches' => fn($q) => $q->with(['teamHome.members.user', 'teamHome.captain', 'teamAway.members.user', 'teamAway.captain', 'winner'])
                    ->orderBy('round')->orderBy('match_number'),
            ])
            ->orderBy('sort_order')
            ->get();

        $setting = \DB::table('event_tournament_settings')
            ->where('event_id', $event->id)->first();

        // Общая статистика
        $totalMatches = TournamentMatch::whereIn('stage_id', $stages->pluck('id'))
            ->where('status', 'completed')->count();

        $totalTeams = \DB::table('event_teams')
            ->where('event_id', $event->id)
            ->whereIn('status', ['approved', 'submitted'])->count();

        // Season stats
        $seasonStats = collect();
        if ($event->season_id) {
            $seasonStats = \App\Models\TournamentSeasonStats::where('season_id', $event->season_id)
                ->with('user')
                ->orderByDesc('match_win_rate')
                ->get();
        }

        // Детальная статистика по игрокам (эйсы/ошибки/блоки) — только для завершённых матчей, если заполнена
        $completedMatches = $stages->flatMap->matches->where('status', TournamentMatch::STATUS_COMPLETED)->values();
        $matchStatsByMatchId = app(PlayerMatchStatsService::class)->getMatchStatsTableForMatches($completedMatches);

        // Ход матча (лента розыгрышей) — ТАКЖЕ для матчей в статусе live, не только
        // completed: данные в match_rally_events пишутся синхронно по ходу игры,
        // раньше лента была видна только после завершения ВСЕГО матча (может идти
        // час+), хотя реальной задержки в данных нет — это был чисто UI-фильтр.
        $progressMatches = $stages->flatMap->matches
            ->whereIn('status', [TournamentMatch::STATUS_COMPLETED, TournamentMatch::STATUS_LIVE])
            ->values();
        $matchProgressByMatchId = app(MatchProgressService::class)->buildForMatches($progressMatches);

        return view('tournaments.public.show', compact(
            'event', 'stages', 'tab', 'setting', 'totalMatches', 'totalTeams', 'occurrences', 'selectedOccurrence', 'seasonStats', 'currentSeason', 'matchStatsByMatchId', 'matchProgressByMatchId'
        ));
    }

    /**
     * JSON endpoint для live-обновления (polling).
     */
    public function liveData(Request $request, Event $event)
    {
        // Occurrence-скоуп — тот же подход, что в show()/tv()/pdf*() (29018cdd,
        // 5c0904f4, 474f16fc). Фронтовый polling (show.blade.php, tv.blade.php)
        // пока не передаёт ?occurrence_id= — см. report/tv-pdf-recon.md,
        // доработка фронта отдельным тикетом; здесь закрывается скоуп на бэке.
        $occurrences = collect();
        $selectedOccurrence = null;
        if ($event->season_id) {
            $occurrences = $event->occurrences()->orderBy('starts_at')->get();
        } else {
            $stageOccurrenceIds = $event->tournamentStages()
                ->whereNotNull('occurrence_id')
                ->pluck('occurrence_id')
                ->unique();
            if ($stageOccurrenceIds->isNotEmpty()) {
                $occurrences = $event->occurrences()
                    ->whereIn('id', $stageOccurrenceIds->all())
                    ->orderBy('starts_at')
                    ->get();
            }
        }

        $occId = $request->query('occurrence_id');
        if ($occId) {
            $selectedOccurrence = $occurrences->firstWhere('id', $occId);
        }
        if (!$selectedOccurrence && $occurrences->isNotEmpty()) {
            $selectedOccurrence = $event->season_id ? $occurrences->first() : $occurrences->last();
        }

        $stages = $event->tournamentStages()
            ->when($selectedOccurrence, fn($q) => $q->where('occurrence_id', $selectedOccurrence->id))
            ->with([
                'groups.standings' => fn($q) => $q->with('team.members.user')->orderBy('rank'),
                'matches' => fn($q) => $q->with(['teamHome.members.user', 'teamAway.members.user', 'winner'])
                    ->orderBy('round')->orderBy('match_number'),
            ])
            ->orderBy('sort_order')
            ->get();

        $data = [];
        foreach ($stages as $stage) {
            $stageData = [
                'id'     => $stage->id,
                'name'   => $stage->name,
                'status' => $stage->status,
                'groups' => [],
                'matches' => [],
            ];

            foreach ($stage->groups as $group) {
                $stageData['groups'][] = [
                    'id'   => $group->id,
                    'name' => $group->name,
                    'standings' => $group->standings->map(fn($s) => [
                        'rank'           => $s->rank,
                        'team'           => $s->team->name ?? '—',
                        'played'         => $s->played,
                        'wins'           => $s->wins,
                        'losses'         => $s->losses,
                        'sets'           => $s->sets_won . ':' . $s->sets_lost,
                        'rating_points'  => $s->rating_points,
                    ]),
                ];
            }

            foreach ($stage->matches as $match) {
                $stageData['matches'][] = [
                    'id'          => $match->id,
                    'round'       => $match->round,
                    'match_number' => $match->match_number,
                    'home'        => $match->teamHome->name ?? 'TBD',
                    'away'        => $match->teamAway->name ?? 'TBD',
                    'score'       => $match->setsScore(),
                    'status'      => $match->status,
                    'winner_home' => $match->winner_team_id === $match->team_home_id,
                    'winner_away' => $match->winner_team_id === $match->team_away_id,
                ];
            }

            $data[] = $stageData;
        }

        return response()->json(['stages' => $data]);
    }

    /**
     * Страница bracket (SVG) для конкретной стадии.
     */
    public function bracket(Request $request, Event $event, TournamentStage $stage)
    {
        if ((int) $stage->event_id !== (int) $event->id) {
            abort(404);
        }

        $matches = $stage->matches()
            ->with(['teamHome', 'teamAway', 'winner'])
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();

        // Определяем кол-во раундов
        $totalRounds = $matches->max('round') ?? 0;

        return view('tournaments.public.bracket', compact('event', 'stage', 'matches', 'totalRounds'));
    }

    /**
     * Публичная страница состава команды (без авторизации).
     */
    public function teamRoster(Request $request, Event $event, EventTeam $team)
    {
        abort_unless((int) $team->event_id === (int) $event->id, 404);

        $team->load(['captain', 'members.user']);

        // Резерв не учитывается в среднем уровне/рейтинге команды — тот же принцип,
        // что и у PlayerQualityService::forOccurrence() (там ->where('position', '!=', 'reserve')).
        $ratingMembers = $team->members
            ->where('confirmation_status', 'confirmed')
            ->filter(fn ($m) => $m->user && $m->effective_team_role !== 'reserve');

        $teamLevel = null;
        $teamRating = null;

        if ($team->team_kind === 'classic_team') {
            $players = $ratingMembers
                ->filter(fn ($m) => !is_null($m->user->classic_level))
                ->map(fn ($m) => ['level' => (int) $m->user->classic_level, 'is_female' => $m->user->gender === 'f'])
                ->values()->all();

            $teamLevel = app(\App\Services\PlayerQualityService::class)->compute($players);
        } else {
            $userIds = $ratingMembers->pluck('user_id');
            $stats = \App\Models\PlayerCareerStats::whereIn('user_id', $userIds)->where('direction', 'beach')->get();

            if ($stats->isNotEmpty()) {
                $teamRating = round($stats->avg(fn ($s) => $s->conservativeRating()), 1);
            }
        }

        return view('tournaments.public.team', compact('event', 'team', 'teamLevel', 'teamRating'));
    }

    /**
     * Публичная страница всех турниров организатора.
     */
    public function organizerTournaments(Request $request, int $organizerId)
    {
        $organizer = \App\Models\User::findOrFail($organizerId);

        $tournaments = Event::where('organizer_id', $organizerId)
            ->where('format', 'tournament')
            ->whereHas('tournamentStages')
            ->with([
                'location:id,name',
                'tournamentStages' => fn($q) => $q->withCount('matches'),
            ])
            ->orderByDesc('starts_at')
            ->paginate(20);

        // Сводная статистика
        $eventIds = Event::where('organizer_id', $organizerId)
            ->where('format', 'tournament')
            ->pluck('id');

        $totalMatches = \App\Models\TournamentMatch::whereHas('stage', fn($q) => $q->whereIn('event_id', $eventIds))
            ->where('status', 'completed')->count();

        $totalTeams = \DB::table('event_teams')
            ->whereIn('event_id', $eventIds)
            ->where('status', 'submitted')->count();

        // Топ игроков по всем турнирам организатора
        $topPlayers = \App\Models\PlayerTournamentStats::whereIn('event_id', $eventIds)
            ->where('matches_played', '>', 0)
            ->with('user')
            ->selectRaw('user_id, SUM(matches_played) as agg_played, SUM(matches_won) as agg_won')
            ->groupBy('user_id')
            ->orderByRaw('SUM(matches_won)::float / GREATEST(SUM(matches_played), 1) DESC')
            ->limit(10)
            ->get();

        return view('tournaments.public.organizer', compact(
            'organizer', 'tournaments', 'totalMatches', 'totalTeams', 'topPlayers', 'eventIds'
        ));
    }

}
