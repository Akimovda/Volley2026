<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
}
