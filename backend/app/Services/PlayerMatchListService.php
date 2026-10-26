<?php

namespace App\Services;

use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Список матчей игрока для профиля («Мои матчи»): завершённые и live, у которых есть события розыгрышей
 * (иначе страница /matches/{id}/stats недоступна). Игрок — в составе одной из команд ИЛИ в событиях матча.
 * Матчи приватных товарищеских игр видит только админ (сюда не попадают в остальных случаях).
 */
class PlayerMatchListService
{
    public const PER_PAGE = 20;

    public function paginate(User $player, ?User $viewer, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $pid = (int) $player->id;
        $viewerIsAdmin = $viewer && in_array(strtolower((string) ($viewer->role ?? '')), ['admin', 'superadmin'], true);

        $query = TournamentMatch::query()
            ->join('tournament_stages as s', 's.id', '=', 'tournament_matches.stage_id')
            ->join('events as e', 'e.id', '=', 's.event_id')
            ->whereIn('tournament_matches.status', [TournamentMatch::STATUS_COMPLETED, TournamentMatch::STATUS_LIVE])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('match_rally_events as r0')->whereColumn('r0.match_id', 'tournament_matches.id'))
            ->where(function ($q) use ($pid) {
                $q->whereExists(fn ($x) => $x->select(DB::raw(1))->from('event_team_members as m')
                        ->whereColumn('m.event_team_id', 'tournament_matches.team_home_id')->where('m.user_id', $pid)->where('m.confirmation_status', 'confirmed'))
                    ->orWhereExists(fn ($x) => $x->select(DB::raw(1))->from('event_team_members as m')
                        ->whereColumn('m.event_team_id', 'tournament_matches.team_away_id')->where('m.user_id', $pid)->where('m.confirmation_status', 'confirmed'))
                    ->orWhereExists(fn ($x) => $x->select(DB::raw(1))->from('match_rally_events as r1')
                        ->whereColumn('r1.match_id', 'tournament_matches.id')
                        ->where(fn ($y) => $y->where('r1.player_id', $pid)->orWhere('r1.dig_user_id', $pid)->orWhere('r1.assist_user_id', $pid)));
            })
            // товарищеская игра — только при включённой статистике; приватные — только админу
            ->where(function ($q) use ($viewerIsAdmin) {
                $q->where('s.type', '!=', 'friendly')
                    ->orWhere(function ($f) use ($viewerIsAdmin) {
                        $f->where('e.collect_stats', true);
                        if (!$viewerIsAdmin) {
                            $f->where(fn ($p) => $p->whereNull('e.is_private')->orWhere('e.is_private', false))
                                ->where(fn ($p) => $p->whereNull('e.visibility')->orWhere('e.visibility', '!=', 'private'));
                        }
                    });
            })
            ->select('tournament_matches.*', 'e.title as ev_title')
            ->selectRaw('COALESCE(tournament_matches.scheduled_at, tournament_matches.started_at, tournament_matches.scored_at, tournament_matches.created_at) as played_at')
            ->with(['teamHome:id,name', 'teamAway:id,name'])
            ->orderByRaw('COALESCE(tournament_matches.scheduled_at, tournament_matches.started_at, tournament_matches.scored_at, tournament_matches.created_at) DESC NULLS LAST')
            ->orderByDesc('tournament_matches.id');

        $page = $query->paginate($perPage, ['*'], 'matches_page');

        $ids = $page->getCollection()->pluck('id')->all();
        if ($ids) {
            $teamIds = $page->getCollection()->flatMap(fn ($m) => [$m->team_home_id, $m->team_away_id])->filter()->unique()->all();
            $myTeams = DB::table('event_team_members')->where('user_id', $pid)->where('confirmation_status', 'confirmed')
                ->whereIn('event_team_id', $teamIds)->pluck('event_team_id')->map(fn ($v) => (int) $v)->all();

            $agg = DB::table('match_rally_events')->whereIn('match_id', $ids)->where('player_id', $pid)
                ->selectRaw("match_id, stat_team_id,
                    SUM(CASE WHEN action_type IN ('kill','block','ace') THEN 1 ELSE 0 END) AS pts,
                    SUM(CASE WHEN action_type LIKE 'opp\\_%' THEN 1 ELSE 0 END) AS errs")
                ->groupBy('match_id', 'stat_team_id')->get()->groupBy('match_id');

            $page->getCollection()->transform(function ($m) use ($myTeams, $agg) {
                $a = $agg->get($m->id, collect());
                $mySide = in_array((int) $m->team_home_id, $myTeams, true) ? 'home'
                    : (in_array((int) $m->team_away_id, $myTeams, true) ? 'away'
                    : ((int) ($a->first()->stat_team_id ?? 0) === (int) $m->team_away_id ? 'away' : 'home'));
                $m->my_side = $mySide;
                $m->my_points = (int) $a->sum('pts');
                $m->my_errors = (int) $a->sum('errs');
                $m->my_sets = $mySide === 'home' ? (int) $m->sets_home : (int) $m->sets_away;
                $m->opp_sets = $mySide === 'home' ? (int) $m->sets_away : (int) $m->sets_home;
                $m->opp_name = (string) (($mySide === 'home' ? $m->teamAway : $m->teamHome)?->name ?? '—');
                $m->won = $m->winner_team_id ? ((int) $m->winner_team_id === (int) ($mySide === 'home' ? $m->team_home_id : $m->team_away_id)) : null;

                return $m;
            });
        }

        return $page;
    }
}
