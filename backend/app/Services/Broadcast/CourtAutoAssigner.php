<?php

namespace App\Services\Broadcast;

use App\Models\Event;
use App\Models\EventCourt;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Единое место правил назначения матчей на площадки (event_courts).
 *
 * Источник площадок — event_courts мероприятия (через CourtRegistry); у группы может быть свой список
 * (tournament_groups.courts). Нет ни одной площадки — создаём «Площадка 1».
 *
 * Правила распределения (только для матчей, которые автоматика вправе трогать):
 *  1. одна площадка — все матчи на неё;
 *  2. несколько площадок и групповой этап — группа целиком на одну площадку, по кругу в порядке групп (A→1, B→2, C→1…);
 *  3. иначе (плей-офф, швейцарка, матчи без группы) — по порядку расписания (время, раунд, номер), по кругу.
 *
 * Трогаем: матчи без площадки и матчи с court_assigned_by='auto', если они не начаты.
 * Никогда не трогаем: 'manual', завершённые/отменённые, начатые (очки или статус live).
 */
class CourtAutoAssigner
{
    /** Метки в tournament_matches.court, на которых держится логика сеток — их не перезаписываем названием площадки. */
    private const PROTECTED_LABELS = ['Grand Final', 'Grand Final Reset', '3rd place'];

    /** Стадии, где корты живут в собственной механике (meta/состояние корта), а не в court_id. */
    private const SKIP_TYPES = [TournamentStage::TYPE_KING_OF_COURT, TournamentStage::TYPE_KING_BEACH];

    /** Сколько площадок создано за время жизни экземпляра (для сводки dry-run). */
    public int $createdCourts = 0;

    /** Рассылать обновление состояния площадок оверлею (в dry-run выключаем). */
    public bool $notify = true;

    /** @var array<int,true> */
    private array $touchedCourts = [];

    public function __construct(protected CourtRegistry $registry) {}

    /* ------------------------------------------------------------------ */
    /*  Площадки                                                           */
    /* ------------------------------------------------------------------ */

    public static function defaultCourtName(): string
    {
        return __('stream.court_default_name');
    }

    /**
     * Активные площадки мероприятия (для тура — площадки тура и общие). Пусто — создаём «Площадка 1»,
     * но только если у мероприятия вообще нет площадок (скрытые площадки не считаются «нет площадок»).
     *
     * @return Collection<int,EventCourt>
     */
    public function pool(int $eventId, ?int $occurrenceId, bool $create = true): Collection
    {
        $scope = fn () => EventCourt::where('event_id', $eventId)
            ->when($occurrenceId, fn ($q) => $q->where(fn ($w) => $w->where('occurrence_id', $occurrenceId)->orWhereNull('occurrence_id')),
                fn ($q) => $q->whereNull('occurrence_id'));

        $courts = $scope()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        // Площадки самого тура важнее общих (occurrence_id = null): общие — только если у тура своих нет
        if ($occurrenceId && $courts->contains(fn ($c) => (int) $c->occurrence_id === $occurrenceId)) {
            $courts = $courts->filter(fn ($c) => (int) $c->occurrence_id === $occurrenceId)->values();
        }

        if ($courts->isEmpty() && $create && !$scope()->exists()) {
            $courts = collect([$this->ensureCourt($eventId, $occurrenceId, self::defaultCourtName())]);
        }

        return $courts;
    }

    private function ensureCourt(int $eventId, ?int $occurrenceId, string $name): EventCourt
    {
        $court = $this->registry->ensure($eventId, $occurrenceId, $name);
        if ($court->wasRecentlyCreated) {
            $this->createdCourts++;
        }
        return $court;
    }

    /* ------------------------------------------------------------------ */
    /*  Автоназначение                                                     */
    /* ------------------------------------------------------------------ */

    /** Матч начат: есть очки/счёт или статус live. Такие автоматика не двигает. */
    public static function isStarted(TournamentMatch $m): bool
    {
        if ($m->status === TournamentMatch::STATUS_LIVE || $m->started_at !== null) {
            return true;
        }
        return !empty($m->score_home) || !empty($m->score_away)
            || (int) $m->total_points_home > 0 || (int) $m->total_points_away > 0
            || (int) $m->sets_home > 0 || (int) $m->sets_away > 0;
    }

    private static function isOpen(TournamentMatch $m): bool
    {
        return !in_array($m->status, [TournamentMatch::STATUS_COMPLETED, TournamentMatch::STATUS_CANCELLED, TournamentMatch::STATUS_FORFEIT], true);
    }

    /** Может ли автоматика (пере)назначить площадку этому матчу. */
    public static function isAutoEditable(TournamentMatch $m): bool
    {
        if (!self::isOpen($m) || self::isStarted($m)) {
            return false;
        }
        return $m->court_id === null || $m->court_assigned_by === TournamentMatch::COURT_BY_AUTO;
    }

