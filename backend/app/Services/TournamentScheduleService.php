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
    ): int {
        if (empty($courts)) {
            $courts = $stage->cfg('courts', []);
        }

        // Гарантируем минимум 1 корт
        if (empty($courts)) {
            $courts = ['Корт 1'];
        }

        $courtsCount = count($courts);
        $slotDuration = $matchDurationMin + $breakMin;

        $matches = $stage->matches()
            ->where('status', TournamentMatch::STATUS_SCHEDULED)
            ->whereNotNull('team_home_id')
            ->whereNotNull('team_away_id')
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();

        if ($matches->isEmpty()) {
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

        foreach ($byRound as $round => $roundMatches) {
            $courtIdx = 0;
            $groupCourtIdx = []; // отдельный счётчик для каждой группы

            foreach ($roundMatches as $match) {
                // Если матч привязан к группе и у группы есть свои корты
                $matchCourts = $courts;
                $matchCourtsCount = $courtsCount;
                $gid = $match->group_id;

                if ($gid && isset($groupCourts[$gid]) && count($groupCourts[$gid]) > 0) {
                    $matchCourts = $groupCourts[$gid];
                    $matchCourtsCount = count($matchCourts);
                    if (!isset($groupCourtIdx[$gid])) $groupCourtIdx[$gid] = 0;
                    $courtIdx = $groupCourtIdx[$gid];
                }

                $court = $matchCourts[$courtIdx % $matchCourtsCount];

                // Проверяем пересечения
                $conflict = $this->checkConflict($match, $currentTime, $slotDuration);
                if ($conflict) {
                    // Сдвигаем на следующий слот
                    $currentTime = $currentTime->copy()->addMinutes($slotDuration);
                    $courtIdx = 0;
                    $court = $courts[0];
                }

                $match->update([
                    'scheduled_at' => $currentTime,
                    'court'        => $court,
                ]);

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

        $match->update([
            'scheduled_at' => $time,
            'court'        => $court,
        ]);

        return $match->fresh();
    }
}
