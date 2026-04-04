<?php

namespace App\Services;

use App\Models\Event;
use App\Models\UserNotificationChannel;

class EventNotificationChannelSyncService
{
    public function sync(Event $event, array $payload, int $ownerUserId): void
    {
        $channelIds = collect($payload['channel_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        $allowedChannelIds = UserNotificationChannel::query()
            ->where('user_id', $ownerUserId)
            ->where('is_verified', true)
            ->whereIn('id', $channelIds)
            ->pluck('id');

        $event->notificationChannels()->delete();

        foreach ($allowedChannelIds as $channelId) {
            $event->notificationChannels()->create([
                'channel_id' => (int) $channelId,
                'notification_type' => 'registration_open',
                'use_private_link' => (bool) ($payload['use_private_link'] ?? false),
                'silent' => (bool) ($payload['silent'] ?? false),
                'update_message' => (bool) ($payload['update_message'] ?? true),
                'include_image' => (bool) ($payload['include_image'] ?? true),
                'include_registered_list' => (bool) ($payload['include_registered_list'] ?? true),
            ]);
        }
    }
}
