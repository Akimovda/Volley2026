<?php

namespace App\Services;

use App\Events\TournamentMatchRallyUpdated;
use App\Models\MatchLineupEvent;
use App\Models\MatchRallyEvent;
use App\Models\MatchServeFirst;
use App\Models\MatchServeMark;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Порядок подачи в пляжной паре (2×2): игроки пары подают по очереди, очередь меняется, когда пара получает подачу
 * (side-out) — та же логика переходов, что у классики при N = 2 (timeline() + rotate-события из MatchLineupService).
 *
 *  • первая подающая команда партии — match_serve_marks, её первый подающий — match_serve_firsts;
 *  • вторая пара: «первый подающий» спрашивается один раз, когда она впервые получает подачу;
 *  • текущий подающий пары T = order[(rot[T] − (T подавала первой ? 0 : 1)) mod 2], order = [первый, партнёр];
 *  • ручная правка очереди («ошибка порядка подачи») — lineup-событие rotate +1 (флип) с seq: откатывается обычным undo.
 * Включено только для пляжных матчей, где в обеих парах ровно 2 подтверждённых игрока; иначе всё как раньше.
 */
class BeachServeOrderService
{
    public function __construct(private MatchLineupService $lineup) {}

    /** @return array<int,int[]> team_id => [user_id, user_id] (порядок добавления) либо [] если режим выключен */
    public function pairs(TournamentMatch $match): array
    {
        $stage = $match->relationLoaded('stage') ? $match->stage : $match->stage()->first();
        $event = $stage?->event;
        if (!$event || $event->direction !== 'beach' || !$match->team_home_id || !$match->team_away_id) {
            return [];
        }

        $out = [];
        foreach ([(int) $match->team_home_id, (int) $match->team_away_id] as $tid) {
            $ids = DB::table('event_team_members')->where('event_team_id', $tid)->where('confirmation_status', 'confirmed')
                ->orderBy('id')->pluck('user_id')->map(fn ($v) => (int) $v)->all();
            if (count($ids) !== 2) {
                return [];
            }
            $out[$tid] = $ids;
        }

        return $out;
    }

    public function enabled(TournamentMatch $match): bool
    {
        return (bool) $this->pairs($match);
    }

    /**
     * Состояние очереди на момент seq (по умолчанию — сейчас).
     *
     * @return array{enabled:bool, start_team_id:?int, serving_team_id:?int, rot:array<int,int>, first:array<int,?int>, server:array<int,?int>, needs_first_team_id:?int}
     */
    public function state(TournamentMatch $match, int $setNumber, ?int $upToSeq = null): array
    {
        $pairs = $this->pairs($match);
        $home = (int) $match->team_home_id;
        $away = (int) $match->team_away_id;
        $empty = ['enabled' => (bool) $pairs, 'start_team_id' => null, 'serving_team_id' => null, 'rot' => [$home => 0, $away => 0],
            'first' => [$home => null, $away => null], 'server' => [$home => null, $away => null], 'needs_first_team_id' => null];
        if (!$pairs) {
            return $empty;
        }

        $start = (int) (MatchServeMark::where('match_id', $match->id)->where('set_number', $setNumber)->value('team_id') ?? 0);
        $first = MatchServeFirst::where('match_id', $match->id)->where('set_number', $setNumber)->pluck('first_server_user_id', 'team_id')
            ->map(fn ($v) => (int) $v)->all();
        $empty['first'] = [$home => $first[$home] ?? null, $away => $first[$away] ?? null];
        if (!$start) {
            return $empty;
        }

        $rot = [$home => 0, $away => 0];
        $serving = $start;
        foreach ($this->lineup->timeline($match, $setNumber, $upToSeq) as $item) {
            if ($item['kind'] === 'point') {
                if ($item['team_id'] !== $serving) {
                    $rot[$item['team_id']]++;
                    $serving = $item['team_id'];
                }
            } elseif ($item['ev']->type === MatchLineupEvent::TYPE_ROTATE) {
                $rot[$item['team_id']] += (int) $item['ev']->position;
            }
        }

        $server = [$home => null, $away => null];
        foreach ([$home, $away] as $tid) {
            $f = $first[$tid] ?? null;
            if (!$f) {
                continue;
            }
            $partner = collect($pairs[$tid])->first(fn ($u) => $u !== $f);
            $idx = ((($rot[$tid] - ($tid === $start ? 0 : 1)) % 2) + 2) % 2;
            if ($tid !== $start && $rot[$tid] < 1) {
                continue; // вторая пара ещё не подавала
            }
            $server[$tid] = $idx === 0 ? $f : $partner;
        }

        return [
            'enabled' => true, 'start_team_id' => $start, 'serving_team_id' => $serving, 'rot' => $rot,
            'first' => [$home => $first[$home] ?? null, $away => $first[$away] ?? null], 'server' => $server,
            'needs_first_team_id' => (!isset($first[$serving])) ? $serving : null,
        ];
    }

