<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaxBindingController extends Controller
{
    public function generate(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $botId = (string) config('services.max.bot_id');
        if ($botId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'MAX_BOT_ID не настроен.',
            ], 500);
        }

        $kind = (string) $request->input('kind', 'channel');
        if (!in_array($kind, ['channel', 'personal'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Некорректный тип привязки.',
            ], 422);
        }

        DB::table('max_bindings')
            ->where('user_id', (int) $user->id)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(48);

        DB::table('max_bindings')->insert([
            'user_id' => (int) $user->id,
            'token' => $token,
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
            'max_chat_id' => null,
            'meta' => json_encode([
                'kind' => $kind,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'kind' => $kind,
            'link' => "https://max.ru/{$botId}?start={$token}",
            'expires_in' => 900,
        ]);
    }

    public function disconnect(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        DB::table('users')
            ->where('id', (int) $user->id)
            ->update([
                'max_chat_id' => null,
                'max_linked_at' => null,
                'max_notifications_enabled' => false,
                'updated_at' => now(),
            ]);

        DB::table('max_bindings')
            ->where('user_id', (int) $user->id)
            ->whereNull('used_at')
            ->delete();

        return back()->with('status', 'MAX отключён ✅');
    }
}