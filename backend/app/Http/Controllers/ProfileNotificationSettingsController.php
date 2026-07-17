<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Общие пользовательские настройки уведомлений (доступно ЛЮБОМУ авторизованному
 * пользователю, в отличие от ProfileNotificationChannelController — тот organizer-only).
 * Список настроек растёт со временем — новую добавлять только в SETTINGS.
 */
class ProfileNotificationSettingsController extends Controller
{
    private const SETTINGS = [
        'notify_new_events_in_city',
    ];

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['ok' => false], 401);
        }

        $data = $request->validate([
            'setting' => ['required', 'string', 'in:' . implode(',', self::SETTINGS)],
            'value'   => ['required', 'boolean'],
        ]);

        $user->update([
            $data['setting'] => (bool) $data['value'],
        ]);

        return response()->json(['ok' => true, 'setting' => $data['setting'], 'value' => (bool) $data['value']]);
    }
}
