<?php

namespace App\Services;

use App\Events\TournamentMatchRallyUpdated;
use App\Models\MatchLineupEvent;
use App\Models\MatchPlayerStats;
use App\Models\MatchRallyEvent;
use App\Models\MatchSetLineup;
use App\Models\MatchSetStart;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Стартовый состав партии, замены и либеро («замена в матче»; не путать с лиговой
 * team_substitutions). Состав «на площадке сейчас» нигде не хранится как изменяемое
 * состояние — он вычисляется реплеем: стартовый состав партии + события по seq.
 * Поэтому отмена события состава = просто удаление строки.
 *
 * seq — сквозная нумерация очков (match_rally_events) и событий состава
 * (match_lineup_events) внутри матча; выдаётся ТОЛЬКО под lockForUpdate матча.
 */
class MatchLineupService
{
    private const LIBERO = 'libero';

    public function profile(TournamentMatch $match): array
    {
        return MatchSchemeProfile::forMatch($match);
    }

    /**
     * Выдать следующий seq. ВЫЗЫВАТЬ только на модели, полученной через lockForUpdate()
     * внутри транзакции — иначе возможны дубли.
     */
    public function nextSeq(TournamentMatch $locked): int
    {
        $seq = (int) DB::table('tournament_matches')->where('id', $locked->id)->value('last_seq') + 1;
        DB::table('tournament_matches')->where('id', $locked->id)->update(['last_seq' => $seq]);

        return $seq;
    }

    /* ------------------------------------------------------------------ */
    /*  Ростер                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Подтверждённые участники обеих команд. Запасной — team_role (fallback role_code),
     * позиция — position_code (fallback role_code, если он сам позиция).
     *
     * @return array<int, array<int, array{user_id:int,team_role:string,position:?string,order:int,name:string}>>
     *         team_id => [user_id => данные]
     */
    public function roster(TournamentMatch $match): array
    {
        $rows = DB::table('event_team_members as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->whereIn('m.event_team_id', array_filter([$match->team_home_id, $match->team_away_id]))
            ->where('m.confirmation_status', 'confirmed')
            ->orderByRaw('m.position_order NULLS LAST')
            ->orderBy('m.id')
            ->get(['m.event_team_id', 'm.user_id', 'm.role_code', 'm.team_role', 'm.position_code',
                'm.position_order', 'u.first_name', 'u.last_name']);

        $result = [(int) $match->team_home_id => [], (int) $match->team_away_id => []];
        $positionRoles = [...MatchSchemeProfile::COURT_POSITIONS, self::LIBERO];

        foreach ($rows as $i => $r) {
            $teamRole = $r->team_role ?: match ((string) $r->role_code) {
                'captain' => 'captain',
                'reserve' => 'reserve',
                default   => 'player',
            };
            $position = $r->position_code ?: (in_array($r->role_code, $positionRoles, true) ? $r->role_code : null);

            $result[(int) $r->event_team_id][(int) $r->user_id] = [
                'user_id'   => (int) $r->user_id,
                'team_role' => $teamRole,
                'position'  => $position,
                'order'     => $i,
                'name'      => trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? '')),
            ];
        }

