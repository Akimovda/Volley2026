<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MaxBindWebhookController extends Controller
{
    protected function assertSecret(Request $request): void
    {
        $incoming = (string) $request->header('X-Bind-Secret', '');
        $expected = (string) config('services.bind.secret', env('BIND_WEBHOOK_SECRET', ''));

        abort_if($expected === '', 500, 'BIND_WEBHOOK_SECRET is not configured.');
        abort_if(!hash_equals($expected, $incoming), 403, 'Invalid secret.');
    }

    public function bindInfo(Request $request)
    {
        $this->assertSecret($request);

        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $binding = DB::table('max_bindings')
            ->where('token', $data['token'])
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        abort_if(!$binding, 404, 'Bind request not found.');

        $meta = [];
        if (!empty($binding->meta)) {
            $decoded = json_decode((string) $binding->meta, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $kind = (string) ($meta['kind'] ?? 'channel');
        if (!in_array($kind, ['channel', 'personal'], true)) {
            $kind = 'channel';
        }

        return response()->json([
            'ok' => true,
            'token' => $binding->token,
            'kind' => $kind,
            'user_id' => (int) $binding->user_id,
            'expires_at' => $binding->expires_at,
        ]);
    }

    public function completePersonalBind(Request $request)
    {
        $this->assertSecret($request);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'max_chat_id' => ['required', 'string'],
            'raw_update' => ['nullable', 'array'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $binding = DB::table('max_bindings')
                ->where('token', $data['token'])
                ->lockForUpdate()
                ->first();

            abort_if(!$binding, 404, 'Bind request not found.');
            abort_if(!is_null($binding->used_at), 422, 'Bind request already used.');
            abort_if(strtotime((string) $binding->expires_at) <= now()->getTimestamp(), 422, 'Bind request expired.');

            $meta = [];
            if (!empty($binding->meta)) {
                $decoded = json_decode((string) $binding->meta, true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }

            $kind = (string) ($meta['kind'] ?? 'channel');
            abort_if($kind !== 'personal', 422, 'Bind request is not personal.');

            $userId = (int) $binding->user_id;

            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'max_chat_id' => $data['max_chat_id'],
                    'max_linked_at' => now(),
                    'max_notifications_enabled' => true,
                    'updated_at' => now(),
                ]);

            $newMeta = array_merge($meta, [
                'kind' => 'personal',
                'completed_via' => 'max_bot',
                'raw_update' => $data['raw_update'] ?? null,
            ]);

            DB::table('max_bindings')
                ->where('id', $binding->id)
                ->update([
                    'used_at' => now(),
                    'max_chat_id' => $data['max_chat_id'],
                    'meta' => json_encode($newMeta, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            return [
                'user_id' => $userId,
                'max_chat_id' => $data['max_chat_id'],
            ];
        });

        return response()->json([
            'ok' => true,
            'message' => 'Personal MAX binding completed.',
            'user_id' => $result['user_id'],
            'max_chat_id' => $result['max_chat_id'],
        ]);
    }
}
