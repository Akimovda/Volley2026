<?php

namespace App\Support;

use App\Models\User;

/** Кто вводит данные матча: организатор/залогиненный (user) или ведущий по токен-ссылке (linkId + name [+ user]). */
final class RallyActor
{
    public function __construct(
        public readonly ?User $user = null,
        public readonly ?string $name = null,
        public readonly ?int $linkId = null,
    ) {}

    public static function user(?User $user): self
    {
        return new self($user);
    }
}
