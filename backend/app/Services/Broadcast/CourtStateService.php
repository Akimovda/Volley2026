<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastSetting;
use App\Models\BroadcastTeamStyle;
use App\Models\EventCourt;
use App\Models\EventTeam;
use App\Models\MatchLineupEvent;
use App\Models\MatchRallyEvent;
use App\Models\MatchSetStart;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\User;
use App\Services\MatchRallyService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Единое компактное состояние площадки для оверлея: тот же JSON отдаётся по GET /o/{token}/state
 * и в payload события CourtStateUpdated. Все временные переключения (итоги партии/матча → табло → заставка)
 * считаются здесь, по меткам времени событий, и отдаются клиенту как mode_until.
 */
class CourtStateService
{
    /** Live без активности дольше этого срока не считается текущим матчем. */
    public const LIVE_STALE_HOURS = 2;

    public function __construct(
        protected MatchRallyService $rally,
        protected TeamStyleService $styles,
    ) {}

    public function forCourt(EventCourt $court, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy();
        $court->loadMissing('event.organizer', 'override');

        $override = $court->override;
        $settings = BroadcastSetting::resolve((int) $court->event_id, $court->occurrence_id ? (int) $court->occurrence_id : null);
        $setSec   = max(0, (int) $settings->set_summary_seconds);
        $matchSec = max(0, (int) $settings->match_summary_seconds);
        $everySet = ($settings->summary_mode ?? BroadcastSetting::SUMMARY_EVERY_SET) === BroadcastSetting::SUMMARY_EVERY_SET;

        $base = $this->courtMatches($court);

        $live   = $this->liveMatch($base, $now);          // ['match','at'] | null
        $recent = $this->lastCompleted($base);            // ['match','at'] | null

        $manualMode  = $override?->manual_mode;
        $manualMatch = $this->manualMatch($court, $override?->manual_match_id);

        $mode = 'splash';
        $subject = null;       // матч, который показываем (match)
        $until = null;
        $summaryScope = null;
        $summarySet = null;

        if ($manualMode !== null) {
            $mode = $manualMode;
            switch ($manualMode) {
                case 'live':
                    $subject = $manualMatch ?? $live['match'] ?? $this->nextUnfinished($base, $now, null);
                    break;
                case 'set_summary':
                    $subject = $manualMatch ?? $live['match'] ?? $recent['match'] ?? null;
                    $summaryScope = 'set';
                    break;
                case 'match_summary':
                    $subject = $manualMatch ?? $recent['match'] ?? $live['match'] ?? null;
                    $summaryScope = 'match';
                    break;
                case 'off':
                    $subject = $manualMatch ?? $live['match'] ?? null;
                    break;
                default: // splash — «следующим» показываем выбранный вручную матч
                    break;
            }
        } elseif ($manualMatch) {
            $subject = $manualMatch;
            if ($manualMatch->isCompleted()) {
                $mode = 'match_summary';
                $summaryScope = 'match';
            } else {
                $mode = 'live';
            }
        } else {
            $recentFresh = $recent && $matchSec > 0 && $now->lt($recent['at']->copy()->addSeconds($matchSec));
            if ($recentFresh && (!$live || $live['at']->lte($recent['at']))) {
                $mode = 'match_summary';
                $subject = $recent['match'];
                $summaryScope = 'match';
                $until = $recent['at']->copy()->addSeconds($matchSec);
            } elseif ($live) {
                $liveState = $this->setsOf($live['match']);
                if ($liveState['match_decided']) {
                    // Решён по очкам, но оператор ещё не финализировал: сразу итоги матча, затем заставка.
                    // Отмена очка вернёт live (решённости больше нет).
                    $lastPoint = $this->lastPointAt($live['match']) ?? $live['at'];
                    if ($matchSec > 0 && $now->lt($lastPoint->copy()->addSeconds($matchSec))) {
                        $mode = 'match_summary';
                        $subject = $live['match'];
                        $summaryScope = 'match';
                        $until = $lastPoint->copy()->addSeconds($matchSec);
                    }
                } else {
                    $mode = 'live';
                    $subject = $live['match'];
                }
            }
        }

        // Состояние партий и итоги — только если есть матч
        $setsState = $subject ? $this->setsOf($subject) : null;

        // Авто: итоги партии после её завершения, только при summary_mode=every_set
        if ($manualMode === null && !$manualMatch && $mode === 'live' && $subject && $everySet && $setSec > 0
            && !$setsState['match_decided']) {
            $ended = $this->decidedSetEnd($subject, $setsState);
            if ($ended && $now->lt($ended['at']->copy()->addSeconds($setSec))) {
                $mode = 'set_summary';
                $summaryScope = 'set';
                $summarySet = $ended['n'];
                $until = $ended['at']->copy()->addSeconds($setSec);
            }
        }

        if ($mode === 'splash') {
            $subject = null;
            $setsState = null;
        }

        $next = $this->nextUnfinished($base, $now, $subject?->id);
        if ($mode === 'splash' && $manualMatch && $manualMode !== null) {
            $next = $manualMatch;
        }

        $teamsFrom = $subject ?? $next;

        // Номер партии для итогов партии
        if ($summaryScope === 'set' && $setsState && $summarySet === null) {
            $summarySet = $this->lastDecidedOrCurrentSet($setsState);
        }

        $current = $subject && $setsState ? $this->currentSet($setsState) : null;

        $payload = [
            'court'      => ['id' => (int) $court->id, 'name' => $court->name],
            'mode'       => $mode,
            'mode_until' => $until?->toIso8601String(),
            'match'      => $subject ? $this->brief($subject) : null,
            'next_match' => $next ? $this->brief($next) : null,
            'teams'      => $teamsFrom ? $this->teamsOf($teamsFrom, $court) : null,
            'set'        => $current,
            'sets'       => $setsState ? array_map(fn ($s) => ['n' => $s['n'], 'home' => $s['home'], 'away' => $s['away']], $setsState['sets']) : [],
            'wins'       => $setsState ? ['home' => $setsState['wins_home'], 'away' => $setsState['wins_away']] : ['home' => 0, 'away' => 0],
            'serving'    => null,
            'last_sub'   => null,
            'summary'    => null,
            'splash'     => null,
            'stats_basis' => 'attributed',
            'seq'        => $subject ? (int) DB::table('tournament_matches')->where('id', $subject->id)->value('last_seq') : 0,
            'aspect'     => $settings->aspect ?: '16:9',
            'event'      => ['title' => $court->event?->title, 'logo_url' => $this->styles->organizerAvatarUrl($court->event?->organizer)],
            'theme'      => $this->theme($settings),
            'now_ms'     => (int) round($now->getPreciseTimestamp(3)),
            'ts'         => $now->toIso8601String(),
        ];

        if ($subject && $current && in_array($mode, ['live', 'set_summary'], true)) {
            $payload['serving'] = $this->serving($subject, (int) $current['n']);
            $payload['last_sub'] = $this->lastSub($subject, (int) $current['n']);
        }

        if ($subject && $summaryScope) {
            $payload['summary'] = $this->summary($subject, $summaryScope, $summaryScope === 'set' ? $summarySet : null);
        }

        if ($mode === 'splash') {
            $payload['splash'] = $this->splash($settings, $next);
        }

        return $payload;
    }

