<?php
namespace App\Http\Controllers;

use App\Models\StaffAssignment;
use App\Models\StaffLog;
use App\Models\User;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    // Список staff организатора
    public function index(Request $request)
    {
        $user = $request->user();
        $staffMembers = StaffAssignment::where('organizer_id', $user->id)
            ->with('staff:id,first_name,last_name,email,role')
            ->orderByDesc('created_at')
            ->get();

        return view('staff.index', compact('staffMembers'));
    }

    // Назначить staff
    public function store(Request $request)
    {
        $currentUser = $request->user();
        $data = $request->validate([
            'staff_user_id'      => ['required', 'integer', 'exists:users,id'],
            'organizer_id_override' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $staffUser = User::findOrFail($data['staff_user_id']);

        // Определяем организатора
        if ($currentUser->isAdmin() && !empty($data['organizer_id_override'])) {
            $organizerId = (int) $data['organizer_id_override'];
            $organizer   = User::findOrFail($organizerId);
            if (!$organizer->isOrganizer() && !$organizer->isAdmin()) {
                return back()->with('error', 'Указанный пользователь не является организатором.');
            }
        } else {
            $organizerId = $currentUser->id;
        }

        // Проверяем что пользователь не является организатором/админом
        if ($staffUser->isAdmin() || $staffUser->isOrganizer()) {
            return back()->with('error', 'Нельзя назначить организатора или администратора помощником.');
        }

        // Проверяем что у этого пользователя ещё нет назначения
        if (StaffAssignment::where('staff_user_id', $staffUser->id)->exists()) {
            return back()->with('error', 'Этот пользователь уже является помощником другого организатора.');
        }

        // Назначаем роль staff
        $staffUser->update(['role' => 'staff']);

        StaffAssignment::create([
            'staff_user_id' => $staffUser->id,
            'organizer_id'  => $organizerId,
        ]);

        return back()->with('status', "✅ {$staffUser->first_name} {$staffUser->last_name} назначен помощником.");
    }

    // Снять staff
    public function destroy(Request $request, StaffAssignment $assignment)
    {
        $user = $request->user();

        if (!$user->isAdmin() && $assignment->organizer_id !== $user->id) {
            abort(403);
        }

        $staffUser = $assignment->staff;
        $assignment->delete();

        // Возвращаем роль user
        $staffUser?->update(['role' => 'user']);

        return back()->with('status', '✅ Помощник снят с должности.');
    }

    // Логи staff для организатора
    public function logs(Request $request)
    {
        $user = $request->user();

        $organizerId = $user->isAdmin()
            ? $request->query('organizer_id', $user->id)
            : $user->id;

        $logs = StaffLog::where('organizer_id', $organizerId)
            ->with('staff:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('staff.logs', compact('logs'));
    }
}
