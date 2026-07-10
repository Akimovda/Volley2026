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

    /**
     * Удалить ранее отправленное сообщение. Бросает исключение при неудаче
     * (бот не админ, лимит платформы истёк, пост уже удалён вручную) — вызывающий
     * код должен ловить и решать, откатываться ли на update() с пометкой отмены.
     */
    public function delete(string $chatId, string $messageId): bool;

    public function supportsDelete(): bool;
}