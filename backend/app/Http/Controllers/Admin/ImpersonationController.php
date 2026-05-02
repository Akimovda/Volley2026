<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ImpersonationController extends Controller
{
    public function index()
    {
        return view('admin.impersonate.index');
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q);
        $like = '%' . $escaped . '%';

        $rows = DB::table('users')
            ->select(['id', 'first_name', 'last_name', 'name', 'email', 'role', 'telegram_username'])
            ->where(function ($w) use ($like, $q) {
                if (ctype_digit($q) && (int) $q > 0) {
                    $w->orWhere('id', (int) $q);
                }
                $w->orWhere('first_name', 'ILIKE', $like)
                  ->orWhere('last_name', 'ILIKE', $like)
                  ->orWhereRaw("(coalesce(first_name,'') || ' ' || coalesce(last_name,'')) ILIKE ?", [$like])
                  ->orWhereRaw("(coalesce(last_name,'') || ' ' || coalesce(first_name,'')) ILIKE ?", [$like])
                  ->orWhere('name', 'ILIKE', $like)
                  ->orWhere('email', 'ILIKE', $like);
            })
            ->orderBy('last_name')->orderBy('first_name')->orderBy('name')
            ->limit(15)
            ->get()
            ->map(function ($u) {
                $fn = trim((string) ($u->first_name ?? ''));
                $ln = trim((string) ($u->last_name ?? ''));
                $label = trim($ln . ' ' . $fn);
                if ($label === '') $label = trim((string) ($u->name ?? ''));
                if ($label === '') $label = '#' . $u->id;

                return [
                    'id'    => (int) $u->id,
                    'label' => $label,
                    'email' => (string) ($u->email ?? ''),
                    'role'  => (string) ($u->role ?? 'user'),
                ];
            })
            ->values()->all();

        return response()->json(['items' => $rows]);
    }

    public function start(Request $request, User $user)
    {
        $admin = $request->user();

        if ($user->id === $admin->id) {
            return back()->with('error', 'Нельзя войти от имени самого себя.');
        }

        if ((string) ($user->role ?? '') === 'admin') {
            return back()->with('error', 'Нельзя войти от имени другого администратора.');
        }

        AdminAuditLogger::log(
            'impersonate.start',
            'user',
            $user->id,
            ['impersonated_user_id' => $user->id, 'impersonated_name' => $user->name],
            null,
            $request
        );

        $request->session()->put('impersonator_id', $admin->id);

        Auth::loginUsingId($user->id);
        $request->session()->regenerate();

        return redirect('/events')->with('status', "Вы вошли от имени: {$user->name}");
    }

    public function leave(Request $request)
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if (!$impersonatorId) {
            return redirect('/events');
        }

        $impersonatedId = Auth::id();

        // Логируем от имени реального админа, так как сессия уже будет переключена
        DB::table('admin_audits')->insert([
            'actor_user_id' => $impersonatorId,
            'admin_user_id' => $impersonatorId,
            'action'        => 'impersonate.leave',
            'target_type'   => 'user',
            'target_id'     => (string) $impersonatedId,
            'ip'            => $request->ip(),
            'user_agent'    => substr((string) $request->userAgent(), 0, 500),
            'meta'          => json_encode(['impersonated_user_id' => $impersonatedId], JSON_UNESCAPED_UNICODE),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $request->session()->forget('impersonator_id');

        Auth::loginUsingId($impersonatorId);
        $request->session()->regenerate();

        return redirect('/admin/impersonate')->with('status', 'Вы вернулись в свой аккаунт.');
    }
}
