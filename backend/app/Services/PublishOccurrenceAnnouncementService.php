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
    ) {}

    /**
     * @param bool $refreshOnly  true = только обновить существующий анонс,
     *                           не отправлять новое сообщение если записи нет.
     *                           Используется при записи/отписке игрока.
     */
    public function publish(EventOccurrence $occurrence, bool $refreshOnly = false): void
    {
        $event = $occurrence->event()
            ->with([
                'notificationChannels.channel',
                'media',
                'location',
                'location.city',
                'organizer',
            ])
            ->firstOrFail();

        foreach ($event->notificationChannels as $link) {
            $channel = $link->channel;

            if (!$channel || !$channel->is_verified) {
                continue;
            }

            $fallbackUrl = route('events.show', [
                'event'      => $event->id,
                'occurrence' => $occurrence->id,
            ]);

            $privateLink = null;
            if ($link->use_private_link || $event->is_private) {
                $privateLink = $this->resolvePrivateLink($event, $occurrence) ?: $fallbackUrl;
            }

            $threadId = data_get($channel->meta, 'message_thread_id');

            $message = $this->builder->build($occurrence, [
                'platform'                => $channel->platform,
                'use_private_link'        => (bool) $link->use_private_link,
                'private_link'            => $privateLink,
                'silent'                  => (bool) $link->silent,
                'include_image'           => (bool) $link->include_image,
                'include_registered_list' => (bool) $link->include_registered_list,
                'message_thread_id'       => $threadId ? (int) $threadId : null,
            ]);

            $messageKind = $message->imageUrl ? 'photo' : 'text';

            $hash = sha1(json_encode([
                'text'        => $message->text,
                'button_url'  => $message->buttonUrl,
                'button_text' => $message->buttonText,
                'image_url'   => $message->imageUrl,
                'silent'      => $message->silent,
            ], JSON_UNESCAPED_UNICODE));

            $record = EventChannelMessage::query()->firstOrNew([
                'event_id'          => (int) $event->id,
                'occurrence_id'     => (int) $occurrence->id,
                'channel_id'        => (int) $channel->id,
                'notification_type' => 'registration_open',
            ]);

            $publisher = $this->factory->forChannel($channel);

            // ── Запись уже существует → пробуем обновить ─────────────────
            if ($record->exists) {
                // Текст не изменился → ничего делать не нужно
                if ($record->last_payload_hash === $hash) {
                    continue;
                }

                $oldMessageKind = data_get($record->meta, 'message_kind');

                // Платформа поддерживает редактирование и есть message_id → редактируем
                if (
                    $link->update_message &&
                    $publisher->supportsUpdate() &&
                    !empty($record->external_message_id) &&
                    $oldMessageKind === $messageKind
                ) {
                    try {
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
                            'external_chat_id'    => $result['external_chat_id'] ?? $record->external_chat_id,
                            'external_message_id' => $result['external_message_id'] ?? $record->external_message_id,
                            'last_payload_hash'   => $hash,
                            'last_synced_at'      => now(),
                            'meta'                => $newMeta,
                        ]);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning(
                            'PublishOccurrenceAnnouncement: update failed',
                            ['channel' => $channel->platform, 'error' => $e->getMessage()]
                        );
                    }
                }

                // В любом случае НЕ отправляем новое сообщение если запись уже есть
                continue;
            }

            // ── Записи нет → отправляем первый анонс ──────────────────────
            // В режиме refreshOnly (обновление при записи игрока) — пропускаем
            if ($refreshOnly) {
                continue;
            }

            try {
                $result = $publisher->send((string) $channel->chat_id, $message);

                $newMeta = array_merge(
                    ['message_kind' => $messageKind],
                    (array) ($result['meta'] ?? []),
                    ['raw' => $result['raw'] ?? []],
                );

                $record->fill([
                    'platform'            => (string) $channel->platform,
                    'external_chat_id'    => $result['external_chat_id'] ?? (string) $channel->chat_id,
                    'external_message_id' => $result['external_message_id'] ?? null,
                    'last_payload_hash'   => $hash,
                    'sent_at'             => now(),
                    'last_synced_at'      => now(),
                    'meta'                => $newMeta,
                ])->save();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'PublishOccurrenceAnnouncement: send failed',
                    ['channel' => $channel->platform, 'error' => $e->getMessage()]
                );
            }
        }
    }

    private function resolvePrivateLink(Event $event, EventOccurrence $occurrence): ?string
    {
        if (!empty($event->public_token)) {
            return route('events.public', ['token' => $event->public_token]);
        }
        return null;
    }
}