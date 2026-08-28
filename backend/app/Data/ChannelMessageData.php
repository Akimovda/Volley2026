<?php

namespace App\Data;

class ChannelMessageData
{
    public function __construct(
        public readonly string $title,
        public readonly string $text,
        public readonly ?string $buttonUrl = null,
        public readonly ?string $buttonText = null,
        public readonly ?string $imageUrl = null,
        public readonly bool $silent = false,
        public readonly ?int $messageThreadId = null,
        public readonly ?array $imageUrls = null,
        public readonly ?string $listTitle = null,
        public readonly ?string $listText = null,
        // $text уже включает список (VK/MAX и Telegram-фолбэк без rich); textShort — та же
        // шапка БЕЗ списка, только для Telegram rich-параграфа (список рендерится отдельно в details).
        public readonly ?string $textShort = null,
    ) {}
}
