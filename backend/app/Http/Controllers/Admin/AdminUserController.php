<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $q      = trim((string) $request->get('q', ''));
        $role   = $request->get('role');   // admin|organizer|staff|user|null
        $status = $request->get('status'); // active|deleted|all|null

        $hasDeletedAt = Schema::hasColumn('users', 'deleted_at');

        $query = User::query();

        // status filter (soft deletes)
        if ($hasDeletedAt) {
            if ($status === 'deleted') {
                $query->onlyTrashed();
            } elseif ($status === 'all') {
                $query->withTrashed();
            } else {
                // default = active
                $query->withoutTrashed();
                $status = $status ?: 'active';
            }
        } else {
            // если deleted_at нет — статус не применяем
            $status = null;
        }

        // search
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('telegram_username', 'like', "%{$q}%")
                    ->orWhere('telegram_id', 'like', "%{$q}%")
                    ->orWhere('vk_id', 'like', "%{$q}%")
                    ->orWhere('vk_email', 'like', "%{$q}%");
            });
        }

        // role filter
        if (!empty($role)) {
            $query->where('role', $role);
        }

        $users = $query
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $roles = ['user', 'admin', 'organizer', 'staff'];

        return view('admin.users.index', compact('users', 'roles', 'q', 'role', 'status', 'hasDeletedAt'));
    }

    public function show(User $user)
    {
        // account_link_audits (если таблица есть)
        $linkAudits = [];
        if (DB::getSchemaBuilder()->hasTable('account_link_audits')) {
            $linkAudits = DB::table('account_link_audits')
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        // admin_audits по этому юзеру (если таблица есть)
        $adminAudits = [];
        if (DB::getSchemaBuilder()->hasTable('admin_audits')) {
            $adminAudits = DB::table('admin_audits')
                ->where('target_type', 'user')
                ->where('target_id', $user->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        $roles = ['user', 'admin', 'organizer', 'staff'];

        return view('admin.users.show', compact('user', 'roles', 'linkAudits', 'adminAudits'));
    }
}
