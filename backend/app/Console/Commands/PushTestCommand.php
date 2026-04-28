<?php

namespace App\Console\Commands;

use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class PushTestCommand extends Command
{
    protected $signature = 'push:test {user_id}';
    protected $description = 'Отправить тестовое push-уведомление пользователю';

    public function handle(PushNotificationService $push): int
    {
        $userId = (int) $this->argument('user_id');

        $push->send($userId, 'Тест', 'Push-уведомления работают!');

        $this->info("Push отправлен пользователю #{$userId}");

        return self::SUCCESS;
    }
}
