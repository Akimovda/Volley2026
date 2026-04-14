<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizerRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        $role = $user->role ?? 'user';
        if (in_array($role, ['admin', 'organizer'], true)) {
            return back()->with('status', 'У вас уже есть права организатора.');
        }

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $hasPending = DB::table('organizer_requests')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return back()->with('status', 'Заявка уже отправлена и ожидает рассмотрения.');
        }

        DB::table('organizer_requests')->insert([
            'user_id'    => $user->id,
            'status'     => 'pending',
            'message'    => $data['message'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Уведомляем администратора (ID=1 по умолчанию)
        try {
            $admin     = User::find(1);
            $adminUrl  = route('admin.organizer_requests.index');
            $userName  = trim("{$user->last_name} {$user->first_name}");
            $msgBody   = "👤 {$userName}\n"
                       . ($data['message'] ? "💬 {$data['message']}\n" : '')
                       . "🔗 {$adminUrl}";

            if ($admin) {
                app(UserNotificationService::class)->create(
                    userId:   $admin->id,
                    type:     'organizer_request',
                    title:    '📋 Новая заявка на организатора',
                    body:     $msgBody,
                    payload:  [
                        'button_text' => 'Просмотреть заявки',
                        'button_url'  => $adminUrl,
                    ],
                    channels: ['in_app', 'telegram', 'vk', 'max'],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('OrganizerRequestController notify failed: ' . $e->getMessage());
        }

        return back()->with('status', '✅ Заявка отправлена администратору.');
    }
}