        return $result;
    }

    /* ------------------------------------------------------------------ */
    /*  Реплей                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Состав на площадке «сейчас» (или на момент seq = $upToSeq включительно).
     *
     * @return array{
     *   has_start: bool,
     *   first_serve_team_id: ?int,
     *   teams: array<int, array{
     *     court: array<int, array{position:?string,zone:?int,libero:bool,for_user_id:?int}>,
     *     held: array<int, array{position:?string,zone:?int}>,
     *     libero_id: ?int
     *   }>
     * }
     * court — user_id => позиция/зона (либеро, если на площадке, тоже здесь, с libero=true и зоной
     * того, вместо кого стоит); held — игроки, вместо которых сейчас стоит либеро (user_id => их позиция/зона);
     * libero_id — назначенный в стартовом составе либеро (на площадке он или нет).
     */
    public function state(TournamentMatch $match, int $setNumber, ?int $upToSeq = null): array
    {
        $start = MatchSetStart::where('match_id', $match->id)->where('set_number', $setNumber)->first();

        $teams = [];
        foreach ([(int) $match->team_home_id, (int) $match->team_away_id] as $tid) {
            $teams[$tid] = ['court' => [], 'held' => [], 'libero_id' => null];
        }

        $lineups = MatchSetLineup::where('match_id', $match->id)->where('set_number', $setNumber)->get();
        foreach ($lineups as $row) {
            $tid = (int) $row->team_id;
            if (!isset($teams[$tid])) {
                continue;
            }
            if ($row->position === self::LIBERO) {
                $teams[$tid]['libero_id'] = (int) $row->user_id;
                continue;
            }
            $teams[$tid]['court'][(int) $row->user_id] = [
                'position' => $row->position, 'zone' => $row->zone === null ? null : (int) $row->zone,
                'libero' => false, 'for_user_id' => null,
            ];
        }

        $events = MatchLineupEvent::where('match_id', $match->id)->where('set_number', $setNumber)
            ->when($upToSeq !== null, fn ($q) => $q->where('seq', '<=', $upToSeq))
            ->orderBy('seq')->get();

        foreach ($events as $ev) {
            $tid = (int) $ev->team_id;
            if (!isset($teams[$tid])) {
                continue;
            }
            $t = &$teams[$tid];
            $zone = $ev->zone === null ? null : (int) $ev->zone;

            switch ($ev->type) {
                case MatchLineupEvent::TYPE_SUBSTITUTION:
                    unset($t['court'][(int) $ev->out_user_id]);
                    $t['court'][(int) $ev->in_user_id] = [
                        'position' => $ev->position, 'zone' => $zone, 'libero' => false, 'for_user_id' => null,
                    ];
                    break;

                case MatchLineupEvent::TYPE_LIBERO_IN:
                    $partner = (int) $ev->out_user_id;
                    $t['held'][$partner] = $t['court'][$partner] ?? ['position' => $ev->position, 'zone' => $zone];
                    unset($t['court'][$partner]);
                    $t['court'][(int) $ev->in_user_id] = [
                        'position' => self::LIBERO, 'zone' => $zone, 'libero' => true, 'for_user_id' => $partner,
                    ];
                    break;

                case MatchLineupEvent::TYPE_LIBERO_OUT:
                    $partner = (int) $ev->in_user_id;
                    unset($t['court'][(int) $ev->out_user_id]);
                    $t['court'][$partner] = $t['held'][$partner]
                        ?? ['position' => $ev->position, 'zone' => $zone];
                    $t['court'][$partner] += ['libero' => false, 'for_user_id' => null];
                    unset($t['held'][$partner]);
                    break;
            }
            unset($t);
        }

        return [
            'has_start'           => $start !== null,
            'first_serve_team_id' => $start?->first_serve_team_id ? (int) $start->first_serve_team_id : null,
            'teams'               => $teams,
        ];
    }

    public function hasStart(TournamentMatch $match, int $setNumber): bool
    {
        return MatchSetStart::where('match_id', $match->id)->where('set_number', $setNumber)->exists();
    }

    /**
     * Снимок для UI: площадка, скамейка (ростер минус площадка минус «вместо кого стоит либеро»), либеро.
     */
    public function describe(TournamentMatch $match, int $setNumber): array
    {
        $state = $this->state($match, $setNumber);
        $roster = $this->roster($match);
        $out = ['has_start' => $state['has_start'], 'first_serve_team_id' => $state['first_serve_team_id'], 'teams' => []];

        foreach ($state['teams'] as $tid => $t) {
            $bench = [];
            foreach ($roster[$tid] ?? [] as $uid => $p) {
                if (isset($t['court'][$uid]) || isset($t['held'][$uid])) {
                    continue;
                }
                $bench[] = $p + ['is_libero' => $uid === $t['libero_id']];
            }
            $out['teams'][$tid] = [
                'court' => $t['court'], 'held' => $t['held'], 'libero_id' => $t['libero_id'], 'bench' => $bench,
            ];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  Стартовый состав                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Черновик стартового состава партии (для экрана расстановки).
     * Партия 2+ (если в прошлой партии состав велся): состав и зоны, какими закончилась прошлая;
     * иначе — все не-запасные на площадке, позиции из position_code, зон нет (вводятся вручную).
     *
     * @return array{
     *   first_serve_team_id: ?int,
     *   teams: array<int, array{court: array<int, array{user_id:int,position:?string,zone:?int}>, libero:?int, bench:array<int,int>}>
     * }
     */
    public function defaultSetStart(TournamentMatch $match, int $setNumber): array
    {
        $profile = $this->profile($match);
        $roster = $this->roster($match);
        $home = (int) $match->team_home_id;
        $away = (int) $match->team_away_id;
        $teams = [];

        $prevTracked = $setNumber > 1 && $this->hasStart($match, $setNumber - 1);

        if ($prevTracked) {
            $prev = $this->state($match, $setNumber - 1);
            foreach ($prev['teams'] as $tid => $t) {
                $court = $t['court'];
                foreach ($t['held'] as $uid => $held) { // либеро на площадке — основной игрок возвращается на своё место
                    unset($court[$t['libero_id']]);
                    $court[$uid] = $held;
                }
                $list = [];
                foreach ($court as $uid => $c) {
                    $list[] = ['user_id' => (int) $uid, 'position' => $c['position'], 'zone' => $c['zone']];
                }
                usort($list, fn ($a, $b) => ($a['zone'] ?? 99) <=> ($b['zone'] ?? 99));
                $onCourtIds = array_column($list, 'user_id');
                $libero = $profile['has_libero'] ? $t['libero_id'] : null;
                $bench = array_values(array_diff(array_keys($roster[$tid] ?? []), $onCourtIds, array_filter([$libero])));
                $teams[$tid] = ['court' => $list, 'libero' => $libero, 'bench' => $bench];
            }
            $prevFirst = $prev['first_serve_team_id'];
            $firstServe = $prevFirst ? ($prevFirst === $home ? $away : $home) : null;
        } else {
            foreach ([$home, $away] as $tid) {
                $court = [];
                $libero = null;
                $bench = [];
                foreach ($roster[$tid] ?? [] as $uid => $p) {
                    if ($p['position'] === self::LIBERO) {
                        if ($libero === null && $profile['has_libero']) {
                            $libero = $uid;
                        } else {
                            $bench[] = $uid;
                        }
                    } elseif ($p['team_role'] === 'reserve' || count($court) >= $profile['on_court_size']) {
                        $bench[] = $uid;
                    } else {
                        $court[] = ['user_id' => $uid, 'position' => $p['position'], 'zone' => null];
                    }
                }
                $teams[$tid] = ['court' => $court, 'libero' => $libero, 'bench' => $bench];
            }
            $firstServe = null;
        }

        return ['first_serve_team_id' => $firstServe, 'teams' => $teams];
    }

    /**
     * Записать стартовый состав партии (можно перезаписать, пока в партии нет очков и событий).
     *
     * @param array<int, array{court: array<int, array{user_id:int,position:string,zone:int}>, libero?: int|array<int,int>|null}> $lineups
     *        team_id => состав обеих команд
     */
    public function setSetStart(
        TournamentMatch $match,
        int $setNumber,
        array $lineups,
        int $firstServeTeamId,
        ?User $recordedBy = null,
        ?string $recordedByName = null,
        ?int $recordedByLinkId = null,
    ): MatchSetStart {
        $profile = $this->profile($match);
        $this->assertEnabled($profile);

        $home = (int) $match->team_home_id;
        $away = (int) $match->team_away_id;

        $start = DB::transaction(function () use (
            $match, $setNumber, $lineups, $firstServeTeamId, $recordedBy, $recordedByName, $recordedByLinkId, $profile, $home, $away
        ) {
            $locked = TournamentMatch::whereKey($match->id)->lockForUpdate()->first();
            $this->assertEditable($locked, $setNumber);

            if (MatchRallyEvent::where('match_id', $locked->id)->where('set_number', $setNumber)->exists()
                || MatchLineupEvent::where('match_id', $locked->id)->where('set_number', $setNumber)->exists()) {
                throw new InvalidArgumentException(
                    'В партии уже есть очки или замены — стартовый состав менять нельзя (сначала отмените действия).'
                );
            }

            if (!in_array($firstServeTeamId, [$home, $away], true)) {
                throw new InvalidArgumentException('Укажите, какая из команд матча подаёт первой.');
            }

            $rows = $this->validatedLineupRows($locked, $setNumber, $lineups, $profile, $home, $away, $recordedBy, $recordedByName, $recordedByLinkId);

            MatchSetLineup::where('match_id', $locked->id)->where('set_number', $setNumber)->delete();
            MatchSetLineup::insert($rows);

            $start = MatchSetStart::updateOrCreate(
                ['match_id' => $locked->id, 'set_number' => $setNumber],
                [
                    'first_serve_team_id' => $firstServeTeamId,
                    'recorded_by_user_id' => $recordedBy?->id,
                    'recorded_by_link_id' => $recordedByLinkId,
                    'recorded_by_name'    => $recordedByName,
                ]
            );

            $this->syncParticipation($locked, $setNumber);

            // Расстановка = игра началась: как и первое очко (MatchRallyService::recordPoint), scheduled → live
            if ($locked->status === TournamentMatch::STATUS_SCHEDULED) {
                $locked->update(['status' => TournamentMatch::STATUS_LIVE, 'started_at' => $locked->started_at ?? now()]);
            }

            return $start;
        });

        broadcast(new TournamentMatchRallyUpdated($match->id, $setNumber));
        app(\App\Services\Broadcast\BroadcastControlService::class)->releaseSplashOnPlay($match);
        app(\App\Services\Broadcast\CourtStateBroadcaster::class)->forMatch($match->id);

        return $start;
    }

    private function validatedLineupRows(
        TournamentMatch $match,
        int $setNumber,
        array $lineups,
        array $profile,
        int $home,
        int $away,
        ?User $by,
        ?string $byName,
        ?int $byLinkId = null,
    ): array {
        $roster = $this->roster($match);
        $size = $profile['on_court_size'];
        $now = now();
        $rows = [];
        $seen = [];

        foreach ([$home, $away] as $tid) {
            $team = $lineups[$tid] ?? null;
            if (!is_array($team)) {
                throw new InvalidArgumentException('Задайте стартовый состав обеих команд.');
            }
            $court = array_values($team['court'] ?? []);
            if (count($court) !== $size) {
                throw new InvalidArgumentException("На площадке должно быть ровно {$size} игроков в каждой команде.");
            }

            $zones = [];
            foreach ($court as $c) {
                $uid = (int) ($c['user_id'] ?? 0);
                $pos = (string) ($c['position'] ?? '');
                $zone = (int) ($c['zone'] ?? 0);

                if (!isset($roster[$tid][$uid])) {
                    throw new InvalidArgumentException('Игрок не входит в подтверждённый состав своей команды.');
                }
                if (isset($seen[$uid])) {
                    throw new InvalidArgumentException('Игрок не может быть в составе дважды или в обеих командах.');
                }
                if ($pos === self::LIBERO) {
                    throw new InvalidArgumentException('Либеро указывается отдельно и не занимает зону площадки.');
                }
                if (!in_array($pos, $profile['positions'], true)) {
                    throw new InvalidArgumentException('Для каждого игрока на площадке нужно выбрать допустимую позицию.');
                }
                if ($zone < 1 || $zone > $size) {
                    throw new InvalidArgumentException("Зона должна быть от 1 до {$size}.");
                }
                if (isset($zones[$zone])) {
                    throw new InvalidArgumentException("Зона {$zone} занята двумя игроками.");
                }
                $zones[$zone] = true;
                $seen[$uid] = true;

                $rows[] = $this->lineupRow($match, $setNumber, $tid, $uid, $pos, $zone, $by, $byName, $now, $byLinkId);
            }

            $liberos = $team['libero'] ?? null;
            $liberos = $liberos === null ? [] : array_values(array_filter((array) $liberos, fn ($v) => $v !== null && $v !== ''));
            if (count($liberos) > 1) {
                throw new InvalidArgumentException('Либеро в команде может быть только один.');
            }
            if ($liberos) {
                $lid = (int) $liberos[0];
                if (!$profile['has_libero']) {
                    throw new InvalidArgumentException('В этой схеме игры либеро не предусмотрен.');
                }
                if (!isset($roster[$tid][$lid])) {
                    throw new InvalidArgumentException('Либеро не входит в подтверждённый состав своей команды.');
                }
                if (isset($seen[$lid])) {
                    throw new InvalidArgumentException('Игрок не может быть в составе дважды или в обеих командах.');
                }
                $seen[$lid] = true;
                $rows[] = $this->lineupRow($match, $setNumber, $tid, $lid, self::LIBERO, null, $by, $byName, $now, $byLinkId);
            }
        }

        return $rows;
    }

    private function lineupRow(TournamentMatch $match, int $set, int $tid, int $uid, string $pos, ?int $zone, ?User $by, ?string $byName, $now, ?int $byLinkId = null): array
    {
        return [
            'match_id' => $match->id, 'set_number' => $set, 'team_id' => $tid, 'user_id' => $uid,
            'position' => $pos, 'zone' => $zone,
            'recorded_by_user_id' => $by?->id, 'recorded_by_link_id' => $byLinkId, 'recorded_by_name' => $byName,
            'created_at' => $now, 'updated_at' => $now,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  События состава                                                    */
    /* ------------------------------------------------------------------ */

    /** Обычная замена: входящий получает позицию И зону ушедшего. Лимитов нет, возврат ушедшего разрешён. */
    public function substitute(
        TournamentMatch $match,
        int $setNumber,
        int $teamId,
        int $outUserId,
        int $inUserId,
        ?User $recordedBy = null,
        ?string $recordedByName = null,
        ?string $clientRequestId = null,
        ?int $recordedByLinkId = null,
    ): MatchLineupEvent {
        return $this->recordEvent($match, $setNumber, $teamId, $recordedBy, $recordedByName, $clientRequestId, byLinkId: $recordedByLinkId, build:
            function (array $team, array $roster) use ($outUserId, $inUserId) {
                if ($outUserId === $inUserId) {
                    throw new InvalidArgumentException('Выберите двух разных игроков.');
                }
                if (isset($team['held'][$outUserId])) {
                    throw new InvalidArgumentException(
                        'Этого игрока заменил либеро — сначала либеро должен уйти с площадки.'
                    );
                }
                $out = $team['court'][$outUserId] ?? null;
                if (!$out) {
                    throw new InvalidArgumentException('Заменяемый игрок сейчас не на площадке.');
                }
                if ($out['libero']) {
                    throw new InvalidArgumentException('Либеро уходит с площадки отдельным действием, а не заменой.');
                }
                if (!isset($roster[$inUserId])) {
                    throw new InvalidArgumentException('Выходящий игрок не входит в подтверждённый состав команды.');
                }
                if (isset($team['court'][$inUserId])) {
                    throw new InvalidArgumentException('Этот игрок уже на площадке.');
                }
                if ($inUserId === $team['libero_id']) {
                    throw new InvalidArgumentException('Либеро выходит на площадку отдельным действием «Либеро».');
                }
                if (isset($team['held'][$inUserId])) {
                    throw new InvalidArgumentException('Этого игрока заменил либеро — он вернётся, когда либеро уйдёт.');
                }

                return [
                    'type' => MatchLineupEvent::TYPE_SUBSTITUTION,
                    'out_user_id' => $outUserId, 'in_user_id' => $inUserId,
                    'position' => $out['position'], 'zone' => $out['zone'],
                ];
            });
    }

    /** Либеро выходит вместо любого игрока площадки и занимает его зону. */
    public function liberoIn(
        TournamentMatch $match,
        int $setNumber,
        int $teamId,
        int $partnerUserId,
        ?User $recordedBy = null,
        ?string $recordedByName = null,
        ?string $clientRequestId = null,
        ?int $recordedByLinkId = null,
    ): MatchLineupEvent {
        $profile = $this->profile($match);
        if (!$profile['has_libero']) {
            throw new InvalidArgumentException('В этой схеме игры либеро не предусмотрен.');
        }

        return $this->recordEvent($match, $setNumber, $teamId, $recordedBy, $recordedByName, $clientRequestId, byLinkId: $recordedByLinkId, build:
            function (array $team) use ($partnerUserId) {
                $libero = $team['libero_id'];
                if (!$libero) {
                    throw new InvalidArgumentException('Либеро не назначен в стартовом составе партии.');
                }
                if (isset($team['court'][$libero])) {
                    throw new InvalidArgumentException('Либеро уже на площадке.');
                }
                $partner = $team['court'][$partnerUserId] ?? null;
                if (!$partner || $partner['libero']) {
                    throw new InvalidArgumentException('Либеро можно выпустить только вместо игрока, который сейчас на площадке.');
                }

                return [
                    'type' => MatchLineupEvent::TYPE_LIBERO_IN,
                    'out_user_id' => $partnerUserId, 'in_user_id' => $libero,
                    'position' => $partner['position'], 'zone' => $partner['zone'],
                ];
            });
    }

    /** Либеро уходит с площадки — на его место возвращается игрок, вместо которого он вышел. */
    public function liberoOut(
        TournamentMatch $match,
        int $setNumber,
        int $teamId,
        ?User $recordedBy = null,
        ?string $recordedByName = null,
        ?string $clientRequestId = null,
        ?int $recordedByLinkId = null,
    ): MatchLineupEvent {
        return $this->recordEvent($match, $setNumber, $teamId, $recordedBy, $recordedByName, $clientRequestId, byLinkId: $recordedByLinkId, build:
            function (array $team) {
                $libero = $team['libero_id'];
                $entry = $libero ? ($team['court'][$libero] ?? null) : null;
                if (!$entry || !$entry['libero']) {
                    throw new InvalidArgumentException('Либеро сейчас не на площадке.');
                }
                $partner = (int) $entry['for_user_id'];
                $held = $team['held'][$partner] ?? ['position' => null, 'zone' => $entry['zone']];

                return [
                    'type' => MatchLineupEvent::TYPE_LIBERO_OUT,
                    'out_user_id' => $libero, 'in_user_id' => $partner,
                    'position' => $held['position'], 'zone' => $held['zone'],
                ];
            });
    }

    /**
     * Общая обвязка событий состава: блокировка матча, проверки, seq, снимок счёта, статистика, broadcast.
     *
     * @param callable(array,array):array $build (состояние команды, ростер команды) => атрибуты события
     */
    private function recordEvent(
        TournamentMatch $match,
        int $setNumber,
        int $teamId,
        ?User $by,
        ?string $byName,
        ?string $clientRequestId,
        callable $build,
        ?int $byLinkId = null,
    ): MatchLineupEvent {
        if ($clientRequestId !== null && $dup = $this->findByRequestId($match->id, $clientRequestId)) {
            return $dup;
        }

        $profile = $this->profile($match);
        $this->assertEnabled($profile);

        if (!in_array($teamId, [(int) $match->team_home_id, (int) $match->team_away_id], true)) {
            throw new InvalidArgumentException('Команда не участвует в этом матче.');
        }

        try {
            [$event, $isDuplicate] = DB::transaction(function () use ($match, $setNumber, $teamId, $by, $byName, $build, $clientRequestId, $byLinkId) {
                $locked = TournamentMatch::whereKey($match->id)->lockForUpdate()->first();

                if ($clientRequestId !== null && $dup = $this->findByRequestId($locked->id, $clientRequestId)) {
                    return [$dup, true];
                }

                $this->assertEditable($locked, $setNumber);

                $state = $this->state($locked, $setNumber);
                if (!$state['has_start']) {
                    throw new InvalidArgumentException('Сначала задайте стартовый состав.');
                }

                $attrs = $build($state['teams'][$teamId], $this->roster($locked)[$teamId] ?? []);

                $info = $this->rally()->computeMatchState($locked)['sets'][$setNumber];

                $event = MatchLineupEvent::create($attrs + [
                    'match_id' => $locked->id, 'set_number' => $setNumber, 'team_id' => $teamId,
                    'seq' => $this->nextSeq($locked),
                    'score_home' => $info['home'], 'score_away' => $info['away'],
                    'recorded_by_user_id' => $by?->id, 'recorded_by_link_id' => $byLinkId, 'recorded_by_name' => $byName,
                    'client_request_id' => $clientRequestId,
                ]);

                $this->rally()->syncPlayerStats($locked, $setNumber);

                return [$event, false];
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() === '23505') {
                throw new InvalidArgumentException('Повторите действие.');
            }
            throw $e;
        }

        if (!$isDuplicate) {
            broadcast(new TournamentMatchRallyUpdated($match->id, $setNumber));
            app(\App\Services\Broadcast\CourtStateBroadcaster::class)->forMatch($match->id);
        }

        return $event;
    }

    private function findByRequestId(int $matchId, string $clientRequestId): ?MatchLineupEvent
    {
        return MatchLineupEvent::where('match_id', $matchId)->where('client_request_id', $clientRequestId)->first();
    }

    /* ------------------------------------------------------------------ */
    /*  Статистика: позиции и «играл»                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Кто и на каких позициях выходил на площадку в партии (по стартовому составу и событиям).
     * null — состав в партии не велся.
     *
     * @return array<int, array<int,string>>|null user_id => позиции в порядке появления
     */
    public function participation(TournamentMatch $match, int $setNumber): ?array
    {
        if (!$this->hasStart($match, $setNumber)) {
            return null;
        }

        $part = [];
        $add = function (int $uid, ?string $pos) use (&$part) {
            $part[$uid] ??= [];
            if ($pos && !in_array($pos, $part[$uid], true)) {
                $part[$uid][] = $pos;
            }
        };

        $lineups = MatchSetLineup::where('match_id', $match->id)->where('set_number', $setNumber)
            ->where('position', '!=', self::LIBERO)->orderBy('zone')->get();
        foreach ($lineups as $row) {
            $add((int) $row->user_id, $row->position);
        }

        $events = MatchLineupEvent::where('match_id', $match->id)->where('set_number', $setNumber)
            ->orderBy('seq')->get();
        foreach ($events as $ev) {
            $pos = $ev->type === MatchLineupEvent::TYPE_LIBERO_IN ? self::LIBERO : $ev->position;
            $add((int) $ev->in_user_id, $pos);
        }

        return $part;
    }

    /**
     * Для статистики матча: кто выходил в стартовом составе (любой партии), кто — только либеро,
     * кто выходил на замену (событие substitution).
     *
     * @return array{starters:int[], liberos:int[], subbed_in:int[]}
     */
    public function participationSummary(int $matchId): array
    {
        $lineups = MatchSetLineup::where('match_id', $matchId)->get(['user_id', 'position']);
        $events = MatchLineupEvent::where('match_id', $matchId)->get(['type', 'in_user_id']);

        $starters = $lineups->where('position', '!=', self::LIBERO)->pluck('user_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $liberos = $lineups->where('position', self::LIBERO)->pluck('user_id')
            ->merge($events->where('type', MatchLineupEvent::TYPE_LIBERO_IN)->pluck('in_user_id'))
            ->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
        $subbed = $events->where('type', MatchLineupEvent::TYPE_SUBSTITUTION)->pluck('in_user_id')
            ->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();

        return ['starters' => $starters, 'liberos' => $liberos, 'subbed_in' => $subbed];
    }

    /**
     * Обновить positions/played у УЖЕ существующих строк match_player_stats (партия и итог матча).
     * Партии без состава не трогаются (positions/played остаются NULL — «не вели»).
     */
    public function syncParticipation(TournamentMatch $match, int $setNumber): void
    {
        $trackedSets = MatchSetStart::where('match_id', $match->id)->pluck('set_number')->map(fn ($v) => (int) $v)->all();
        if (!$trackedSets) {
            return;
        }

        $total = [];
        foreach ($trackedSets as $s) {
            $part = $this->participation($match, $s) ?? [];
            if ($s === $setNumber) {
                $this->applyParticipation($match, $s, $part);
            }
            foreach ($part as $uid => $positions) {
                $total[$uid] = array_values(array_unique([...($total[$uid] ?? []), ...$positions]));
            }
        }

        $this->applyParticipation($match, 0, $total);
    }

    private function applyParticipation(TournamentMatch $match, int $setNumber, array $part): void
    {
        foreach (MatchPlayerStats::where('match_id', $match->id)->where('set_number', $setNumber)->get() as $row) {
            $uid = (int) $row->user_id;
            $row->positions = $part[$uid] ?? [];
            $row->played = isset($part[$uid]);
            if ($row->isDirty()) {
                $row->save();
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Проверки                                                           */
    /* ------------------------------------------------------------------ */

    private function assertEnabled(array $profile): void
    {
        if (!$profile['enabled']) {
            throw new InvalidArgumentException('Учёт стартового состава и замен для этого матча не включён.');
        }
    }

    /** Матч не завершён, партия существует (предыдущие сыграны) и ещё не решена. */
    private function assertEditable(TournamentMatch $locked, int $setNumber): void
    {
        if ($locked->isCompleted()) {
            throw new InvalidArgumentException('Матч уже завершён.');
        }
        if (!$locked->hasTeams()) {
            throw new InvalidArgumentException('У матча не назначены команды.');
        }
        $info = $this->rally()->computeMatchState($locked)['sets'][$setNumber] ?? null;
        if (!$info) {
            throw new InvalidArgumentException('Эта партия сейчас недоступна.');
        }
        if ($info['decided']) {
            throw new InvalidArgumentException('Эта партия уже завершена.');
        }
    }

    /** Ленивое получение, чтобы не получить цикл зависимостей (MatchRallyService тоже зависит от этого сервиса). */
    private function rally(): MatchRallyService
    {
        return app(MatchRallyService::class);
    }
}
