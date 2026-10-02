<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Списки турниров для всплывающих окон «Игры» на /players/rating и «Игр вместе» на /players/teams.
 *
 * Считаются только командные матчи (tournament_matches с team_home_id/team_away_id): завершённые, без тай-брейков,
 * зачётные (как TournamentMatch::ratable()). Индивидуальные форматы без команд (king_beach) сюда не попадают.
 * Результат — массив «ключ => [ ['title','date','url','matches','wins'], … ]» (свежие турниры первыми).
 */
class PlayerTournamentListService
{
    /** Пары (пляж): ключ «player1-player2». @param array<int, array{0:int,1:int}> $pairs */
    public function forPairs(array $pairs): array
    {
        if (!$pairs) return [];

        $q = $this->base('m1', 'beach_pair')
            ->join('event_team_members as m2', function ($j) {
                $j->on('m2.event_team_id', '=', 'm1.event_team_id')->where('m2.confirmation_status', '=', 'confirmed');
            })
            ->where(function ($w) use ($pairs) {
                foreach ($pairs as [$a, $b]) {
                    $w->orWhere(fn ($x) => $x->where('m1.user_id', $a)->where('m2.user_id', $b));
                }
            })
            ->selectRaw("CONCAT(m1.user_id, '-', m2.user_id) as k")
            ->groupBy('m1.user_id', 'm2.user_id');

        return $this->collect($q);
    }

    /** Команды (классика): ключ — состав «id,id,id» (как в teamsClassic). @param string[] $rosters */
    public function forRosters(array $rosters): array
    {
        if (!$rosters) return [];

        $rosterSub = DB::table('event_teams as rt')
            ->join('event_team_members as rm', function ($j) {
                $j->on('rm.event_team_id', '=', 'rt.id')->where('rm.confirmation_status', '=', 'confirmed');
            })
            ->where('rt.team_kind', 'classic_team')
            ->groupBy('rt.id')
            ->havingRaw('COUNT(*) >= 2')
            ->selectRaw("rt.id as team_id, string_agg(rm.user_id::text, ',' ORDER BY rm.user_id) as roster");

        $q = DB::query()->fromSub($rosterSub, 'r')
            ->join('event_teams as t', 't.id', '=', 'r.team_id')
            ->join('events as e', 'e.id', '=', 't.event_id')
            ->leftJoin('event_occurrences as o', 'o.id', '=', 't.occurrence_id')
            ->joinSub($this->sides(), 'sd', 'sd.team_id', '=', 't.id')
            ->whereIn('r.roster', $rosters)
            ->selectRaw($this->cols() . ", r.roster as k")
            ->groupBy('r.roster', 't.event_id', 't.occurrence_id', 'e.title', 'o.starts_at', 'e.starts_at');

        return $this->collect($q);
    }

    /** Игроки (рейтинг): ключ — user_id. Для сезонного режима — только события этого сезона. @param int[] $userIds */
    public function forPlayers(array $userIds, string $direction = 'beach', ?int $seasonId = null): array
    {
        if (!$userIds) return [];

        $q = $this->base('m1', $direction === 'classic' ? 'classic_team' : 'beach_pair')
            ->whereIn('m1.user_id', $userIds)
            ->selectRaw("m1.user_id as k")
            ->groupBy('m1.user_id');

        if ($seasonId) {
            $q->where('e.season_id', $seasonId);
        } else {
            // карьерный режим считается по направлению; в сезонном направление задано самим сезоном
        }

        return $this->collect($q);
    }

    // ---------------------------------------------------------------

    private function base(string $alias, string $teamKind): Builder
    {
        $q = DB::table("event_team_members as $alias")
            ->join('event_teams as t', 't.id', '=', "$alias.event_team_id")
            ->join('events as e', 'e.id', '=', 't.event_id')
            ->leftJoin('event_occurrences as o', 'o.id', '=', 't.occurrence_id')
            ->joinSub($this->sides(), 'sd', 'sd.team_id', '=', 't.id')
            ->where("$alias.confirmation_status", 'confirmed')
            ->where('t.team_kind', $teamKind)
            ->selectRaw($this->cols());

        return $q->groupBy('t.event_id', 't.occurrence_id', 'e.title', 'o.starts_at', 'e.starts_at');
    }

