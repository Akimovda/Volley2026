<?php

namespace App\Services;

use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use Illuminate\Support\Facades\DB;

/**
 * Завершение стадии/турнира после записи счёта матча. Вынесено из TournamentController
 * (checkStageCompletion/afterStageCompleted) без изменения логики — чтобы им могли пользоваться
 * и экран организатора, и ввод по токен-ссылке (MatchFinalizeService).
 */
class StageCompletionService
{
    public function __construct(private TournamentSetupService $setupService) {}

    public function check(TournamentStage $stage): void
    {
        // King of the Court (переписано 2026-09-07) больше не создаёт
        // TournamentMatch вообще — завершение стадии целиком управляется
        // TournamentKingOfCourtController::endRound() (финализация после
        // 3-го раунда), эта функция для него неприменима.
        if ($stage->type === TournamentStage::TYPE_KING_OF_COURT) {
            return;
        }

        $total = $stage->matches()
            ->whereNotIn('status', [TournamentMatch::STATUS_CANCELLED])
            ->count();

        $completed = $stage->matches()
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->count();

        // Swiss — тот же наивный критерий ложно совпадает с "формат закончен"
        // после каждого сыгранного тура, до клика "Следующий тур" (см.
        // report/kotc_deps_recon_2026-08-21.md, п.4). В отличие от KotC, у Swiss
        // нет config['current_round'] (нет одного "текущего" матча — весь тур
        // генерируется батчем) — текущий тур вычисляется как max(round) уже
        // СОЗДАННЫХ матчей стадии. Стадия считается завершённой только когда
        // сыгран последний тур (rounds_count) целиком.
        if ($total > 0 && $total === $completed && $stage->type === TournamentStage::TYPE_SWISS) {
            $roundsCount = (int) $stage->cfg('rounds_count', 0);
            $maxRound = (int) $stage->matches()->max('round');
            if ($roundsCount > 0 && $maxRound < $roundsCount) {
                return;
            }
        }

        if ($total > 0 && $total === $completed) {
            $stage->update(['status' => TournamentStage::STATUS_COMPLETED]);
            $this->afterCompleted($stage);
        }
    }

