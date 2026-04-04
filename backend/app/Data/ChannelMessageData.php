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
    ) {}
}