    /** Подающий сейчас: team_id, user_id (null, если первый подающий этой пары ещё не указан). */
    public function currentServer(TournamentMatch $match, int $setNumber): ?array
    {
        $s = $this->state($match, $setNumber);
        if (!$s['enabled'] || !$s['serving_team_id']) {
            return null;
        }

        return ['team_id' => $s['serving_team_id'], 'user_id' => $s['server'][$s['serving_team_id']] ?? null];
    }

    /**
     * «Подаёт первым»: пара подаёт и первым подаёт этот игрок (первая команда партии — только пока нет очков) либо один раз
     * указать первого подающего второй пары. Идемпотентно по client_request_id.
     */
    public function setFirst(TournamentMatch $match, int $setNumber, int $teamId, int $userId, ?User $by = null, ?string $byName = null, ?string $clientRequestId = null, ?int $byLinkId = null): void
    {
        $pairs = $this->pairs($match);
        if (!$pairs) {
            throw new InvalidArgumentException('Порядок подачи доступен только для пляжных пар из двух игроков.');
        }
        if (!isset($pairs[$teamId]) || !in_array($userId, $pairs[$teamId], true)) {
            throw new InvalidArgumentException('Игрок не входит в состав этой пары.');
        }

        $changed = DB::transaction(function () use ($match, $setNumber, $teamId, $userId, $by, $byName, $clientRequestId, $byLinkId) {
            $locked = TournamentMatch::whereKey($match->id)->lockForUpdate()->first();
            if ($locked->isCompleted()) {
                throw new InvalidArgumentException('Матч уже завершён.');
            }
            if ($clientRequestId && MatchServeFirst::where('match_id', $locked->id)->where('client_request_id', $clientRequestId)->exists()) {
                return false;
            }

            $hasPoints = MatchRallyEvent::where('match_id', $locked->id)->where('set_number', $setNumber)->exists();
            $mark = MatchServeMark::where('match_id', $locked->id)->where('set_number', $setNumber)->first();
            $isStartTeam = $mark && (int) $mark->team_id === $teamId;

            if (!$hasPoints) {
                // начало партии: тап по игроку = эта пара подаёт первой и первым подаёт этот игрок
                if ($setNumber !== app(MatchRallyService::class)->getActiveSetNumber($locked)) {
                    throw new InvalidArgumentException('Подачу можно отметить только в текущей партии.');
                }
                $mark ??= new MatchServeMark(['match_id' => $locked->id, 'set_number' => $setNumber]);
                $other = (int) $locked->team_home_id === $teamId ? (int) $locked->team_away_id : (int) $locked->team_home_id;
                if ((int) ($mark->team_id ?? 0) !== $teamId) {
                    // смена подающей пары: строка прежней первой пары и «вторая» больше не актуальны
                    MatchServeFirst::where('match_id', $locked->id)->where('set_number', $setNumber)->delete();
                }
                $mark->team_id = $teamId;
                $mark->client_request_id = $clientRequestId;
                $mark->recorded_by_user_id = $by?->id;
                $mark->recorded_by_link_id = $byLinkId;
                $mark->recorded_by_name = $byName;
                $mark->save();
            } elseif (!$mark || $isStartTeam) {
                throw new InvalidArgumentException('Первого подающего первой пары можно менять только до первого очка партии.');
            }

            MatchServeFirst::updateOrCreate(
                ['match_id' => $locked->id, 'set_number' => $setNumber, 'team_id' => $teamId],
                ['first_server_user_id' => $userId, 'client_request_id' => $clientRequestId,
                    'recorded_by_user_id' => $by?->id, 'recorded_by_link_id' => $byLinkId, 'recorded_by_name' => $byName]
            );

            return true;
        });

        if ($changed) {
            try {
                broadcast(new TournamentMatchRallyUpdated($match->id, $setNumber));
                app(\App\Services\Broadcast\CourtStateBroadcaster::class)->forMatch($match->id);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * «Исправить очередь» (ошибка порядка подачи): флип очереди пары — lineup-событие rotate +1 с seq.
     * Откат — обычный undo; повтор client_request_id не создаёт второе событие.
     */
    public function flip(TournamentMatch $match, int $setNumber, int $teamId, ?User $by = null, ?string $byName = null, ?string $clientRequestId = null, ?int $byLinkId = null): ?MatchLineupEvent
    {
        $pairs = $this->pairs($match);
        if (!$pairs || !isset($pairs[$teamId])) {
            throw new InvalidArgumentException('Порядок подачи доступен только для пляжных пар из двух игроков.');
        }
        if ($clientRequestId && $dup = MatchLineupEvent::where('match_id', $match->id)->where('client_request_id', $clientRequestId)->first()) {
            return $dup;
        }

        $event = DB::transaction(function () use ($match, $setNumber, $teamId, $by, $byName, $clientRequestId, $byLinkId) {
            $locked = TournamentMatch::whereKey($match->id)->lockForUpdate()->first();
            if ($locked->isCompleted()) {
                throw new InvalidArgumentException('Матч уже завершён.');
            }
            if ($clientRequestId && $dup = MatchLineupEvent::where('match_id', $locked->id)->where('client_request_id', $clientRequestId)->first()) {
                return $dup;
            }
            if (!MatchServeMark::where('match_id', $locked->id)->where('set_number', $setNumber)->exists()) {
                throw new InvalidArgumentException('Сначала выберите, кто подаёт первым.');
            }
            $info = app(MatchRallyService::class)->computeMatchState($locked)['sets'][$setNumber] ?? ['home' => 0, 'away' => 0];

            return MatchLineupEvent::create([
                'match_id' => $locked->id, 'set_number' => $setNumber, 'team_id' => $teamId, 'type' => MatchLineupEvent::TYPE_ROTATE,
                'position' => '+1', 'out_user_id' => null, 'in_user_id' => null, 'zone' => null,
                'seq' => $this->lineup->nextSeq($locked), 'score_home' => $info['home'], 'score_away' => $info['away'],
                'recorded_by_user_id' => $by?->id, 'recorded_by_link_id' => $byLinkId, 'recorded_by_name' => $byName,
                'client_request_id' => $clientRequestId,
            ]);
        });

        try {
            broadcast(new TournamentMatchRallyUpdated($match->id, $setNumber));
            app(\App\Services\Broadcast\CourtStateBroadcaster::class)->forMatch($match->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return $event;
    }

    /**
     * Данные для пульта (пляжка 2×2): карточки игроков обеих пар (фото/инициалы, фамилия, номер, метка Бл/Защ), цвета пар,
     * кто сейчас подаёт, нужна ли отметка первого подающего второй пары, можно ли выбрать «подаёт первым».
     */
    public function forRally(TournamentMatch $match, int $setNumber, bool $decided): array
    {
        $pairs = $this->pairs($match);
        if (!$pairs) {
            return ['enabled' => false];
        }

        $st = $this->state($match, $setNumber);
        $stage = $match->relationLoaded('stage') ? $match->stage : $match->stage()->first();
        $eventId = (int) $stage?->event_id;
        $allIds = array_merge(...array_values($pairs));
        $cards = app(PlayerCardService::class)->cards($allIds, $eventId);
        $posCodes = DB::table('event_team_members')->whereIn('event_team_id', array_keys($pairs))->where('confirmation_status', 'confirmed')
            ->pluck('position_code', 'user_id');
        $styles = app(\App\Services\Broadcast\TeamStyleService::class);
        $teamModels = \App\Models\EventTeam::whereIn('id', array_keys($pairs))->get()->keyBy('id');

        $cardOf = function (int $uid) use ($cards, $posCodes) {
            $c = $cards[$uid] ?? ['id' => $uid, 'surname' => '#' . $uid, 'first' => '', 'initials' => '?', 'photo' => null, 'number' => null];
            $code = $posCodes[$uid] ?? null;
            $c['pos'] = in_array($code, ['blocker', 'defender'], true) ? __('tournaments.pos_abbr_' . $code) : '';

            return $c;
        };

        $sides = ['home' => (int) $match->team_home_id, 'away' => (int) $match->team_away_id];
        $teams = [];
        foreach ($sides as $side => $tid) {
            $color = $teamModels->get($tid) ? $styles->colorFor($teamModels->get($tid)) : '#2967BA';
            $teams[$side] = [
                'team_id' => $tid, 'name' => (string) ($teamModels->get($tid)?->name ?? ''), 'color' => $color, 'text' => PlayerCardService::textOn($color),
                'players' => array_map($cardOf, $pairs[$tid]), 'first_id' => $st['first'][$tid] ?? null,
                'server_id' => $st['server'][$tid] ?? null, 'serving' => $st['serving_team_id'] === $tid,
            ];
        }

        $servingSide = $st['serving_team_id'] ? (array_search($st['serving_team_id'], $sides, true) ?: null) : null;
        $serverId = $servingSide ? ($teams[$servingSide]['server_id'] ?? null) : null;
        $needsSide = $st['needs_first_team_id'] ? (array_search($st['needs_first_team_id'], $sides, true) ?: null) : null;
        $hasPoints = MatchRallyEvent::where('match_id', $match->id)->where('set_number', $setNumber)->exists();

        return [
            'enabled' => true, 'teams' => $teams, 'serving_side' => $servingSide, 'server' => $serverId ? $cardOf((int) $serverId) : null,
            'needs_first_side' => $needsSide,
            'can_pick_first' => !$hasPoints && !$decided && !$match->isCompleted() && $setNumber === app(MatchRallyService::class)->getActiveSetNumber($match),
            'has_start' => (bool) $st['start_team_id'], 'set' => $setNumber,
            'sig' => $setNumber . ':' . ($st['serving_team_id'] ?? 0) . ':' . implode('-', $st['rot']) . ':' . implode('-', array_map(fn ($x) => (int) $x, $st['first'])),
        ];
    }

    /**
     * Подающий каждого розыгрыша матча: seq очка => user_id (null — подающий не известен). Для статистики «подачи на игрока».
     *
     * @return array<int, array{set:int, team_id:int, user_id:?int}>
     */
    public function serversBySeq(TournamentMatch $match): array
    {
        $pairs = $this->pairs($match);
        if (!$pairs) {
            return [];
        }
        $home = (int) $match->team_home_id;
        $away = (int) $match->team_away_id;
        $out = [];
        $sets = MatchRallyEvent::where('match_id', $match->id)->distinct()->pluck('set_number')->all();
        foreach ($sets as $sn) {
            $start = (int) (MatchServeMark::where('match_id', $match->id)->where('set_number', $sn)->value('team_id') ?? 0);
            if (!$start) {
                continue;
            }
            $first = MatchServeFirst::where('match_id', $match->id)->where('set_number', $sn)->pluck('first_server_user_id', 'team_id')->map(fn ($v) => (int) $v)->all();
            $rot = [$home => 0, $away => 0];
            $serving = $start;
            foreach ($this->lineup->timeline($match, (int) $sn) as $item) {
                if ($item['kind'] === 'lineup') {
                    if ($item['ev']->type === MatchLineupEvent::TYPE_ROTATE) {
                        $rot[$item['team_id']] += (int) $item['ev']->position;
                    }
                    continue;
                }
                $uid = null;
                $f = $first[$serving] ?? null;
                if ($f && ($serving === $start || $rot[$serving] >= 1)) {
                    $partner = collect($pairs[$serving])->first(fn ($u) => $u !== $f);
                    $idx = ((($rot[$serving] - ($serving === $start ? 0 : 1)) % 2) + 2) % 2;
                    $uid = $idx === 0 ? $f : $partner;
                }
                $out[$item['seq']] = ['set' => (int) $sn, 'team_id' => $serving, 'user_id' => $uid];
                if ($item['team_id'] !== $serving) {
                    $rot[$item['team_id']]++;
                    $serving = $item['team_id'];
                }
            }
        }

        return $out;
    }
}
