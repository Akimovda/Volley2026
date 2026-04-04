<?php

namespace App\Services\Channels;

use App\Services\Channels\Contracts\ChannelPublisher;
use InvalidArgumentException;

class ChannelPublisherFactory
{
    public function for(string $platform): ChannelPublisher
    {
        return match ($platform) {
            'telegram' => app(TelegramChannelPublisher::class),
            'vk' => app(VkChannelPublisher::class),
            'max' => app(MaxChannelPublisher::class),
            default => throw new InvalidArgumentException("Unsupported platform [{$platform}]"),
        };
    }
}