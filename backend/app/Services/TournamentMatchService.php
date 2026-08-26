<?php

namespace App\Services;

use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Models\TournamentTiebreakerSet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TournamentMatchService
{
    public function __construct(
        protected TournamentStandingsService $standingsService,
        protected TournamentScheduleService $scheduleService,
        protected TournamentKingService $kingService,
    ) {}


    /**
     * Записать счёт матча (API контроллера).
     *
     * @param  array  $sets  [[25,23], [21,25], [15,11]]
     */
    public function recordScore(
        TournamentMatch $match,
        array $sets,
        ?User $scorer = null,
    ): TournamentMatch {
        $scoreHome = array_map(fn($s) => (int) $s[0], $sets);
        $scoreAway = array_map(fn($s) => (int) $s[1], $sets);

        return $this->submitScore($match, $scoreHome, $scoreAway, $scorer);
    }

    /**
     * Ввести счёт матча (внутренний).
     *
     * @param  array  $scoreHome  [25, 21, 15] — очки по сетам
     * @param  array  $scoreAway  [23, 25, 11]
     */
    public function submitScore(
        TournamentMatch $match,
        array $scoreHome,
        array $scoreAway,
        ?User $scorer = null,
    ): TournamentMatch {
        if (!$match->hasTeams()) {
            throw new InvalidArgumentException('Матч не имеет обеих команд.');
        }

        if ($match->isCompleted()) {
            throw new InvalidArgumentException('Матч уже завершён.');
        }

        $stage  = $match->stage;
        $format = $stage->matchFormat();

        $tbOverrides = $this->resolveTiebreakerOverrides($match);
        $this->validateScore($scoreHome, $scoreAway, $format, $stage, $tbOverrides);

        $setsHome = 0;
        $setsAway = 0;
        foreach ($scoreHome as $i => $pts) {
            $awayPts = $scoreAway[$i] ?? 0;
            if ($pts > $awayPts) {
                $setsHome++;
            } else {
                $setsAway++;
            }
        }

        $winnerId = $setsHome > $setsAway
            ? $match->team_home_id
            : $match->team_away_id;

        DB::transaction(function () use ($match, $scoreHome, $scoreAway, $setsHome, $setsAway, $winnerId, $scorer) {
            $match->update([
                'score_home'        => $scoreHome,
                'score_away'        => $scoreAway,
                'sets_home'         => $setsHome,
                'sets_away'         => $setsAway,
                'total_points_home' => array_sum($scoreHome),
                'total_points_away' => array_sum($scoreAway),
                'winner_team_id'    => $winnerId,
                'status'            => TournamentMatch::STATUS_COMPLETED,
                'scored_by_user_id' => $scorer?->id,
                'scored_at'         => now(),
            ]);

            if ($match->is_tiebreaker && $match->group_id) {
                $this->maybeResolveTiebreakerSet($match->fresh(), $scorer);
            }

            if ($match->group_id) {
                $this->standingsService->recalculateGroup(
                    $match->stage,
                    $match->group,
                );
            }

            $this->advanceWinner($match, $winnerId);
            $this->advanceLoser($match);
            $this->maybeScheduleNextRound($match);

            $this->handleGrandFinalReset($match);

            // King of the Court: сдвиг короля/очереди. Порядок важен — ПОСЛЕ
            // recalculateGroup() выше (та уже посчитала rating_points по
            // свежему winner_team_id), afterMatch() только двигает состояние
            // очереди, таблицу больше не трогает (см. коммит "afterMatch —
            // только стейт-машина").
            if ($match->stage->type === TournamentStage::TYPE_KING_OF_COURT) {
                $this->kingService->afterMatch($match->stage, $match);
            }
        });

        return $match->fresh();
    }

    /**
     * Bracket reset (double elimination): после Grand Final (GF1) решаем судьбу
     * pre-created GF2 ('Grand Final Reset', см. generateDoubleElimination()).
     * Инвариант генератора: team_home_id GF1 — представитель верхней сетки,
     * team_away_id — представитель нижней. Победил home → у представителя
     * нижней сетки уже второе поражение, турнир окончен, reset не нужен —
     * GF2 отменяется. Победил away → у обоих участников по одному поражению,
     * нужен решающий матч — GF2 заполняется теми же двумя командами (спецкодом,
     * не через next_match_id — у GF2 нет входящих связей).
     */
    protected function handleGrandFinalReset(TournamentMatch $match): void
    {
        if ($match->court !== 'Grand Final') {
            return;
        }

        $grandFinalReset = TournamentMatch::where('stage_id', $match->stage_id)
            ->where('court', 'Grand Final Reset')
            ->first();

        if (!$grandFinalReset) {
            return; // defensive: старые DE-стадии без pre-created GF2
        }

        if ($match->winner_team_id === $match->team_home_id) {
            // Победил представитель верхней сетки — турнир окончен без reset
            $grandFinalReset->update([
                'status' => TournamentMatch::STATUS_CANCELLED,
            ]);
        } else {
            // Победил представитель нижней сетки — нужен решающий матч
            $grandFinalReset->update([
                'team_home_id' => $match->team_home_id,
                'team_away_id' => $match->team_away_id,
            ]);
        }
    }

    /**
     * Bracket reset (double elimination): запрет рескоринга GF1, если GF2 уже
     * разрешён. GF2 заполняется/отменяется спецкодом (handleGrandFinalReset()),
     * не через next_match_id — обычный resetScore(GF1) откатывает только сам
     * GF1 и никак не трогает GF2, поэтому автоматический откат невозможен без
     * риска рассинхрона (GF2 остался бы заполнен/сыгран под уже несуществующий
     * результат GF1). Организатор должен сначала вручную откатить GF2.
     *
     * @throws InvalidArgumentException  если GF2 не в исходном состоянии
     *         (заполнен, cancelled или completed)
     */
    public function guardGrandFinalRescore(TournamentMatch $match): void
    {
        if ($match->court !== 'Grand Final') {
            return;
        }

        $grandFinalReset = TournamentMatch::where('stage_id', $match->stage_id)
            ->where('court', 'Grand Final Reset')
            ->first();

        if (!$grandFinalReset) {
            return; // не-DE или старые стадии без pre-created GF2
        }

        $isPristine = $grandFinalReset->status === TournamentMatch::STATUS_SCHEDULED
            && $grandFinalReset->team_home_id === null
            && $grandFinalReset->team_away_id === null;

        if (!$isPristine) {
            throw new InvalidArgumentException(__('tournaments.gf1_rescore_blocked'));
        }
    }

    /**
     * King of the Court: полный запрет рескора завершённого матча (вариант А,
     * без частичного отката). TournamentKingService::afterMatch() необратим —
     * очередь FIFO, откат счёта одного матча не восстанавливает состояние
     * "король + очередь" на момент ДО этого матча без отката всех сыгранных
     * после него. См. report/kotc_deps_recon_2026-08-21.md, п.3.
     */
    public function guardKotcRescore(TournamentMatch $match): void
    {
        if ($match->stage->type !== TournamentStage::TYPE_KING_OF_COURT) {
            return;
        }

        if ($match->isCompleted()) {
            throw new InvalidArgumentException(__('tournaments.kotc_rescore_blocked'));
        }
    }

    /**
     * Отменить счёт (откат результата матча).
     */
    public function resetScore(TournamentMatch $match): TournamentMatch
    {
        DB::transaction(function () use ($match) {
            $winnerId = $match->winner_team_id;

            if ($match->next_match_id && $winnerId) {
                $slot = $match->next_match_slot;
                TournamentMatch::where('id', $match->next_match_id)->update([
                    "team_{$slot}_id" => null,
                ]);
            }

            if ($match->loser_next_match_id) {
                $slot = $match->loser_next_match_slot;
                TournamentMatch::where('id', $match->loser_next_match_id)->update([
                    "team_{$slot}_id" => null,
                ]);
            }

            $match->update([
                'score_home'        => null,
                'score_away'        => null,
                'sets_home'         => 0,
                'sets_away'         => 0,
                'total_points_home' => 0,
                'total_points_away' => 0,
                'winner_team_id'    => null,
                'status'            => TournamentMatch::STATUS_SCHEDULED,
                'scored_by_user_id' => null,
                'scored_at'         => null,
            ]);

            if ($match->group_id) {
                $this->standingsService->recalculateGroup(
                    $match->stage,
                    $match->group,
                );
            }
        });

        return $match->fresh();
    }

    /**
     * Записать forfeit (техническое поражение).
     */
    public function forfeit(TournamentMatch $match, int $loserTeamId, ?User $scorer = null): TournamentMatch
    {
        if (!$match->hasTeams()) {
            throw new InvalidArgumentException('Матч не имеет обеих команд.');
        }

        $winnerId = ($loserTeamId === $match->team_home_id)
            ? $match->team_away_id
            : $match->team_home_id;

        DB::transaction(function () use ($match, $winnerId, $scorer) {
            $match->update([
                'winner_team_id'    => $winnerId,
                'status'            => TournamentMatch::STATUS_FORFEIT,
                'scored_by_user_id' => $scorer?->id,
                'scored_at'         => now(),
            ]);

            if ($match->group_id) {
                $this->standingsService->recalculateGroup(
                    $match->stage,
                    $match->group,
                );
            }

            $this->advanceWinner($match, $winnerId);
            $this->advanceLoser($match);
            $this->maybeScheduleNextRound($match);
        });

        return $match->fresh();
    }

    protected function advanceWinner(TournamentMatch $match, int $winnerId): void
    {
        if (!$match->next_match_id) {
            return;
        }

        $this->fillSlotAndCascadeBye($match->next_match_id, $match->next_match_slot, $winnerId);
    }

    protected function advanceLoser(TournamentMatch $match): void
    {
        if (!$match->loser_next_match_id || !$match->winner_team_id) {
            return;
        }

        $loserId = $match->loserId();
        if (!$loserId) {
            return;
        }

        $this->fillSlotAndCascadeBye($match->loser_next_match_id, $match->loser_next_match_slot, $loserId);
    }

    /**
     * Заполняет слот целевого матча и, если ВТОРОЙ его слот помечен как
     * постоянно пустой (double_elim BYE, см.
     * TournamentBracketService::generateDoubleElimination() —
     * meta['bye_home']/meta['bye_away']), сразу отдаёт пришедшей команде
     * техпобеду и каскадно продвигает её дальше через next_match_id. Без
     * этого такой матч навсегда зависает в scheduled с одним TBD-слотом
     * (report/double-elim-bye-stuck.md) — второй команды там просто не
     * существует, submitScore() её никогда не дождётся.
     */
    private function fillSlotAndCascadeBye(int $matchId, string $slot, int $teamId): void
    {
        TournamentMatch::where('id', $matchId)->update([
            "team_{$slot}_id" => $teamId,
        ]);

        $match = TournamentMatch::find($matchId);
        if (!$match || $match->status !== TournamentMatch::STATUS_SCHEDULED) {
            return;
        }

        $otherSlot = $slot === 'home' ? 'away' : 'home';
        if (!is_null($match->{"team_{$otherSlot}_id"})) {
            return; // обе команды реальны — обычный, не bye-случай
        }
        if (empty($match->meta['bye_' . $otherSlot] ?? null)) {
            return; // второй слот пока просто пуст, ждём реальную команду
        }

        $match->update([
            'winner_team_id' => $teamId,
            'status'         => TournamentMatch::STATUS_COMPLETED,
            'score_home'     => [],
            'score_away'     => [],
            'scored_at'      => now(),
        ]);

        if ($match->next_match_id && $match->next_match_slot) {
            $this->fillSlotAndCascadeBye($match->next_match_id, $match->next_match_slot, $teamId);
        }

        // У техпобеды нет реального проигравшего — если у этого матча был
        // loser_next_match_id, помечаем ЕГО целевой слот тоже постоянно
        // пустым, чтобы каскад продолжился при нескольких BYE подряд.
        if ($match->loser_next_match_id && $match->loser_next_match_slot) {
            $target = TournamentMatch::find($match->loser_next_match_id);
            if ($target) {
                $meta = $target->meta ?? [];
                $meta['bye_' . $match->loser_next_match_slot] = true;
                $target->update(['meta' => $meta]);
            }
        }
    }

    /**
     * Кусок 2, шаг 2а (2026-08-15): расписание bracket-сетки (single_elim) сегодня
     * генерируется ОДИН раз при запуске стадии (только раунд 1, см.
     * TournamentSetupService::maybeGenerateInitialSchedule()) — раунды 2+
     * остаются без scheduled_at/court, пока предыдущий раунд не доиграется и
     * advanceWinner()/advanceLoser() выше не заполнят team_home_id/team_away_id
     * следующего раунда. Этот метод — тот самый повторный триггер: как только
     * раунд ПОЛНОСТЬЮ укомплектован (обе команды у ВСЕХ его матчей, включая
     * матч за 3-е место — он лежит в том же round, что и финал, и получает
     * команду через advanceLoser() полуфинала) — генерируем расписание именно
     * для него, продолжая от последнего уже расписанного слота стадии.
     *
     * No-op для НЕbracket-стадий и там, где расписание при запуске не
     * запрашивалось (schedule_match_duration_min в config отсутствует —
     * organizer не указал schedule_start ни в новом launchStage(), ни в
     * старом advance()/advanceCrossover() — те его сегодня вообще не
     * генерируют, см. CLAUDE.md) — старый companion-путь остаётся полностью
     * незатронутым (условие ниже никогда не станет true для его стадий).
     */
    protected function maybeScheduleNextRound(TournamentMatch $match): void
    {
        $stage = $match->stage;
        if ($stage->type !== TournamentStage::TYPE_SINGLE_ELIM) {
            return;
        }

        $matchDuration = $stage->cfg('schedule_match_duration_min');
        if ($matchDuration === null) {
            return;
        }
        $breakDuration = (int) $stage->cfg('schedule_break_duration_min', 5);

        $targetRound = $match->round + 1;
        $roundMatches = TournamentMatch::where('stage_id', $stage->id)
            ->where('round', $targetRound)
            ->whereNotIn('status', [TournamentMatch::STATUS_CANCELLED])
            ->get();

        if ($roundMatches->isEmpty()) {
            return; // финал был последним раундом — дальше некуда продвигать
        }

        $allFilled = $roundMatches->every(fn ($m) => $m->team_home_id && $m->team_away_id);
        if (!$allFilled) {
            return; // раунд ещё не укомплектован — ждём остальные матчи предыдущего
        }

        $alreadyScheduled = $roundMatches->contains(fn ($m) => $m->scheduled_at !== null);
        if ($alreadyScheduled) {
            return; // защита от повторного триггера (идемпотентность)
        }

        $lastScheduled = TournamentMatch::where('stage_id', $stage->id)
            ->whereNotNull('scheduled_at')
            ->max('scheduled_at');
        if (!$lastScheduled) {
            return; // раунд 1 никогда не был расписан — не от чего продолжать
        }

        $startTime = \Carbon\Carbon::parse($lastScheduled)->addMinutes((int) $matchDuration + $breakDuration);
        $courts = $stage->cfg('courts', []);

        $this->scheduleService->generateSchedule($stage, $startTime, (int) $matchDuration, $breakDuration, $courts, true);
    }

    /**
     * Валидация введённого счёта.
     */
    protected function validateScore(
        array $scoreHome,
        array $scoreAway,
        string $format,
        TournamentStage $stage,
        ?array $tbOverrides = null,
    ): void {
        $setCount = count($scoreHome);

        if ($setCount !== count($scoreAway)) {
            throw new InvalidArgumentException('Количество сетов home и away должно совпадать.');
        }

        // Тайбрейк-матч: один сет с кастомными правилами
        if ($tbOverrides !== null) {
            if ($setCount !== 1) {
                throw new InvalidArgumentException('Тайбрейк-матч играется в один сет.');
            }
            $this->validateTiebreakerSet(
                (int) $scoreHome[0],
                (int) $scoreAway[0],
                (int) $tbOverrides['points_to_win'],
                (bool) ($tbOverrides['two_point_margin'] ?? false),
            );
            return;
        }

        $maxSets = match ($format) {
            'bo1' => 1,
            'bo3' => 3,
            'bo5' => 5,
            default => 3,
        };

        $setsToWin = match ($format) {
            'bo1' => 1,
            'bo3' => 2,
            'bo5' => 3,
            default => 2,
        };

        if ($setCount < 1 || $setCount > $maxSets) {
            throw new InvalidArgumentException("Для формата {$format} допустимо от 1 до {$maxSets} сетов.");
        }

        $setPoints      = $stage->setPoints();
        $decidingSetPts = $stage->decidingSetPoints();

        $homeWins = 0;
        $awayWins = 0;

        foreach ($scoreHome as $i => $h) {
            $a = $scoreAway[$i];
            // Решающий сет только для Bo3/Bo5 (не Bo1)
            $isDecidingSet = $maxSets > 1 && (($i + 1 === $maxSets) || ($homeWins === $setsToWin - 1 && $awayWins === $setsToWin - 1));
            $target = $isDecidingSet ? $decidingSetPts : $setPoints;

            $this->validateSet($h, $a, $target, $i + 1);

            if ($h > $a) {
                $homeWins++;
            } else {
                $awayWins++;
            }
        }

        if ($homeWins !== $setsToWin && $awayWins !== $setsToWin) {
            throw new InvalidArgumentException("Матч не завершён: ни одна команда не набрала {$setsToWin} сета(-ов).");
        }

        if ($homeWins > $setsToWin || $awayWins > $setsToWin) {
            throw new InvalidArgumentException('Слишком много сетов — матч уже должен был завершиться.');
        }
    }

    protected function validateSet(int $home, int $away, int $targetPoints, int $setNumber): void
    {
        if ($home < 0 || $away < 0) {
            throw new InvalidArgumentException("Сет {$setNumber}: очки не могут быть отрицательными.");
        }

        $winner = max($home, $away);
        $loser  = min($home, $away);

        if ($winner < $targetPoints) {
            throw new InvalidArgumentException("Сет {$setNumber}: победитель должен набрать минимум {$targetPoints} очков (сейчас {$winner}).");
        }

        if ($winner - $loser < 2) {
            throw new InvalidArgumentException("Сет {$setNumber}: разница должна быть минимум 2 очка ({$home}:{$away}).");
        }

        if ($loser >= $targetPoints - 1 && $winner - $loser !== 2) {
            throw new InvalidArgumentException("Сет {$setNumber}: при тай-брейке разница должна быть ровно 2 ({$home}:{$away}).");
        }

        if ($loser < $targetPoints - 1 && $winner !== $targetPoints) {
            throw new InvalidArgumentException("Сет {$setNumber}: победитель должен набрать ровно {$targetPoints} (не {$winner}), если проигравший набрал {$loser}.");
        }
    }

    /**
     * Валидация одного сета тайбрейк-матча с кастомными правилами.
     */
    protected function validateTiebreakerSet(int $home, int $away, int $target, bool $twoPointMargin): void
    {
        if ($home < 0 || $away < 0) {
            throw new InvalidArgumentException('Очки не могут быть отрицательными.');
        }
        if ($home === $away) {
            throw new InvalidArgumentException('Тайбрейк-матч не может закончиться вничью.');
        }

        $winner = max($home, $away);
        $loser  = min($home, $away);

        if ($winner < $target) {
            throw new InvalidArgumentException("Победитель должен набрать минимум {$target} очков (сейчас {$winner}).");
        }

        if ($twoPointMargin) {
            if ($winner - $loser < 2) {
                throw new InvalidArgumentException("Разница должна быть минимум 2 очка ({$home}:{$away}).");
            }
            if ($loser >= $target - 1 && $winner - $loser !== 2) {
                throw new InvalidArgumentException("При тай-брейке разница должна быть ровно 2 ({$home}:{$away}).");
            }
            if ($loser < $target - 1 && $winner !== $target) {
                throw new InvalidArgumentException("Победитель должен набрать ровно {$target} (не {$winner}), если проигравший набрал {$loser}.");
            }
        } else {
            if ($winner !== $target) {
                throw new InvalidArgumentException("Победитель должен набрать ровно {$target} очков (сейчас {$winner}).");
            }
            if ($loser >= $target) {
                throw new InvalidArgumentException("Проигравший не может набрать {$loser} очков при цели {$target}.");
            }
        }
    }

    /**
     * Найти match_settings из pending tiebreaker_set, к которому относится матч.
     */
    protected function resolveTiebreakerOverrides(TournamentMatch $match): ?array
    {
        if (!$match->is_tiebreaker || !$match->group_id) {
            return null;
        }

        $set = TournamentTiebreakerSet::where('stage_id', $match->stage_id)
            ->where('group_id', $match->group_id)
            ->where('status', 'pending')
            ->where('method', 'match')
            ->whereJsonContains('team_ids', (int) $match->team_home_id)
            ->whereJsonContains('team_ids', (int) $match->team_away_id)
            ->first();

        return $set?->match_settings;
    }

    /**
     * Если все RR-матчи tiebreaker_set завершены — вычислить порядок и резолвить set.
     */
    protected function maybeResolveTiebreakerSet(TournamentMatch $match, ?User $scorer): void
    {
        $set = TournamentTiebreakerSet::where('stage_id', $match->stage_id)
            ->where('group_id', $match->group_id)
            ->where('status', 'pending')
            ->where('method', 'match')
            ->whereJsonContains('team_ids', (int) $match->team_home_id)
            ->whereJsonContains('team_ids', (int) $match->team_away_id)
            ->first();

        if (!$set) return;

        $teamIds = array_map('intval', $set->team_ids ?? []);
        $expectedMatches = count($teamIds) * (count($teamIds) - 1) / 2;

        $rrMatches = TournamentMatch::where('stage_id', $set->stage_id)
            ->where('group_id', $set->group_id)
            ->where('is_tiebreaker', true)
            ->whereIn('team_home_id', $teamIds)
            ->whereIn('team_away_id', $teamIds)
            ->where('created_at', '>=', $set->created_at)
            ->get();

        $completed = $rrMatches->where('status', TournamentMatch::STATUS_COMPLETED);
        if ($completed->count() < $expectedMatches) {
            return;
        }

        // Ранжируем по: wins desc → diff desc → points_scored desc
        $stats = [];
        foreach ($teamIds as $tid) {
            $stats[$tid] = ['wins' => 0, 'ps' => 0, 'pc' => 0];
        }
        foreach ($completed as $m) {
            if (!isset($stats[$m->team_home_id]) || !isset($stats[$m->team_away_id])) continue;
            $stats[$m->team_home_id]['ps'] += $m->total_points_home;
            $stats[$m->team_home_id]['pc'] += $m->total_points_away;
            $stats[$m->team_away_id]['ps'] += $m->total_points_away;
            $stats[$m->team_away_id]['pc'] += $m->total_points_home;
            if ($m->winner_team_id) {
                $stats[$m->winner_team_id]['wins']++;
            }
        }

        $sorted = $teamIds;
        usort($sorted, function ($a, $b) use ($stats) {
            if ($stats[$a]['wins'] !== $stats[$b]['wins']) return $stats[$b]['wins'] <=> $stats[$a]['wins'];
            $da = $stats[$a]['ps'] - $stats[$a]['pc'];
            $db = $stats[$b]['ps'] - $stats[$b]['pc'];
            if ($da !== $db) return $db <=> $da;
            return $stats[$b]['ps'] <=> $stats[$a]['ps'];
        });

        $set->update([
            'resolved_order'      => $sorted,
            'status'              => 'resolved',
            'resolved_by_user_id' => $scorer?->id,
            'resolved_at'         => now(),
        ]);
    }
}
