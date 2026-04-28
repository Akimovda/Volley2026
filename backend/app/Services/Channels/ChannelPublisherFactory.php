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

        if ($plt === 'vk') {
            $meta = $channel?->meta ?? [];
            if (($meta['kind'] ?? '') === 'vk_wall') {
                $rawToken = $meta['access_token'] ?? '';
                $decrypted = '';
                if ($rawToken !== '') {
                    try {
                        $decrypted = decrypt($rawToken);
                    } catch (\Throwable) {}
                }
                return new VkWallPublisher($decrypted);
            }
            return app(VkChannelPublisher::class);
        }

        return match ($plt) {
            'telegram' => new TelegramChannelPublisher($token),
            'max'      => new MaxChannelPublisher($token),
            default    => throw new InvalidArgumentException("Unsupported platform [{$plt}]"),
        };
    }
}