    /* ------------------------------------------------------------------ */
    /*  Выбор матча                                                        */
    /* ------------------------------------------------------------------ */

    private function courtMatches(EventCourt $court): Builder
    {
        // Friendly-игра: площадка дня — матчи служебной стадии тура, у которых court_id не задан
        $friendlyStageId = null;
        if ($court->occurrence_id) {
            $primary = EventCourt::where('event_id', $court->event_id)
                ->where('occurrence_id', $court->occurrence_id)
                ->orderBy('sort_order')->orderBy('id')->value('id');
            if ((int) $primary === (int) $court->id) {
                $friendlyStageId = TournamentStage::where('event_id', $court->event_id)
                    ->where('occurrence_id', $court->occurrence_id)
                    ->where('type', TournamentStage::TYPE_FRIENDLY)->value('id');
            }
        }

        return TournamentMatch::query()
            ->with(['stage', 'teamHome', 'teamAway'])
            ->where(function ($w) use ($court, $friendlyStageId) {
                $w->where('court_id', $court->id);
                if ($friendlyStageId) {
                    $w->orWhere(fn ($x) => $x->where('stage_id', $friendlyStageId)->whereNull('court_id'));
                }
            })
            ->whereNotIn('status', [TournamentMatch::STATUS_CANCELLED, TournamentMatch::STATUS_FORFEIT]);
    }

