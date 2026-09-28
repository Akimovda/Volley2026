<?php

namespace App\Services;

use App\Data\ChannelMessageData;
use App\Models\UserNotificationChannel;
use App\Services\Channels\ChannelPublisherFactory;
use Illuminate\Support\Facades\Log;

/**
 * Отправка произвольного текстового сообщения во ВСЕ подтверждённые каналы
 * организатора (Telegram/VK/MAX группы из «Каналы уведомлений»), не привязанного
 * к конкретному мероприятию — в отличие от PublishOccurrenceAnnouncementService.
 */
class OrganizerChannelBroadcastService
{
    public function __construct(private ChannelPublisherFactory $factory) {}

    public function sendText(
        int $organizerId,
        string $title,
        string $text,
        ?string $buttonUrl = null,
        ?string $buttonText = null
    ): void {
        $channels = UserNotificationChannel::where('user_id', $organizerId)
            ->verified()
            ->get();

        foreach ($channels as $channel) {
            try {
                $publisher = $this->factory->forChannel($channel);
                $threadId = data_get($channel->meta, 'message_thread_id');

                $message = new ChannelMessageData(
                    title: $title,
                    text: $text,
                    buttonUrl: $buttonUrl,
                    buttonText: $buttonText,
                    messageThreadId: $threadId ? (int) $threadId : null,
                );

                $publisher->send((string) $channel->chat_id, $message);
            } catch (\Throwable $e) {
                Log::warning('OrganizerChannelBroadcast: send failed', [
                    'organizer_id' => $organizerId,
                    'channel_id'   => $channel->id,
                    'platform'     => $channel->platform,
                    'error'        => $e->getMessage(),
                ]);
            }
        }
    }
}
