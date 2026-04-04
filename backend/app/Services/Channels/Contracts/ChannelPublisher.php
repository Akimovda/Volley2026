<?php

namespace App\Services\Channels\Contracts;

use App\Data\ChannelMessageData;

interface ChannelPublisher
{
    public function send(string $chatId, ChannelMessageData $message): array;

    public function update(
        string $chatId,
        string $messageId,
        ChannelMessageData $message,
        array $previousMeta = []
    ): array;

    public function supportsUpdate(): bool;

    public function supportsSilent(): bool;
}