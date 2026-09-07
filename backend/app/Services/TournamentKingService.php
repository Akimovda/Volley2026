<?php

namespace App\Services;

use App\Models\KingOfCourtEvent;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use Illuminate\Support\Facades\DB;

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

        $config = $stage->config ?? [];
        $stage->update([
            'config' => array_merge($config, [
                'court_team_ids'      => array_values($teamIds),
                'round_duration_min'  => (int) ($config['round_duration_min'] ?? 15),
                'final_target_points' => (int) ($config['final_target_points'] ?? 15),
                'total_rounds'        => self::TOTAL_ROUNDS,
                'current_round'       => 0,
                'round_status'        => 'pending',
                'round_started_at'    => null,
                'eliminated_team_ids' => [],
                'rounds_history'      => [],
                'team_colors'         => $config['team_colors'] ?? [],
            ]),
        ]);
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
