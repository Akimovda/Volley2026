<?php

namespace App\Services\Channels;

use App\Models\UserNotificationChannel;
use App\Services\Channels\Contracts\ChannelPublisher;
use InvalidArgumentException;

class ChannelPublisherFactory
{
    /**
     * Создать publisher по имени платформы (системный бот).
     */
    public function for(string $platform): ChannelPublisher
    {
        return $this->forChannel(null, $platform);
    }

    /**
     * Создать publisher для конкретного канала.
     * Если канал — персональный бот, передаёт расшифрованный токен.
     */
    public function forChannel(?UserNotificationChannel $channel, ?string $platform = null): ChannelPublisher
    {
        $plt   = $platform ?? (string) ($channel?->platform ?? '');
        $token = $channel?->resolveToken(); // null для системного

        return match ($plt) {
            'telegram' => new TelegramChannelPublisher($token),
            'max'      => new MaxChannelPublisher($token),
            'vk'       => app(VkChannelPublisher::class), // VK — только системный пока
            default    => throw new InvalidArgumentException("Unsupported platform [{$plt}]"),
        };
    }
}
