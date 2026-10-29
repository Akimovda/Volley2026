<?php

namespace App\Services;

use App\Events\TournamentMatchRallyUpdated;
use App\Models\MatchActionLog;
use App\Models\MatchLineupEvent;
use App\Models\MatchRallyEvent;
use App\Models\MatchServeMark;
use App\Models\MatchSetStart;
use App\Models\TournamentMatch;
use App\Services\Broadcast\BroadcastControlService;
use App\Services\Broadcast\CourtStateBroadcaster;
use App\Support\RallyActor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Действия экрана поочкового ввода (очко, отмена, старт партии, замены): правила валидации и вызовы сервисов —
 * один код для организаторских роутов (TournamentController) и для ввода по токен-ссылке (ScorerController).
 * Исключения InvalidArgumentException (текст для пользователя) отдаёт вызывающий.
 */
class RallyInputService
{
    public function __construct(
        private MatchRallyService $rally,
        private MatchLineupService $lineupService,
    ) {}

    /** «Матч начался» / «Отменить начало»: партия не нужна — set_number только для возврата фрагмента экрана. */
    public static function matchStateRules(): array
    {
        return [
            'set_number'        => 'nullable|integer|min:1|max:5',
            'client_request_id' => 'nullable|uuid',
        ];
    }

    public static function pointRules(): array
    {
        return [
            'set_number'        => 'required|integer|min:1|max:5',
            'team_id'           => 'required|integer',
            'action_type'       => 'required|string',
            'player_id'         => 'nullable|integer',
            'dig_user_id'       => 'nullable|integer',
            'assist_user_id'    => 'nullable|integer',
            'attack_type'       => 'nullable|in:power,cut,poke,roll,tip',
            'client_request_id' => 'nullable|uuid',
        ];
    }

    public static function undoRules(): array
    {
        return [
            'set_number'        => 'required|integer|min:1|max:5',
            'expected_last_seq' => 'nullable|integer|min:0',
        ];
    }

    public static function startRules(): array
    {
        return [
            'set_number'                 => 'required|integer|min:1|max:5',
            'first_serve_team_id'        => 'required|integer',
            'lineups'                    => 'required|array',
            'lineups.*.court'            => 'required|array',
            'lineups.*.court.*.user_id'  => 'required|integer',
            'lineups.*.court.*.position' => 'required|string|max:20',
            'lineups.*.court.*.zone'     => 'required|integer|min:1|max:12',
            'lineups.*.libero'           => 'nullable|integer',
        ];
    }

    /** Номер на футболке (событие + игрок): правка с экрана расстановки. number пусто — убрать номер. */
    public static function jerseyRules(): array
    {
        return [
            'set_number' => 'required|integer|min:1|max:5',
            'user_id'    => 'required|integer',
            'number'     => 'nullable',
        ];
    }

    public static function eventRules(): array
    {
        return [
            'set_number'        => 'required|integer|min:1|max:5',
            'team_id'           => 'required|integer',
            'type'              => 'required|in:substitution,libero_in,libero_out,rotate,swap,fix,serve_flip',
            'out_user_id'       => 'nullable|integer',
            'in_user_id'        => 'nullable|integer',
            // «Исправить расстановку»: rotate — delta; swap — out/in; fix — delta + swaps (один шаг пользователя, отдельные события)
            'delta'             => 'nullable|integer|between:-11,11',
            'swaps'             => 'nullable|array|max:12',
            'swaps.*'           => 'array|size:2',
            'swaps.*.*'         => 'integer',
            'client_request_id' => 'nullable|uuid',
        ];
    }

    public function point(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $this->rally->recordPoint(
            $match,
            (int) $v['set_number'],
            (int) $v['team_id'],
            $v['action_type'],
            isset($v['player_id']) ? (int) $v['player_id'] : null,
            isset($v['dig_user_id']) ? (int) $v['dig_user_id'] : null,
            isset($v['assist_user_id']) ? (int) $v['assist_user_id'] : null,
            $actor->user,
            $v['client_request_id'] ?? null,
            $actor->name,
            $actor->linkId,
            $v['attack_type'] ?? null,
        );

        $this->scheduleAutoFinalize($match);
    }

    /** Секунд до автозавершения решённого матча «Игры со статистикой» (если не нажали «Записать счёт» и не было отмены). */
    public const AUTO_FINALIZE_DELAY = 90;

    private function scheduleAutoFinalize(TournamentMatch $match): void
    {
        try {
            $fresh = $match->fresh(['stage']);
            if (!$fresh || $fresh->isCompleted() || $fresh->stage?->type !== \App\Models\TournamentStage::TYPE_FRIENDLY) {
                return;
            }
            if (!$this->rally->isMatchReadyToFinalize($fresh)) {
                return;
            }
            \App\Jobs\FinalizeDecidedFriendlyMatchJob::dispatch($fresh->id, (int) $fresh->last_seq)
                ->delay(now()->addSeconds(self::AUTO_FINALIZE_DELAY));
        } catch (\Throwable $e) {
            report($e); // автозавершение не должно ломать ввод счёта
        }
    }

