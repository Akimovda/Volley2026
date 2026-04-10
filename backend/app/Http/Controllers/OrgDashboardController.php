<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrgDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $orgId = $user->id;

        // --- МЕРОПРИЯТИЯ ---
        $eventsBase = DB::table('events')->where('organizer_id', $orgId);

        $totalEvents  = (clone $eventsBase)->count();
        $activeEvents = (clone $eventsBase)->where('allow_registration', true)->count();

        // Постоянные (recurring) vs разовые
        $recurringEvents = (clone $eventsBase)->whereNotNull('recurrence_rule')->count();
        $oneTimeEvents   = $totalEvents - $recurringEvents;

        // --- ИГРОКИ (только реальные, без ботов) ---
        $playersStats = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.is_cancelled', false)
            ->select(
                DB::raw('COUNT(DISTINCT er.user_id) as unique_players'),
                DB::raw('COUNT(er.id) as total_registrations')
            )
            ->first();

        // Новые игроки (первый раз за последние 30 дней)
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
            ->select(DB::raw('COUNT(DISTINCT er.user_id) as cnt'))
            ->value('cnt');

        // --- ДИНАМИКА ПО МЕСЯЦАМ (12 месяцев) ---
        $monthlyStats = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw("TO_CHAR(er.created_at, 'YYYY-MM') as month"),
                DB::raw('COUNT(CASE WHEN er.is_cancelled = false THEN 1 END) as registrations'),
                DB::raw('COUNT(CASE WHEN er.is_cancelled = true THEN 1 END) as cancellations')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // --- ЗАГРУЗКА МЕРОПРИЯТИЙ ---
        $occurrenceLoad = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->join('event_game_settings as egs', 'egs.event_id', '=', 'e.id')
            ->leftJoin('event_occurrence_stats as eos', 'eos.occurrence_id', '=', 'eo.id')
            ->where('e.organizer_id', $orgId)
            ->where('eo.starts_at', '>=', now()->subMonths(3))
            ->select(
                'e.title',
                DB::raw('AVG(eos.registered_count::float / NULLIF(egs.max_players, 0) * 100) as avg_load_pct'),
                DB::raw('COUNT(eo.id) as occurrences_count'),
                DB::raw('SUM(eos.registered_count) as total_registered')
            )
            ->groupBy('e.id', 'e.title')
            ->orderByDesc('total_registered')
            ->limit(10)
            ->get();

        // --- ЭФФЕКТИВНОСТЬ БОТОВ ---
        $botEffect = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->join('event_occurrence_stats as eos', 'eos.occurrence_id', '=', 'eo.id')
            ->where('e.organizer_id', $orgId)
            ->where('eo.starts_at', '>=', now()->subMonths(3))
            ->select(
                DB::raw('SUM(CASE WHEN EXISTS(
                    SELECT 1 FROM event_registrations er2
                    JOIN users u2 ON u2.id = er2.user_id
                    WHERE er2.occurrence_id = eo.id AND u2.is_bot = true AND er2.is_cancelled = false
                ) THEN eos.registered_count ELSE 0 END)::float / NULLIF(COUNT(*), 0) as avg_with_bots'),
                DB::raw('SUM(CASE WHEN NOT EXISTS(
                    SELECT 1 FROM event_registrations er2
                    JOIN users u2 ON u2.id = er2.user_id
                    WHERE er2.occurrence_id = eo.id AND u2.is_bot = true AND er2.is_cancelled = false
                ) THEN eos.registered_count ELSE 0 END)::float / NULLIF(COUNT(*), 0) as avg_without_bots'),
                DB::raw('COUNT(CASE WHEN EXISTS(
                    SELECT 1 FROM event_registrations er2
                    JOIN users u2 ON u2.id = er2.user_id
                    WHERE er2.occurrence_id = eo.id AND u2.is_bot = true AND er2.is_cancelled = false
                ) THEN 1 END) as occurrences_with_bots'),
                DB::raw('COUNT(CASE WHEN NOT EXISTS(
                    SELECT 1 FROM event_registrations er2
                    JOIN users u2 ON u2.id = er2.user_id
                    WHERE er2.occurrence_id = eo.id AND u2.is_bot = true AND er2.is_cancelled = false
                ) THEN 1 END) as occurrences_without_bots')
            )
            ->first();

        // --- ПРОСМОТРЫ ---
        $pageViews = DB::table('page_views as pv')
            ->join('events as e', function($j) {
                $j->on('pv.entity_id', '=', 'e.id')
                  ->where('pv.entity_type', '=', 'event');
            })
            ->where('e.organizer_id', $orgId)
            ->where('pv.created_at', '>=', now()->subDays(30))
            ->count();

        $profileViews = DB::table('page_views')
            ->where('entity_type', 'user')
            ->where('entity_id', $orgId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // --- ТОП ИГРОКОВ (без ботов) ---
        $topPlayers = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.is_cancelled', false)
            ->select(
                'u.id', 'u.first_name', 'u.last_name',
                DB::raw('COUNT(er.id) as visits'),
                DB::raw('COUNT(CASE WHEN er.created_at >= NOW() - INTERVAL \'30 days\' THEN 1 END) as visits_30d')
            )
            ->groupBy('u.id', 'u.first_name', 'u.last_name')
            ->orderByDesc('visits')
            ->limit(10)
            ->get();

        // --- ИГРОКИ ЧАСТО ОТМЕНЯЮЩИЕ ---
        $topCancellers = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('e.organizer_id', $orgId)
            ->where('u.is_bot', false)
            ->where('er.is_cancelled', true)
            ->select(
                'u.id', 'u.first_name', 'u.last_name',
                DB::raw('COUNT(er.id) as cancellations')
            )
            ->groupBy('u.id', 'u.first_name', 'u.last_name')
            ->orderByDesc('cancellations')
            ->limit(10)
            ->get();

        // --- СТАТИСТИКА АБОНЕМЕНТОВ ---
        $subStats = \DB::table('subscriptions')
            ->where('organizer_id', $orgId)
            ->select(
                \DB::raw('COUNT(*) as total'),
                \DB::raw('COUNT(CASE WHEN status = \'active\' THEN 1 END) as active'),
                \DB::raw('COUNT(CASE WHEN status = \'expired\' THEN 1 END) as expired'),
                \DB::raw('COUNT(CASE WHEN status = \'exhausted\' THEN 1 END) as exhausted'),
                \DB::raw('SUM(visits_used) as total_visits_used'),
                \DB::raw('SUM(visits_remaining) as total_visits_remaining')
            )->first();

        $subRevenue = \DB::table('subscriptions as s')
            ->join('subscription_templates as st', 'st.id', '=', 's.template_id')
            ->where('s.organizer_id', $orgId)
            ->where('s.payment_status', 'paid')
            ->sum('st.price_minor');

        $topSubTemplates = \DB::table('subscriptions as s')
            ->join('subscription_templates as st', 'st.id', '=', 's.template_id')
            ->where('s.organizer_id', $orgId)
            ->select('st.name', \DB::raw('COUNT(s.id) as sold'), \DB::raw('SUM(s.visits_used) as visits_used'))
            ->groupBy('st.id', 'st.name')
            ->orderByDesc('sold')
            ->limit(5)
            ->get();

        $couponStats = \DB::table('coupons')
            ->where('organizer_id', $orgId)
            ->select(
                \DB::raw('COUNT(*) as total'),
                \DB::raw('COUNT(CASE WHEN status = \'active\' THEN 1 END) as active'),
                \DB::raw('COUNT(CASE WHEN status = \'used\' THEN 1 END) as used'),
                \DB::raw('SUM(uses_used) as total_uses')
            )->first();

        return view('dashboard.org', compact(
            'totalEvents', 'activeEvents', 'recurringEvents', 'oneTimeEvents',
            'playersStats', 'newPlayers',
            'monthlyStats', 'occurrenceLoad',
            'botEffect', 'pageViews', 'profileViews',
            'topPlayers', 'topCancellers',
            'subStats', 'subRevenue', 'topSubTemplates', 'couponStats'
        ));
    }
}
