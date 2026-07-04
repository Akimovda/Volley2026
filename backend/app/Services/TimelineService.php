<?php

namespace App\Services;

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
     * Занятые интервалы корта в дату.
     *
     * Пока: события локации в этот день (court_bookings появятся в Фазе 3 — метод
     * будет расширен). События сейчас не привязаны к конкретному корту, поэтому
     * в Фазе 2 событие показывается на ВСЕХ кортах СВОЕГО направления (совпадение
     * определяется по direction_id корта ↔ events.direction).
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

        return $this->directionSlotsCache[$cacheKey];
    }

    /**
     * Найти все незавершённые occurrences на этой локации+направлении в указанную
     * календарную дату (в таймзоне локации) и сформировать слоты для таймлайна.
     */
    private function fetchDirectionSlots(Location $location, string $direction, Carbon $date): array
    {
        $tz = $location->timezone ?: 'Europe/Moscow';
        $dayStart = Carbon::parse($date->toDateString() . ' 00:00:00', $tz);
        $dayEnd = $dayStart->copy()->endOfDay();
        $dayStartUtc = $dayStart->copy()->setTimezone('UTC');
        $dayEndUtc = $dayEnd->copy()->setTimezone('UTC');

        $occurrences = EventOccurrence::query()
            ->join('events', 'events.id', '=', 'event_occurrences.event_id')
            ->whereRaw('COALESCE(event_occurrences.location_id, events.location_id) = ?', [$location->id])
            ->where('events.direction', $direction)
            ->whereBetween('event_occurrences.starts_at', [$dayStartUtc, $dayEndUtc])
            ->where(function ($q) {
                $q->whereNull('event_occurrences.is_cancelled')
                    ->orWhere('event_occurrences.is_cancelled', false);
            })
            ->whereNull('event_occurrences.cancelled_at')
            ->with(['event.organizer'])
            ->select('event_occurrences.*')
            ->orderBy('event_occurrences.starts_at')
            ->get();

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
