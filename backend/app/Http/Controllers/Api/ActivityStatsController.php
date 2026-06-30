<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ActivityStatsController extends Controller
{
    public function weekly(Request $request): JsonResponse
    {
        $user = $request->user();
        $startOfWeek = Carbon::now()->startOfWeek();
        $today = Carbon::today();

        $cacheKey = "activity_stats_weekly_{$user->id}";

        $data = Cache::remember($cacheKey, 300, function () use ($user, $startOfWeek, $today) {
            $weeklyCalories = (int) ActivitySession::where('user_id', $user->id)
                ->where('status', 'completed')
                ->where('started_at', '>=', $startOfWeek)
                ->sum('calories_kcal');

            $todayCalories = (int) ActivitySession::where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereDate('started_at', $today)
                ->sum('calories_kcal');

            $todayJumps = (int) ActivitySession::where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereDate('started_at', $today)
                ->sum('jump_count');

            $todayMaxJumpHeight = (int) ActivitySession::where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereDate('started_at', $today)
                ->max('jump_max_height_cm');

            $todaySteps = (int) ActivitySession::where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereDate('started_at', $today)
                ->sum('steps');

            $streak = $this->calculateActivityStreak($user->id);

            return [
                'weekly_calories'          => $weeklyCalories,
                'today_calories'           => $todayCalories,
                'today_jumps'              => $todayJumps,
                'today_max_jump_height_cm' => $todayMaxJumpHeight,
                'today_steps'              => $todaySteps,
                'streak_days'              => $streak,
            ];
        });

        $data['updated_at'] = now()->toIso8601String();

        return response()->json($data);
    }

    private function calculateActivityStreak(int $userId): int
    {
        $dates = ActivitySession::where('user_id', $userId)
            ->where('status', 'completed')
            ->selectRaw('DATE(started_at) as session_date')
            ->distinct()
            ->orderByDesc('session_date')
            ->pluck('session_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        if (empty($dates)) {
            return 0;
        }

        $streak = 0;
        $cursor = Carbon::today();

        if (!in_array($cursor->toDateString(), $dates)) {
            $cursor = $cursor->subDay();
        }

        foreach ($dates as $date) {
            if ($date === $cursor->toDateString()) {
                $streak++;
                $cursor = $cursor->subDay();
            } elseif ($date < $cursor->toDateString()) {
                break;
            }
        }

        return $streak;
    }
}
