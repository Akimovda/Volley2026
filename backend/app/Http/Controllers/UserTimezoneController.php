<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserTimezoneController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
        }

        $tz = trim((string)$request->input('timezone', ''));
        if ($tz === '' || mb_strlen($tz) > 64) {
            return response()->json(['ok' => false, 'message' => 'Invalid timezone'], 422);
        }

        // Простая валидация через PHP: проверим что timezone существует
        // (иначе можно сломать форматирование Carbon)
        try {
            new \DateTimeZone($tz);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Unknown timezone'], 422);
        }

        // Обновляем только если реально изменилось
        if ((string)($user->timezone ?? '') !== $tz) {
            $user->timezone = $tz;
            $user->save();
        }

        return response()->json(['ok' => true, 'timezone' => $tz]);
    }
}
