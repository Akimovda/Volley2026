<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\User;
use App\Support\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRoleController extends Controller
{
    public function updateUserRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(['user', 'admin', 'organizer', 'staff'])],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'role.required' => 'Выберите роль.',
            'role.in' => 'Недопустимая роль.',
        ]);

        $oldRole = (string) ($user->role ?? 'user');
        $newRole = (string) $data['role'];

        if ($oldRole === $newRole) {
            return back()->with('status', 'Роль не изменилась.');
        }

        $user->role = $newRole;
        $user->save();

        AdminAuditLogger::log(
            'user.role.update',
            'user',
            (string) $user->id,
            [
                'old_role' => $oldRole,
                'new_role' => $newRole,
            ],
            $data['note'] ?? null,
            $request
        );

        return back()->with('status', "Роль обновлена: {$oldRole} → {$newRole}");
    }

    public function updateClubManager(Request $request, User $user)
    {
        $data = $request->validate([
            'is_club_manager' => ['nullable', 'boolean'],
        ]);

        $old = (bool) $user->is_club_manager;
        $new = (bool) $request->boolean('is_club_manager');

        if ($old === $new) {
            return back()->with('status', 'Статус «Управляющий клубом» не изменился.');
        }

        $user->is_club_manager = $new;
        $user->save();

        AdminAuditLogger::log(
            'user.club_manager.update',
            'user',
            (string) $user->id,
            [
                'old_is_club_manager' => $old,
                'new_is_club_manager' => $new,
            ],
            null,
            $request
        );

        return back()->with('status', $new ? 'Пользователь назначен управляющим клубом.' : 'Статус управляющего клубом снят.');
    }

    public function updateUserBrand(Request $request, User $user)
    {
        $data = $request->validate([
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
        ]);

        $oldBrandId = $user->brand_id;
        $newBrandId = $data['brand_id'] !== '' && $data['brand_id'] !== null ? (int) $data['brand_id'] : null;

        if ($oldBrandId === $newBrandId) {
            return back()->with('status', 'Приложение не изменилось.');
        }

        $user->brand_id = $newBrandId;
        $user->save();

        AdminAuditLogger::log(
            'user.brand.update',
            'user',
            (string) $user->id,
            [
                'old_brand_id' => $oldBrandId,
                'new_brand_id' => $newBrandId,
            ],
            null,
            $request
        );

        $brandName = $newBrandId ? Brand::find($newBrandId)?->display_name : 'по умолчанию (Volley Club)';

        return back()->with('status', "Приложение обновлено: {$brandName}");
    }
}
