<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\KingOfCourtEvent;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * King of the Court — официальные правила (probeach.ru), переписано 2026-09-07.
 * Корт делится на "королевскую" (King) и "претендентскую" (Challenge) половины,
 * 3-5 команд на корте, 3 раунда, между раундами выбывает худшая команда (кроме
 * финального раунда). Матчи (TournamentMatch) НЕ используются — весь стейт живёт
 * в TournamentStage.config (раундовые метаданные) + king_of_court_events
 * (снимки состояния после каждого розыгрыша, тот же паттерн undo, что у
 * MatchRallyService/match_rally_events: удалить последнюю строку — вернулись к
 * предыдущему снимку).
 */
class TournamentKingService
{
    public const MIN_TEAMS = 3;
    public const MAX_TEAMS = 5;
    public const TOTAL_ROUNDS = 3;

    public const EVENT_ROUND_START = 'round_start';
    public const EVENT_KING_POINT  = 'king_point';
    public const EVENT_FAULT       = 'fault';
    public const EVENT_TAKEOVER    = 'takeover';

    /**
     * Визуально различимые цвета для авто-назначения командам корта (до 5 команд
     * на корте, но разных кортов/финалов может быть много — палитра шире, чтобы
     * реже повторяться между ними) — организатор может изменить в форме "Цвета команд".
     * Все оттенки достаточно тёмные/насыщенные для белого текста поверх (см. badge style="color:#fff").
     */
    public const COLOR_PALETTE = [
        '#2967BA', '#E7612F', '#2E7D32', '#8E24AA', '#C2185B', '#F9A825', '#00838F', '#5D4037',
        '#1565C0', '#D84315', '#43A047', '#6A1B9A', '#AD1457', '#F57F17', '#00695C', '#4E342E',
        '#3949AB', '#EF6C00', '#7CB342', '#EC407A', '#FBC02D', '#6D4C41',
        '#5E35B1', '#FF7043', '#558B2F', '#D32F2F', '#0277BD', '#C0CA33',
    ];

    public function __construct(
        private TournamentSetupService $setupService,
    ) {
    }

    /**
     * Назначить команды на корт (3-5 шт) — создаёт TournamentStanding на
     * каждую (лидерборд без мест, только по очкам) и раундовые дефолты в
     * config. Раунд НЕ стартует сам — отдельный вызов startRound().
     */
    public function initialize(TournamentStage $stage, array $teamIds): void
    {
        if (count($teamIds) < self::MIN_TEAMS || count($teamIds) > self::MAX_TEAMS) {
            throw new \InvalidArgumentException(
                "King of the Court: нужно от " . self::MIN_TEAMS . " до " . self::MAX_TEAMS . " команд на корте."
            );
        }

        foreach ($teamIds as $teamId) {
            TournamentStanding::firstOrCreate([
                'stage_id' => $stage->id,
                'group_id' => null,
                'team_id'  => $teamId,
            ]);
        }

        // Финальный раунд ВСЕГДА играется ровно тремя командами (кульминация
        // формата — race to N очков) — это НЕ "всегда 3-й раунд по счёту", а
        // "раунд, на старте которого осталось 3 команды". При старте с 5
        // команд: 5→4→3(финал) — 3 раунда. С 4: 4→3(финал) — 2 раунда. С 3:
        // сразу 3(финал) — 1 раунд, без единого выбывания. Формула —
        // teamsCount-2, что для 3/4/5 команд даёт 1/2/3 ровно как нужно.
        $totalRounds = max(1, count($teamIds) - 2);

        $config = $stage->config ?? [];
        $stage->update([
            'config' => array_merge($config, [
                'court_team_ids'      => array_values($teamIds),
                'round_duration_min'  => (int) ($config['round_duration_min'] ?? 15),
                'final_target_points' => (int) ($config['final_target_points'] ?? 15),
                'total_rounds'        => $totalRounds,
                'current_round'       => 0,
                'round_status'        => 'pending',
                'round_started_at'    => null,
                'eliminated_team_ids' => [],
                'rounds_history'      => [],
                'team_colors'         => $this->assignRandomColors($teamIds, $config['team_colors'] ?? []),
            ]),
        ]);
    }

