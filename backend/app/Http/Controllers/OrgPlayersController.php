<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrgPlayersController extends Controller
{
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

        return view('dashboard.org_players', compact(
            'topPlayers', 'newPlayers', 'churnRisk',
            'genderStats', 'classicLevels', 'beachLevels',
            'reservePlayers'
        ));
    }
}
