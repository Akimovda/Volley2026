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
            'brand'         => $request->user()->brand,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('audience-' . now()->format('Ymd_His') . '.pdf');
    }

    // ───────────────────────── История посещений игрока ─────────────────────────

    private function historyTz(Request $request): string
    {
        return $request->user()->timezone ?: 'Europe/Moscow';
    }

    /** Границы диапазона дат (локальные даты организатора) → UTC-строки для starts_at. */
    private function historyRange(Request $request): array
    {
        $tz   = $this->historyTz($request);
        $from = (string) $request->query('from', '');
        $to   = (string) $request->query('to', '');
        $re   = '/^\d{4}-\d{2}-\d{2}$/';

        $fromUtc = preg_match($re, $from)
            ? \Carbon\Carbon::parse($from, $tz)->startOfDay()->utc()->format('Y-m-d H:i:s') : null;
        $toUtc = preg_match($re, $to)
            ? \Carbon\Carbon::parse($to, $tz)->endOfDay()->utc()->format('Y-m-d H:i:s') : null;

        return [$fromUtc, $toUtc, preg_match($re, $from) ? $from : null, preg_match($re, $to) ? $to : null];
    }

    /** Запрос посещений игрока ТОЛЬКО у данного организатора (прошедшие туры, без отмен). */
    private function historyQuery(int $orgId, int $userId, ?string $fromUtc, ?string $toUtc)
    {
        $q = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('event_occurrences as eo', 'eo.id', '=', 'er.occurrence_id')
            ->where('e.organizer_id', $orgId)
            ->where('er.user_id', $userId)
            ->whereRaw("(er.is_cancelled IS NULL OR er.is_cancelled = false) AND (er.status IS NULL OR er.status != 'cancelled') AND er.cancelled_at IS NULL")
            ->whereRaw('(eo.is_cancelled IS NULL OR eo.is_cancelled = false)')
            ->where('eo.starts_at', '<=', now()->utc()->format('Y-m-d H:i:s'));

        if ($fromUtc) $q->where('eo.starts_at', '>=', $fromUtc);
        if ($toUtc)   $q->where('eo.starts_at', '<=', $toUtc);

        return $q->select(
                'e.id as event_id', 'e.title', 'eo.starts_at',
                DB::raw('COALESCE(eo.timezone, e.timezone) as tz'),
                'er.position'
            )
            ->orderByDesc('eo.starts_at')
            ->orderByDesc('er.id');
    }

    private function historyFormatRow(object $r): array
    {
        $tz = $r->tz ?: 'Europe/Moscow';
        try {
            $dt = \Carbon\Carbon::parse($r->starts_at, 'UTC')->setTimezone($tz);
        } catch (\Throwable $e) {
            $dt = \Carbon\Carbon::parse($r->starts_at, 'UTC');
        }
        return [
            'event_id' => $r->event_id,
            'title'    => (string) $r->title,
            'when'     => $dt->format('d.m.Y H:i'),
            'position' => $r->position ? position_name($r->position) : '—',
        ];
    }

    /** Игрок должен быть в аудитории организатора, иначе 404. */
    private function historyPlayerOrFail(int $orgId, int $userId): object
    {
        $exists = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->where('e.organizer_id', $orgId)
            ->where('er.user_id', $userId)
            ->exists();
        abort_unless($exists, 404);

        return DB::table('users')->where('id', $userId)->first();
    }

    /** GET /org/players/history/search?q= — игроки аудитории организатора для выбора. */
    public function historySearch(Request $request)
    {
        $orgId = $request->user()->id;
        $q     = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($q)) . '%';

        $rows = DB::table('users as u')
            ->where('u.is_bot', false)
            ->whereNull('u.deleted_at')
            ->whereExists(function ($s) use ($orgId) {
                $s->select(DB::raw(1))->from('event_registrations as er')
                    ->join('events as e', 'e.id', '=', 'er.event_id')
                    ->whereColumn('er.user_id', 'u.id')
                    ->where('e.organizer_id', $orgId);
            })
            ->where(function ($w) use ($like) {
                $w->whereRaw("LOWER(CONCAT_WS(' ', u.last_name, u.first_name, u.patronymic)) LIKE ?", [$like])
                  ->orWhereRaw("LOWER(CONCAT_WS(' ', u.first_name, u.last_name)) LIKE ?", [$like]);
            })
            ->orderBy('u.last_name')->orderBy('u.first_name')
            ->limit(10)
            ->get(['u.id', 'u.first_name', 'u.last_name', 'u.patronymic']);

        return response()->json(['items' => $rows->map(fn ($r) => [
            'id'    => $r->id,
            'label' => $this->audienceFullName($r),
        ])->values()]);
    }

    /** GET /org/players/history — JSON, 10 на страницу. */
    public function historyData(Request $request)
    {
        $orgId  = $request->user()->id;
        $userId = (int) $request->query('user_id');
        $this->historyPlayerOrFail($orgId, $userId);
        [$fromUtc, $toUtc] = $this->historyRange($request);

        $page = $this->historyQuery($orgId, $userId, $fromUtc, $toUtc)
            ->paginate(10, ['*'], 'page', max(1, (int) $request->query('page', 1)));

        return response()->json([
            'items'        => collect($page->items())->map(fn ($r) => $this->historyFormatRow($r))->values(),
            'total'        => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
        ]);
    }

    /** Общие данные для экспортов: игрок, диапазон, все строки. */
    private function historyExportData(Request $request): array
    {
        $orgId  = $request->user()->id;
        $userId = (int) $request->query('user_id');
        $player = $this->historyPlayerOrFail($orgId, $userId);
        [$fromUtc, $toUtc, $from, $to] = $this->historyRange($request);

        $rows = $this->historyQuery($orgId, $userId, $fromUtc, $toUtc)->get()
            ->map(fn ($r) => (object) $this->historyFormatRow($r));

        $u = \App\Models\User::find($userId);
        $range = match (true) {
            $from && $to => \Carbon\Carbon::parse($from)->format('d.m.Y') . ' — ' . \Carbon\Carbon::parse($to)->format('d.m.Y'),
            (bool) $from => 'с ' . \Carbon\Carbon::parse($from)->format('d.m.Y'),
            (bool) $to   => 'по ' . \Carbon\Carbon::parse($to)->format('d.m.Y'),
            default      => 'за всё время',
        };

        return [
            'name'  => $this->audienceFullName($player),
            'phone' => $u?->formatted_phone ?: '—',
            'range' => $range,
            'rows'  => $rows,
        ];
    }

    public function historyExportPdf(Request $request)
    {
        $d = $this->historyExportData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.org_players_history_pdf', $d + [
            'brand' => $request->user()->brand,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('visit-history-' . now()->format('Ymd_His') . '.pdf');
    }

    public function historyExportXlsx(Request $request)
    {
        $d = $this->historyExportData($request);

        $data = [['ИСТОРИЯ ПОСЕЩЕНИЙ'], ['Игрок', $d['name']], ['Телефон', $d['phone']], ['Период', $d['range']], [''],
                 ['#', 'Мероприятие', 'Дата и время', 'Позиция']];
        foreach ($d['rows'] as $i => $r) {
            $data[] = [$i + 1, $r->title, $r->when, $r->position];
        }
        $path = \App\Support\SimpleXlsx::write($data, 'История посещений');

        return response()->download($path, 'visit-history-' . now()->format('Ymd_His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
