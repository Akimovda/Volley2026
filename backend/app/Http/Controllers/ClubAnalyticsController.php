<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\ClubAnalyticsService;
use App\Support\SimpleXlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ClubAnalyticsController extends Controller
{
    public function index(Request $request, ClubAnalyticsService $service)
    {
        $ctx = $this->context($request, $service);

        return view('club.analytics', $ctx + [
            'canExport' => $this->canExport($request),
        ]);
    }

    /** Выгрузка в PDF — только с подпиской Организатор Pro (middleware organizer.pro; админ — всегда). */
    public function exportPdf(Request $request, ClubAnalyticsService $service)
    {
        $ctx = $this->context($request, $service);
        abort_if($ctx['location'] === null, 404);

        // dompdf кэширует метрики шрифтов в storage/fonts — каталог должен существовать (его нет в git)
        \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('club.analytics_pdf', $ctx + [
            'brand' => $request->user()->brand,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->fileName($ctx, 'pdf'));
    }

    /** Выгрузка в Excel (.xlsx) — только с подпиской Организатор Pro. */
    public function exportXlsx(Request $request, ClubAnalyticsService $service)
    {
        $ctx = $this->context($request, $service);
        abort_if($ctx['location'] === null, 404);

        $stats = $ctx['stats'];
        $dirLabel = fn (string $d) => $d === 'beach' ? __('club.direction_beach') : __('club.direction_classic');

        $rows = [[
            __('club.xl_location'), __('club.xl_direction'), __('club.xl_court'), __('club.xl_occupancy'),
            __('club.xl_hours_busy'), __('club.xl_hours_total'), __('club.xl_revenue'),
            __('club.xl_revenue_online'), __('club.xl_revenue_on_site'),
        ]];
        foreach ($stats['directions'] as $dir) {
            foreach ($dir['courts'] as $c) {
                $rows[] = [
                    $ctx['location']->name, $dirLabel($dir['direction']), $c['name'], (float) $c['occupancy_pct'],
                    (float) $c['occupied_hours'], (float) $c['available_hours'], (float) $c['revenue'],
                    (float) $c['revenue_online'], (float) $c['revenue_on_site'],
                ];
            }
            $rows[] = [
                $ctx['location']->name, $dirLabel($dir['direction']), __('club.xl_total_direction'), (float) $dir['occupancy_pct'],
                (float) array_sum(array_column($dir['courts'], 'occupied_hours')),
                (float) array_sum(array_column($dir['courts'], 'available_hours')),
                (float) $dir['revenue'], (float) $dir['revenue_online'], (float) $dir['revenue_on_site'],
            ];
        }
        $center = $stats['center'];
        $all = array_merge(...array_map(fn ($d) => $d['courts'], $stats['directions'] ?: [[]]));
        $rows[] = [
            $ctx['location']->name, __('club.whole_center'), '', (float) $center['occupancy_pct'],
            (float) array_sum(array_column($all, 'occupied_hours')), (float) array_sum(array_column($all, 'available_hours')),
            (float) $center['revenue'], (float) $center['revenue_online'], (float) $center['revenue_on_site'],
        ];
        $rows[] = [''];
        $rows[] = [__('club.xl_period'), $ctx['periodLabel']];
        $rows[] = [__('club.xl_generated'), now($ctx['location']->effectiveTimezone())->format('d.m.Y H:i')];

        $path = SimpleXlsx::write($rows, mb_substr(__('club.analytics'), 0, 30));

        return response()->download($path, $this->fileName($ctx, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function canExport(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user && ($user->isAdmin() || $user->isOrganizerPro()));
    }

    private function fileName(array $ctx, string $ext): string
    {
        return 'club-analytics-' . Str::slug($ctx['location']->name ?: 'location') . '-' . $ctx['period'] . '-'
            . $ctx['periodStart']->format('Ymd') . '.' . $ext;
    }

    /** Общий расчёт для страницы и выгрузок: локация, период, статистика. */
    private function context(Request $request, ClubAnalyticsService $service): array
    {
        $user = $request->user();
        abort_unless($user && ($user->is_club_manager || $user->isAdmin()), 403);

        $locationIds = $user->isAdmin()
            ? Location::whereNotNull('owner_id')->pluck('id')
            : Location::where('owner_id', $user->id)->pluck('id');

        $locations = Location::whereIn('id', $locationIds)->orderBy('name')->get();

        $period = $request->input('period', ClubAnalyticsService::PERIOD_MONTH);
        if (!in_array($period, ClubAnalyticsService::PERIODS, true)) {
            $period = ClubAnalyticsService::PERIOD_MONTH;
        }

        if ($locations->isEmpty()) {
            return [
                'locations' => $locations,
                'location' => null,
                'period' => $period,
                'periodStart' => null,
                'periodEnd' => null,
                'periodLabel' => '',
                'prevAnchor' => null,
                'nextAnchor' => null,
                'stats' => null,
            ];
        }

        $selectedLocationId = (int) $request->input('location_id', $locations->first()->id);
        $location = $locations->firstWhere('id', $selectedLocationId) ?? $locations->first();

        // Границы периода считаются в локальном календаре локации (её effectiveTimezone) —
        // иначе "июль" для клуба в Москве мог бы съехать на несколько часов от реального
        // местного месяца при сравнении с court_bookings.starts_at (хранится в UTC).
        $tz = $location->effectiveTimezone();
        $anchor = $request->filled('anchor')
            ? Carbon::parse($request->input('anchor'), $tz)
            : Carbon::now($tz);

        [$periodStart, $periodEnd] = $service->resolvePeriod($period, $anchor);

        $stats = $service->forLocation($location, $periodStart, $periodEnd);

        return [
            'locations' => $locations,
            'location' => $location,
            'period' => $period,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'periodLabel' => $this->periodLabel($period, $periodStart),
            'prevAnchor' => $service->shiftAnchor($period, $anchor, -1),
            'nextAnchor' => $service->shiftAnchor($period, $anchor, 1),
            'stats' => $stats,
        ];
    }

    private function periodLabel(string $period, Carbon $start): string
    {
        $qNum = intdiv($start->month - 1, 3) + 1;
        $hNum = $start->month <= 6 ? 1 : 2;

        return match ($period) {
            'quarter' => __('club.period_label_quarter', ['n' => $qNum, 'year' => $start->year]),
            'half_year' => __('club.period_label_half_year', ['n' => $hNum, 'year' => $start->year]),
            'year' => (string) $start->year,
            default => Str::ucfirst($start->copy()->locale(app()->getLocale())->isoFormat('MMMM YYYY')),
        };
    }
}