    /**
     * Каждой команде без уже назначенного цвета — случайный цвет из палитры,
     * без повторов среди команд этого же корта (пока хватает палитры).
     * Существующие назначения (организатор уже сохранил через форму "Цвета
     * команд") не трогаем.
     */
    private function assignRandomColors(array $teamIds, array $existingColors): array
    {
        $colors = $existingColors;
        $missingIds = array_values(array_filter($teamIds, fn($id) => empty($colors[$id])));
        if (empty($missingIds)) {
            return $colors;
        }

        $available = array_values(array_diff(self::COLOR_PALETTE, $colors));
        if (count($available) < count($missingIds)) {
            $available = self::COLOR_PALETTE;
        }
        shuffle($available);

        foreach ($missingIds as $i => $teamId) {
            $colors[$teamId] = $available[$i % count($available)];
        }

        return $colors;
    }

    /**
     * Валидный диапазон количества групп/кортов для N команд — раз финал сам
     * является king_of_court кортом (3-5 команд), число групп G обязано быть
     * в диапазоне 3-5, а размер каждой группы (при равномерном делении с
     * остатком) — тоже 3-5. G_min = max(ceil(N/5), 3), G_max = min(floor(N/3), 5).
     * Если G_min > G_max — авто-разбиение для этого N невозможно (напр. N=7 —
     * мало для ≥3 групп по ≥3 команды; N>25 — уже не помещается ни в одну
     * комбинацию). Возвращает null в этом случае.
     */
    public static function validGroupsRange(int $teamsCount): ?array
    {
        $min = max((int) ceil($teamsCount / self::MAX_TEAMS), self::MIN_TEAMS);
        $max = min((int) intdiv($teamsCount, self::MIN_TEAMS), self::MAX_TEAMS);

        return $min <= $max ? [$min, $max] : null;
    }