    /** Матчи площадки (без отменённых) — для проверки «матч принадлежит площадке ссылки». */
    public function matchesOfCourt(EventCourt $court): Builder
    {
        return $this->courtMatches($court);
    }

    /**
     * Очередь ведущего: идущие и ожидающие матчи площадки с известными командами.
     * current — как в оверлее (живой матч, иначе ближайший нерозыгранный), upcoming — остальные по порядку.
     *
     * @return array{current:?TournamentMatch, upcoming:\Illuminate\Support\Collection<int,TournamentMatch>}
     */
    public function queueFor(EventCourt $court, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $base = $this->courtMatches($court);
        $live = $this->liveMatch($base, $now);

        $pending = (clone $base)
            ->whereIn('status', [TournamentMatch::STATUS_LIVE, TournamentMatch::STATUS_SCHEDULED])
            ->whereNotNull('team_home_id')->whereNotNull('team_away_id')
            ->orderByRaw("CASE WHEN status = 'live' THEN 0 ELSE 1 END")
            ->orderByRaw('scheduled_at IS NULL')->orderBy('scheduled_at')
            ->orderBy('round')->orderBy('match_number')->orderBy('id')
            ->get();

        $current = $live['match'] ?? $pending->first();

        return [
            'current'  => $current,
            'upcoming' => $pending->reject(fn ($m) => $current && (int) $m->id === (int) $current->id)->values(),
        ];
    }

    private function manualMatch(EventCourt $court, ?int $id): ?TournamentMatch
    {
        if (!$id) {
            return null;
        }
        $m = TournamentMatch::with(['stage', 'teamHome', 'teamAway'])->find($id);
        return $m && $m->stage && (int) $m->stage->event_id === (int) $court->event_id && $m->hasTeams() ? $m : null;
    }

    /** @return array{match:TournamentMatch, at:Carbon}|null */
    private function liveMatch(Builder $base, Carbon $now): ?array
    {
        $best = null;
        $threshold = $now->copy()->subHours(self::LIVE_STALE_HOURS);

        foreach ((clone $base)->where('status', TournamentMatch::STATUS_LIVE)->get() as $m) {
            if (!$m->hasTeams()) {
                continue;
            }
            $at = $this->activityAt($m);
            if ($at->lt($threshold)) {
                continue;
            }
            if (!$best || $at->gt($best['at'])) {
                $best = ['match' => $m, 'at' => $at];
            }
        }

        return $best;
    }

    private function lastPointAt(TournamentMatch $m): ?Carbon
    {
        $t = MatchRallyEvent::where('match_id', $m->id)->max('created_at');
        return $t ? Carbon::parse($t) : null;
    }

    private function activityAt(TournamentMatch $m): Carbon
    {
        $times = array_filter([
            $m->updated_at,
            ($t = MatchRallyEvent::where('match_id', $m->id)->max('created_at')) ? Carbon::parse($t) : null,
            ($t = MatchLineupEvent::where('match_id', $m->id)->max('created_at')) ? Carbon::parse($t) : null,
        ]);

        return collect($times)->max() ?? Carbon::createFromTimestamp(0);
    }

    /** @return array{match:TournamentMatch, at:Carbon}|null */
    private function lastCompleted(Builder $base): ?array
    {
        $m = (clone $base)->where('status', TournamentMatch::STATUS_COMPLETED)
            ->orderByRaw('scored_at DESC NULLS LAST')->orderByDesc('updated_at')->orderByDesc('id')->first();

        if (!$m) {
            return null;
        }
        $at = ($m->scored_at ?? $m->updated_at)->copy();
        // Итоги уже показывались с момента последнего очка — финализация их не продлевает
        $last = $this->lastPointAt($m);
        if ($last && $last->lt($at)) {
            $at = $last;
        }

        return ['match' => $m, 'at' => $at];
    }

    private function nextUnfinished(Builder $base, Carbon $now, ?int $excludeId): ?TournamentMatch
    {
        return (clone $base)
            ->where('status', TournamentMatch::STATUS_SCHEDULED)
            ->whereNotNull('team_home_id')->whereNotNull('team_away_id')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderByRaw('scheduled_at IS NULL')->orderBy('scheduled_at')
            ->orderBy('round')->orderBy('match_number')->orderBy('id')
            ->first();
    }