    public function undo(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $this->rally->undoLast(
            $match,
            (int) $v['set_number'],
            isset($v['expected_last_seq']) ? (int) $v['expected_last_seq'] : null,
            $actor->user,
            $actor->name,
            $actor->linkId,
        );
    }

    public function setStart(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $lineups = [];
        foreach ($v['lineups'] as $teamId => $team) {
            $lineups[(int) $teamId] = [
                'court'  => array_values($team['court']),
                'libero' => $team['libero'] ?? null,
            ];
        }

        $this->lineupService->setSetStart(
            $match, (int) $v['set_number'], $lineups, (int) $v['first_serve_team_id'],
            $actor->user, $actor->name, $actor->linkId
        );
    }

    public function serveFirst(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        app(BeachServeOrderService::class)->setFirst(
            $match, (int) $v['set_number'], (int) $v['team_id'], (int) $v['user_id'], $actor->user, $actor->name, $v['client_request_id'] ?? null, $actor->linkId
        );
    }

    public function jersey(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        app(JerseyNumberService::class)->setForMatch($match, (int) $v['user_id'], $v['number'] ?? null, $actor->user, $actor->linkId);
    }

    public function lineupEvent(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $svc = $this->lineupService;
        $set = (int) $v['set_number'];
        $team = (int) $v['team_id'];
        $out = isset($v['out_user_id']) ? (int) $v['out_user_id'] : 0;
        $in = isset($v['in_user_id']) ? (int) $v['in_user_id'] : 0;
        $rid = $v['client_request_id'] ?? null;

        if ($v['type'] === 'serve_flip') {
            // пляжка: «ошибка порядка подачи» — флип очереди подачи пары (lineup-событие rotate +1)
            app(BeachServeOrderService::class)->flip($match, $set, $team, $actor->user, $actor->name, $rid, $actor->linkId);

            return;
        }

        match ($v['type']) {
            'substitution' => $svc->substitute($match, $set, $team, $out, $in, $actor->user, $actor->name, $rid, $actor->linkId),
            'libero_in'    => $svc->liberoIn($match, $set, $team, $out, $actor->user, $actor->name, $rid, $actor->linkId),
            'libero_out'   => $svc->liberoOut($match, $set, $team, $actor->user, $actor->name, $rid, $actor->linkId),
            'rotate'       => $svc->rotate($match, $set, $team, (int) ($v['delta'] ?? 0), $actor->user, $actor->name, $rid, $actor->linkId),
            'swap'         => $svc->swapPlayers($match, $set, $team, $out, $in, $actor->user, $actor->name, $rid, $actor->linkId),
            'fix'          => $svc->fixLineup($match, $set, $team, (int) ($v['delta'] ?? 0), (array) ($v['swaps'] ?? []), $actor->user, $actor->name, $rid, $actor->linkId),
        };
    }

    /**
     * «Матч начался»: scheduled → live, started_at, снятие ручной заставки, табло 0:0 в эфире.
     * Идемпотентно: уже live — тихий успех; client_request_id защищает от запоздалого повтора после «Отменить начало».
     */
    public function matchStart(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $rid = $v['client_request_id'] ?? null;
        $cacheKey = $rid ? "rally:match-start:{$match->id}:{$rid}" : null;
        if ($cacheKey && Cache::has($cacheKey)) {
            return;
        }

        $changed = DB::transaction(function () use ($match) {
            $locked = TournamentMatch::whereKey($match->id)->lockForUpdate()->first();
            if ($locked->isCompleted()) {
                throw new InvalidArgumentException('Матч уже завершён.');
            }
            if (!$locked->hasTeams()) {
                throw new InvalidArgumentException('У матча не назначены команды.');
            }
            if ($locked->status === TournamentMatch::STATUS_LIVE) {
                return false;
            }
            if ($locked->status !== TournamentMatch::STATUS_SCHEDULED) {
                throw new InvalidArgumentException('Матч нельзя начать в текущем статусе.');
            }
            $locked->update(['status' => TournamentMatch::STATUS_LIVE, 'started_at' => now()]);

            return true;
        });

        if ($cacheKey) {
            Cache::put($cacheKey, 1, 600);
        }
        if ($changed) {
            $this->afterMatchStateChange($match, true);
        }
    }

