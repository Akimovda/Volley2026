<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrgPlayersController extends Controller
{
    private const PERIOD_DAYS = [
        '30d'  => 30,
        '90d'  => 90,
        '180d' => 180,
        '365d' => 365,
    ];

    private const PERIOD_LABELS = [
        '30d'  => 'за последние 30 дней',
        '90d'  => 'за последние 3 месяца',
        '180d' => 'за последние 6 месяцев',
        '365d' => 'за последний год',
        'all'  => 'за всё время',
    ];

    public function index(Request $request)
    {
        $user  = $request->user();
        $orgId = $user->id;

        // --- ТОП АКТИВНЫХ (одним запросом, разрезы по периодам) ---
        $topPlayers = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.is_cancelled', false)
            ->select(
                'u.id', 'u.first_name', 'u.last_name',
                DB::raw("COUNT(er.id) as v_all"),
                DB::raw("COUNT(CASE WHEN er.created_at >= NOW() - INTERVAL '30 days'  THEN 1 END) as v_30d"),
                DB::raw("COUNT(CASE WHEN er.created_at >= NOW() - INTERVAL '90 days'  THEN 1 END) as v_90d"),
                DB::raw("COUNT(CASE WHEN er.created_at >= NOW() - INTERVAL '180 days' THEN 1 END) as v_180d"),
                DB::raw("COUNT(CASE WHEN er.created_at >= NOW() - INTERVAL '365 days' THEN 1 END) as v_365d")
            )
            ->groupBy('u.id', 'u.first_name', 'u.last_name')
            ->orderByDesc('v_all')
            ->limit(50)
            ->get();

        // --- НОВЫЕ ИГРОКИ (первый визит в последние 30 дней) ---
        $newPlayers = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.created_at', '>=', now()->subDays(30))
            ->where('er.is_cancelled', false)
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM event_registrations er2
                JOIN events e2 ON e2.id = er2.event_id
                WHERE er2.user_id = er.user_id
                  AND e2.organizer_id = ?
                  AND er2.created_at < ?
                  AND er2.is_cancelled = false
            )', [$orgId, now()->subDays(30)])
            ->select(
                'u.id', 'u.first_name', 'u.last_name',
                DB::raw('MIN(er.created_at) as first_visit'),
                DB::raw('COUNT(er.id) as visit_count')
            )
            ->groupBy('u.id', 'u.first_name', 'u.last_name')
            ->orderByDesc(DB::raw('MIN(er.created_at)'))
            ->limit(50)
            ->get();

        // --- РИСК ОТТОКА (>= 3 визитов, последний > 60 дней назад) ---
        $churnRisk = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.is_cancelled', false)
            ->select(
                'u.id', 'u.first_name', 'u.last_name',
                DB::raw('COUNT(er.id) as visits_total'),
                DB::raw('MAX(er.created_at) as last_visit')
            )
            ->groupBy('u.id', 'u.first_name', 'u.last_name')
            ->having(DB::raw('COUNT(er.id)'), '>=', 3)
            ->having(DB::raw('MAX(er.created_at)'), '<', now()->subDays(60))
            ->orderByDesc('visits_total')
            ->limit(30)
            ->get();

        // --- РАСПРЕДЕЛЕНИЕ ПО ПОЛУ ---
        $genderStats = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.is_cancelled', false)
            ->whereNotNull('u.gender')
            ->select('u.gender', DB::raw('COUNT(DISTINCT u.id) as cnt'))
            ->groupBy('u.gender')
            ->get()
            ->keyBy('gender');

        // --- РАСПРЕДЕЛЕНИЕ ПО УРОВНЯМ ---
        $classicLevels = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.is_cancelled', false)
            ->whereNotNull('u.classic_level')
            ->select('u.classic_level as level', DB::raw('COUNT(DISTINCT u.id) as cnt'))
            ->groupBy('u.classic_level')
            ->orderBy('u.classic_level')
            ->get();

        $beachLevels = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.is_cancelled', false)
            ->whereNotNull('u.beach_level')
            ->select('u.beach_level as level', DB::raw('COUNT(DISTINCT u.id) as cnt'))
            ->groupBy('u.beach_level')
            ->orderBy('u.beach_level')
            ->get();

        // --- ЧАСТО В РЕЗЕРВЕ ---
        $reservePlayers = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.is_cancelled', false)
            ->where('er.position', 'reserve')
            ->select(
                'u.id', 'u.first_name', 'u.last_name',
                DB::raw('COUNT(er.id) as reserve_count'),
                DB::raw("COUNT(CASE WHEN er.created_at >= NOW() - INTERVAL '90 days' THEN 1 END) as reserve_90d")
            )
            ->groupBy('u.id', 'u.first_name', 'u.last_name')
            ->having(DB::raw('COUNT(er.id)'), '>=', 2)
            ->orderByDesc('reserve_count')
            ->limit(10)
            ->get();

        // --- ЛОКАЦИИ ОРГАНИЗАТОРА (для разреза аудитории) ---
        $locations = DB::table('events')
            ->join('locations', 'locations.id', '=', 'events.location_id')
            ->where('events.organizer_id', $orgId)
            ->whereNotNull('events.location_id')
            ->select('locations.id', 'locations.name')
            ->distinct()
            ->orderBy('locations.name')
            ->get();

        return view('dashboard.org_players', compact(
            'topPlayers', 'newPlayers', 'churnRisk',
            'genderStats', 'classicLevels', 'beachLevels',
            'reservePlayers', 'locations'
        ));
    }

    /**
     * Базовый запрос аудитории: количество визитов по игроку,
     * с фильтром по локации и периоду. Переиспользуется для страницы, AJAX-пагинации и экспортов.
     */
    private function audienceBaseQuery(int $orgId, ?string $locationId, string $period)
    {
        $q = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.is_cancelled', false);

        if ($locationId !== null && $locationId !== '' && $locationId !== 'all') {
            $q->where('e.location_id', (int) $locationId);
        }

        if (isset(self::PERIOD_DAYS[$period])) {
            $q->where('er.created_at', '>=', now()->subDays(self::PERIOD_DAYS[$period]));
        }

        return $q->select(
                'u.id',
                'u.first_name', 'u.last_name', 'u.patronymic',
                DB::raw('COUNT(er.id) as visits')
            )
            ->groupBy('u.id', 'u.first_name', 'u.last_name', 'u.patronymic')
            ->orderByDesc('visits')
            ->orderBy('u.last_name');
    }

    private function audienceLocationLabel(?string $locationId): string
    {
        if ($locationId === null || $locationId === '' || $locationId === 'all') {
            return 'Вся аудитория (все локации)';
        }
        $name = DB::table('locations')->where('id', (int) $locationId)->value('name');
        return $name ?: ('Локация #' . $locationId);
    }

    private function audienceFullName(object $row): string
    {
        return trim(implode(' ', array_filter([
            $row->last_name ?? '',
            $row->first_name ?? '',
            $row->patronymic ?? '',
        ]))) ?: ('#' . $row->id);
    }

    /**
     * GET /org/players/audience — постраничный список аудитории (JSON), 15 на страницу.
     */
    public function audienceData(Request $request)
    {
        $orgId      = $request->user()->id;
        $locationId = (string) $request->query('location', 'all');
        $period     = (string) $request->query('period', 'all');
        $page       = max(1, (int) $request->query('page', 1));

        $paginator = $this->audienceBaseQuery($orgId, $locationId, $period)
            ->paginate(15, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(fn ($row) => [
            'id'     => $row->id,
            'name'   => $this->audienceFullName($row),
            'visits' => (int) $row->visits,
        ])->values();

        return response()->json([
            'items'        => $items,
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
        ]);
    }

    /**
     * GET /org/players/audience/export/csv — выгрузка в CSV (открывается и редактируется в Excel).
     */
    public function audienceExportCsv(Request $request)
    {
        $orgId      = $request->user()->id;
        $locationId = (string) $request->query('location', 'all');
        $period     = (string) $request->query('period', 'all');

        $rows = $this->audienceBaseQuery($orgId, $locationId, $period)->get();

        $locationLabel = $this->audienceLocationLabel($locationId);
        $periodLabel   = self::PERIOD_LABELS[$period] ?? self::PERIOD_LABELS['all'];

        $filename = 'audience-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows, $locationLabel, $periodLabel) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$locationLabel . ', ' . $periodLabel], ';');
            fputcsv($out, ['#', 'Игрок', 'Визитов'], ';');
            foreach ($rows as $i => $r) {
                fputcsv($out, [$i + 1, $this->audienceFullName($r), $r->visits], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * GET /org/players/audience/export/pdf — выгрузка в PDF.
     */
    public function audienceExportPdf(Request $request)
    {
        $orgId      = $request->user()->id;
        $locationId = (string) $request->query('location', 'all');
        $period     = (string) $request->query('period', 'all');

        $rows = $this->audienceBaseQuery($orgId, $locationId, $period)->get()
            ->map(fn ($row) => (object) [
                'name'   => $this->audienceFullName($row),
                'visits' => (int) $row->visits,
            ]);

        $locationLabel = $this->audienceLocationLabel($locationId);
        $periodLabel   = self::PERIOD_LABELS[$period] ?? self::PERIOD_LABELS['all'];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.org_players_audience_pdf', [
            'rows'          => $rows,
            'locationLabel' => $locationLabel,
            'periodLabel'   => $periodLabel,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('audience-' . now()->format('Ymd_His') . '.pdf');
    }
}