    /* ------------------------------------------------------------------ */
    /*  Партии и счёт                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{sets:array<int,array{n:int,home:int,away:int,target:int,decided:bool,deciding:bool}>,wins_home:int,wins_away:int,match_decided:bool}
     */
    private function setsOf(TournamentMatch $m): array
    {
        $stage = $m->stage;

        // Завершённый матч: итоговый счёт по партиям — из самого матча (он авторитетнее лога)
        if ($m->isCompleted() && !empty($m->score_home) && is_array($m->score_home)) {
            $sets = [];
            $wh = $wa = 0;
            $max = max(count($m->score_home), count((array) $m->score_away));
            foreach (array_values($m->score_home) as $i => $h) {
                $a = (int) (array_values((array) $m->score_away)[$i] ?? 0);
                $h = (int) $h;
                $sets[] = ['n' => $i + 1, 'home' => $h, 'away' => $a, 'target' => $stage->setPoints(), 'decided' => true, 'deciding' => false];
                $h > $a ? $wh++ : $wa++;
            }
            return ['sets' => $sets, 'wins_home' => $wh, 'wins_away' => $wa, 'match_decided' => true];
        }

        $st = $this->rally->computeMatchState($m);
        $sets = [];
        foreach ($st['sets'] as $n => $s) {
            $sets[] = [
                'n' => (int) $n, 'home' => (int) $s['home'], 'away' => (int) $s['away'],
                'target' => (int) $s['target'], 'decided' => (bool) $s['decided'], 'deciding' => (bool) $s['isDecidingSet'],
            ];
        }
        return ['sets' => $sets, 'wins_home' => (int) $st['homeWins'], 'wins_away' => (int) $st['awayWins'], 'match_decided' => (bool) $st['matchDecided']];
    }

    private function currentSet(array $state): ?array
    {
        $last = end($state['sets']);
        return $last ? ['n' => $last['n'], 'target' => $last['target'], 'home' => $last['home'], 'away' => $last['away'], 'deciding' => $last['deciding']] : null;
    }

    private function lastDecidedOrCurrentSet(array $state): ?int
    {
        $n = null;
        foreach ($state['sets'] as $s) {
            if ($s['decided']) {
                $n = $s['n'];
            }
        }
        return $n ?? ($state['sets'] ? end($state['sets'])['n'] : null);
    }

    /**
     * Последняя завершённая партия, после которой в следующей ещё не было очков: время её конца = последнее событие.
     * @return array{n:int, at:Carbon}|null
     */
    private function decidedSetEnd(TournamentMatch $m, array $state): ?array
    {
        $n = null;
        foreach ($state['sets'] as $s) {
            if ($s['decided']) {
                $n = $s['n'];
            }
        }
        if ($n === null) {
            return null;
        }
        if (MatchRallyEvent::where('match_id', $m->id)->where('set_number', '>', $n)->exists()) {
            return null;
        }
        $t = MatchRallyEvent::where('match_id', $m->id)->where('set_number', $n)->max('created_at');
        return $t ? ['n' => $n, 'at' => Carbon::parse($t)] : null;
    }

    /** Подаёт: только если у партии есть стартовый состав (first_serve_team_id). */
    private function serving(TournamentMatch $m, int $setNumber): ?string
    {
        $start = MatchSetStart::where('match_id', $m->id)->where('set_number', $setNumber)->first();
        if (!$start || !$start->first_serve_team_id) {
            return null;
        }
        $lastPoint = MatchRallyEvent::where('match_id', $m->id)->where('set_number', $setNumber)->orderByDesc('seq')->first();
        $teamId = (int) ($lastPoint?->team_id ?? $start->first_serve_team_id);

        return $teamId === (int) $m->team_home_id ? 'home' : ($teamId === (int) $m->team_away_id ? 'away' : null);
    }

