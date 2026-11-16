<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Location;
use App\Models\LocationCourt;
use App\Models\RentalWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Данные виджета «Таймлайн бронирования»: занятость кортов на день и на неделю без персональных данных
 * (имена и телефоны игроков не отдаём — только факт занятости, для мероприятий допускается название).
 */
class RentalTimelineService
{
    public function __construct(private TimelineService $timeline, private CourtPricingService $pricing)
    {
    }

    /** Локации, доступные виджету: только свои у владельца, при выборе — только выбранные. */
    public function locationsFor(RentalWidget $widget, int $userId)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $widget->getSetting('location_ids', []))));

        return Location::query()
            ->where('owner_id', $userId)
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->with(['city:id,name,timezone'])
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->filter(fn (Location $l) => $l->directions()->where('is_active', true)->whereHas('courts', fn ($q) => $q->where('is_active', true))->exists())
            ->values();
    }

    /**
     * @param array{location_id?:int, direction?:string, date?:string, mode?:string} $params
     * @param bool $showTitles показывать названия мероприятий в занятых слотах
     */
    public function payload($locations, array $params, bool $showTitles = false): array
    {
        $location = $locations->firstWhere('id', (int) ($params['location_id'] ?? 0)) ?? $locations->first();
        if (!$location) {
            return ['empty' => true, 'locations' => []];
        }

        $tz = $location->effectiveTimezone();
        $now = Carbon::now($tz);

        // направления, в которых есть активные корты (для переключателя Классика / Пляжка)
        $dirs = $location->directions()->where('is_active', true)
            ->whereHas('courts', fn ($q) => $q->where('is_active', true))->orderBy('direction')->pluck('direction')->all();
        $direction = in_array($params['direction'] ?? '', $dirs, true) ? $params['direction'] : ($dirs[0] ?? 'classic');

        try {
            $date = isset($params['date']) ? Carbon::parse((string) $params['date'], $tz)->startOfDay() : $now->copy()->startOfDay();
        } catch (\Throwable) {
            $date = $now->copy()->startOfDay();
        }
        $mode = ($params['mode'] ?? 'day') === 'week' ? 'week' : 'day';

        $base = [
            'empty'      => false,
            'locations'  => $locations->map(fn (Location $l) => ['id' => $l->id, 'name' => $l->name])->all(),
            'location'   => ['id' => $location->id, 'name' => $location->name, 'address' => (string) $location->address],
            'directions' => $dirs,
            'direction'  => $direction,
            'mode'       => $mode,
            'date'       => $date->toDateString(),
            'today'      => $now->toDateString(),
            'now_min'    => $now->hour * 60 + $now->minute,
        ];

        $key = 'rtl_v2_' . $location->id . '_' . $direction . '_' . $date->toDateString() . '_' . $mode . '_' . (int) $showTitles;
        $body = Cache::remember($key, 45, fn () => $mode === 'week'
            ? $this->week($location, $direction, $date, $now)
            : $this->day($location, $direction, $date, $showTitles));

        return $base + $body;
    }

    private function day(Location $location, string $direction, Carbon $date, bool $showTitles): array
    {
        $row = collect($this->timeline->day($location, $date))->firstWhere('direction', $direction);
        if (!$row || $row['is_closed']) {
            return ['closed' => true, 'courts' => []];
        }

        $opens = $this->toMin($row['opens_at']);
        $closes = $this->toMin($row['closes_at']);
        $models = LocationCourt::with('direction.location.city')->whereIn('id', array_column($row['courts'], 'id'))->get()->keyBy('id');

        $courts = [];
        foreach ($row['courts'] as $c) {
            $slots = [];
            foreach ($c['slots'] as $s) {
                $slots[] = array_filter([
                    'from'  => $s['starts_at'],
                    'to'    => $s['ends_at'],
                    'title' => ($showTitles && ($s['type'] ?? '') === 'event') ? ($s['title'] ?? null) : null,
                ], fn ($v) => $v !== null);
            }
            $model = $models->get($c['id']);
            $rates = $model ? $this->pricing->ratesForDay($model, $date, $opens, $closes) : [];
            $courts[] = ['id' => $c['id'], 'name' => $c['name'], 'indoor' => (bool) $c['is_indoor'], 'slots' => $slots, 'rates' => $rates];
        }

        return ['closed' => false, 'opens' => $row['opens_at'], 'closes' => $row['closes_at'], 'courts' => $courts];
    }

    /** Неделя: для каждого дня — занятость выбранного направления в % и число занятых интервалов. */
    private function week(Location $location, string $direction, Carbon $date, Carbon $now): array
    {
        $start = $date->copy()->startOfWeek();
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i);
            $row = collect($this->timeline->day($location, $d))->firstWhere('direction', $direction);

            if (!$row || $row['is_closed']) {
                $days[] = ['date' => $d->toDateString(), 'closed' => true, 'pct' => 0, 'busy' => 0, 'courts' => 0];
                continue;
            }

            $open = $this->toMin($row['opens_at']);
            $close = $this->toMin($row['closes_at']);
            $avail = 0;
            $busyMin = 0;
            $busyCount = 0;
            foreach ($row['courts'] as $c) {
                $avail += max(0, $close - $open);
                foreach ($this->merge($c['slots'], $open, $close) as [$a, $b]) {
                    $busyMin += $b - $a;
                    $busyCount++;
                }
            }
            $days[] = [
                'date'   => $d->toDateString(),
                'closed' => false,
                'pct'    => $avail > 0 ? (int) round($busyMin / $avail * 100) : 0,
                'busy'   => $busyCount,
                'courts' => count($row['courts']),
            ];
        }

        return ['week_start' => $start->toDateString(), 'days' => $days];
    }

    /** Объединённые занятые интервалы корта в рамках рабочих часов. */
    private function merge(array $slots, int $open, int $close): array
    {
        $iv = [];
        foreach ($slots as $s) {
            $a = max($open, $this->toMin($s['starts_at']));
            $b = min($close, $this->toMin($s['ends_at']));
            if ($b > $a) {
                $iv[] = [$a, $b];
            }
        }
        usort($iv, fn ($x, $y) => $x[0] <=> $y[0]);
        $out = [];
        foreach ($iv as [$a, $b]) {
            if ($out && $a <= $out[count($out) - 1][1]) {
                $out[count($out) - 1][1] = max($out[count($out) - 1][1], $b);
            } else {
                $out[] = [$a, $b];
            }
        }

        return $out;
    }

    private function toMin(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }
}
