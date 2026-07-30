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

        // Админа помощником назначить нельзя (у него и так полный доступ),
        // организатора — можно: роль organizer не отменяет staff-назначение
        // на чужих мероприятиях (см. баг "Staff теряет доступ к чужим
        // мероприятиям, став организатором").
        if ($staffUser->isAdmin()) {
            return back()->with('error', 'Нельзя назначить администратора помощником.');
        }

        // Проверяем что у этого пользователя ещё нет назначения
        if (StaffAssignment::where('staff_user_id', $staffUser->id)->exists()) {
            return back()->with('error', 'Этот пользователь уже является помощником другого организатора.');
        }

        // Роль повышаем до staff только простому пользователю — у organizer
        // (и любой более сильной роли) она остаётся как есть, иначе
        // назначение в staff стирало бы его собственный организаторский статус.
        // ВАЖНО: 'role' нет в $fillable у User (осознанно, во избежание
        // самоповышения через массовое присвоение) — update(['role' => ...])
        // тут молча ничего не делает. Присваиваем напрямую, как в
        // AdminRoleController — единственном месте, где role меняется намеренно.
        if ($staffUser->role === 'user') {
            $staffUser->role = 'staff';
            $staffUser->save();
        }

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

        // Возвращаем роль user, только если она реально была повышена до staff
        // этим назначением (organizer/admin, ставшие staff чужого организатора,
        // сохраняют свою настоящую роль — снятие staff не должно её стирать).
        // Прямое присваивание — см. комментарий в store() про $fillable.
        if ($staffUser && $staffUser->role === 'staff') {
            $staffUser->role = 'user';
            $staffUser->save();
        }

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
