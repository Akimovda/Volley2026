<?php

namespace App\Services;

use App\Models\CourtBooking;
use App\Models\EventOccurrence;
use App\Models\Location;
use App\Models\LocationCourt;
use Illuminate\Support\Carbon;

class TimelineService
{
    /** Кэш занятых слотов по направлению+дате в рамках одного вызова day()/week() — курты одного направления показывают одинаковые слоты. */
    private array $directionSlotsCache = [];

    /**
     * Данные таймлайна для одного дня.
     * Возвращает структуру для рендера: направления → корты → занятые интервалы.
     */
    public function day(Location $location, Carbon $date): array
    {
        $directions = $location->directions()
            ->where('is_active', true)
            ->with(['courts' => fn($q) => $q->where('is_active', true)
                ->orderBy('sort_order')])
            ->with('workingHours')
            ->get();

        $result = [];
        foreach ($directions as $dir) {
            $wh = $dir->workingHours
                ->firstWhere('day_of_week', ($date->dayOfWeekIso - 1));

            // День закрыт
            if (!$wh || $wh->is_day_off) {
                $result[] = [
                    'direction'  => $dir->direction,
                    'is_closed'  => true,
                    'courts'     => [],
                ];
                continue;
            }

            $courts = [];
            foreach ($dir->courts as $court) {
                $courts[] = [
                    'id'    => $court->id,
                    'name'  => $court->name,
                    'slots' => $this->occupiedSlots($location, $court, $date),
                ];
            }

            $result[] = [
                'direction' => $dir->direction,
                'is_closed' => false,
                'opens_at'  => substr($wh->opens_at, 0, 5),
                'closes_at' => substr($wh->closes_at, 0, 5),
                'courts'    => $courts,
            ];
        }

        return $result;
    }

    /**
     * Занятые интервалы корта в дату: события направления + брони этого корта.
     *
     * Событие, привязанное к конкретному корту через court_booking_id, показывается
     * ТОЛЬКО на этом корте. Событие без привязки (легаси/не через бронирование) —
     * на ВСЕХ кортах направления, как в Фазе 2 (court_id слота === null).
     * Брони кортов без события (event_id пуст) добавляются отдельно, цвет — по статусу.
     */
    private function occupiedSlots(Location $location, LocationCourt $court, Carbon $date): array
    {
        $direction = $court->relationLoaded('direction') ? $court->direction : $court->direction()->first();
        $directionKey = $direction?->direction;

        if (!$directionKey) {
            return [];
        }

        $cacheKey = $directionKey . '|' . $date->toDateString();

        if (!isset($this->directionSlotsCache[$cacheKey])) {
            $this->directionSlotsCache[$cacheKey] = $this->fetchDirectionSlots($location, $directionKey, $date);
        }

        $eventSlots = array_values(array_filter(
            $this->directionSlotsCache[$cacheKey],
            fn(array $slot) => $slot['court_id'] === null || $slot['court_id'] === $court->id
        ));

        $bookingSlots = $this->fetchCourtBookingSlots($court, $location, $date);

        $all = array_merge($eventSlots, $bookingSlots);
        usort($all, fn($a, $b) => $a['starts_at'] <=> $b['starts_at']);

        return $all;
    }

    /**
     * Брони этого корта, НЕ привязанные к событию (event_id пуст) — брони с событием
     * уже показаны выше как event-слот, отфильтрованный по своему court_id.
     */
    private function fetchCourtBookingSlots(LocationCourt $court, Location $location, Carbon $date): array
    {
        $tz = $location->timezone ?: 'Europe/Moscow';
        $dayStart = Carbon::parse($date->toDateString() . ' 00:00:00', $tz);
        $dayEnd = $dayStart->copy()->endOfDay();

        $statusColors = [
            CourtBooking::STATUS_PENDING   => '#8E8E93',
            CourtBooking::STATUS_CONFIRMED => '#4A9EFF',
            CourtBooking::STATUS_PAID      => '#34C759',
        ];

        $bookings = CourtBooking::active()
            ->whereNull('event_id')
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $dayEnd->copy()->setTimezone('UTC'))
            ->where('ends_at', '>', $dayStart->copy()->setTimezone('UTC'))
            ->with('user')
            ->get();

        $slots = [];
        foreach ($bookings as $booking) {
            $s = Carbon::parse($booking->getRawOriginal('starts_at'), 'UTC')->setTimezone($tz);
            $e = Carbon::parse($booking->getRawOriginal('ends_at'), 'UTC')->setTimezone($tz);

            $userName = null;
            if ($booking->user) {
                $lastName = $booking->user->last_name ?? '';
                $firstInitial = $booking->user->first_name ? mb_substr($booking->user->first_name, 0, 1) . '.' : '';
                $userName = trim($lastName . ' ' . $firstInitial) ?: $booking->user->name;
            }

            $slots[] = [
                'type'          => 'booking',
                'court_id'      => $court->id,
                'booking_id'    => $booking->id,
                'title'         => $userName ?: __('club.booking_by'),
                'starts_at'     => $s->format('H:i'),
                'ends_at'       => $e->format('H:i'),
                'color'         => $statusColors[$booking->status] ?? '#8E8E93',
                'status'        => $booking->status,
                'organizer'     => $userName,
            ];
        }