    /**
     * Хвост checkStageCompletion(), выполняемый ПОСЛЕ выставления status=completed:
     * автозапуск companion-стадий, гейт divisions, финальная проверка завершённости
     * всего турнира (уведомление + промоушен). Вынесен отдельным методом, чтобы
     * его же мог переиспользовать finishStage() (ручное завершение инкрементальной
     * стадии организатором) без прохождения через наивные guard'ы rounds_count.
     */
    public function afterCompleted(TournamentStage $stage): void
    {
        // Проверяем завершение
        $event = $stage->event;

        // Для сезонных турниров: НЕ отправляем "турнир завершён"
        // Каждый тур — отдельный цикл, завершение управляется промоушеном
        if ($event->season_id) {
            // Проверяем только: нужно ли формировать группы Hard/Lite?
            $occId = $stage->occurrence_id;
            $occStages = $event->tournamentStages()->where('occurrence_id', $occId);
            $allOccCompleted = $occStages->count() > 0
                && $occStages->where('status', TournamentStage::STATUS_COMPLETED)->count() === $occStages->count();

            // Ничего не делаем — организатор сам нажимает "Применить промоушен"
            return;
        }

        // === АВТОЗАПУСК bracket-плей-офф (только несезонные туры) ===
        // Сезонные туры сюда не доходят (early return выше) — у них запуск ручной.
        // Для несезонных после завершения группового этапа автоматически
        // запускаем pending-скелет с finals_mode='bracket'. Placement и divisions
        // остаются ручными. Защита от гонки: два последних матча группы могут
        // завершиться почти одновременно (параллельные корты, два запроса) — оба
        // дойдут сюда, а advanceToPlayoff() неатомарен (delete→create), что дало
        // бы дубли сетки. Атомарный claim pending→in_progress одним UPDATE
        // пропускает ровно один параллельный запрос (affected=1), остальные видят
        // affected=0 и просто ничего не делают. launchStage обёрнут в транзакцию
        // (advanceToPlayoff сам неатомарен) — при сбое откатывается, claim
        // возвращается в pending, чтобы организатор мог запустить вручную.
        $autoNext = $event->tournamentStages()
            ->where('occurrence_id', $stage->occurrence_id)
            ->where('sort_order', '>', $stage->sort_order)
            ->where('status', TournamentStage::STATUS_PENDING)
            ->orderBy('sort_order')
            ->first();

        if ($autoNext && $autoNext->cfg('finals_mode') === 'bracket') {
            $claimed = TournamentStage::where('id', $autoNext->id)
                ->where('status', TournamentStage::STATUS_PENDING)
                ->update(['status' => TournamentStage::STATUS_IN_PROGRESS]);

            if ($claimed === 1) {
                $autoPrev = $event->tournamentStages()
                    ->where('occurrence_id', $autoNext->occurrence_id)
                    ->where('sort_order', '<', $autoNext->sort_order)
                    ->orderByDesc('sort_order')
                    ->first();

                if ($autoPrev && $autoPrev->isCompleted()) {
                    try {
                        DB::transaction(function () use ($autoNext, $autoPrev) {
                            $this->setupService->launchStage($autoNext->fresh(), $autoPrev, []);
                        });
                        \Log::warning("Автозапуск bracket-стадии {$autoNext->id} после завершения группы {$stage->id}");
                    } catch (\Throwable $e) {
                        TournamentStage::where('id', $autoNext->id)
                            ->update(['status' => TournamentStage::STATUS_PENDING]);
                        \Log::warning("Ошибка автозапуска bracket-стадии {$autoNext->id}, статус возвращён в pending: " . $e->getMessage());
                    }
                } else {
                    // предшественник ещё не завершён (не должно происходить, т.к. мы
                    // сюда попадаем именно из завершения группы) — откатить claim
                    TournamentStage::where('id', $autoNext->id)
                        ->update(['status' => TournamentStage::STATUS_PENDING]);
                }
            }
        }

        // 'divisions' (финальные группы по уровням) — эта группа завершена,
        // но турнир НЕ закончен: для divisions companion-стадия сознательно
        // не создаётся при createStage() (см. комментарий там), поэтому без
        // этой проверки завершённая группа была бы ЕДИНСТВЕННОЙ batch-стадией
        // и турнир закрылся бы сразу — тот же баг, что чинили для event 402.
        // Ждём, пока организатор явно нажмёт "Сформировать группы" на пульте
        // (formDivisions() создаёт стадии "Группа Hard/Lite/...") — до этого
        // момента считаем турнир незавершённым.
        if (
            $stage->canHaveFollowupStage()
            && $stage->cfg('finals_mode') === 'divisions'
            && $stage->groups->count() >= 2
            && !$event->tournamentStages()->where('name', 'like', 'Группа %')->exists()
        ) {
            return;
        }

        // Инкрементальные форматы (swiss/king_beach) генерируют матчи по ходу
        // турнира — "все СОЗДАННЫЕ на сейчас матчи сыграны" (наивный критерий
        // строкой выше, применённый к $stage) ложно совпадает с "формат закончен":
        // swiss закрывается после первого раунда, до клика "Следующий тур" (см.
        // report_stage_type_branching_audit.md §3/§4.4). king_of_court
        // (переписано 2026-09-07) сюда вообще не попадает — TournamentMatch для
        // него больше не создаются, checkStageCompletion() возвращается для
        // него рано, до этой функции. Поэтому при решении "весь ТУРНИР завершён"
        // инкрементальные стадии не учитываются вовсе, если в событии есть хотя
        // бы одна batch/bracket стадия (round_robin/groups_playoff/single_elim/
        // double_elim/thai) — завершённость турнира определяется ТОЛЬКО ими.
        $stages = $event->tournamentStages()->get();
        $batchStages = $stages->reject(fn($s) => $s->hasIncrementalMatchGeneration());

        if ($batchStages->isEmpty()) {
            // Событие целиком состоит из инкрементальных стадий. Область этой
            // проверки — ТОЛЬКО swiss: для него isCompleted()===true достигается
            // исключительно либо честным исчерпанием rounds_count (guard'ы выше
            // в checkStageCompletion()), либо явным ручным finishStage() — оба
            // случая означают реальное завершение. king_beach тоже incremental,
            // но у него НЕТ такого guard'а в checkStageCompletion() (completed
            // выставляется безусловно по total===completed) — включать его сюда
            // было бы преждевременным закрытием турнира. Если среди
            // инкрементальных стадий события есть king_beach — сохраняем прежнее
            // консервативное поведение (return), это отдельный backlog, не в
            // рамках этого прохода (report/finish_stage_recon_2026-08-21.md).
            $onlySwiss = $stages->every(fn($s) => $s->type === TournamentStage::TYPE_SWISS);

            if (!$onlySwiss) {
                return;
            }

            $countableStages = $stages;
        } else {
            $countableStages = $batchStages;
        }

        $allStages = $countableStages->count();
        $completedStages = $countableStages->filter(fn($s) => $s->isCompleted())->count();

        if ($allStages > 0 && $allStages === $completedStages) {
            try {
                // Атомарный claim: "турнир завершён" — событие, которое должно
                // произойти РОВНО ОДИН РАЗ за жизнь турнира. Без guard'а этот
                // блок срабатывает при КАЖДОМ переходе allStages===completedStages
                // в true — если в уже завершённый турнир позже дозаполняется и
                // завершается ещё один матч (например, дозаполнение плей-офф
                // матчем за 3-4 место), участники получали бы повторное
                // "Турнир завершён!" уведомление. Тот же паттерн, что и
                // events.city_notified_at.
                $claimed = DB::table('events')
                    ->where('id', $event->id)
                    ->whereNull('tournament_completed_notified_at')
                    ->update(['tournament_completed_notified_at' => now()]);

                if ($claimed) {
                    app(\App\Services\TournamentNotificationService::class)
                        ->notifyTournamentCompleted($event);
                }

                // Пересчёт статистики/Elo/OpenSkill НЕ дублируем здесь: единственные
                // реальные вызывающие пути этого метода — score()/rescoreMatch() — уже
                // ставят RecalculateTournamentStatsJob (обёртка над recalculateTournament(),
                // сама ставит ratings job внутри себя) в очередь непосредственно перед
                // checkStageCompletion() в том же запросе — ShouldBeUnique по event_id
                // и так схлопнул бы повторный dispatch, но лишний вызов здесь не нужен.
                // (scoreKingBeach() тоже зовёт этот метод, но не через team_home_id/
                // winner_team_id — для king_beach recalculateTournament() безрезультатен
                // и раньше не вызывался вовсе, см. отдельный backlog.)

                // Авто-продвижение в сезоне (promote/relegate/reserve)
                app(\App\Services\TournamentPromotionService::class)
                    ->processEvent($event);
            } catch (\Throwable $e) {
                \Log::warning('Tournament completion notification failed: ' . $e->getMessage());
            }
        }
    }
}
