<?php

namespace App\Services;

use App\Data\ChannelMessageData;
use App\Models\Event;
use App\Models\EventChannelMessage;
use App\Models\EventOccurrence;
use App\Models\UserNotificationChannel;
use App\Services\Channels\ChannelPublisherFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                $this->log($event->id, $occurrence->id, $channel?->id ?? 0, $channel?->platform ?? '?', 'skip', [
                    'reason' => !$channel ? 'channel_missing' : 'not_verified',
                ]);
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

            $threadId = $link->message_thread_id ?? data_get($channel->meta, 'message_thread_id');

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
                // Мероприятие уже финализировано (markFinalized) — не перезаписывать
                // кнопку/текст обратно на «Записаться», даже если что-то триггернуло refresh
                // задним числом (например отмена регистрации на прошедшее мероприятие).
                if ($record->announcement_finalized_at !== null) {
                    $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'skip', [
                        'reason' => 'already_finalized',
                    ]);
                    continue;
                }

                // Текст не изменился → ничего делать не нужно
                if ($record->last_payload_hash === $hash) {
                    $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'skip', [
                        'reason' => 'hash_unchanged',
                    ]);
                    continue;
                }

                $oldMessageKind = data_get($record->meta, 'message_kind');

                // Платформа поддерживает редактирование и есть message_id → редактируем
                // MAX photo: update() сам передаёт photo_id обратно, чтобы сохранить вложение
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

                        $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'update', [
                            'external_message_id' => $result['external_message_id'] ?? $record->external_message_id,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning(
                            'PublishOccurrenceAnnouncement: update failed',
                            ['channel' => $channel->platform, 'error' => $e->getMessage()]
                        );
                        $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'fail', [
                            'action' => 'update',
                            'error'  => $e->getMessage(),
                        ]);
                    }
                }

                // В любом случае НЕ отправляем новое сообщение если запись уже есть
                continue;
            }

            // ── Записи нет → отправляем первый анонс ──────────────────────
            // В режиме refreshOnly (обновление при записи игрока) — пропускаем
            if ($refreshOnly) {
                $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'skip', [
                    'reason' => 'refresh_only_no_record',
                ]);
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

                $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'send', [
                    'external_message_id' => $result['external_message_id'] ?? null,
                    'message_kind'        => $messageKind,
                ]);
            } catch (\Throwable $e) {
                Log::warning(
                    'PublishOccurrenceAnnouncement: send failed',
                    ['channel' => $channel->platform, 'error' => $e->getMessage()]
                );
                $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'fail', [
                    'action' => 'send',
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Сценарий A: occurrence отменена (is_cancelled=true), но ещё существует.
     * Редактирует уже отправленные анонсы, приклеивая баннер отмены сверху
     * оригинального (пересобранного заново, с актуальными данными) текста.
     * Публикатор без supportsUpdate() (VK) — пропускается с логом, не падает.
     */
    public function markCancelled(EventOccurrence $occurrence): void
    {
        $event = $occurrence->event()
            ->with(['notificationChannels.channel', 'media', 'location', 'location.city', 'organizer'])
            ->first();

        if (!$event) {
            return;
        }

        foreach ($event->notificationChannels as $link) {
            $channel = $link->channel;

            if (!$channel || !$channel->is_verified) {
                continue;
            }

            $record = EventChannelMessage::query()
                ->where('event_id', (int) $event->id)
                ->where('occurrence_id', (int) $occurrence->id)
                ->where('channel_id', (int) $channel->id)
                ->where('notification_type', 'registration_open')
                ->first();

            if (!$record || empty($record->external_message_id)) {
                // Анонс никогда не публиковался в этот канал — нечего помечать
                continue;
            }

            $publisher = $this->factory->forChannel($channel);

            if (!$publisher->supportsUpdate()) {
                $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'skip', [
                    'reason' => 'cancel_update_not_supported',
                ]);
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
            $threadId = $link->message_thread_id ?? data_get($channel->meta, 'message_thread_id');

            // Пересобираем текст заново (актуальные дата/место/список и т.п.),
            // затем приклеиваем баннер отмены сверху — не трогаем сам builder.
            $original = $this->builder->build($occurrence, [
                'platform'                => $channel->platform,
                'use_private_link'        => (bool) $link->use_private_link,
                'private_link'            => $privateLink,
                'silent'                  => (bool) $link->silent,
                'include_image'           => (bool) $link->include_image,
                'include_registered_list' => (bool) $link->include_registered_list,
                'message_thread_id'       => $threadId ? (int) $threadId : null,
            ]);

            $banner = __('events.channel_announcement_cancelled_banner', [], 'ru');
            $cancelledMessage = new ChannelMessageData(
                title:           $original->title,
                text:            $banner . "\n\n" . $original->text,
                buttonUrl:       $original->buttonUrl,
                buttonText:      $original->buttonText,
                imageUrl:        $original->imageUrl,
                silent:          $original->silent,
                messageThreadId: $original->messageThreadId,
            );

            try {
                $result = $publisher->update(
                    (string) $channel->chat_id,
                    (string) $record->external_message_id,
                    $cancelledMessage,
                    (array) ($record->meta ?? [])
                );

                $newMeta = array_merge(
                    (array) ($record->meta ?? []),
                    ['raw' => $result['raw'] ?? []],
                    ['cancelled_marked_at' => now()->toIso8601String()],
                );

                $record->update([
                    'external_chat_id'    => $result['external_chat_id'] ?? $record->external_chat_id,
                    'external_message_id' => $result['external_message_id'] ?? $record->external_message_id,
                    'last_payload_hash'   => sha1($cancelledMessage->text),
                    'last_synced_at'      => now(),
                    'meta'                => $newMeta,
                ]);

                $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'cancel_mark', []);
            } catch (\Throwable $e) {
                Log::warning('PublishOccurrenceAnnouncement: markCancelled update failed', [
                    'occurrence_id' => $occurrence->id,
                    'channel_id'    => $channel->id,
                    'platform'      => $channel->platform,
                    'error'         => $e->getMessage(),
                ]);
                $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'fail', [
                    'action' => 'cancel_mark',
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Occurrence завершилась (ends_at < now): редактирует уже отправленные анонсы —
     * кнопка «Записаться!» → «🏁 Мероприятие завершено», строка «Осталось мест» →
     * «🏁 Мероприятие завершено!». Идемпотентно: пропускает канал, если
     * announcement_finalized_at уже проставлен (не редактирует повторно).
     * Отменённые occurrences не финализируются — у отмены своя семантика (markCancelled).
     * Публикатор без supportsUpdate() (VK) — пропускается с логом, не падает.
     */
    public function markFinalized(EventOccurrence $occurrence): void
    {
        if ($occurrence->isCancelled()) {
            return;
        }

        $event = $occurrence->event()
            ->with(['notificationChannels.channel', 'media', 'location', 'location.city', 'organizer'])
            ->first();

        if (!$event) {
            return;
        }

        foreach ($event->notificationChannels as $link) {
            $channel = $link->channel;

            if (!$channel || !$channel->is_verified) {
                continue;
            }

            $record = EventChannelMessage::query()
                ->where('event_id', (int) $event->id)
                ->where('occurrence_id', (int) $occurrence->id)
                ->where('channel_id', (int) $channel->id)
                ->where('notification_type', 'registration_open')
                ->first();

            if (!$record || empty($record->external_message_id)) {
                // Анонс никогда не публиковался в этот канал — нечего финализировать
                continue;
            }

            if ($record->announcement_finalized_at !== null) {
                // Уже финализировано ранее — не редактируем повторно
                continue;
            }

            $publisher = $this->factory->forChannel($channel);

            if (!$publisher->supportsUpdate()) {
                $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'skip', [
                    'reason' => 'finalize_update_not_supported',
                ]);
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
            $threadId = $link->message_thread_id ?? data_get($channel->meta, 'message_thread_id');

            $finalMessage = $this->builder->build($occurrence, [
                'platform'                => $channel->platform,
                'use_private_link'        => (bool) $link->use_private_link,
                'private_link'            => $privateLink,
                'silent'                  => (bool) $link->silent,
                'include_image'           => (bool) $link->include_image,
                'include_registered_list' => (bool) $link->include_registered_list,
                'message_thread_id'       => $threadId ? (int) $threadId : null,
                'finalized'               => true,
            ]);

            try {
                $result = $publisher->update(
                    (string) $channel->chat_id,
                    (string) $record->external_message_id,
                    $finalMessage,
                    (array) ($record->meta ?? [])
                );

                $newMeta = array_merge(
                    (array) ($record->meta ?? []),
                    ['raw' => $result['raw'] ?? []],
                );

                $record->update([
                    'external_chat_id'          => $result['external_chat_id'] ?? $record->external_chat_id,
                    'external_message_id'       => $result['external_message_id'] ?? $record->external_message_id,
                    'last_payload_hash'         => sha1($finalMessage->text),
                    'last_synced_at'            => now(),
                    'announcement_finalized_at' => now(),
                    'meta'                      => $newMeta,
                ]);

                $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'finalize', []);
            } catch (\Throwable $e) {
                Log::warning('PublishOccurrenceAnnouncement: markFinalized update failed', [
                    'occurrence_id' => $occurrence->id,
                    'channel_id'    => $channel->id,
                    'platform'      => $channel->platform,
                    'error'         => $e->getMessage(),
                ]);
                $this->log($event->id, $occurrence->id, $channel->id, $channel->platform, 'fail', [
                    'action' => 'finalize',
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Сценарий B: occurrence/событие физически удалены. Вызывается ПОСЛЕ каскадного
     * удаления event_channel_messages — принимает уже собранный до DELETE массив
     * примитивов (не Eloquent-модели, не occurrence: их к этому моменту уже нет в БД).
     * Каждый элемент: ['event_id','occurrence_id','channel_id','platform',
     * 'external_chat_id','external_message_id','event_title','starts_at_text'].
     * Пытается delete(), при неудаче — откатывается на update() с упрощённым текстом
     * (оригинал недоступен — occurrence уже удалена), при неудаче — Log::warning.
     */
    public function deletePosts(array $messages): void
    {
        foreach ($messages as $msg) {
            $eventId      = (int) ($msg['event_id'] ?? 0);
            $occurrenceId = (int) ($msg['occurrence_id'] ?? 0);
            $channelId  = (int) ($msg['channel_id'] ?? 0);
            $platform   = (string) ($msg['platform'] ?? '');
            $chatId     = (string) ($msg['external_chat_id'] ?? '');
            $messageId  = (string) ($msg['external_message_id'] ?? '');
            $eventTitle = (string) ($msg['event_title'] ?? '');
            $startsText = (string) ($msg['starts_at_text'] ?? '');

            if ($chatId === '' || $messageId === '') {
                continue;
            }

            $channel = $channelId ? UserNotificationChannel::find($channelId) : null;

            try {
                $publisher = $channel ? $this->factory->forChannel($channel) : $this->factory->for($platform);
            } catch (\Throwable $e) {
                Log::warning('PublishOccurrenceAnnouncement: deletePosts publisher resolve failed', [
                    'channel_id' => $channelId,
                    'platform'   => $platform,
                    'error'      => $e->getMessage(),
                ]);
                continue;
            }

            $handled = false;

            if ($publisher->supportsDelete()) {
                try {
                    $handled = $publisher->delete($chatId, $messageId);
                    if ($handled) {
                        $this->log($eventId, $occurrenceId, $channelId, $platform, 'delete', []);
                    }
                } catch (\Throwable $e) {
                    Log::warning('PublishOccurrenceAnnouncement: deletePosts delete failed, falling back to update', [
                        'platform'   => $platform,
                        'chat_id'    => $chatId,
                        'message_id' => $messageId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            if (!$handled && $publisher->supportsUpdate()) {
                try {
                    $fallbackText = __('events.channel_announcement_cancelled_deleted_fallback', [
                        'title' => $eventTitle,
                        'date'  => $startsText,
                    ], 'ru');

                    $publisher->update($chatId, $messageId, new ChannelMessageData(
                        title: $eventTitle,
                        text:  $fallbackText,
                    ), []);

                    $handled = true;
                    $this->log($eventId, $occurrenceId, $channelId, $platform, 'cancel_mark_fallback', []);
                } catch (\Throwable $e) {
                    Log::warning('PublishOccurrenceAnnouncement: deletePosts fallback update failed', [
                        'platform'   => $platform,
                        'chat_id'    => $chatId,
                        'message_id' => $messageId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            if (!$handled) {
                Log::warning('PublishOccurrenceAnnouncement: could not delete or mark post as cancelled', [
                    'platform'   => $platform,
                    'chat_id'    => $chatId,
                    'message_id' => $messageId,
                ]);
            }
        }
    }

    private function log(int $eventId, int $occurrenceId, int $channelId, string $platform, string $action, array $meta = []): void
    {
        try {
            DB::table('channel_publish_logs')->insert([
                'event_id'          => $eventId,
                'occurrence_id'     => $occurrenceId,
                'channel_id'        => $channelId,
                'platform'          => $platform,
                'action'            => $action,
                'notification_type' => 'registration_open',
                'error'             => $action === 'fail' ? ($meta['error'] ?? null) : null,
                'meta'              => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at'        => now(),
            ]);
        } catch (\Throwable) {
            // не прерываем основной поток
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
