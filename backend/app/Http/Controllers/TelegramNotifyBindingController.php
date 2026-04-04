<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TelegramNotifyBindingController extends Controller
{
    public function generate(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $botUsername = (string) config('services.telegram.bot_username', '');
        if ($botUsername === '') {
            return response()->json([
                'ok' => false,
                'message' => 'TELEGRAM_BOT_USERNAME не настроен.',
            ], 500);
        }

        DB::table('telegram_notify_bindings')
            ->where('user_id', (int) $user->id)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(48);

        DB::table('telegram_notify_bindings')->insert([
            'user_id' => (int) $user->id,
            'token' => $token,
            'expires_at' => now()->addMinutes(15),
            'telegram_chat_id' => null,
            'used_at' => null,
            'raw_update' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'link' => 'https://t.me/' . ltrim($botUsername, '@') . '?start=notify_' . $token,
            'expires_in' => 900,
        ]);
    }

    public function disconnect(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        DB::table('users')
            ->where('id', (int) $user->id)
            ->update([
                'telegram_notify_chat_id' => null,
                'telegram_notify_linked_at' => null,
                'updated_at' => now(),
            ]);

        DB::table('telegram_notify_bindings')
            ->where('user_id', (int) $user->id)
            ->whereNull('used_at')
            ->delete();

        return back()->with('status', 'Уведомления в Telegram отключены ✅');
    }
}