        return $slots;
    }

    /**
     * Найти все незавершённые occurrences на этой локации+направлении в указанную
     * календарную дату (в таймзоне локации).
     */
    private function fetchOccurrences(Location $location, string $direction, Carbon $date)
    {
        $tz = $location->timezone ?: 'Europe/Moscow';
        $dayStart = Carbon::parse($date->toDateString() . ' 00:00:00', $tz);
        $dayEnd = $dayStart->copy()->endOfDay();
        $dayStartUtc = $dayStart->copy()->setTimezone('UTC');
        $dayEndUtc = $dayEnd->copy()->setTimezone('UTC');

        return EventOccurrence::query()
            ->join('events', 'events.id', '=', 'event_occurrences.event_id')
            ->whereRaw('COALESCE(event_occurrences.location_id, events.location_id) = ?', [$location->id])
            ->where('events.direction', $direction)
            ->whereBetween('event_occurrences.starts_at', [$dayStartUtc, $dayEndUtc])
            ->where(function ($q) {
                $q->whereNull('event_occurrences.is_cancelled')
                    ->orWhere('event_occurrences.is_cancelled', false);
            })
            ->whereNull('event_occurrences.cancelled_at')
            ->with(['event.organizer', 'event.courtBooking'])
            ->select('event_occurrences.*')
            ->orderBy('event_occurrences.starts_at')
            ->get();
    }

    /**
     * Занятые минутные интервалы (от начала суток по локальному времени локации)
     * событиями направления в дату — переиспользуется CourtAvailabilityService
     * для проверки пересечений (Фаза 2: событие занимает ВСЕ корты направления,
     * т.к. ещё не привязано к конкретному корту).
     *
     * @return array<int, array{0:int,1:int}> [[startMin,endMin], ...]
     */
    public function eventMinuteIntervals(Location $location, string $direction, Carbon $date): array
    {
        $intervals = [];
        foreach ($this->fetchOccurrences($location, $direction, $date) as $occ) {
            $startsLocal = $occ->starts_at_local;
            $endsLocal = $occ->ends_at_local;
            if (!$startsLocal || !$endsLocal) {
                continue;
            }
            $intervals[] = [
                $startsLocal->hour * 60 + $startsLocal->minute,
                $endsLocal->hour * 60 + $endsLocal->minute,
            ];
        }
        return $intervals;
    }

    /**
     * Найти все незавершённые occurrences на этой локации+направлении в указанную
     * календарную дату (в таймзоне локации) и сформировать слоты для таймлайна.
     */
    private function fetchDirectionSlots(Location $location, string $direction, Carbon $date): array
    {
        $occurrences = $this->fetchOccurrences($location, $direction, $date);

        $slots = [];
        foreach ($occurrences as $occ) {
            $startsLocal = $occ->starts_at_local;
            $endsLocal = $occ->ends_at_local;
            if (!$startsLocal || !$endsLocal) {
                continue;
            }

            $organizer = $occ->event?->organizer;
            $organizerLabel = null;
            if ($organizer) {
                $lastName = $organizer->last_name ?? '';
                $firstInitial = $organizer->first_name ? mb_substr($organizer->first_name, 0, 1) . '.' : '';
                $organizerLabel = trim($lastName . ' ' . $firstInitial) ?: $organizer->name;
            }

            $slots[] = [
                'type'          => 'event',
                'event_id'      => $occ->event_id,
                'occurrence_id' => $occ->id,
                'title'         => $occ->event?->title,
                'starts_at'     => $startsLocal->format('H:i'),
                'ends_at'       => $endsLocal->format('H:i'),
                'color'         => $occ->event?->timeline_color,
                'organizer'     => $organizerLabel,
                'court_id'      => $occ->event?->courtBooking?->court_id,
            ];
        }

        return $slots;
    }

    /**
     * Данные для недельного режима: 7 дней, загруженность каждого направления
     * по дням (для heat map).
     */
    public function week(Location $location, Carbon $weekStart): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dayData = $this->day($location, $date);

            $days[] = [
                'date'       => $date->toDateString(),
                'day_label'  => $date->isoFormat('dd, D MMM'),
                'directions' => array_map(fn($d) => [
                    'direction'    => $d['direction'],
                    'is_closed'    => $d['is_closed'],
                    'events_count' => $d['is_closed'] ? 0
                        : array_sum(array_map(
                            fn($c) => count($c['slots']), $d['courts'])),
                ], $dayData),
            ];
        }
        return $days;
    }
}
