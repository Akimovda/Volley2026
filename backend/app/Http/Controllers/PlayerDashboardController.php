<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;

        // --- ПОСЕЩЕНИЯ ---
        $totalVisits = DB::table('event_registrations')
            ->where('user_id', $userId)
            ->where('is_cancelled', false)
            ->count();

        $totalCancellations = DB::table('event_registrations')
            ->where('user_id', $userId)
            ->where('is_cancelled', true)
            ->count();

        $visitsThisMonth = DB::table('event_registrations')
            ->where('user_id', $userId)
            ->where('is_cancelled', false)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        // --- ПРОСМОТРЫ ПРОФИЛЯ ---
        $profileViews = DB::table('page_views')
            ->where('entity_type', 'user')
            ->where('entity_id', $userId)
            ->count();

        $profileViews30d = DB::table('page_views')
            ->where('entity_type', 'user')
            ->where('entity_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // --- ОЦЕНКИ УРОВНЯ ---
        $levelVotes = DB::table('user_level_votes')
            ->where('target_id', $userId)
            ->select(
                DB::raw('COUNT(*) as total_votes'),
                DB::raw('AVG(level) as avg_level'),
                'level',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('level')
            ->orderByDesc('cnt')
            ->get();

        $totalVotes   = $levelVotes->sum('cnt');
        $avgLevel     = $levelVotes->avg('level');
        $topLevel     = $levelVotes->sortByDesc('cnt')->first()?->level;

        // --- ЛАЙКИ ---
        $likesCount = DB::table('user_play_likes')
            ->where('target_id', $userId)
            ->count();

        // --- ДИНАМИКА ПО МЕСЯЦАМ ---
        $monthlyVisits = DB::table('event_registrations as er')
            ->join('event_occurrences as eo', 'eo.id', '=', 'er.occurrence_id')
            ->where('er.user_id', $userId)
            ->where('er.is_cancelled', false)
            ->where('er.created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw("TO_CHAR(er.created_at, 'YYYY-MM') as month"),
                DB::raw('COUNT(*) as visits')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // --- ПОЗИЦИИ ---
        $positions = DB::table('event_registrations')
            ->where('user_id', $userId)
            ->where('is_cancelled', false)
            ->whereNotNull('position')
            ->select('position', DB::raw('COUNT(*) as cnt'))
            ->groupBy('position')
            ->orderByDesc('cnt')
            ->get();

        // --- ЛЮБИМЫЕ ЛОКАЦИИ ---
        $topLocations = DB::table('event_registrations as er')
            ->join('event_occurrences as eo', 'eo.id', '=', 'er.occurrence_id')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('locations as l', 'l.id', '=', 'e.location_id')
            ->where('er.user_id', $userId)
            ->where('er.is_cancelled', false)
            ->select('l.id', 'l.name', DB::raw('COUNT(*) as visits'))
            ->groupBy('l.id', 'l.name')
            ->orderByDesc('visits')
            ->limit(5)
            ->get();

        // --- ЛЮБИМЫЕ ОРГАНИЗАТОРЫ ---
        $topOrganizers = DB::table('event_registrations as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->join('users as u', 'u.id', '=', 'e.organizer_id')
            ->where('er.user_id', $userId)
            ->where('er.is_cancelled', false)
            ->select(
                'u.id', 'u.first_name', 'u.last_name',
                DB::raw('COUNT(*) as visits')
            )
            ->groupBy('u.id', 'u.first_name', 'u.last_name')
            ->orderByDesc('visits')
            ->limit(5)
            ->get();

        // --- STREAK (недель подряд) ---
        $streak = $this->calculateStreak($userId);

        // --- РЕЙТИНГ АКТИВНОСТИ ---
        $totalUsersCount = DB::table('users')->where('is_bot', false)->count();
        $usersWithMoreVisits = DB::table('event_registrations')
            ->where('is_cancelled', false)
            ->where('user_id', '!=', $userId)
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > ?', [$totalVisits])
            ->get()->count();

        $percentile = $totalUsersCount > 0
            ? round((1 - $usersWithMoreVisits / $totalUsersCount) * 100)
            : 0;

        // --- PREMIUM ---
        $isPremium    = $user->isPremium();
        $activePremium = $user->activePremium();

        // Друзья
        $friends = $isPremium
            ? $user->friends()->select('users.id', 'users.first_name', 'users.last_name', 'users.profile_photo_path')->limit(6)->get()
            : collect();
        $friendsCount = $isPremium
            ? \App\Models\Friendship::where('user_id', $userId)->count()
            : 0;

        // Гости за 7 дней
        $recentVisitors = $isPremium
            ? $user->recentVisitors(7)
            : collect();

        // История игр с фильтром
        $historyFilter = $request->input('filter', []);
        $historyQuery  = DB::table('event_registrations as er')
            ->join('event_occurrences as eo', 'eo.id', '=', 'er.occurrence_id')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->leftJoin('locations as l', 'l.id', '=', 'e.location_id')
            ->leftJoin('users as org', 'org.id', '=', 'e.organizer_id')
            ->where('er.user_id', $userId)
            ->where('er.is_cancelled', false)
            ->select(
                'e.id as event_id', 'e.title',
                'eo.starts_at', 'er.position',
                'l.name as location_name', 'l.id as location_id',
                DB::raw("TRIM(CONCAT(org.first_name, ' ', org.last_name)) as organizer_name"),
                'org.id as organizer_id'
            )
            ->orderByDesc('eo.starts_at');

        if (!empty($historyFilter['position'])) {
            $historyQuery->where('er.position', $historyFilter['position']);
        }
        if (!empty($historyFilter['location_id'])) {
            $historyQuery->where('l.id', $historyFilter['location_id']);
        }
        if (!empty($historyFilter['organizer_id'])) {
            $historyQuery->where('org.id', $historyFilter['organizer_id']);
        }

        $gameHistory = $isPremium ? $historyQuery->paginate(10) : collect();

        return view('dashboard.player', compact(
            'totalVisits', 'totalCancellations', 'visitsThisMonth',
            'profileViews', 'profileViews30d',
            'totalVotes', 'avgLevel', 'topLevel', 'levelVotes',
            'likesCount', 'monthlyVisits', 'positions',
            'topLocations', 'topOrganizers', 'streak', 'percentile',
            'isPremium', 'activePremium', 'friends', 'friendsCount',
            'recentVisitors', 'gameHistory', 'historyFilter'
        ));
    }

    private function calculateStreak(int $userId): int
    {
        $weeks = DB::table('event_registrations as er')
            ->join('event_occurrences as eo', 'eo.id', '=', 'er.occurrence_id')
            ->where('er.user_id', $userId)
            ->where('er.is_cancelled', false)
            ->where('er.created_at', '>=', now()->subMonths(6))
            ->select(DB::raw("DATE_TRUNC('week', er.created_at) as week"))
            ->groupBy('week')
            ->orderByDesc('week')
            ->pluck('week')
            ->map(fn($w) => \Carbon\Carbon::parse($w)->startOfWeek())
            ->toArray();

        if (empty($weeks)) return 0;

        $streak = 1;
        for ($i = 0; $i < count($weeks) - 1; $i++) {
            if ($weeks[$i]->diffInWeeks($weeks[$i + 1]) === 1) {
                $streak++;
            } else {
                break;
            }
        }
        return $streak;
    }

    public function myEvents(Request $request)
    {
        $userId = $request->user()->id;
        $filter = $request->input('filter', 'current');
        $userTz = $request->user()->timezone ?? 'Europe/Moscow';

        $query = DB::table('event_registrations as er')
            ->join('event_occurrences as eo', 'eo.id', '=', 'er.occurrence_id')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->leftJoin('locations as l', 'l.id', '=', 'e.location_id')
            ->leftJoin('cities as ci', 'ci.id', '=', 'l.city_id')
            ->where('er.user_id', $userId)
            ->whereRaw('(er.is_cancelled IS NULL OR er.is_cancelled = false)')
            ->whereNull('eo.cancelled_at')
            ->select(
                'er.id as registration_id',
                'er.position',
                'er.created_at as registered_at',
                'e.id as event_id',
                'e.title',
                'e.format',
                'eo.id as occurrence_id',
                'eo.starts_at',
                'eo.cancel_self_until',
                'e.cancel_self_until as event_cancel_self_until',
                'l.name as location_name',
                'ci.name as city_name'
            );

        if ($filter === 'current') {
            $query->where('eo.starts_at', '>=', now())->orderBy('eo.starts_at', 'asc');
        } else {
            $query->where('eo.starts_at', '<', now())->orderBy('eo.starts_at', 'desc');
        }

        $registrations = $query->paginate(20)->withQueryString();

        return view('player.my-events', compact('registrations', 'filter', 'userTz'));
    }
}
