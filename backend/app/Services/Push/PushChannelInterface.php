<?php

namespace App\Services\Push;

use App\Models\DeviceToken;

interface PushChannelInterface
{
    public function send(DeviceToken $deviceToken, string $title, string $body, array $data, int $badge): bool;
}
