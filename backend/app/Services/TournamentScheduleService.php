<?php

namespace App\Services;

use App\Models\TournamentStage;
use App\Models\TournamentMatch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TournamentScheduleService
{
    private ?\App\Services\Broadcast\CourtRegistry $courtRegistry = null;

    private function courtRegistry(): \App\Services\Broadcast\CourtRegistry
    {
        return $this->courtRegistry ??= new \App\Services\Broadcast\CourtRegistry();
    }

    private function assigner(): \App\Services\Broadcast\CourtAutoAssigner
    {
        return app(\App\Services\Broadcast\CourtAutoAssigner::class);
    }

    /**
     * Назначить расписание для матчей стадии.
     *
     * @param  TournamentStage $stage
     * @param  Carbon          $startTime   Время начала первого матча
     * @param  int             $matchDurationMin  Длительность матча в минутах
     * @param  int             $breakMin    Перерыв между матчами
     * @param  string[]        $courts      Доступные площадки
     */
    public function generateSchedule(
        TournamentStage $stage,
        Carbon $startTime,
        int $matchDurationMin = 60,
        int $breakMin = 10,
        array $courts = [],
        bool $onlyUnscheduled = false,
    ): int {
        if (empty($courts)) {
            $courts = $stage->cfg('courts', []);
        }

        // Названные организатором площадки становятся площадками мероприятия; сколько матчей идёт
        // параллельно в одном слоте — по числу активных площадок (нет ни одной — создаётся «Площадка 1»).
        $eventId = (int) $stage->event_id;
        $occId = $stage->occurrence_id ? (int) $stage->occurrence_id : null;
        foreach ($courts as $name) {
            if (is_string($name) && trim($name) !== '') {
                $this->courtRegistry()->ensure($eventId, $occId, trim($name));
            }
        }
        $courtsCount = max(1, $this->assigner()->pool($eventId, $occId)->count());
        $slotDuration = $matchDurationMin + $breakMin;

        // $onlyUnscheduled=true — инкрементальный режим (используется триггером
        // повторного расписания раундов 2+ bracket-сетки, см.
        // TournamentMatchService::maybeScheduleNextRound()): без него повторный
        // вызов молча ПЕРЕЗАПИСАЛ бы scheduled_at/court уже расписанных матчей
        // других раундов той же стадии (метод сам по себе не идемпотентен —
        // выборка ниже фильтрует только по team_home_id/team_away_id, не по
        // scheduled_at). Старые вызовы (createStage()/formDivisions(), где
        // расписание генерируется РОВНО ОДИН РАЗ сразу после жеребьёвки, когда
        // ничего ещё не расписано) передают $onlyUnscheduled=false по умолчанию —
        // поведение не меняется.
        $matches = $stage->matches()
            ->where('status', TournamentMatch::STATUS_SCHEDULED)
            ->whereNotNull('team_home_id')
            ->whereNotNull('team_away_id')
            ->when($onlyUnscheduled, fn ($q) => $q->whereNull('scheduled_at'))
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();

        if ($matches->isEmpty()) {
            $this->assigner()->assignStage($stage); // матчи без команд (сетка) тоже получают площадку
            return 0;
        }

        $currentTime = $startTime->copy();
        $courtIdx = 0;
        $scheduled = 0;

        // Группируем по раундам — все матчи одного раунда идут параллельно
        $byRound = $matches->groupBy('round');

        // Собираем корты по группам
        $groupCourts = [];
        foreach ($stage->groups as $group) {
            if (!empty($group->courts)) {
                $groupCourts[$group->id] = $group->courts;
            }
        }

        // Групповой этап без собственных списков кортов у групп: группа целиком на одной площадке,
        // поэтому время считаем по занятости площадки и команд, а не «слотами по числу кортов».
        if (empty($groupCourts) && $stage->isGroupStage() && $matches->whereNotNull('group_id')->isNotEmpty()) {
            return $this->scheduleByCourt($stage, $startTime, $slotDuration, $onlyUnscheduled);
        }

        foreach ($byRound as $round => $roundMatches) {
            $courtIdx = 0;
            $groupCourtIdx = []; // отдельный счётчик для каждой группы

            foreach ($roundMatches as $match) {
                // Если матч привязан к группе со своими кортами — слоты считаем по ним
                $matchCourtsCount = $courtsCount;
                $gid = $match->group_id;

                if ($gid && isset($groupCourts[$gid]) && count($groupCourts[$gid]) > 0) {
                    $matchCourtsCount = count($groupCourts[$gid]);
                    if (!isset($groupCourtIdx[$gid])) $groupCourtIdx[$gid] = 0;
                    $courtIdx = $groupCourtIdx[$gid];
                }

                // Проверяем пересечения
                $conflict = $this->checkConflict($match, $currentTime, $slotDuration);
                if ($conflict) {
                    // Сдвигаем на следующий слот
                    $currentTime = $currentTime->copy()->addMinutes($slotDuration);
                    $courtIdx = 0;
                }

                // Площадку (court_id/court) ставит CourtAutoAssigner после расстановки времени
                $match->update(['scheduled_at' => $currentTime]);

                $courtIdx++;
                if ($gid && isset($groupCourts[$gid])) {
                    $groupCourtIdx[$gid] = $courtIdx;
                }
                $scheduled++;

                // Если все корты заняты — переходим к следующему слоту
                $allFull = true;
                if (!empty($groupCourts)) {
                    // Проверяем все группы
                    foreach ($groupCourts as $gcId => $gcList) {
                        if (($groupCourtIdx[$gcId] ?? 0) < count($gcList)) {
                            $allFull = false;
                            break;
                        }
                    }
                } else {
                    $allFull = ($courtIdx >= $courtsCount);
                }

                if ($allFull) {
                    $currentTime = $currentTime->copy()->addMinutes($slotDuration);
                    $courtIdx = 0;
                    $groupCourtIdx = [];
                }
            }

            // Между раундами — перерыв
            if ($courtIdx > 0) {
                $currentTime = $currentTime->copy()->addMinutes($slotDuration);
            }
        }

        // Площадки по единым правилам (ручные и начатые матчи не трогаются)
        $this->assigner()->assignStage($stage);

        return $scheduled;
    }

    /**
     * Расписание «по площадкам»: сначала площадки (CourtAutoAssigner), затем время — матч встаёт, когда свободны
     * его площадка и обе команды. Раунды идут друг за другом (как в обычной раскладке слотами).
     */
    private function scheduleByCourt(TournamentStage $stage, Carbon $startTime, int $slotMinutes, bool $onlyUnscheduled): int
    {
        $this->assigner()->assignStage($stage);

        $matches = $stage->matches()
            ->where('status', TournamentMatch::STATUS_SCHEDULED)
            ->whereNotNull('team_home_id')->whereNotNull('team_away_id')
            ->when($onlyUnscheduled, fn ($q) => $q->whereNull('scheduled_at'))
            ->orderBy('round')->orderBy('match_number')->get();

        $courtNext = [];
        $teamNext = [];
        $barrier = $startTime->copy();
        $scheduled = 0;

        foreach ($matches->groupBy('round') as $roundMatches) {
            $roundEnd = $barrier->copy();
            foreach ($roundMatches as $m) {
                $cid = (int) $m->court_id;
                $t = $barrier->copy();
                foreach ([$courtNext[$cid] ?? null, $teamNext[$m->team_home_id] ?? null, $teamNext[$m->team_away_id] ?? null] as $busyUntil) {
                    if ($busyUntil && $busyUntil->gt($t)) {
                        $t = $busyUntil->copy();
                    }
                }
                $m->update(['scheduled_at' => $t]);

                $end = $t->copy()->addMinutes($slotMinutes);
                $courtNext[$cid] = $teamNext[$m->team_home_id] = $teamNext[$m->team_away_id] = $end;
                if ($end->gt($roundEnd)) {
                    $roundEnd = $end;
                }
                $scheduled++;
            }
            $barrier = $roundEnd;
        }

        return $scheduled;
    }

    /**
     * Проверить пересечения: участник не может играть 2 матча одновременно.
     *
     * @return bool  true если есть конфликт
     */
    private function checkConflict(TournamentMatch $match, Carbon $time, int $slotMinutes): bool
    {
        $teamIds = array_filter([$match->team_home_id, $match->team_away_id]);
        if (empty($teamIds)) return false;

        $slotEnd = $time->copy()->addMinutes($slotMinutes);

        return TournamentMatch::where('stage_id', $match->stage_id)
            ->where('id', '!=', $match->id)
            ->whereNotNull('scheduled_at')
            ->where(function ($q) use ($time, $slotEnd) {
                $q->whereBetween('scheduled_at', [$time, $slotEnd->subSecond()]);
            })
            ->where(function ($q) use ($teamIds) {
                $q->whereIn('team_home_id', $teamIds)
                  ->orWhereIn('team_away_id', $teamIds);
            })
            ->exists();
    }

    /**
     * Очистить расписание стадии.
     */
    public function clearSchedule(TournamentStage $stage): int
    {
        return $stage->matches()->update([
            'scheduled_at' => null,
            'court'        => null,
        ]);
    }

    /**
     * Вручную назначить время и площадку для матча.
     */
    public function setMatchSchedule(TournamentMatch $match, Carbon $time, ?string $court = null): TournamentMatch
    {
        // Проверяем конфликт
        $teamIds = array_filter([$match->team_home_id, $match->team_away_id]);

        $conflict = TournamentMatch::where('stage_id', $match->stage_id)
            ->where('id', '!=', $match->id)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', $time)
            ->where(function ($q) use ($teamIds) {
                $q->whereIn('team_home_id', $teamIds)
                  ->orWhereIn('team_away_id', $teamIds);
            })
            ->first();

        if ($conflict) {
            throw new InvalidArgumentException(
                "Конфликт: команда уже играет в это время (матч #{$conflict->match_number})."
            );
        }

        $match->update(['scheduled_at' => $time]);

        // Площадка, названная вручную — это 'manual'
        if ($court !== null && trim($court) !== '') {
            $stage = $match->stage ?? TournamentStage::find($match->stage_id);
            $courtModel = $this->courtRegistry()->ensure((int) $stage->event_id, $stage->occurrence_id ? (int) $stage->occurrence_id : null, trim($court));
            $this->assigner()->assignManual($match, $courtModel);
        }

        return $match->fresh();
    }
}