    /**
     * Разбить пул команд на несколько king_of_court кортов ("групповой
     * этап") — по одной операции сразу создаёт $groupsCount стадий, каждую
     * инициализирует и стартует раунд 1. Все стадии батча помечаются общим
     * config['koc_batch_id'] — по нему formFinal() потом соберёт победителей.
     *
     * $manualBuckets (ручной режим) — [label => [team_id,...]], уже
     * сгруппировано контроллером из формы assign[team_id]=label (тот же
     * паттерн, что TournamentController::kingBeachAssignManual()). Каждый
     * непустой бакет обязан быть размером 3-5 — all-or-nothing, при ошибке
     * ничего не создаётся.
     *
     * Без $manualBuckets (случайный/seeded режим) — $groupsCount обязан
     * попадать в validGroupsRange(count($teamIds)); команды делятся на
     * $groupsCount кусков близкого размера (base/остаток).
     */
    public function formCourts(
        Event $event,
        ?int $occurrenceId,
        array $teamIds,
        int $groupsCount,
        string $drawMode = 'random',
        ?array $manualBuckets = null,
        int $roundDurationMin = 15,
        int $finalTargetPoints = 15,
    ): \Illuminate\Support\Collection {
        if ($manualBuckets !== null) {
            $badLabels = [];
            foreach ($manualBuckets as $label => $ids) {
                $n = count($ids);
                if ($n < self::MIN_TEAMS || $n > self::MAX_TEAMS) {
                    $badLabels[] = "{$label} ({$n})";
                }
            }
            if (!empty($badLabels)) {
                throw new \InvalidArgumentException(
                    'В каждой группе должно быть от ' . self::MIN_TEAMS . ' до ' . self::MAX_TEAMS
                    . ' команд, нарушено: ' . implode(', ', $badLabels) . '.'
                );
            }
            $buckets = array_values($manualBuckets);
        } else {
            // Один корт (groupsCount===1) — не "групповой этап" в смысле
            // формата (нет последующего финала, batch_id не ставится, см.
            // ниже), просто прямое создание единственной стадии из 3-5
            // выбранных команд. formCourts()/formFinal() — ЕДИНАЯ точка входа
            // для King of the Court независимо от числа команд, отдельной
            // формы/кнопки "Создать стадию" для этого типа в UI больше нет
            // (см. setup.blade.php) — поэтому валидация здесь та же самая
            // (3-5), что раньше делал controller у старого assign().
            if ($groupsCount === 1) {
                $n = count($teamIds);
                if ($n < self::MIN_TEAMS || $n > self::MAX_TEAMS) {
                    throw new \InvalidArgumentException(
                        'Для одного корта нужно от ' . self::MIN_TEAMS . ' до ' . self::MAX_TEAMS . ' команд, выбрано ' . $n . '.'
                    );
                }
            } else {
                $range = self::validGroupsRange(count($teamIds));
                if (!$range || $groupsCount < $range[0] || $groupsCount > $range[1]) {
                    throw new \InvalidArgumentException(
                        'Для ' . count($teamIds) . ' команд число групп должно быть '
                        . ($range ? "от {$range[0]} до {$range[1]}" : 'недоступно — авто-разбиение не подходит для этого количества команд')
                        . '.'
                    );
                }
            }

            if ($drawMode === 'seeded') {
                // sortByRating() исторически принимает TournamentStage (читает
                // draw_seed_by из его config + event через связь) — на этом
                // этапе ни одна стадия батча ещё не создана. Собираем
                // временный несохранённый TournamentStage только чтобы
                // передать нужные данные (event для direction, дефолтный
                // config → 'elo' по умолчанию в самом sortByRating()).
                $transientStage = new TournamentStage(['event_id' => $event->id]);
                $transientStage->setRelation('event', $event);

                $order = $this->setupService
                    ->sortByRating(EventTeam::whereIn('id', $teamIds)->get(), $transientStage)
                    ->pluck('id')->toArray();
            } else {
                $order = collect($teamIds)->shuffle()->values()->toArray();
            }

            $n = count($order);
            $base = intdiv($n, $groupsCount);
            $remainder = $n % $groupsCount;
            $buckets = [];
            $cursor = 0;
            for ($i = 0; $i < $groupsCount; $i++) {
                $size = $base + ($i < $remainder ? 1 : 0);
                $buckets[] = array_slice($order, $cursor, $size);
                $cursor += $size;
            }
        }

        // batch_id — только когда кортов реально несколько (нужен формат
        // "группы → финал"). Один корт (ручной label на единственную группу
        // ИЛИ groupsCount===1) — самодостаточная стадия, не ждёт финала.
        $isSingleCourt = count($buckets) === 1;
        $batchId = $isSingleCourt ? null : Str::random(12);
        $sortOrder = ($event->tournamentStages()->max('sort_order') ?? 0) + 1;

        return DB::transaction(function () use ($event, $occurrenceId, $buckets, $batchId, $isSingleCourt, &$sortOrder, $roundDurationMin, $finalTargetPoints) {
            $stages = collect();
            foreach ($buckets as $i => $ids) {
                $config = [
                    'round_duration_min'  => $roundDurationMin,
                    'final_target_points' => $finalTargetPoints,
                ];
                if (!$isSingleCourt) {
                    $config['koc_batch_id'] = $batchId;
                }

                $stage = TournamentStage::create([
                    'event_id'      => $event->id,
                    'occurrence_id' => $occurrenceId,
                    'type'          => TournamentStage::TYPE_KING_OF_COURT,
                    'name'          => $isSingleCourt ? 'Корт' : ('Корт ' . ($i + 1)),
                    'sort_order'    => $sortOrder++,
                    'status'        => TournamentStage::STATUS_PENDING,
                    'config'        => $config,
                ]);

                $this->initialize($stage, $ids);
                $stage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
                $this->startRound($stage->fresh(), $ids);

                $stages->push($stage->fresh());
            }
            return $stages;
        });
    }

