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
            ->where('target_user_id', $userId)
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
            ->where('target_user_id', $userId)
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

        return view('dashboard.player', compact(
            'totalVisits', 'totalCancellations', 'visitsThisMonth',
            'profileViews', 'profileViews30d',
            'totalVotes', 'avgLevel', 'topLevel', 'levelVotes',
            'likesCount', 'monthlyVisits', 'positions',
            'topLocations', 'topOrganizers', 'streak', 'percentile'
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
}