    /** «Отменить начало»: live → scheduled, started_at = null — только пока нет очков/замен/расстановки. Идемпотентно. */
    public function matchUnstart(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $changed = DB::transaction(function () use ($match) {
            $locked = TournamentMatch::whereKey($match->id)->lockForUpdate()->first();
            if ($locked->isCompleted()) {
                throw new InvalidArgumentException('Матч уже завершён.');
            }
            if ($locked->status === TournamentMatch::STATUS_SCHEDULED) {
                return false;
            }
            if ($locked->status !== TournamentMatch::STATUS_LIVE) {
                throw new InvalidArgumentException('Матч нельзя вернуть в ожидание.');
            }
            if (self::hasPlay($locked)) {
                throw new InvalidArgumentException('В матче уже есть очки — отменить начало нельзя.');
            }
            $locked->update(['status' => TournamentMatch::STATUS_SCHEDULED, 'started_at' => null]);

            return true;
        });

        if ($changed) {
            $this->afterMatchStateChange($match, false);
        }
    }

    /** Пляжка 2×2: «Подаёт первым» (пара + игрок) / первый подающий второй пары. */
    public static function serveFirstRules(): array
    {
        return [
            'set_number'        => 'required|integer|min:1|max:5',
            'team_id'           => 'required|integer',
            'user_id'           => 'required|integer',
            'client_request_id' => 'nullable|uuid',
        ];
    }

    public static function serveMarkRules(): array
    {
        return [
            'set_number'        => 'required|integer|min:1|max:5',
            'team_id'           => 'required|integer',
            'client_request_id' => 'nullable|uuid',
        ];
    }

    /**
     * Ручная отметка «подаёт первым» для табло: только пока в партии нет очков, меняется до первого очка.
     * Не очко и не событие состава: без seq, не затрагивает undoLast. Идемпотентность — client_request_id
     * (кеш 10 мин, как у matchStart) + сравнение с текущим состоянием; каждое реальное изменение — в match_action_log.
     */
    public function serveMark(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $set = (int) $v['set_number'];
        $teamId = (int) $v['team_id'];
        $rid = $v['client_request_id'] ?? null;
        $cacheKey = $rid ? "rally:serve-mark:{$match->id}:{$rid}" : null;
        if ($cacheKey && Cache::has($cacheKey)) {
            return;
        }

        $changed = DB::transaction(function () use ($match, $set, $teamId, $rid, $actor) {
            $locked = TournamentMatch::whereKey($match->id)->lockForUpdate()->first();
            if ($locked->isCompleted()) {
                throw new InvalidArgumentException('Матч уже завершён.');
            }
            if (!$locked->hasTeams()) {
                throw new InvalidArgumentException('У матча не назначены команды.');
            }
            if (!in_array($teamId, [(int) $locked->team_home_id, (int) $locked->team_away_id], true)) {
                throw new InvalidArgumentException('Команда не участвует в этом матче.');
            }
            if ($set !== $this->rally->getActiveSetNumber($locked)) {
                throw new InvalidArgumentException('Подачу можно отметить только в текущей партии.');
            }
            if (MatchRallyEvent::where('match_id', $locked->id)->where('set_number', $set)->exists()) {
                throw new InvalidArgumentException('В партии уже есть очки — подача определяется по ним.');
            }

            $mark = MatchServeMark::where('match_id', $locked->id)->where('set_number', $set)->first();
            if ($mark && (int) $mark->team_id === $teamId) {
                return false;
            }

            MatchActionLog::create([
                'match_id'            => $locked->id,
                'set_number'          => $set,
                'action'              => 'serve_mark',
                'payload'             => ['team_id' => $teamId, 'previous_team_id' => $mark?->team_id, 'client_request_id' => $rid],
                'recorded_by_user_id' => $actor->user?->id,
                'recorded_by_link_id' => $actor->linkId,
                'recorded_by_name'    => $actor->name,
            ]);

            $mark ??= new MatchServeMark(['match_id' => $locked->id, 'set_number' => $set]);
            $mark->team_id = $teamId;
            $mark->client_request_id = $rid;
            $mark->recorded_by_user_id = $actor->user?->id;
            $mark->recorded_by_link_id = $actor->linkId;
            $mark->recorded_by_name = $actor->name;
            $mark->save();

            return true;
        });

        if ($cacheKey) {
            Cache::put($cacheKey, 1, 600);
        }
        if ($changed) {
            try {
                app(CourtStateBroadcaster::class)->forMatch($match->id);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** Есть очки, замены или расстановка партии (то, что «Отменить начало» затереть не должна). */
    public static function hasPlay(TournamentMatch $match): bool
    {
        return MatchRallyEvent::where('match_id', $match->id)->exists()
            || MatchLineupEvent::where('match_id', $match->id)->exists()
            || MatchSetStart::where('match_id', $match->id)->exists();
    }

    private function afterMatchStateChange(TournamentMatch $match, bool $started): void
    {
        try {
            broadcast(new TournamentMatchRallyUpdated($match->id, 1));
        } catch (\Throwable $e) {
            report($e);
        }
        if ($started) {
            app(BroadcastControlService::class)->releaseSplashOnPlay($match);
        }
        app(CourtStateBroadcaster::class)->forMatch($match->id);
    }
}