    private function cols(): string
    {
        return "t.event_id, t.occurrence_id, e.title, COALESCE(o.starts_at, e.starts_at) as starts_at,
                COUNT(*) as matches, SUM(CASE WHEN sd.won THEN 1 ELSE 0 END) as wins,
                string_agg(DISTINCT t.id::text, ',') as team_ids";
    }

    /** Команда → завершённые зачётные матчи (по одной строке на команду и матч). */
    private function sides(): Builder
    {
        $one = fn (string $col, string $won) => DB::table('tournament_matches as tm')
            ->join('tournament_stages as st', 'st.id', '=', 'tm.stage_id')
            ->join('events as ev', 'ev.id', '=', 'st.event_id')
            ->where('tm.status', 'completed')
            ->whereNotNull("tm.$col")
            ->whereNotNull('tm.winner_team_id')
            ->whereRaw('(tm.is_tiebreaker IS NULL OR tm.is_tiebreaker = false)')
            ->whereRaw("(st.type <> 'friendly' OR ev.stats_rated = true)")
            ->selectRaw("tm.id as match_id, tm.$col as team_id, ($won) as won");

        return $one('team_home_id', 'tm.winner_team_id = tm.team_home_id')
            ->unionAll($one('team_away_id', 'tm.winner_team_id = tm.team_away_id'));
    }

    private function collect(Builder $q): array
    {
        $out = [];
        $rows = $q->orderByDesc('starts_at')->get();
        $places = $this->places($rows);
        foreach ($rows as $r) {
            $place = null;
            foreach (array_filter(explode(',', (string) $r->team_ids)) as $tid) {
                $p = $places[$r->event_id . ':' . (int) $r->occurrence_id][(int) $tid] ?? null;
                if ($p !== null && ($place === null || $p < $place)) $place = $p;
            }
            $url = route('tournament.public.show', $r->event_id) . '?tab=results'
                . ($r->occurrence_id ? '&occurrence_id=' . (int) $r->occurrence_id : '');
            $out[(string) $r->k][] = [
                'title'   => (string) $r->title,
                'date'    => $r->starts_at ? Carbon::parse($r->starts_at)->format('d.m.Y') : '',
                'url'     => $url,
                'matches' => (int) $r->matches,
                'wins'    => (int) $r->wins,
                'place'   => $place,
            ];
        }
        return $out;
    }

    /**
     * Итоговые места команд — ТОЛЬКО для завершённых турниров (все стадии occurrence в статусе completed).
     * Источник — TournamentStatsService::calculateFinalClassification() (учитывает плей-офф/дивизионы), как на странице игрока.
     * @return array<string, array<int,int>> «eventId:occurrenceId» => [team_id => place]
     */
    private function places($rows): array
    {
        $eventIds = $rows->pluck('event_id')->unique()->values()->all();
        if (!$eventIds) return [];

        $done = [];
        foreach (DB::table('tournament_stages')->whereIn('event_id', $eventIds)
            ->selectRaw("event_id, occurrence_id, bool_and(status = 'completed') as done")
            ->groupBy('event_id', 'occurrence_id')->get() as $st) {
            $done[$st->event_id . ':' . (int) $st->occurrence_id] = (bool) $st->done;
        }

        $events = \App\Models\Event::whereIn('id', $eventIds)->get()->keyBy('id');
        $svc = app(TournamentStatsService::class);
        $out = [];
        foreach ($rows as $r) {
            $key = $r->event_id . ':' . (int) $r->occurrence_id;
            if (isset($out[$key])) continue;
            $out[$key] = [];
            if (!($done[$key] ?? $done[$r->event_id . ':0'] ?? false) || !isset($events[$r->event_id])) continue;
            try {
                foreach ($svc->calculateFinalClassification($events[$r->event_id], $r->occurrence_id ? (int) $r->occurrence_id : null) as $c) {
                    $tid = (int) $c['team_id'];
                    if (!isset($out[$key][$tid]) || $c['place'] < $out[$key][$tid]) $out[$key][$tid] = (int) $c['place'];
                }
            } catch (\Throwable $e) {
                // места — украшение окна: ошибка расчёта не должна ронять страницу
            }
        }
        return $out;
    }
}
