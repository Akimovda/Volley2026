<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizerRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        // Если уже organizer/admin — заявка не нужна
        $role = $user->role ?? 'user';
        if (in_array($role, ['admin', 'organizer'], true)) {
            return back()->with('status', 'У вас уже есть права организатора.');
        }

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        // Не даём создать второй pending (у тебя есть unique(user_id,status), но это даёт UX)
        $hasPending = DB::table('organizer_requests')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return back()->with('status', 'Заявка уже отправлена и ожидает рассмотрения.');
        }

        DB::table('organizer_requests')->insert([
            'user_id' => $user->id,
            'status' => 'pending',
            'message' => $data['message'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Заявка отправлена администратору.');
    }
}