    /**
     * Расставить площадки матчам стадии.
     *
     * @return int сколько матчей изменили
     */
    public function assignStage(TournamentStage $stage): int
    {
        if (in_array($stage->type, self::SKIP_TYPES, true) || !$stage->event_id) {
            return 0;
        }

        $todo = TournamentMatch::where('stage_id', $stage->id)->get()->filter(fn ($m) => self::isAutoEditable($m));
        if ($todo->isEmpty()) {
            return 0;
        }

        $eventId = (int) $stage->event_id;
        $occId = $stage->occurrence_id ? (int) $stage->occurrence_id : null;

        // Площадки, заданные организатором в стадии, становятся площадками мероприятия
        foreach ((array) $stage->cfg('courts', []) as $name) {
            if (is_string($name) && trim($name) !== '') {
                $this->ensureCourt($eventId, $occId, trim($name));
            }
        }

        $stage->loadMissing('groups');
        $groupOwn = [];
        foreach ($stage->groups as $g) {
            $names = array_values(array_filter(array_map(fn ($n) => is_string($n) ? trim($n) : '', (array) ($g->courts ?? []))));
            if ($names) {
                $groupOwn[$g->id] = array_map(fn ($n) => $this->ensureCourt($eventId, $occId, $n), $names);
            }
        }

        $pool = $this->pool($eventId, $occId)->values();
        if ($pool->isEmpty()) {
            return 0; // все площадки скрыты — распределять некуда
        }

        $groupIndex = $stage->groups->pluck('id')->values()->flip();
        $groupMode = $stage->isGroupStage();

        $ordered = $todo->sortBy([
            fn ($a, $b) => ($a->scheduled_at ? $a->scheduled_at->getTimestamp() : PHP_INT_MAX) <=> ($b->scheduled_at ? $b->scheduled_at->getTimestamp() : PHP_INT_MAX),
            ['round', 'asc'], ['match_number', 'asc'], ['id', 'asc'],
        ])->values();

        $rr = 0;
        $rrGroup = [];
        $changed = 0;

        foreach ($ordered as $m) {
            $gid = $groupMode && $m->group_id ? (int) $m->group_id : null;
            if ($gid && isset($groupOwn[$gid])) {
                $i = $rrGroup[$gid] = ($rrGroup[$gid] ?? -1) + 1;
                $court = $groupOwn[$gid][$i % count($groupOwn[$gid])];
            } elseif ($gid) {
                $court = $pool[((int) ($groupIndex[$gid] ?? 0)) % $pool->count()];
            } else {
                $court = $pool[$rr++ % $pool->count()];
            }

            if ($this->apply($m, $court, TournamentMatch::COURT_BY_AUTO)) {
                $changed++;
            }
        }

        $this->flushNotifications();

        return $changed;
    }

    /** Все стадии мероприятия (в рамках тура — стадии тура и общие). */
    public function assignScope(int $eventId, ?int $occurrenceId): int
    {
        $n = 0;
        TournamentStage::where('event_id', $eventId)
            ->when($occurrenceId, fn ($q) => $q->where(fn ($w) => $w->where('occurrence_id', $occurrenceId)->orWhereNull('occurrence_id')))
            ->orderBy('id')->get()
            ->each(function (TournamentStage $s) use (&$n) {
                $n += $this->assignStage($s);
            });
        return $n;
    }

    public function assignEvent(Event $event): int
    {
        return $this->assignScope((int) $event->id, null);
    }

    /* ------------------------------------------------------------------ */
    /*  Ручное назначение                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Организатор поставил матч на площадку (в т.ч. на ту же самую) — это 'manual'.
     * Возвращает предупреждение, если в это время на площадке уже стоит другой матч (не запрещаем).
     */
    public function assignManual(TournamentMatch $match, EventCourt $court): ?string
    {
        $this->apply($match, $court, TournamentMatch::COURT_BY_MANUAL, true);
        $this->flushNotifications();

        return $this->conflictWarning($match, $court);
    }

