<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelegramNotifyWebhookController extends Controller
{
    protected function assertSecret(Request $request): void
    {
        $incoming = (string) $request->header('X-Bind-Secret', '');
        $expected = (string) config('services.bind.secret', env('BIND_WEBHOOK_SECRET', ''));

        abort_if($expected === '', 500, 'BIND_WEBHOOK_SECRET is not configured.');
        abort_if(!hash_equals($expected, $incoming), 403, 'Invalid secret.');
    }

    public function complete(Request $request)
    {
        $this->assertSecret($request);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'telegram_chat_id' => ['required', 'string'],
            'telegram_user_id' => ['required', 'string'],
            'raw_update' => ['nullable', 'array'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $binding = DB::table('telegram_notify_bindings')
                ->where('token', $data['token'])
                ->lockForUpdate()
                ->first();

            abort_if(!$binding, 404, 'Bind request not found.');
            abort_if(!is_null($binding->used_at), 422, 'Bind request already used.');
            abort_if(!empty($binding->expires_at) && strtotime((string) $binding->expires_at) <= now()->getTimestamp(), 422, 'Bind request expired.');

            $userId = (int) $binding->user_id;

            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'telegram_notify_chat_id' => $data['telegram_chat_id'],
                    'telegram_notify_linked_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('telegram_notify_bindings')
                ->where('id', $binding->id)
                ->update([
                    'telegram_chat_id' => $data['telegram_chat_id'],
                    'used_at' => now(),
                    'raw_update' => json_encode($data['raw_update'] ?? null, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            return [
                'user_id' => $userId,
                'telegram_chat_id' => $data['telegram_chat_id'],
            ];
        });

        return response()->json([
            'ok' => true,
            'message' => 'Telegram notify binding completed.',
            'user_id' => $result['user_id'],
            'telegram_chat_id' => $result['telegram_chat_id'],
        ]);
    }
}
