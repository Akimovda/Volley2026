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
        $q    = trim((string) $request->get('q', ''));
        $role = $request->get('role'); // admin|organizer|staff|user|null

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
                    ->orWhere('vk_email', 'like', "%{$q}%");
            });
        }

        if (!empty($role)) {
            $query->where('role', $role);
        }

        $users = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $roles = ['user', 'admin', 'organizer', 'staff'];

        return view('admin.users.index', compact('users', 'roles', 'q', 'role'));
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

        $roles = ['user', 'admin', 'organizer', 'staff'];

        return view('admin.users.show', compact('user', 'roles', 'linkAudits', 'adminAudits'));
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

            // 2) Удаляем пользователя (FK каскад/нулл сделает остальное)
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
