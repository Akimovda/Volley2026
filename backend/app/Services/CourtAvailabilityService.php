<?php

namespace App\Services;

use App\Models\CourtBooking;
use App\Models\Location;
use App\Models\LocationCourt;
use App\Models\LocationDirection;
use App\Models\LocationWorkingHour;
use Illuminate\Support\Carbon;

/**
 * Занятость кортов. Сетка 30 минут.
 *
 * Конвенция хранения времени: как и events/event_occurrences, court_bookings.starts_at/
 * ends_at хранятся в UTC — при сравнении с локальным рабочим временем локации нужно
 * приводить к таймзоне локации (аналогично TimelineService).
 */
class CourtAvailabilityService
{
    public function __construct(
        private TimelineService $timelineService,
    ) {}

    /**
     * Свободные слоты корта в дату.
     *
     * @return array<int, array{time:string, available:bool}>
     */
    public function slotsForDate(LocationCourt $court, Carbon $date): array
    {
        $direction = $court->relationLoaded('direction') ? $court->direction : $court->direction()->first();
        if (!$direction) {
            return [];
        }
        $location = $direction->relationLoaded('location') ? $direction->location : $direction->location()->first();
        if (!$location) {
            return [];
        }

        $wh = LocationWorkingHour::where('direction_id', $direction->id)
            ->where('day_of_week', $date->dayOfWeekIso - 1)
            ->first();
        if (!$wh || $wh->is_day_off) {
            return [];
        }

        $opens = $this->timeToMin($wh->opens_at);
        $closes = $this->timeToMin($wh->closes_at);
        $busy = $this->busyIntervalsForCourt($court, $direction, $location, $date);

        $slots = [];
        for ($m = $opens; $m < $closes; $m += 30) {
            $slotEnd = $m + 30;
            $isBusy = false;
            foreach ($busy as [$bs, $be]) {
                if ($m < $be && $bs < $slotEnd) {
                    $isBusy = true;
                    break;
                }
            }
            $slots[] = ['time' => $this->minToTime($m), 'available' => !$isBusy];
        }

        return $slots;
    }

    /**
     * Найти свободные окна >= durationMinutes для направления в дату.
     * Для формы создания события: "куда влезет событие длительностью X".
     *
     * @return array<int, array<int, array{start:string, end:string}>> court_id => [['start'=>...,'end'=>...], ...]
     */
    public function windowsForDuration(LocationDirection $direction, Carbon $date, int $durationMinutes): array
    {
        $location = $direction->relationLoaded('location') ? $direction->location : $direction->location()->first();
        if (!$location) {
            return [];
        }

        $wh = LocationWorkingHour::where('direction_id', $direction->id)
            ->where('day_of_week', $date->dayOfWeekIso - 1)
            ->first();
        if (!$wh || $wh->is_day_off) {
            return [];
        }

        $opens = $this->timeToMin($wh->opens_at);
        $closes = $this->timeToMin($wh->closes_at);

        $courts = $direction->relationLoaded('courts')
            ? $direction->courts
            : $direction->courts()->where('is_active', true)->orderBy('sort_order')->get();

        $result = [];
        foreach ($courts as $court) {
            $busy = $this->busyIntervalsForCourt($court, $direction, $location, $date);
            $result[$court->id] = $this->freeWindows($opens, $closes, $busy, $durationMinutes);
        }

        return $result;
    }

    /**
     * Объединяет занятые интервалы и возвращает свободные окна между ними
     * (в рамках [opens, closes]), оставляя только окна длиной >= durationMinutes.
     *
     * @param array<int, array{0:int,1:int}> $busy
     * @return array<int, array{start:string, end:string}>
     */
    private function freeWindows(int $opens, int $closes, array $busy, int $durationMinutes): array
    {
        usort($busy, fn($a, $b) => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($busy as [$s, $e]) {
            $s = max($s, $opens);
            $e = min($e, $closes);
            if ($e <= $s) {
                continue;
            }
            if ($merged && $s <= $merged[count($merged) - 1][1]) {
                $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $e);
            } else {
                $merged[] = [$s, $e];
            }
        }

        $windows = [];
        $cursor = $opens;
        foreach ($merged as [$s, $e]) {
            if ($s > $cursor) {
                $windows[] = [$cursor, $s];
            }
            $cursor = max($cursor, $e);
        }
        if ($cursor < $closes) {
            $windows[] = [$cursor, $closes];
        }

        $result = [];
        foreach ($windows as [$s, $e]) {
            if ($e - $s >= $durationMinutes) {
                $result[] = ['start' => $this->minToTime($s), 'end' => $this->minToTime($e)];
            }
        }

        return $result;
    }

    /**
     * Занятые минутные интервалы (от полуночи по локальному времени локации) для
     * конкретного корта в дату: активные брони этого корта + события направления
     * (Фаза 2 — событие занимает все корты направления, т.к. пока не привязано
     * к конкретному корту).
     *
     * @return array<int, array{0:int,1:int}>
     */
    private function busyIntervalsForCourt(LocationCourt $court, LocationDirection $direction, Location $location, Carbon $date): array
    {
        $intervals = $this->timelineService->eventMinuteIntervals($location, $direction->direction, $date);

        $tz = $location->timezone ?: 'Europe/Moscow';
        $dayStart = Carbon::parse($date->toDateString() . ' 00:00:00', $tz);
        $dayEnd = $dayStart->copy()->endOfDay();

        $bookings = CourtBooking::active()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $dayEnd->copy()->setTimezone('UTC'))
            ->where('ends_at', '>', $dayStart->copy()->setTimezone('UTC'))
            ->get();

        foreach ($bookings as $booking) {
            $s = Carbon::parse($booking->getRawOriginal('starts_at'), 'UTC')->setTimezone($tz);
            $e = Carbon::parse($booking->getRawOriginal('ends_at'), 'UTC')->setTimezone($tz);

            $startMin = $s->isSameDay($dayStart) ? ($s->hour * 60 + $s->minute) : 0;
            $endMin = $e->isSameDay($dayStart) ? ($e->hour * 60 + $e->minute) : 24 * 60;

            $intervals[] = [$startMin, $endMin];
        }

        return $intervals;
    }

    private function timeToMin(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));
        return $h * 60 + $m;
    }

    private function minToTime(int $min): string
    {
        $h = intdiv($min, 60);
        $m = $min % 60;
        return sprintf('%02d:%02d', $h, $m);
    }
}
