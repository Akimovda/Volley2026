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
        // Живой COUNT вместо event_occurrence_stats (кеш устаревает при массовых
        // отменах через QueryBuilder — см. EventOccurrenceStatsService::getRegisteredCount).
        // Вместимость по типу мероприятия (тот же приоритет, что и в EventRegistrationGuard):
        // командные турниры — команды (events.tournament_teams_count → ets.teams_count →
        // egs.teams_count), tournament_individual/king_beach и обычные — игроки (max_players).
        $teamModes = "('team_classic','team_beach','team')";
        // Статус-лист и исключение резерва — SQL-дубль канонической логики
        // TournamentTeamService::countRegisteredTeams() (SQL-подзапрос не может звать PHP-метод,
        // условие обязано оставаться идентичным ему дословно — при правке одного менять оба).
        // Резерв команд бывает двух видов и они взаимоисключающие для одного occurrence:
        // событийный (et.reserve_position — лист ожидания сверх tournament_teams_count) и
        // лиговый (tournament_league_teams.status='reserve', только когда у события есть season_id).
        $registeredExpr = "(CASE
                WHEN e.format = 'tournament' AND e.registration_mode IN {$teamModes} THEN (
                    SELECT COUNT(*) FROM event_teams et
                    WHERE et.event_id = e.id
                    AND (et.occurrence_id = eo.id OR et.occurrence_id IS NULL)
                    AND et.status IN ('ready','pending','pending_members','submitted','confirmed','approved')
                    AND et.reserve_position IS NULL
                    AND NOT EXISTS (
                        SELECT 1 FROM tournament_season_events tse
                        JOIN tournament_league_teams tlt
                            ON tlt.league_id = tse.league_id
                            AND tlt.status = 'reserve'
                            AND tlt.team_id = et.id
                        WHERE tse.occurrence_id = eo.id
                    )
                )
                ELSE (
                    SELECT COUNT(*) FROM event_registrations er
                    WHERE er.occurrence_id = eo.id
                    AND er.cancelled_at IS NULL
                    AND (er.is_cancelled IS NULL OR er.is_cancelled = false)
                    AND (er.status IS NULL OR er.status != 'cancelled')
                )
            END)";
        $capacityExpr = "(CASE
                WHEN e.format = 'tournament' AND e.registration_mode IN {$teamModes} THEN
                    COALESCE(NULLIF(e.tournament_teams_count, 0), NULLIF(ets.teams_count, 0), NULLIF(egs.teams_count, 0), 0)
                WHEN e.format = 'tournament' AND e.registration_mode IN ('tournament_individual','king_beach') THEN
                    -- egs.max_players пересчитывается GameCalculator'ом от teams_count при каждом
                    -- сохранении формы (EventManagementController::update()) — надёжный источник.
                    -- ets.total_players_max для tournament_individual — это НЕ общая вместимость
                    -- турнира, а team_size_min+reserve (размер ОДНОЙ команды, см. EventGameSettingsService
                    -- normalizeTournamentDefaults) — оставлен только как fallback.
                    COALESCE(NULLIF(egs.max_players, 0), NULLIF(ets.total_players_max, 0), 0)
                ELSE
                    COALESCE(NULLIF(egs.max_players, 0), 0) + COALESCE(egs.reserve_players_max, 0)
            END)";

        $occurrenceLoadSub = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->leftJoin('event_game_settings as egs', 'egs.event_id', '=', 'e.id')
            ->leftJoin('event_tournament_settings as ets', 'ets.event_id', '=', 'e.id')
            ->where('e.organizer_id', $orgId)
            ->where('eo.starts_at', '>=', now()->subMonths(3))
            ->whereRaw('(eo.is_cancelled IS NULL OR eo.is_cancelled = false)')
            ->select(
                'e.id as event_id',
                'e.title',
                DB::raw("{$registeredExpr} as registered"),
                DB::raw("{$capacityExpr} as capacity")
            );

        $occurrenceLoad = DB::query()->fromSub($occurrenceLoadSub, 't')
            ->select(
                'event_id',
                'title',
                DB::raw('COUNT(*) as occurrences_count'),
                DB::raw('SUM(registered) as total_registered'),
                DB::raw('AVG(CASE WHEN capacity > 0 THEN registered::float / capacity * 100 END) as avg_load_pct')
            )
            ->groupBy('event_id', 'title')
            ->orderByDesc('total_registered')
            ->limit(10)
            ->get();

        // --- ЭФФЕКТИВНОСТЬ БОТОВ ---
        // Живой COUNT вместо event_occurrence_stats (та же болезнь кеша, что и у occurrenceLoad
        // выше, — INNER JOIN к нему исключал 86.6% occurrences без строки в кеше, давая
        // смещённую survivorship-bias оценку). $registeredExpr уже посчитан выше для occurrenceLoad.
        $botEffect = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->where('e.organizer_id', $orgId)
            ->where('eo.starts_at', '>=', now()->subMonths(3))
            ->whereRaw('(eo.is_cancelled IS NULL OR eo.is_cancelled = false)')
            ->select(
                DB::raw("SUM(CASE WHEN EXISTS(
                    SELECT 1 FROM event_registrations er2
                    JOIN users u2 ON u2.id = er2.user_id
                    WHERE er2.occurrence_id = eo.id AND u2.is_bot = true AND er2.is_cancelled = false
                ) THEN {$registeredExpr} ELSE 0 END)::float / NULLIF(COUNT(*), 0) as avg_with_bots"),
                DB::raw("SUM(CASE WHEN NOT EXISTS(
                    SELECT 1 FROM event_registrations er2
                    JOIN users u2 ON u2.id = er2.user_id
                    WHERE er2.occurrence_id = eo.id AND u2.is_bot = true AND er2.is_cancelled = false
                ) THEN {$registeredExpr} ELSE 0 END)::float / NULLIF(COUNT(*), 0) as avg_without_bots"),
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

    /**
     * /my/events — упрощённый карточный список мероприятий организатора
     * (название, дата, место + быстрые ссылки на управление турниром/регистрациями).
     * Доступ: organizer/admin — тот же паттерн, что в EventRegistrationsOverviewController.
     */
    public function myEvents(Request $request)
    {
        $user = $request->user();

        $role = (string) ($user->role ?? 'user');
        if (!in_array($role, ['organizer', 'admin'], true)) {
            abort(403);
        }

        $filter = $request->input('filter', 'current');
        $today = \Illuminate\Support\Carbon::now('UTC');

        $q = DB::table('event_occurrences as eo')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->leftJoin('locations as l', 'l.id', '=', 'e.location_id')
            ->where('e.organizer_id', (int) $user->id)
            ->where(function ($w) {
                $w->whereNull('eo.is_cancelled')->orWhere('eo.is_cancelled', false);
            })
            ->select([
                'eo.id as occurrence_id',
                'eo.starts_at',
                'eo.timezone',
                'e.id as event_id',
                'e.title',
                'e.format',
                'e.is_recurring',
                'l.name as loc_name',
                'l.address as loc_address',
            ]);

        if ($filter === 'current') {
            $q->where('eo.starts_at', '>=', $today);
            $q->orderBy('eo.starts_at', 'asc');
        } else {
            $q->where('eo.starts_at', '<', $today);
            $q->orderBy('eo.starts_at', 'desc');
        }

        $occurrences = $q->paginate(25)->withQueryString();

        return view('dashboard.org_my_events', compact('occurrences', 'filter'));
    }
}