    /**
     * Сформировать финал по итогам отборочного этапа (батча кортов).
     * Принимает любую стадию батча (кнопка есть на карточке каждой) — читает
     * её koc_batch_id, собирает ВСЕ стадии события/тура с тем же батчем,
     * требует, чтобы все были завершены, берёт по 1 победителю (rank 1 по
     * points_scored) с каждой, создаёт новую king_of_court стадию "Финал" с
     * этими командами. Идемпотентно: если финал для этого батча уже создан
     * (config['koc_final_of_batch'] === $batchId у другой стадии) — бросает
     * исключение вместо дубля (защита от повторного клика/двойного сабмита).
     */
    public function formFinal(TournamentStage $anyQualifierStage): TournamentStage
    {
        $batchId = $anyQualifierStage->cfg('koc_batch_id');
        if (!$batchId) {
            throw new \InvalidArgumentException('Эта стадия не относится к групповому этапу King of the Court.');
        }

        $event = $anyQualifierStage->event;
        $allStages = $event->tournamentStages()
            ->where('type', TournamentStage::TYPE_KING_OF_COURT)
            ->when($anyQualifierStage->occurrence_id, fn($q) => $q->where('occurrence_id', $anyQualifierStage->occurrence_id))
            ->get();

        if ($allStages->contains(fn($s) => $s->cfg('koc_final_of_batch') === $batchId)) {
            throw new \InvalidArgumentException('Финал для этого группового этапа уже сформирован.');
        }

        $qualifiers = $allStages->filter(fn($s) => $s->cfg('koc_batch_id') === $batchId);

        if ($qualifiers->contains(fn($s) => !$s->isCompleted())) {
            throw new \InvalidArgumentException('Не все корты группового этапа завершены.');
        }

        $winnerIds = [];
        foreach ($qualifiers as $q) {
            $winner = TournamentStanding::where('stage_id', $q->id)
                ->where('group_id', null)
                ->orderByDesc('points_scored')
                ->first();
            if ($winner) {
                $winnerIds[] = $winner->team_id;
            }
        }

        $sortOrder = ($qualifiers->max('sort_order') ?? $event->tournamentStages()->max('sort_order') ?? 0) + 1;
        // Настройки раунда (длительность/цель по очкам) наследуем от кортов
        // батча — так же задавались одной формой на всех при formCourts().
        $roundDurationMin = (int) $anyQualifierStage->cfg('round_duration_min', 15);
        $finalTargetPoints = (int) $anyQualifierStage->cfg('final_target_points', 15);

        return DB::transaction(function () use ($event, $anyQualifierStage, $winnerIds, $batchId, $sortOrder, $roundDurationMin, $finalTargetPoints) {
            $finalStage = TournamentStage::create([
                'event_id'      => $event->id,
                'occurrence_id' => $anyQualifierStage->occurrence_id,
                'type'          => TournamentStage::TYPE_KING_OF_COURT,
                'name'          => 'Финал',
                'sort_order'    => $sortOrder,
                'status'        => TournamentStage::STATUS_PENDING,
                'config'        => [
                    'koc_final_of_batch'  => $batchId,
                    'round_duration_min'  => $roundDurationMin,
                    'final_target_points' => $finalTargetPoints,
                ],
            ]);

            $this->initialize($finalStage, $winnerIds);
            $finalStage->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);
            $this->startRound($finalStage->fresh(), collect($winnerIds)->shuffle()->values()->toArray());

            return $finalStage->fresh();
        });
    }

    /**
     * Начать следующий раунд (1, 2 или 3). Раунд 1 — порядок по draw_mode
     * (seeded/random) среди всех court_team_ids. Раунд 2/3 — порядок по
     * ranking предыдущего раунда (уже без выбывших): 1-е место — король,
     * 2-е — претендент, остальные — очередь в этом же порядке.
     */
    public function startRound(TournamentStage $stage, ?array $seededOrder = null): void
    {
        $config = $stage->config ?? [];
        $roundNumber = (int) ($config['current_round'] ?? 0) + 1;

        if ($roundNumber > self::TOTAL_ROUNDS) {
            throw new \InvalidArgumentException('Все 3 раунда уже сыграны.');
        }

        if ($roundNumber === 1) {
            $order = $seededOrder ?? $config['court_team_ids'];
        } else {
            $history = $config['rounds_history'] ?? [];
            $prevRound = $history[$roundNumber - 2] ?? null;
            if (!$prevRound) {
                throw new \InvalidArgumentException('Предыдущий раунд ещё не завершён.');
            }
            // ranking предыдущего раунда включает и только что выбывшую команду
            // (последнее место) — она НЕ играет дальше, исключаем явно.
            $order = array_values(array_filter(
                $prevRound['ranking'],
                fn($teamId) => $teamId != $prevRound['eliminated_team_id']
            ));
        }

        $order = array_values($order);
        if (count($order) < 2) {
            throw new \InvalidArgumentException('Недостаточно команд для старта раунда.');
        }

        $kingId = array_shift($order);
        $challengerId = array_shift($order);
        $queue = $order; // остаток — очередь, в том же порядке

        $roundPoints = [];
        foreach (array_merge([$kingId, $challengerId], $queue) as $tid) {
            $roundPoints[$tid] = 0;
        }

        DB::transaction(function () use ($stage, $roundNumber, $kingId, $challengerId, $queue, $roundPoints, $config) {
            KingOfCourtEvent::create([
                'stage_id'           => $stage->id,
                'round_number'       => $roundNumber,
                'event_type'         => self::EVENT_ROUND_START,
                'team_id'            => $kingId,
                'king_team_id'       => $kingId,
                'challenger_team_id' => $challengerId,
                'queue'              => $queue,
                'round_points'       => $roundPoints,
            ]);

            $stage->update([
                'config' => array_merge($config, [
                    'current_round'    => $roundNumber,
                    'round_status'     => 'in_progress',
                    'round_started_at' => now()->toIso8601String(),
                ]),
            ]);
        });
    }

    /**
     * Текущее состояние розыгрыша (последний снимок текущего раунда).
     */
    public function currentState(TournamentStage $stage): ?array
    {
        $roundNumber = (int) $stage->configValue('current_round', 0);
        if ($roundNumber < 1) {
            return null;
        }

        $last = KingOfCourtEvent::where('stage_id', $stage->id)
            ->where('round_number', $roundNumber)
            ->orderByDesc('id')
            ->first();

        if (!$last) {
            return null;
        }

        return [
            'round_number'       => $roundNumber,
            'king_team_id'       => $last->king_team_id,
            'challenger_team_id' => $last->challenger_team_id,
            'queue'              => $last->queue ?? [],
            'round_points'       => $last->round_points ?? [],
            'last_event_id'      => $last->id,
        ];
    }

    /**
     * Записать розыгрыш: king_point (король выиграл — +1 очко, король и так
     * остаётся, претендент меняется), fault (претендент ошибся на подаче —
     * тот же переход, что и king_point, но БЕЗ очка), takeover (претендент
     * выигрывает розыгрыш — становится королём, старый король уходит в
     * очередь, БЕЗ очка).
     */
    public function recordEvent(TournamentStage $stage, string $type, ?int $recordedByUserId = null): array
    {
        if (!in_array($type, [self::EVENT_KING_POINT, self::EVENT_FAULT, self::EVENT_TAKEOVER], true)) {
            throw new \InvalidArgumentException('Неизвестный тип розыгрыша.');
        }

        if ($stage->cfg('round_status') !== 'in_progress') {
            throw new \InvalidArgumentException('Раунд не идёт.');
        }

        $state = $this->currentState($stage);
        if (!$state) {
            throw new \InvalidArgumentException('Раунд ещё не начат.');
        }

        $roundNumber = $state['round_number'];
        $king = $state['king_team_id'];
        $challenger = $state['challenger_team_id'];
        $queue = $state['queue'];
        $points = $state['round_points'];

        if (empty($queue)) {
            throw new \InvalidArgumentException('Очередь пуста — больше нет команд для ротации.');
        }

        $newQueue = $queue;
        $nextFromQueue = array_shift($newQueue);

        if ($type === self::EVENT_TAKEOVER) {
            $newQueue[] = $king; // старый король — в конец очереди
            $newKing = $challenger;
            $newChallenger = $nextFromQueue;
            $heroTeamId = $challenger;
        } else {
            // king_point и fault — одинаковый переход очереди/сторон
            $newQueue[] = $challenger; // претендент — в конец очереди
            $newKing = $king;
            $newChallenger = $nextFromQueue;
            $heroTeamId = $type === self::EVENT_KING_POINT ? $king : $challenger;
        }

        if ($type === self::EVENT_KING_POINT) {
            $points[$king] = (int) ($points[$king] ?? 0) + 1;
        }

        $event = KingOfCourtEvent::create([
            'stage_id'            => $stage->id,
            'round_number'        => $roundNumber,
            'event_type'          => $type,
            'team_id'             => $heroTeamId,
            'king_team_id'        => $newKing,
            'challenger_team_id'  => $newChallenger,
            'queue'               => $newQueue,
            'round_points'        => $points,
            'created_by_user_id'  => $recordedByUserId,
        ]);

        // Финальный раунд (всегда ровно 3 команды на старте, см. initialize())
        // ограничен ОБОИМИ условиями сразу — очки И время, что раньше
        // наступит. Время — организатор завершает вручную (таймер только
        // визуальный подсказчик). Очки — проверяем здесь и завершаем раунд
        // сами в момент, когда король их набрал, не дожидаясь клика.
        $targetPoints = (int) $stage->cfg('final_target_points', 15);
        $isFinalRound = $roundNumber === (int) $stage->cfg('total_rounds', self::TOTAL_ROUNDS);

        if ($isFinalRound && $type === self::EVENT_KING_POINT && ($points[$king] ?? 0) >= $targetPoints) {
            $this->endRound($stage);
        }

        return [
            'round_number'       => $roundNumber,
            'king_team_id'       => $newKing,
            'challenger_team_id' => $newChallenger,
            'queue'              => $newQueue,
            'round_points'       => $points,
            'last_event_id'      => $event->id,
        ];
    }

    /**
     * Отменить последнее записанное действие (не считая round_start —
     * начало раунда отменить нельзя, для этого есть удаление стадии).
     */
    public function undoLast(TournamentStage $stage): ?array
    {
        $roundNumber = (int) $stage->cfg('current_round', 0);
        if ($roundNumber < 1) {
            return null;
        }

        $last = KingOfCourtEvent::where('stage_id', $stage->id)
            ->where('round_number', $roundNumber)
            ->orderByDesc('id')
            ->first();

        if (!$last || $last->event_type === self::EVENT_ROUND_START) {
            return null; // нечего отменять
        }

        $last->delete();

        return $this->currentState($stage);
    }

    /**
     * Завершить текущий раунд: считает ranking (с тайбрейком), выбывание
     * (раунды 1-2 — последняя команда ranking'а), пишет rounds_history.
     * Для финального раунда (round_number === total_rounds) — финализирует
     * всю стадию (суммирует очки в TournamentStanding, статус completed).
     */
    public function endRound(TournamentStage $stage): void
    {
        $state = $this->currentState($stage);
        if (!$state) {
            throw new \InvalidArgumentException('Раунд ещё не начат.');
        }

        $roundNumber = $state['round_number'];
        $totalRounds = (int) $stage->cfg('total_rounds', self::TOTAL_ROUNDS);
        $isFinalRound = $roundNumber === $totalRounds;

        $ranking = $this->rankTeamsForRound($stage, $roundNumber, $state['round_points']);

        $eliminatedTeamId = null;
        if (!$isFinalRound && count($ranking) > 0) {
            $eliminatedTeamId = end($ranking);
        }

        $config = $stage->config ?? [];
        $history = $config['rounds_history'] ?? [];
        $history[$roundNumber - 1] = [
            'round'              => $roundNumber,
            'points'             => $state['round_points'],
            'ranking'            => $ranking,
            'eliminated_team_id' => $eliminatedTeamId,
        ];

        $eliminated = $config['eliminated_team_ids'] ?? [];
        if ($eliminatedTeamId) {
            $eliminated[] = $eliminatedTeamId;
        }

        DB::transaction(function () use ($stage, $config, $history, $eliminated, $isFinalRound) {
            $stage->update([
                'config' => array_merge($config, [
                    'rounds_history'      => $history,
                    'eliminated_team_ids' => $eliminated,
                    'round_status'        => 'finished',
                ]),
            ]);

            if ($isFinalRound) {
                $this->finalizeStandings($stage->fresh());
            }
        });
    }

    /**
     * Итоговые очки — сумма round_points по всем сыгранным раундам (команда
     * сохраняет очки, заработанные до выбывания). Пишет TournamentStanding,
     * закрывает стадию.
     */
    private function finalizeStandings(TournamentStage $stage): void
    {
        $history = $stage->cfg('rounds_history', []);
        $totals = [];
        foreach ($history as $round) {
            foreach (($round['points'] ?? []) as $teamId => $pts) {
                $totals[$teamId] = ($totals[$teamId] ?? 0) + (int) $pts;
            }
        }

        arsort($totals);
        $rank = 1;
        foreach ($totals as $teamId => $pts) {
            TournamentStanding::where('stage_id', $stage->id)
                ->where('team_id', $teamId)
                ->update([
                    'points_scored' => $pts,
                    'rating_points' => $pts,
                    'rank'          => $rank,
                ]);
            $rank++;
        }

        $stage->update(['status' => TournamentStage::STATUS_COMPLETED]);
    }

    /**
     * Ranking команд раунда: 1) round_points desc; 2) при равенстве —
     * длиннейшая непрерывная серия очков ОДНОЙ командой за одно "царствование"
     * (обрывается только takeover, fault её не прерывает — король не менялся);
     * 3) при равенстве и здесь — кто раньше по времени достиг максимального
     * для этой пары счёта.
     */
    private function rankTeamsForRound(TournamentStage $stage, int $roundNumber, array $roundPoints): array
    {
        $events = KingOfCourtEvent::where('stage_id', $stage->id)
            ->where('round_number', $roundNumber)
            ->orderBy('id')
            ->get();

        $streaks = $this->computeMaxStreaks($events);
        $firstReachedAt = $this->computeFirstReachedTimestamps($events, $roundPoints);

        $teamIds = array_keys($roundPoints);
        usort($teamIds, function ($a, $b) use ($roundPoints, $streaks, $firstReachedAt) {
            $pa = $roundPoints[$a] ?? 0;
            $pb = $roundPoints[$b] ?? 0;
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            $sa = $streaks[$a] ?? 0;
            $sb = $streaks[$b] ?? 0;
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            $ta = $firstReachedAt[$a] ?? PHP_INT_MAX;
            $tb = $firstReachedAt[$b] ?? PHP_INT_MAX;
            return $ta <=> $tb;
        });

        return $teamIds;
    }

    /**
     * Для каждой команды — максимальная непрерывная серия king_point-очков,
     * набранных за одно царствование (сбрасывается только событием takeover,
     * т.к. fault не меняет короля и не прерывает серию текущего короля).
     */
    private function computeMaxStreaks($events): array
    {
        $max = [];
        $currentKing = null;
        $currentStreak = 0;

        foreach ($events as $event) {
            if ($event->event_type === self::EVENT_TAKEOVER) {
                $currentKing = $event->king_team_id;
                $currentStreak = 0;
            } elseif ($event->event_type === self::EVENT_KING_POINT) {
                if ($currentKing !== $event->king_team_id) {
                    $currentKing = $event->king_team_id;
                    $currentStreak = 0;
                }
                $currentStreak++;
                $max[$currentKing] = max($max[$currentKing] ?? 0, $currentStreak);
            }
            // fault — король тот же, серию не трогает и не прерывает
        }

        return $max;
    }

    /**
     * Момент (unix timestamp), когда команда впервые достигла своего
     * итогового счёта раунда — для тайбрейка "кто первым набрал".
     */
    private function computeFirstReachedTimestamps($events, array $finalPoints): array
    {
        $result = [];
        foreach ($events as $event) {
            if ($event->event_type !== self::EVENT_KING_POINT) {
                continue;
            }
            $teamId = $event->king_team_id;
            $scoreAfter = (int) ($event->round_points[$teamId] ?? 0);
            if (($finalPoints[$teamId] ?? null) == $scoreAfter && !isset($result[$teamId])) {
                $result[$teamId] = $event->created_at->timestamp;
            }
        }
        return $result;
    }
}
