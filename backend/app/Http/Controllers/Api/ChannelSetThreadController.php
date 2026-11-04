<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotificationChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelSetThreadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expectedSecret = (string) config('services.bind.secret', '');
        $incomingSecret = (string) $request->header('X-Bind-Secret', '');

        abort_unless(
            $expectedSecret !== '' && hash_equals($expectedSecret, $incomingSecret),
            403,
            'Invalid bind secret'
        );

        $data = $request->validate([
            'platform' => ['required', 'string', 'in:telegram'],
            'chat_id' => ['required', 'string'],
            'message_thread_id' => ['required', 'integer'],
        ]);

        $channel = UserNotificationChannel::query()
            ->where('platform', $data['platform'])
            ->where('chat_id', $data['chat_id'])
            ->first();

        if (!$channel) {
            return response()->json([
                'ok' => false,
                'message' => 'Канал не найден. Сначала привяжите чат.',
            ], 404);
        }

        $meta = (array) ($channel->meta ?? []);
        $threadId = \App\Support\TelegramTopic::normalize($data['message_thread_id']);

        // тема №1 — главная тема форума: запоминать нельзя (Telegram отвечает «message thread not found»), анонс и так идёт в неё
        if ($threadId === null) {
            unset($meta['message_thread_id']);
        } else {
            $meta['message_thread_id'] = $threadId;
        }

        $channel->update(['meta' => $meta]);

        return response()->json([
            'ok' => true,
            'channel_id' => $channel->id,
            'topic_name' => $threadId === null ? 'главная тема' : "#{$threadId}",
        ]);
    }
}
