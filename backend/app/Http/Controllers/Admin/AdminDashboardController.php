<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
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
                SUM(CASE WHEN telegram_id IS NOT NULL AND vk_id IS NULL AND yandex_id IS NULL AND apple_id IS NULL THEN 1 ELSE 0 END) as tg_only,
                SUM(CASE WHEN telegram_id IS NULL AND vk_id IS NOT NULL AND yandex_id IS NULL AND apple_id IS NULL THEN 1 ELSE 0 END) as vk_only,
                SUM(CASE WHEN telegram_id IS NULL AND vk_id IS NULL AND yandex_id IS NOT NULL AND apple_id IS NULL THEN 1 ELSE 0 END) as ya_only,
                SUM(CASE WHEN telegram_id IS NULL AND vk_id IS NULL AND yandex_id IS NULL AND apple_id IS NOT NULL THEN 1 ELSE 0 END) as apple_only,
                SUM(CASE WHEN telegram_id IS NOT NULL AND vk_id IS NOT NULL AND yandex_id IS NULL AND apple_id IS NULL THEN 1 ELSE 0 END) as tg_vk,
                SUM(CASE WHEN telegram_id IS NOT NULL AND vk_id IS NULL AND yandex_id IS NOT NULL AND apple_id IS NULL THEN 1 ELSE 0 END) as tg_ya,
                SUM(CASE WHEN telegram_id IS NULL AND vk_id IS NOT NULL AND yandex_id IS NOT NULL AND apple_id IS NULL THEN 1 ELSE 0 END) as ya_vk,
                SUM(CASE WHEN telegram_id IS NOT NULL AND vk_id IS NOT NULL AND yandex_id IS NOT NULL THEN 1 ELSE 0 END) as ya_vk_tg,
                SUM(CASE WHEN apple_id IS NOT NULL THEN 1 ELSE 0 END) as apple_any,
                SUM(CASE WHEN google_id IS NOT NULL THEN 1 ELSE 0 END) as google_any
            ")
            ->first();

        $providers = [
            'tg_only'    => (int) ($row->tg_only ?? 0),
            'vk_only'    => (int) ($row->vk_only ?? 0),
            'ya_only'    => (int) ($row->ya_only ?? 0),
            'apple_only' => (int) ($row->apple_only ?? 0),
            'tg_vk'      => (int) ($row->tg_vk ?? 0),
            'tg_ya'      => (int) ($row->tg_ya ?? 0),
            'ya_vk'      => (int) ($row->ya_vk ?? 0),
            'ya_vk_tg'   => (int) ($row->ya_vk_tg ?? 0),
            'apple_any'  => (int) ($row->apple_any ?? 0),
            'google_any' => (int) ($row->google_any ?? 0),
        ];

        // -----------------------------
        // Restrictions widget (events) (новое)
        // Event All = restrictions scope=events, active, event_ids пустой/NULL (глобальный бан)
        // activeRestrictionsTotal = все активные ограничения (глобальные + точечные по event_id), для карточки на дашборде
        // -----------------------------
        $eventAllRestrictions = 0;
        $activeRestrictionsTotal = 0;
        $restrictionByEvent = [];

        if (DB::getSchemaBuilder()->hasTable('user_restrictions')) {
            $activeRestrictionsBase = fn () => DB::table('user_restrictions')
                ->where('scope', 'events')
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                });

            $activeRestrictionsTotal = (int) $activeRestrictionsBase()->count();

            $eventAllRestrictions = (int) $activeRestrictionsBase()
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
        $dupCount = count(app(\App\Services\UserMergeService::class)->findDuplicates());
        $deletionDelay = (int) getAppSetting('account_deletion_delay_seconds', 30);

        // -----------------------------
        // Подписки и оплаты: Premium (игроки) + PRO (организаторы)
        // -----------------------------
        $activePremiumCount = (int) DB::table('premium_subscriptions')
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->count();

        $activeProCount = (int) DB::table('organizer_subscriptions')
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->count();

        // premium_subscriptions.payment_id / organizer_subscriptions.payment_id — historically varchar
        // (задумано под внешний ID платежа), а payments.id — bigint; Postgres не кастует bigint=varchar
        // автоматически в JOIN (в отличие от bind-параметров Eloquent) — везде ниже явный ::bigint.
        $premiumRevenueMonthMinor = (int) DB::table('premium_subscriptions as ps')
            ->join('payments as p', 'p.id', '=', DB::raw('ps.payment_id::bigint'))
            ->where('p.status', 'paid')
            ->whereYear('p.org_confirmed_at', now()->year)
            ->whereMonth('p.org_confirmed_at', now()->month)
            ->sum('p.amount_minor');

        $proRevenueMonthMinor = (int) DB::table('organizer_subscriptions as os')
            ->join('payments as p', 'p.id', '=', DB::raw('os.payment_id::bigint'))
            ->where('p.status', 'paid')
            ->whereYear('p.org_confirmed_at', now()->year)
            ->whereMonth('p.org_confirmed_at', now()->month)
            ->sum('p.amount_minor');

        $subsRevenueMonthRub = ($premiumRevenueMonthMinor + $proRevenueMonthMinor) / 100;

        $premiumRows = DB::table('premium_subscriptions as ps')
            ->join('users as u', 'u.id', '=', 'ps.user_id')
            ->join('payments as p', 'p.id', '=', DB::raw('ps.payment_id::bigint'))
            ->select(
                'u.id as user_id', 'u.first_name', 'u.last_name',
                DB::raw("'premium' as kind"),
                'ps.plan', 'p.status as payment_status', 'p.amount_minor', 'p.created_at as payment_created_at',
                'p.id as sort_id'
            )
            ->orderByDesc('p.id')
            ->limit(30)
            ->get();

        $proRows = DB::table('organizer_subscriptions as os')
            ->join('users as u', 'u.id', '=', 'os.user_id')
            ->join('payments as p', 'p.id', '=', DB::raw('os.payment_id::bigint'))
            ->select(
                'u.id as user_id', 'u.first_name', 'u.last_name',
                DB::raw("'pro' as kind"),
                'os.plan', 'p.status as payment_status', 'p.amount_minor', 'p.created_at as payment_created_at',
                'p.id as sort_id'
            )
            ->orderByDesc('p.id')
            ->limit(30)
            ->get();

        $recentPayments = $premiumRows->concat($proRows)
            ->sortByDesc('payment_created_at')
            ->take(15)
            ->values();

        $pendingPremiumPayments = DB::table('premium_subscriptions as ps')
            ->join('users as u', 'u.id', '=', 'ps.user_id')
            ->join('payments as p', 'p.id', '=', DB::raw('ps.payment_id::bigint'))
            ->where('ps.status', 'pending')
            ->where('p.status', 'pending')
            ->where('p.user_confirmed', true)
            ->select(
                'ps.id as sub_id', 'ps.plan', 'u.id as user_id', 'u.first_name', 'u.last_name',
                'p.id as payment_id', 'p.amount_minor', 'p.created_at as payment_created_at'
            )
            ->orderBy('p.user_confirmed_at')
            ->get()
            ->map(function ($row) {
                $row->age_days = (int) now()->diffInDays(\Carbon\Carbon::parse($row->payment_created_at));
                return $row;
            });

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
            'activeRestrictionsTotal',
            'restrictionByEvent',
            'eventsCount',
            'dupCount',
            'deletionDelay',
            'activePremiumCount',
            'activeProCount',
            'subsRevenueMonthRub',
            'recentPayments',
            'pendingPremiumPayments',
        ));
    }

    public function updateDeletionDelay(Request $request)
    {
        $request->validate(['value' => 'required|integer|min:5|max:3600']);

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'account_deletion_delay_seconds'],
            ['value' => (string) $request->integer('value'), 'updated_at' => now()]
        );

        return response()->json(['success' => true]);
    }
}