    private function lastSub(TournamentMatch $m, int $setNumber): ?array
    {
        $ev = MatchLineupEvent::where('match_id', $m->id)->where('set_number', $setNumber)->orderByDesc('seq')->first();
        if (!$ev) {
            return null;
        }
        $ids = array_filter([$ev->out_user_id, $ev->in_user_id]);
        $names = User::whereIn('id', $ids)->get()->mapWithKeys(fn ($u) => [$u->id => trim($u->last_name ?: $u->first_name ?: '—')]);
        $out = $names[$ev->out_user_id] ?? '—';
        $in  = $names[$ev->in_user_id] ?? '—';

        if ($ev->type === MatchLineupEvent::TYPE_SUBSTITUTION) {
            $text = __('tournaments.lineup_ev_substitution') . ': ' . $out . ' → ' . $in;
        } elseif ($ev->type === MatchLineupEvent::TYPE_LIBERO_IN) {
            $text = __('tournaments.lineup_ev_libero') . ': ' . $in . ' → ' . $out;
        } else {
            $text = __('tournaments.lineup_ev_libero') . ': ' . $out . ' ↔ ' . $in;
        }

        return [
            'text' => $text,
            'team' => (int) $ev->team_id === (int) $m->team_home_id ? 'home' : 'away',
            'at'   => $ev->created_at?->toIso8601String(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Итоги и статистика                                                 */
    /* ------------------------------------------------------------------ */

    /** Командные суммы из match_player_stats (set_number>0 — партия, 0 — весь матч). Только «по засчитанным действиям». */
    private function summary(TournamentMatch $m, string $scope, ?int $setNumber): array
    {
        $set = $scope === 'set' ? ($setNumber ?? 1) : 0;

        $rows = DB::table('match_player_stats')
            ->where('match_id', $m->id)->where('set_number', $set)
            ->selectRaw('team_id, SUM(aces) aces, SUM(kills) kills, SUM(blocks) blocks, SUM(digs) digs, SUM(assists) assists,
                SUM(serve_errors + attack_errors + block_errors + reception_errors) errors')
            ->groupBy('team_id')->get()->keyBy('team_id');

        $pack = function (?int $teamId) use ($rows) {
            $r = $teamId ? ($rows[$teamId] ?? null) : null;
            $aces = (int) ($r->aces ?? 0);
            $kills = (int) ($r->kills ?? 0);
            $blocks = (int) ($r->blocks ?? 0);
            return [
                'aces' => $aces, 'kills' => $kills, 'blocks' => $blocks,
                'points' => $aces + $kills + $blocks,
                'digs' => (int) ($r->digs ?? 0), 'assists' => (int) ($r->assists ?? 0), 'errors' => (int) ($r->errors ?? 0),
            ];
        };

        return [
            'scope' => $scope,
            'set_number' => $scope === 'set' ? $set : null,
            'teams' => ['home' => $pack($m->team_home_id), 'away' => $pack($m->team_away_id)],
        ];
    }

    public const DEMOS = ['splash', 'live', 'sub', 'set_summary', 'match_summary'];

    /**
     * Образцовое состояние для предпросмотра оверлея (?preview=1&demo=…). Ничего не пишет и не читает счёт:
     * оформление/заставка/события — реальные, команды — реальные (матч площадки или первые две команды события),
     * иначе «Команда А/Б».
     */
    public function demo(EventCourt $court, string $kind, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy();
        $court->loadMissing('event.organizer');
        $settings = BroadcastSetting::resolve((int) $court->event_id, $court->occurrence_id ? (int) $court->occurrence_id : null);

        $teams = null;
        $m = $this->courtMatches($court)->whereNotIn('status', [TournamentMatch::STATUS_COMPLETED, TournamentMatch::STATUS_CANCELLED])
            ->whereNotNull('team_home_id')->whereNotNull('team_away_id')->orderByRaw('scheduled_at IS NULL')->orderBy('scheduled_at')->orderBy('id')
            ->with(['teamHome', 'teamAway'])->first();
        if ($m) {
            $teams = $this->teamsOf($m, $court);
        } else {
            $two = EventTeam::where('event_id', $court->event_id)
                ->when($court->occurrence_id, fn ($q) => $q->where('occurrence_id', $court->occurrence_id))
                ->orderBy('id')->limit(2)->get();
            if ($two->count() === 2) {
                $side = fn (EventTeam $t) => ['name' => $t->name, 'short_name' => $this->styles->shortFor($t),
                    'color' => $this->styles->colorFor($t), 'logo_url' => $this->styles->logoUrlFor($t, $court->event)];
                $teams = ['home' => $side($two[0]), 'away' => $side($two[1])];
            }
        }
        $teams ??= [
            'home' => ['name' => 'Команда А', 'short_name' => 'КА', 'color' => BroadcastTeamStyle::PALETTE[0], 'logo_url' => null],
            'away' => ['name' => 'Команда Б', 'short_name' => 'КБ', 'color' => BroadcastTeamStyle::PALETTE[1], 'logo_url' => null],
        ];

        $stat = fn (array $h, array $a) => ['scope' => null, 'set_number' => null, 'teams' => [
            'home' => $h + ['points' => $h['aces'] + $h['kills'] + $h['blocks'], 'digs' => 0, 'assists' => 0],
            'away' => $a + ['points' => $a['aces'] + $a['kills'] + $a['blocks'], 'digs' => 0, 'assists' => 0],
        ]];

        $payload = [
            'court' => ['id' => (int) $court->id, 'name' => $court->name],
            'mode' => $kind === 'sub' ? 'live' : $kind,
            'mode_until' => null,
            'match' => ['id' => 0, 'status' => 'live', 'stage' => null, 'round' => null, 'format' => null, 'scheduled_at' => null,
                'home' => $teams['home']['name'], 'away' => $teams['away']['name']],
            'next_match' => ['id' => 0, 'status' => 'scheduled', 'home' => 'Команда В', 'away' => 'Команда Г', 'scheduled_at' => null],
            'teams' => $teams,
            'set' => ['n' => 2, 'target' => 25, 'home' => 18, 'away' => 16, 'deciding' => false],
            'sets' => [['n' => 1, 'home' => 25, 'away' => 21], ['n' => 2, 'home' => 18, 'away' => 16]],
            'wins' => ['home' => 1, 'away' => 0],
            'serving' => 'home',
            'last_sub' => null,
            'summary' => null,
            'splash' => null,
            'stats_basis' => 'attributed',
            'seq' => 1,
            'aspect' => $settings->aspect ?: '16:9',
            'event' => ['title' => $court->event?->title, 'logo_url' => $this->styles->organizerAvatarUrl($court->event?->organizer)],
            'theme' => $this->theme($settings),
            'now_ms' => (int) round($now->getPreciseTimestamp(3)),
            'ts' => $now->toIso8601String(),
        ];

        switch ($kind) {
            case 'sub':
                $payload['last_sub'] = ['text' => __('tournaments.lineup_ev_substitution') . ': Иванов → Петров', 'team' => 'home', 'at' => $now->toIso8601String()];
                break;
            case 'set_summary':
                $payload['set'] = ['n' => 2, 'target' => 25, 'home' => 0, 'away' => 0, 'deciding' => false];
                $payload['sets'] = [['n' => 1, 'home' => 25, 'away' => 21]];
                $payload['summary'] = ['scope' => 'set', 'set_number' => 1] + $stat(
                    ['aces' => 3, 'kills' => 12, 'blocks' => 4, 'errors' => 7],
                    ['aces' => 1, 'kills' => 9, 'blocks' => 2, 'errors' => 5]);
                break;
            case 'match_summary':
                $payload['sets'] = [['n' => 1, 'home' => 25, 'away' => 21], ['n' => 2, 'home' => 22, 'away' => 25], ['n' => 3, 'home' => 25, 'away' => 19], ['n' => 4, 'home' => 25, 'away' => 23]];
                $payload['wins'] = ['home' => 3, 'away' => 1];
                $payload['serving'] = null;
                $payload['match']['status'] = 'completed';
                $payload['summary'] = ['scope' => 'match', 'set_number' => null] + $stat(
                    ['aces' => 11, 'kills' => 47, 'blocks' => 13, 'errors' => 26],
                    ['aces' => 6, 'kills' => 38, 'blocks' => 8, 'errors' => 21]);
                break;
            case 'splash':
                $payload['match'] = null;
                $payload['sets'] = [];
                $payload['set'] = null;
                $payload['wins'] = ['home' => 0, 'away' => 0];
                $payload['serving'] = null;
                $payload['next_match'] = ['id' => 0, 'status' => 'scheduled', 'home' => $teams['home']['name'], 'away' => $teams['away']['name'], 'scheduled_at' => null];
                $payload['splash'] = $this->splash($settings, null);
                $payload['splash']['countdown_to'] = $now->copy()->addMinutes(5)->toIso8601String();
                break;
        }

        return $payload;
    }

    /* ------------------------------------------------------------------ */
    /*  Блоки payload                                                      */
    /* ------------------------------------------------------------------ */

    private function brief(TournamentMatch $m): array
    {
        return [
            'id'           => (int) $m->id,
            'status'       => $m->status,
            'stage'        => $m->stage?->name,
            'round'        => $m->round,
            'format'       => $m->stage?->matchFormat(),
            'scheduled_at' => $m->scheduled_at?->toIso8601String(),
            'home'         => $m->teamHome?->name,
            'away'         => $m->teamAway?->name,
        ];
    }

    private function teamsOf(TournamentMatch $m, EventCourt $court): array
    {
        $side = fn (?EventTeam $t) => $t ? [
            'name' => $t->name,
            'short_name' => $this->styles->shortFor($t),
            'color' => $this->styles->colorFor($t),
            'logo_url' => $this->styles->logoUrlFor($t, $court->event),
        ] : ['name' => null, 'short_name' => null, 'color' => null, 'logo_url' => null];

        return ['home' => $side($m->teamHome), 'away' => $side($m->teamAway)];
    }

    /** Непрозрачность панелей: 30–100 % шагом 5, по умолчанию 85. */
    public static function clampOpacity(mixed $v): int
    {
        if (!is_numeric($v)) {
            return 85;
        }

        return max(30, min(100, (int) round(((int) $v) / 5) * 5));
    }

    /** Масштаб табло: 50–150 % шагом 10, по умолчанию 70. */
    public static function clampScale(mixed $v): int
    {
        if (!is_numeric($v)) {
            return 70;
        }
        $v = (int) round(((int) $v) / 10) * 10;

        return max(50, min(150, $v));
    }

    public function theme(BroadcastSetting $settings): array
    {
        $t = (array) ($settings->theme ?? []);
        $corner = $t['corner'] ?? 'top-left';
        $template = $t['template'] ?? 'compact';

        return [
            'corner'          => in_array($corner, ['top-left', 'top-right', 'bottom-left', 'bottom-right'], true) ? $corner : 'top-left',
            'template'        => in_array($template, ['compact', 'sets_table'], true) ? $template : 'compact',
            'show_set_scores' => (bool) ($t['show_set_scores'] ?? true),
            'show_logos'      => (bool) ($t['show_logos'] ?? true),
            'scale'           => self::clampScale($t['scale'] ?? null),
            'opacity'         => self::clampOpacity($t['opacity'] ?? null),
            'edge_margin'     => in_array($t['edge_margin'] ?? null, ['small', 'medium', 'large'], true) ? $t['edge_margin'] : 'medium',
            'show_team_names' => (bool) ($t['show_team_names'] ?? true),
        ];
    }

    private function splash(BroadcastSetting $settings, ?TournamentMatch $next): array
    {
        $cfg = (array) ($settings->splash ?? []);
        // timer: schedule (по расписанию матча) | custom (своё время) | none; старые настройки — show_countdown/manual_start_at
        $timer = $cfg['timer'] ?? (($cfg['show_countdown'] ?? true) ? 'schedule' : 'none');
        $to = null;
        if ($timer === 'custom' && !empty($cfg['manual_start_at'])) {
            $to = Carbon::parse($cfg['manual_start_at']);
        } elseif ($timer === 'schedule' || !isset($cfg['timer'])) {
            if ($timer !== 'none') {
                $to = $next?->scheduled_at;
                if (!$to && !empty($cfg['manual_start_at'])) {
                    $to = Carbon::parse($cfg['manual_start_at']);
                }
            }
        }

        $media = $settings->exists ? $settings->getFirstMedia('stream_bg') : null;
        $bg = $media ? ($media->hasGeneratedConversion('hd') ? $media->getUrl('hd') : $media->getUrl()) : null;
        if ($bg && str_starts_with($bg, '/')) {
            $bg = url($bg);
        }

        return ['text' => $cfg['text'] ?? null, 'bg_url' => $bg, 'countdown_to' => $to?->toIso8601String()];
    }
}
