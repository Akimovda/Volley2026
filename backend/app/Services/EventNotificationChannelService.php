<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventNotificationChannel;
use App\Models\UserNotificationChannel;
use Illuminate\Http\Request;

class EventNotificationChannelService
{
    public function storeChannels(Event $event, Request $request): void
    {
        $channelIds = $request->input('channels', []);

        if (!is_array($channelIds)) {
            $channelIds = [];
        }

        $channelIds = array_values(array_unique(array_map('intval', $channelIds)));
        $channelIds = array_values(array_filter($channelIds, fn ($id) => $id > 0));

        if (empty($channelIds)) {
            return;
        }

        $ownerUserId = (int) ($event->organizer_id ?? 0);

        $channels = UserNotificationChannel::query()
            ->whereIn('id', $channelIds)
            ->verified()
            ->where('user_id', $ownerUserId)
            ->get(['id']);

        if ($channels->isEmpty()) {
            return;
        }

        $now = now();

        $rows = [];
        foreach ($channels as $channel) {
            $rows[] = [
                'event_id' => (int) $event->id,
                'channel_id' => (int) $channel->id,
                'notification_type' => 'registration_open',
                'use_private_link' => $request->boolean('channel_use_private_link'),
                'silent' => $request->boolean('channel_silent'),
                'update_message' => $request->boolean('channel_update_message', true),
                'include_image' => $request->boolean('channel_include_image', true),
                'include_registered_list' => $request->boolean('channel_include_registered', true),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        EventNotificationChannel::insert($rows);
    }

    public function updateChannels(Event $event, Request $request): void
    {
        EventNotificationChannel::query()
            ->where('event_id', (int) $event->id)
            ->delete();

        $this->storeChannels($event, $request);
    }

    public function deleteChannels(Event $event): void
    {
        EventNotificationChannel::query()
            ->where('event_id', (int) $event->id)
            ->delete();
    }
}