    public function conflictWarning(TournamentMatch $match, EventCourt $court): ?string
    {
        if (!$match->scheduled_at) {
            return null;
        }
        $match->loadMissing('stage');
        $slot = (int) ($match->stage?->cfg('schedule_match_duration_min', 30) ?? 30) + (int) ($match->stage?->cfg('schedule_break_duration_min', 5) ?? 5);
        $slot = max(1, $slot);
        $t = $match->scheduled_at->copy();

        $other = TournamentMatch::with(['teamHome', 'teamAway'])
            ->where('court_id', $court->id)->where('id', '!=', $match->id)
            ->whereNotIn('status', [TournamentMatch::STATUS_COMPLETED, TournamentMatch::STATUS_CANCELLED, TournamentMatch::STATUS_FORFEIT])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', $t->copy()->subMinutes($slot))
            ->where('scheduled_at', '<', $t->copy()->addMinutes($slot))
            ->orderBy('scheduled_at')->first();
        if (!$other) {
            return null;
        }

        $tz = $match->stage?->event?->timezone ?: 'Europe/Moscow';
        $label = '№' . $other->match_number . ' ' . ($other->teamHome?->name ?? '?') . ' — ' . ($other->teamAway?->name ?? '?')
            . ' (' . $other->scheduled_at->copy()->timezone($tz)->format('H:i') . ')';

        return __('stream.court_conflict', ['court' => $court->name, 'match' => $label]);
    }

    /* ------------------------------------------------------------------ */
    /*  Удаление площадки                                                  */
    /* ------------------------------------------------------------------ */

    /** На площадке сейчас идёт (или начат) матч. */
    public function liveMatchOn(EventCourt $court): ?TournamentMatch
    {
        return TournamentMatch::with(['teamHome', 'teamAway'])->where('court_id', $court->id)
            ->whereNotIn('status', [TournamentMatch::STATUS_COMPLETED, TournamentMatch::STATUS_CANCELLED, TournamentMatch::STATUS_FORFEIT])
            ->get()->first(fn ($m) => self::isStarted($m));
    }

    /**
     * Удалить площадку: не начатые матчи (и 'auto', и 'manual') возвращаются в автоматику и расставляются заново,
     * завершённые остаются без площадки. Нельзя, пока на площадке идёт матч.
     */
    public function deleteCourt(EventCourt $court): void
    {
        if ($m = $this->liveMatchOn($court)) {
            throw new InvalidArgumentException(__('stream.court_delete_live', [
                'match' => ($m->teamHome?->name ?? '?') . ' — ' . ($m->teamAway?->name ?? '?'),
            ]));
        }

        $eventId = (int) $court->event_id;
        $occId = $court->occurrence_id ? (int) $court->occurrence_id : null;

        DB::transaction(function () use ($court) {
            $rows = TournamentMatch::where('court_id', $court->id)->get();
            foreach ($rows as $m) {
                $open = self::isOpen($m) && !self::isStarted($m);
                TournamentMatch::whereKey($m->id)->update([
                    'court_id'          => null,
                    'court_assigned_by' => $open ? TournamentMatch::COURT_BY_AUTO : null,
                ]);
            }
            $court->delete();
        });

        $this->assignScope($eventId, $occId);
    }

    /* ------------------------------------------------------------------ */
    /*  Внутреннее                                                         */
    /* ------------------------------------------------------------------ */

    /** Записать площадку матчу. Возвращает true, если что-то изменилось. */
    private function apply(TournamentMatch $m, EventCourt $court, string $by, bool $viaModel = false): bool
    {
        $label = $this->labelFor($m, $court);
        $same = (int) $m->court_id === (int) $court->id && $m->court_assigned_by === $by && $m->court === $label;
        if ($same) {
            return false;
        }

        if ($m->court_id) {
            $this->touchedCourts[(int) $m->court_id] = true;
        }
        $this->touchedCourts[(int) $court->id] = true;

        $attrs = ['court_id' => $court->id, 'court_assigned_by' => $by, 'court' => $label];
        if ($viaModel) {
            $m->forceFill($attrs)->save();
        } else {
            TournamentMatch::whereKey($m->id)->update($attrs);
            $m->forceFill($attrs)->syncOriginal();
        }
        return true;
    }

    /** Строка tournament_matches.court (её читают PDF/сетка/уведомления): название площадки, кроме меток логики сетки. */
    private function labelFor(TournamentMatch $m, EventCourt $court): string
    {
        $cur = (string) $m->court;
        if (in_array($cur, self::PROTECTED_LABELS, true)) {
            return $cur;
        }
        if (empty($m->meta['placement_from']) && preg_match('/за\s+\d+-\d+\s+место/u', $cur) === 1) {
            return $cur; // старый матч без meta: метка — единственный источник правды
        }
        return $court->name;
    }

    private function flushNotifications(): void
    {
        $ids = array_keys($this->touchedCourts);
        $this->touchedCourts = [];
        if (!$this->notify || !$ids) {
            return;
        }
        try {
            $b = app(CourtStateBroadcaster::class);
            foreach (EventCourt::whereIn('id', $ids)->get() as $c) {
                $b->forCourt($c);
            }
        } catch (\Throwable $e) {
            report($e); // обновление оверлея не должно ломать создание матчей
        }
    }
}
