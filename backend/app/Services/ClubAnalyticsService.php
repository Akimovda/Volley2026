<?php

namespace App\Services;

use App\Models\CourtBooking;
use App\Models\Location;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClubAnalyticsService
{
    public const PERIOD_MONTH = 'month';
    public const PERIOD_QUARTER = 'quarter';
    public const PERIOD_HALF_YEAR = 'half_year';
    public const PERIOD_YEAR = 'year';

    public const PERIODS = [self::PERIOD_MONTH, self::PERIOD_QUARTER, self::PERIOD_HALF_YEAR, self::PERIOD_YEAR];

    public function __construct(private TimelineService $timelineService)
    {
    }

    /**
     * Границы периода [start, end) — обе локальные календарные полуночи (00:00) в
     * таймзоне локации. $anchor — любая дата внутри желаемого периода.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolvePeriod(string $period, Carbon $anchor): array
    {
        $anchor = $anchor->copy()->startOfDay();

        return match ($period) {
            self::PERIOD_QUARTER => $this->quarterBounds($anchor),
            self::PERIOD_HALF_YEAR => $this->halfYearBounds($anchor),
            self::PERIOD_YEAR => [$anchor->copy()->startOfYear(), $anchor->copy()->startOfYear()->addYear()],
            default => [$anchor->copy()->startOfMonth(), $anchor->copy()->startOfMonth()->addMonthNoOverflow()],
        };
    }

    private function quarterBounds(Carbon $anchor): array
    {
        $quarterStartMonth = intdiv($anchor->month - 1, 3) * 3 + 1;
        $start = $anchor->copy()->setDate($anchor->year, $quarterStartMonth, 1);

        return [$start, $start->copy()->addMonthsNoOverflow(3)];
    }

    private function halfYearBounds(Carbon $anchor): array
    {
        $halfStartMonth = $anchor->month <= 6 ? 1 : 7;
        $start = $anchor->copy()->setDate($anchor->year, $halfStartMonth, 1);

        return [$start, $start->copy()->addMonthsNoOverflow(6)];
    }

    /**
     * Новый anchor (первый день соседнего периода) для стрелок навигации ← →.
     * $direction: -1 назад, +1 вперёд.
     */
    public function shiftAnchor(string $period, Carbon $anchor, int $direction): Carbon
    {
        [$start] = $this->resolvePeriod($period, $anchor);
        $months = match ($period) {
            self::PERIOD_QUARTER => 3,
            self::PERIOD_HALF_YEAR => 6,
            self::PERIOD_YEAR => 12,
            default => 1,
        };

        return $direction < 0
            ? $start->copy()->subMonthsNoOverflow($months)
            : $start->copy()->addMonthsNoOverflow($months);
    }

    /**
     * Загрузка (%) и выручка (₽) локации за период — по каждому корту, по направлению
     * и по центру в целом.
     *
     * Знаменатель загрузки — рабочие часы корта за период (расписание направления,
     * выходные дни не считаются). Числитель — часы занятости: активные брони
     * (confirmed/paid) + события локации этого направления, посчитанные ТЕМИ ЖЕ
     * данными и с тем же клампингом по рабочим часам, что и в таймлайне
     * (TimelineService::day()) — избегаем повторной реализации привязки турнирных
     * матчей к кортам/резолва "событие без court_booking_id = вся сетка направления".
     *
     * Событие без court_booking_id и без резолва через tournament_matches.court
     * (см. TimelineService::tournamentCourtIds) продолжает занимать ВСЕ корты
     * направления в таймлайне — здесь это означает, что КАЖДЫЙ такой корт получает
     * полную длительность события в свой числитель. Загрузка тогда завышена
     * относительно физической реальности (турнир реально шёл на части кортов),
     * но это то же допущение, что уже принято в таймлайне (Фаза 2) — сумма загрузки
     * не претендует на бухгалтерскую точность для событий без явной привязки.
     */
    /** Те же данные, но с кэшем на 5 минут (расчёт обходит каждый день периода — для года это сотни вызовов). */
    public function forLocationCached(Location $location, Carbon $periodStart, Carbon $periodEnd, bool $refresh = false): array
    {
        $key = 'club_analytics_v2_' . $location->id . '_' . $periodStart->timestamp . '_' . $periodEnd->timestamp;
        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 300, fn () => $this->forLocation($location, $periodStart, $periodEnd));
    }

    public function forLocation(Location $location, Carbon $periodStart, Carbon $periodEnd): array
    {
        $location->loadMissing(['directions' => fn ($q) => $q->where('is_active', true)
            ->with(['courts' => fn ($q2) => $q2->where('is_active', true)->orderBy('sort_order')])]);

        $courtOccupiedHours = [];
        $courtAvailableHours = [];
        foreach ($location->directions as $dir) {
            foreach ($dir->courts as $court) {
                $courtOccupiedHours[$court->id] = 0.0;
                $courtAvailableHours[$court->id] = 0.0;
            }
        }

        // [день недели 0..6][час 0..23] => минуты занято / доступно по всем кортам (для тепловой карты)
        $heatOcc = [];
        $heatAvail = [];

        $cursor = $periodStart->copy();
        while ($cursor->lt($periodEnd)) {
            $this->accumulateDay($location, $cursor, $courtOccupiedHours, $courtAvailableHours, $heatOcc, $heatAvail);
            $cursor->addDay();
        }

        $courtRevenue = $this->revenueByCourt(array_keys($courtOccupiedHours), $periodStart, $periodEnd);

        $result = $this->buildResult($location, $courtOccupiedHours, $courtAvailableHours, $courtRevenue);
        $result['heatmap'] = $this->buildHeatmap($heatOcc, $heatAvail);
        $result['bookings'] = $this->bookingStats($location, $periodStart, $periodEnd);

        return $result;
    }

    /**
     * Числитель/знаменатель одного календарного дня — переиспользует
     * TimelineService::day(), который уже умеет: рабочие часы направления по
     * дню недели, привязку турнирных матчей к конкретным кортам по имени, и
     * события/брони без явной привязки к корту (показ на всех кортах направления).
     */
    private function accumulateDay(Location $location, Carbon $date, array &$occupiedHours, array &$availableHours, array &$heatOcc, array &$heatAvail): void
    {
        $dayData = $this->timelineService->day($location, $date);
        $dow = $date->dayOfWeekIso - 1;

        foreach ($dayData as $dirRow) {
            if ($dirRow['is_closed']) {
                continue;
            }

            $dayStart = $this->timeToMin($dirRow['opens_at']);
            $dayEnd = $this->timeToMin($dirRow['closes_at']);
            $dayHours = max(0, $dayEnd - $dayStart) / 60;

            foreach ($dirRow['courts'] as $courtRow) {
                $courtId = $courtRow['id'];
                $availableHours[$courtId] = ($availableHours[$courtId] ?? 0.0) + $dayHours;
                $this->addToBuckets($heatAvail, $dow, $dayStart, $dayEnd);

                foreach ($courtRow['slots'] as $slot) {
                    // Числитель — только реально подтверждённая занятость: pending-брони
                    // ещё не гарантированы (TTL/ожидание оплаты), в загрузку не считаем.
                    if (($slot['type'] ?? null) === 'booking' && ($slot['status'] ?? null) === CourtBooking::STATUS_PENDING) {
                        continue;
                    }

                    $rawStart = $this->timeToMin($slot['starts_at']);
                    $rawEnd = $this->timeToMin($slot['ends_at']);
                    if ($rawEnd <= $dayStart || $rawStart >= $dayEnd) {
                        continue;
                    }

                    $clampedStart = max($rawStart, $dayStart);
                    $clampedEnd = min($rawEnd, $dayEnd);
                    $occupiedHours[$courtId] = ($occupiedHours[$courtId] ?? 0.0) + max(0, $clampedEnd - $clampedStart) / 60;
                    $this->addToBuckets($heatOcc, $dow, $clampedStart, $clampedEnd);
                }
            }
        }
    }

    /**
     * Выручка — сумма price_total активных броней (confirmed/paid), сгруппированная
     * по корту и payment_mode. prepaid => "оплачено онлайн", on_site/trusted =>
     * "на месте" (обе означают, что оплата прошла не через онлайн-эквайринг платформы).
     *
     * @param int[] $courtIds
     * @return array<int, array{online: float, on_site: float, total: float}>
     */
    private function revenueByCourt(array $courtIds, Carbon $periodStart, Carbon $periodEnd): array
    {
        $result = [];
        foreach ($courtIds as $id) {
            $result[$id] = ['online' => 0.0, 'on_site' => 0.0, 'total' => 0.0];
        }
        if (empty($courtIds)) {
            return $result;
        }

        // $periodStart/$periodEnd приходят уже в таймзоне локации (см. resolvePeriod()) —
        // просто переводим в UTC для сравнения с starts_at (хранится в UTC).
        $periodStartUtc = $periodStart->copy()->setTimezone('UTC');
        $periodEndUtc = $periodEnd->copy()->setTimezone('UTC');

        $rows = CourtBooking::query()
            ->whereIn('court_id', $courtIds)
            ->whereIn('status', [CourtBooking::STATUS_CONFIRMED, CourtBooking::STATUS_PAID])
            ->where('starts_at', '>=', $periodStartUtc)
            ->where('starts_at', '<', $periodEndUtc)
            ->select('court_id', 'payment_mode', DB::raw('SUM(price_total) as total'))
            ->groupBy('court_id', 'payment_mode')
            ->get();

        foreach ($rows as $row) {
            $bucket = $row->payment_mode === CourtBooking::PAYMENT_MODE_PREPAID ? 'online' : 'on_site';
            $total = (float) $row->total;
            $result[$row->court_id]['online'] += $bucket === 'online' ? $total : 0.0;
            $result[$row->court_id]['on_site'] += $bucket === 'on_site' ? $total : 0.0;
            $result[$row->court_id]['total'] += $total;
        }

        return $result;
    }

    /** Раскладывает интервал [from, to) в минутах от полуночи по часовым корзинам дня недели. */
    private function addToBuckets(array &$buckets, int $dow, int $from, int $to): void
    {
        for ($h = intdiv($from, 60); $h * 60 < $to && $h < 24; $h++) {
            $mins = min($to, ($h + 1) * 60) - max($from, $h * 60);
            if ($mins > 0) {
                $buckets[$dow][$h] = ($buckets[$dow][$h] ?? 0) + $mins;
            }
        }
    }

    /**
     * Тепловая карта загрузки: ячейки [день недели][час] => % (null — в этот час всегда закрыто),
     * плюс «горячие часы» (топ загрузки) и «свободные окна» (минимум загрузки среди рабочих часов).
     */
    private function buildHeatmap(array $occ, array $avail): array
    {
        $cells = [];
        $minH = 24;
        $maxH = -1;
        $flat = [];
        for ($d = 0; $d < 7; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $a = $avail[$d][$h] ?? 0;
                if ($a <= 0) {
                    $cells[$d][$h] = null;
                    continue;
                }
                $pct = round(($occ[$d][$h] ?? 0) / $a * 100);
                $cells[$d][$h] = $pct;
                $minH = min($minH, $h);
                $maxH = max($maxH, $h);
                $flat[] = ['dow' => $d, 'hour' => $h, 'pct' => $pct];
            }
        }

        $hot = $flat;
        usort($hot, fn ($x, $y) => $y['pct'] <=> $x['pct'] ?: $x['dow'] <=> $y['dow'] ?: $x['hour'] <=> $y['hour']);
        $cold = $flat;
        usort($cold, fn ($x, $y) => $x['pct'] <=> $y['pct'] ?: $x['dow'] <=> $y['dow'] ?: $x['hour'] <=> $y['hour']);

        return [
            'cells' => $cells,
            'from_hour' => $maxH < 0 ? 0 : $minH,
            'to_hour' => $maxH < 0 ? -1 : $maxH,
            'hot' => array_values(array_filter(array_slice($hot, 0, 3), fn ($c) => $c['pct'] > 0)),
            'cold' => array_slice($cold, 0, 3),
        ];
    }

    /**
     * Брони за период по всем кортам локации: отмены/истечения, потерянная выручка и разбивка выручки по источнику.
     * Источник (court_bookings.source): player — игрок через сайт, manual — внесена управляющим, event — мероприятие,
     * other — исторические брони без отметки.
     */
    private function bookingStats(Location $location, Carbon $periodStart, Carbon $periodEnd): array
    {
        $courtIds = DB::table('location_courts')
            ->join('location_directions', 'location_directions.id', '=', 'location_courts.direction_id')
            ->where('location_directions.location_id', $location->id)
            ->pluck('location_courts.id')->all();

        $empty = ['total' => 0, 'active' => 0, 'cancelled' => 0, 'expired' => 0, 'cancel_rate' => 0.0, 'lost_revenue' => 0.0,
            'by_source' => ['player' => 0.0, 'manual' => 0.0, 'event' => 0.0, 'other' => 0.0]];
        if ($courtIds === []) {
            return $empty;
        }

        $startUtc = $periodStart->copy()->setTimezone('UTC');
        $endUtc = $periodEnd->copy()->setTimezone('UTC');

        $base = CourtBooking::query()->whereIn('court_id', $courtIds)->where('starts_at', '>=', $startUtc)->where('starts_at', '<', $endUtc);

        $byStatus = (clone $base)->select('status', DB::raw('COUNT(*) as c'), DB::raw('COALESCE(SUM(price_total),0) as s'))
            ->groupBy('status')->get()->keyBy('status');

        $active = (int) ($byStatus[CourtBooking::STATUS_CONFIRMED]->c ?? 0) + (int) ($byStatus[CourtBooking::STATUS_PAID]->c ?? 0);
        $cancelled = (int) ($byStatus[CourtBooking::STATUS_CANCELLED]->c ?? 0);
        $expired = (int) ($byStatus[CourtBooking::STATUS_EXPIRED]->c ?? 0);
        $considered = $active + $cancelled;

        $bySource = $empty['by_source'];
        $rows = (clone $base)->whereIn('status', [CourtBooking::STATUS_CONFIRMED, CourtBooking::STATUS_PAID])
            ->select(DB::raw("COALESCE(source,'other') as src"), DB::raw('COALESCE(SUM(price_total),0) as s'))
            ->groupBy('src')->get();
        foreach ($rows as $r) {
            $key = array_key_exists($r->src, $bySource) ? $r->src : 'other';
            $bySource[$key] += (float) $r->s;
        }

        return [
            'total' => $active + $cancelled + $expired,
            'active' => $active,
            'cancelled' => $cancelled,
            'expired' => $expired,
            'cancel_rate' => $considered > 0 ? round($cancelled / $considered * 100, 1) : 0.0,
            'lost_revenue' => round((float) ($byStatus[CourtBooking::STATUS_CANCELLED]->s ?? 0), 2),
            'by_source' => array_map(fn ($v) => round($v, 2), $bySource),
        ];
    }

    private function buildResult(Location $location, array $occupiedHours, array $availableHours, array $courtRevenue): array
    {
        $directions = [];

        foreach ($location->directions as $dir) {
            $courtsOut = [];
            foreach ($dir->courts as $court) {
                $occupied = $occupiedHours[$court->id] ?? 0.0;
                $available = $availableHours[$court->id] ?? 0.0;
                $revenue = $courtRevenue[$court->id] ?? ['online' => 0.0, 'on_site' => 0.0, 'total' => 0.0];

                $courtsOut[] = [
                    'id' => $court->id,
                    'name' => $court->name,
                    'occupied_hours' => round($occupied, 2),
                    'available_hours' => round($available, 2),
                    'occupancy_pct' => $available > 0 ? round($occupied / $available * 100, 1) : 0.0,
                    'revenue' => round($revenue['total'], 2),
                    'revenue_online' => round($revenue['online'], 2),
                    'revenue_on_site' => round($revenue['on_site'], 2),
                ];
            }

            $directions[] = [
                'direction' => $dir->direction,
                'occupancy_pct' => $this->average(array_column($courtsOut, 'occupancy_pct')),
                'revenue' => round(array_sum(array_column($courtsOut, 'revenue')), 2),
                'revenue_online' => round(array_sum(array_column($courtsOut, 'revenue_online')), 2),
                'revenue_on_site' => round(array_sum(array_column($courtsOut, 'revenue_on_site')), 2),
                'courts' => $courtsOut,
            ];
        }

        $allCourts = array_merge(...array_map(fn ($d) => $d['courts'], $directions ?: [[]]));

        return [
            'directions' => $directions,
            'center' => [
                'occupancy_pct' => $this->average(array_column($allCourts, 'occupancy_pct')),
                'revenue' => round(array_sum(array_column($allCourts, 'revenue')), 2),
                'revenue_online' => round(array_sum(array_column($allCourts, 'revenue_online')), 2),
                'revenue_on_site' => round(array_sum(array_column($allCourts, 'revenue_on_site')), 2),
            ],
        ];
    }

    private function average(array $values): float
    {
        return empty($values) ? 0.0 : round(array_sum($values) / count($values), 1);
    }

    private function timeToMin(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }
}
