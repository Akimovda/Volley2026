<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelBindRequest;
use App\Models\UserNotificationChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChannelBindWebhookController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $expectedSecret = (string) config('services.bind.secret', '');
        $incomingSecret = (string) $request->header('X-Bind-Secret', '');

        abort_unless(
            $expectedSecret !== '' && hash_equals($expectedSecret, $incomingSecret),
            403,
            'Invalid bind secret'
        );

        $data = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'platform' => ['required', 'string', 'in:telegram,vk,max'],
            'chat_id' => ['required', 'string', 'max:191'],
            'title' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ]);

        $result = DB::transaction(function () use ($data) {
            /** @var ChannelBindRequest|null $bind */
            $bind = ChannelBindRequest::query()
                ->where('token', $data['token'])
                ->where('platform', $data['platform'])
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            abort_if(!$bind, 404, 'Bind request not found');

            if ($bind->isExpired()) {
                $bind->update(['status' => 'expired']);
                abort(410, 'Bind token expired');
            }

            $channel = UserNotificationChannel::query()->updateOrCreate(
                [
                    'user_id' => (int) $bind->user_id,
                    'platform' => $data['platform'],
                    'chat_id' => $data['chat_id'],
                ],
                [
                    'title' => $data['title'] ?: strtoupper($data['platform']) . ' channel',
                    'is_verified' => true,
                    'verified_at' => Carbon::now(),
                    'meta' => $data['meta'] ?? [],
                ]
            );

            $bind->update([
                'status' => 'completed',
                'meta' => array_merge($bind->meta ?? [], [
                    'completed_channel_id' => $channel->id,
                    'completed_chat_id' => $channel->chat_id,
                ]),
            ]);

            return $channel;
        });

        return response()->json([
            'ok' => true,
            'channel_id' => $result->id,
            'platform' => $result->platform,
            'title' => $result->title,
        ]);
    }
}