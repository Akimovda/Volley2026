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
use Illuminate\Http\Request;

class TournamentPublicController extends Controller
{
    /**
     * Главная публичная страница турнира (табы).
     */
    public function show(Request $request, Event $event)
    {
        $tab = $request->query('tab', 'overview');

        // Occurrence selector для сезонных турниров
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

            $occId = $request->query('occurrence_id');
            if ($occId) {
                $selectedOccurrence = $occurrences->firstWhere('id', $occId);
            }
            if (!$selectedOccurrence && $occurrences->isNotEmpty()) {
                $selectedOccurrence = $occurrences->first();
            }

            // Определяем сезон по occurrence если season_id не передан явно
            if (!$currentSeason && $selectedOccurrence) {
                $se = TournamentSeasonEvent::where('event_id', $event->id)
                    ->where('occurrence_id', $selectedOccurrence->id)
                    ->first();
                if ($se) {
                    $currentSeason = \App\Models\TournamentSeason::find($se->season_id);
                }
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

        return view('tournaments.public.show', compact(
            'event', 'stages', 'tab', 'setting', 'totalMatches', 'totalTeams', 'occurrences', 'selectedOccurrence', 'seasonStats', 'currentSeason', 'matchStatsByMatchId'
        ));
    }

    /**
     * JSON endpoint для live-обновления (polling).
     */
    public function liveData(Request $request, Event $event)
    {
        $stages = $event->tournamentStages()
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

        return view('tournaments.public.team', compact('event', 'team'));
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
