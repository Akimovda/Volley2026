<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->startOfDay();
        $d7    = now()->subDays(7)->startOfDay();
        $d30   = now()->subDays(30)->startOfDay();

        $hasDeletedAt = Schema::hasColumn('users', 'deleted_at');

        // totals
        $totalUsers = User::query()->count();

        // registrations
        $registeredToday = User::query()->where('created_at', '>=', $today)->count();
        $registered7     = User::query()->where('created_at', '>=', $d7)->count();
        $registered30    = User::query()->where('created_at', '>=', $d30)->count();

        // deletions (SoftDeletes)
        $deletedToday = $hasDeletedAt
            ? User::query()->whereNotNull('deleted_at')->where('deleted_at', '>=', $today)->count()
            : null;

        $deleted7 = $hasDeletedAt
            ? User::query()->whereNotNull('deleted_at')->where('deleted_at', '>=', $d7)->count()
            : null;

        // activity (proxy metric: updated_at in last 7/30 days)
        $active7  = User::query()->where('updated_at', '>=', $d7)->count();
        $active30 = User::query()->where('updated_at', '>=', $d30)->count();

        // providers split (rough by presence of IDs)
        $tgOnly = User::query()->whereNotNull('telegram_id')->whereNull('vk_id')->count();
        $vkOnly = User::query()->whereNotNull('vk_id')->whereNull('telegram_id')->count();
        $both   = User::query()->whereNotNull('telegram_id')->whereNotNull('vk_id')->count();
        $none   = User::query()->whereNull('telegram_id')->whereNull('vk_id')->count();

        // link audits (если таблица есть)
        $hasLinkAudits = DB::getSchemaBuilder()->hasTable('account_link_audits');
        $linkCount7 = $hasLinkAudits
            ? DB::table('account_link_audits')->where('created_at', '>=', $d7)->count()
            : null;

        // admin audits (если таблица есть)
        $hasAdminAudits = DB::getSchemaBuilder()->hasTable('admin_audits');
        $adminActions7 = $hasAdminAudits
            ? DB::table('admin_audits')->where('created_at', '>=', $d7)->count()
            : null;

        return view('admin.dashboard', compact(
            'totalUsers',
            'registeredToday', 'registered7', 'registered30',
            'deletedToday', 'deleted7',
            'active7', 'active30',
            'tgOnly', 'vkOnly', 'both', 'none',
            'linkCount7',
            'adminActions7',
            'hasDeletedAt',
            'hasLinkAudits',
            'hasAdminAudits',
        ));
    }
}
