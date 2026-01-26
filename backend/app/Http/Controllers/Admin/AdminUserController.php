<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        // -----------------------------
        // Filters
        // -----------------------------
        $q = trim((string) $request->get('q', ''));
        $role = $request->get('role'); // admin|organizer|staff|user|null

        // Только events-блокировки:
        // all | restricted | not_restricted
        $restricted = (string) $request->get('restricted', 'all');

        // Опции для select в blade (раньше отсутствовали => ошибка)
        $restrictedOptions = [
            'all'            => 'Все',
            'restricted'     => 'Только с блокировками (events)',
            'not_restricted' => 'Только без блокировок',
        ];

        // -----------------------------
        // Base query
        // -----------------------------
        $query = User::query();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('telegram_username', 'like', "%{$q}%")
                    ->orWhere('telegram_id', 'like', "%{$q}%")
                    ->orWhere('vk_id', 'like', "%{$q}%")
                    ->orWhere('vk_email', 'like', "%{$q}%")
                    ->orWhere('yandex_id', 'like', "%{$q}%")
                    ->orWhere('yandex_email', 'like', "%{$q}%");
            });
        }

        if (!empty($role)) {
            $query->where('role', $role);
        }

        // -----------------------------
        // Restriction filter (active events restrictions)
        // active = ends_at is null OR ends_at > now()
        // scope = 'events'
        // -----------------------------
        if ($restricted === 'restricted' || $restricted === 'not_restricted') {
            $now = now();

            $restrictedUserIdsSubquery = DB::table('user_restrictions')
                ->select('user_id')
                ->where('scope', 'events')
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')
                      ->orWhere('ends_at', '>', $now);
                });

            if ($restricted === 'restricted') {
                $query->whereIn('id', $restrictedUserIdsSubquery);
            } else { // not_restricted
                $query->whereNotIn('id', $restrictedUserIdsSubquery);
            }
        }

        $users = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $roles = ['user', 'admin', 'organizer', 'staff'];

        return view('admin.users.index', compact(
            'users',
            'roles',
            'q',
            'role',
            'restricted',
            'restrictedOptions'
        ));
    }

    public function show(User $user)
    {
        $linkAudits = [];
        if (DB::getSchemaBuilder()->hasTable('account_link_audits')) {
            $linkAudits = DB::table('account_link_audits')
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        $adminAudits = [];
        if (DB::getSchemaBuilder()->hasTable('admin_audits')) {
            $adminAudits = DB::table('admin_audits')
                ->where('target_type', 'user')
                ->where('target_id', $user->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        // Active restrictions for right-block (если используете в show.blade.php)
        $restrictions = [];
        if (DB::getSchemaBuilder()->hasTable('user_restrictions')) {
            $now = now();
            $restrictions = DB::table('user_restrictions')
                ->where('user_id', $user->id)
                ->where('scope', 'events')
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                })
                ->orderByDesc('id')
                ->get();
        }

        $roles = ['user', 'admin', 'organizer', 'staff'];

        return view('admin.users.show', compact('user', 'roles', 'linkAudits', 'adminAudits', 'restrictions'));
    }

    /**
     * Полное удаление пользователя (purge).
     * Требуем confirm=yes
     */
    public function purge(Request $request, User $user)
    {
        if ((int) auth()->id() === (int) $user->id) {
            return back()->withErrors(['user' => 'Нельзя удалить самого себя.']);
        }

        $data = $request->validate([
            'confirm' => ['required', 'in:yes'],
            'note'    => ['nullable', 'string', 'max:500'],
        ], [
            'confirm.in' => 'Подтверждение удаления не пройдено.',
        ]);

        $userId = (int) $user->id;
        $email  = (string) ($user->email ?? '');

        DB::transaction(function () use ($request, $user, $userId, $email, $data) {

            // 1) Удаляем файл профиля (если есть)
            $path = $user->profile_photo_path ?? null;
            if (!empty($path)) {
                $disk = config('jetstream.profile_photo_disk', 'public');
                try {
                    Storage::disk($disk)->delete($path);
                } catch (\Throwable $e) {
                    // не роняем purge из-за файла
                }
            }

            // 2) Удаляем пользователя
            if (method_exists($user, 'forceDelete')) {
                $user->forceDelete();
            } else {
                $user->delete();
            }

            // 3) Аудит
            AdminAuditLogger::log(
                action: 'user.delete.purge',
                targetType: 'user',
                targetId: (string) $userId,
                meta: [
                    'email'   => $email,
                    'confirm' => 'yes',
                ],
                note: !empty($data['note']) ? (string) $data['note'] : 'Purge from admin',
                request: $request,
            );
        });

        return redirect()->route('admin.users.index')
            ->with('status', "Пользователь #{$userId} удалён полностью (purge).");
    }
}
