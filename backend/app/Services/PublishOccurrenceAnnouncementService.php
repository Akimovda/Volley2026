<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventChannelMessage;
use App\Models\EventOccurrence;
use App\Services\Channels\ChannelPublisherFactory;

class PublishOccurrenceAnnouncementService
{
    public function __construct(
        private readonly OccurrenceAnnouncementMessageBuilder $builder,
        private readonly ChannelPublisherFactory $factory,
    ) {
    }

    public function publish(EventOccurrence $occurrence): void
    {
        $event = $occurrence->event()
            ->with([
                'notificationChannels.channel',
                'media',
                'location',
            ])
            ->firstOrFail();

        foreach ($event->notificationChannels as $link) {
            $channel = $link->channel;

            if (!$channel || !$channel->is_verified) {
                continue;
            }

            $fallbackUrl = route('events.show', [
                'event' => $event->id,
                'occurrence' => $occurrence->id,
            ]);

            $privateLink = null;
            if ($link->use_private_link) {
                $privateLink = $this->resolvePrivateLink($event, $occurrence) ?: $fallbackUrl;
            }

            $message = $this->builder->build($occurrence, [
                'platform' => $channel->platform,
                'use_private_link' => (bool) $link->use_private_link,
                'private_link' => $privateLink,
                'silent' => (bool) $link->silent,
                'include_image' => (bool) $link->include_image,
                'include_registered_list' => (bool) $link->include_registered_list,
            ]);

            $messageKind = $message->imageUrl ? 'photo' : 'text';

            $hash = sha1(json_encode([
                'text' => $message->text,
                'button_url' => $message->buttonUrl,
                'button_text' => $message->buttonText,
                'image_url' => $message->imageUrl,
                'silent' => $message->silent,
            ], JSON_UNESCAPED_UNICODE));

            $record = EventChannelMessage::query()->firstOrNew([
                'event_id' => (int) $event->id,
                'occurrence_id' => (int) $occurrence->id,
                'channel_id' => (int) $channel->id,
                'notification_type' => 'registration_open',
            ]);

            $publisher = $this->factory->for((string) $channel->platform);

            if ($record->exists) {
                if ($record->last_payload_hash === $hash) {
                    continue;
                }

                $oldMessageKind = data_get($record->meta, 'message_kind');

                if (
                    $link->update_message &&
                    $publisher->supportsUpdate() &&
                    !empty($record->external_message_id) &&
                    $oldMessageKind === $messageKind
                ) {
                    $result = $publisher->update(
                        (string) $channel->chat_id,
                        (string) $record->external_message_id,
                        $message,
                        (array) ($record->meta ?? [])
                    );

                    $newMeta = array_merge(
                        (array) ($record->meta ?? []),
                        ['message_kind' => $messageKind],
                        (array) ($result['meta'] ?? []),
                        ['raw' => $result['raw'] ?? []],
                    );

                    $record->update([
                        'external_chat_id' => $result['external_chat_id'] ?? $record->external_chat_id,
                        'external_message_id' => $result['external_message_id'] ?? $record->external_message_id,
                        'last_payload_hash' => $hash,
                        'last_synced_at' => now(),
                        'meta' => $newMeta,
                    ]);

                    continue;
                }

                if (
                    !$link->update_message ||
                    !$publisher->supportsUpdate()
                ) {
                    continue;
                }
            }

            $result = $publisher->send((string) $channel->chat_id, $message);

            $newMeta = array_merge(
                ['message_kind' => $messageKind],
                (array) ($result['meta'] ?? []),
                ['raw' => $result['raw'] ?? []],
            );

            $record->fill([
                'platform' => (string) $channel->platform,
                'external_chat_id' => $result['external_chat_id'] ?? (string) $channel->chat_id,
                'external_message_id' => $result['external_message_id'] ?? null,
                'last_payload_hash' => $hash,
                'sent_at' => $record->sent_at ?: now(),
                'last_synced_at' => now(),
                'meta' => $newMeta,
            ])->save();
        }
    }

    private function resolvePrivateLink(Event $event, EventOccurrence $occurrence): ?string
    {
        return null;
    }
}