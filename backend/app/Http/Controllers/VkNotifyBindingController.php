<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VkNotifyBindingController extends Controller
{
    public function generate(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $vkBotLink = trim((string) config('services.vk.bot_link', ''));
        if ($vkBotLink === '') {
            return response()->json([
                'ok' => false,
                'message' => 'VK_BOT_LINK не настроен.',
            ], 500);
        }

        DB::table('vk_notify_bindings')
            ->where('user_id', (int) $user->id)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(48);
        $command = 'notify_' . $token;

        DB::table('vk_notify_bindings')->insert([
            'user_id' => (int) $user->id,
            'token' => $token,
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
            'vk_user_id' => null,
            'raw_update' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'link' => $vkBotLink,
            'command' => $command,
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
                'vk_notify_user_id' => null,
                'vk_notify_linked_at' => null,
                'updated_at' => now(),
            ]);

        DB::table('vk_notify_bindings')
            ->where('user_id', (int) $user->id)
            ->whereNull('used_at')
            ->delete();

        return back()->with('status', 'Уведомления во VK отключены ✅');
    }
}