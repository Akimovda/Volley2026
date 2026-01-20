<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // -----------------------------
        // Users counters (как у тебя было)
        // -----------------------------
        $totalUsers = (int) DB::table('users')->count();
        $activeUsers = (int) DB::table('users')->whereNull('deleted_at')->count();
        $deletedUsers = (int) DB::table('users')->whereNotNull('deleted_at')->count();

        $usersCreatedToday = (int) DB::table('users')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $usersDeletedToday = (int) DB::table('users')
            ->whereNotNull('deleted_at')
            ->whereDate('deleted_at', now()->toDateString())
            ->count();

        // -----------------------------
        // Roles widget (как у тебя было)
        // -----------------------------
        $roles = DB::table('users')
            ->selectRaw("COALESCE(role,'null') as role, COUNT(*) as c")
            ->whereNull('deleted_at')
            ->groupBy('role')
            ->orderByDesc('c')
            ->get();

        // -----------------------------
        // Organizer requests (как у тебя было)
        // -----------------------------
        $organizerRequests = [];
        if (DB::getSchemaBuilder()->hasTable('organizer_requests')) {
            $organizerRequests = DB::table('organizer_requests')
                ->selectRaw("COALESCE(status,'null') as status, COUNT(*) as c")
                ->groupBy('status')
                ->orderByDesc('c')
                ->get();
        }

        // -----------------------------
        // Providers stats (новое)
        // -----------------------------
        $row = DB::table('users')
            ->whereNull('deleted_at')
            ->selectRaw("
                SUM(CASE WHEN telegram_id IS NOT NULL AND vk_id IS NULL AND yandex_id IS NULL THEN 1 ELSE 0 END) as tg_only,
                SUM(CASE WHEN telegram_id IS NULL AND vk_id IS NOT NULL AND yandex_id IS NULL THEN 1 ELSE 0 END) as vk_only,
                SUM(CASE WHEN telegram_id IS NULL AND vk_id IS NULL AND yandex_id IS NOT NULL THEN 1 ELSE 0 END) as ya_only,
                SUM(CASE WHEN telegram_id IS NOT NULL AND vk_id IS NOT NULL AND yandex_id IS NULL THEN 1 ELSE 0 END) as tg_vk,
                SUM(CASE WHEN telegram_id IS NOT NULL AND vk_id IS NULL AND yandex_id IS NOT NULL THEN 1 ELSE 0 END) as tg_ya,
                SUM(CASE WHEN telegram_id IS NULL AND vk_id IS NOT NULL AND yandex_id IS NOT NULL THEN 1 ELSE 0 END) as ya_vk,
                SUM(CASE WHEN telegram_id IS NOT NULL AND vk_id IS NOT NULL AND yandex_id IS NOT NULL THEN 1 ELSE 0 END) as ya_vk_tg
            ")
            ->first();

        $providers = [
            'tg_only'  => (int) ($row->tg_only ?? 0),
            'vk_only'  => (int) ($row->vk_only ?? 0),
            'ya_only'  => (int) ($row->ya_only ?? 0),
            'tg_vk'    => (int) ($row->tg_vk ?? 0),
            'tg_ya'    => (int) ($row->tg_ya ?? 0),
            'ya_vk'    => (int) ($row->ya_vk ?? 0),
            'ya_vk_tg' => (int) ($row->ya_vk_tg ?? 0),
        ];

        // -----------------------------
        // Restrictions widget (events) (новое)
        // Event All = restrictions scope=events, active, event_ids пустой/NULL
        // -----------------------------
        $eventAllRestrictions = 0;
        $restrictionByEvent = [];

        if (DB::getSchemaBuilder()->hasTable('user_restrictions')) {
            $eventAllRestrictions = (int) DB::table('user_restrictions')
                ->where('scope', 'events')
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->where(function ($q) {
                    $q->whereNull('event_ids')->orWhereRaw("event_ids::text = '[]'");
                })
                ->count();

            // Postgres: jsonb_array_elements_text
            $eventById = DB::select("
                SELECT
                    (eid)::int AS event_id,
                    COUNT(DISTINCT user_id) AS cnt
                FROM user_restrictions ur
                CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(ur.event_ids::jsonb, '[]'::jsonb)) AS eid
                WHERE ur.scope = 'events'
                  AND (ur.ends_at IS NULL OR ur.ends_at > NOW())
                GROUP BY (eid)::int
                ORDER BY (eid)::int ASC
            ");

            foreach ($eventById as $r) {
                $restrictionByEvent[(int) $r->event_id] = (int) $r->cnt;
            }
        }

        // -----------------------------
        // Events widget (новое)
        // -----------------------------
        $eventsCount = (int) Event::query()->count();

        return view('admin.dashboard.index', compact(
            'totalUsers',
            'activeUsers',
            'deletedUsers',
            'usersCreatedToday',
            'usersDeletedToday',
            'roles',
            'organizerRequests',
            'providers',
            'eventAllRestrictions',
            'restrictionByEvent',
            'eventsCount',
        ));
    }
}
