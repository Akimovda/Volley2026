<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnnounceActivity extends Command
{
    protected $signature = 'activity:announce
        {--send : Реально отправить (по умолчанию — dry-run, только печатает охват)}';

    protected $description = 'Анонс функции записи активности всем пользователям (dry-run по умолчанию)';

    public function handle(UserNotificationService $notif): int
    {
        $isDryRun = !$this->option('send');

        $title = __('activity.announce_title');
        $body  = __('activity.announce_body');
        $push  = __('activity.announce_push');

        $channels = ['in_app', 'telegram', 'vk', 'max', 'push'];

        // --- Охват ---
        $total    = User::count();
        $tgCount  = User::whereNotNull('telegram_id')->where('telegram_id', '!=', '')->count();
        $vkCount  = User::whereNotNull('vk_id')->where('vk_id', '!=', '')->count();
        $maxCount = User::whereNotNull('max_chat_id')->where('max_chat_id', '!=', '')->count();
        $pushCount = DB::table('device_tokens')->where('is_active', true)->distinct('user_id')->count('user_id');

        $this->newLine();
        $this->line('=== ' . ($isDryRun ? 'DRY-RUN' : 'SEND') . ' activity:announce ===');
        $this->newLine();
        $this->line('Охват:');
        $this->line("  Всего пользователей : {$total}");
        $this->line("  Telegram            : {$tgCount}");
        $this->line("  VK                  : {$vkCount}");
        $this->line("  MAX                 : {$maxCount}");
        $this->line("  Push (APNs)         : {$pushCount}");
        $this->line("  In-app (все)        : {$total}");
        $this->newLine();
        $this->line('Каналы: ' . implode(', ', $channels));
        $this->newLine();
        $this->line('--- Текст уведомления ---');
        $this->line("Заголовок : {$title}");
        $this->newLine();
        $this->line("Тело :\n{$body}");
        $this->newLine();
        $this->line("Push (короткий) : {$push}");
        $this->newLine();

        if ($isDryRun) {
            $this->warn('Dry-run: ничего не отправлено. Добавьте --send для реальной рассылки.');
            $this->newLine();
            return self::SUCCESS;
        }

        // --- Реальная отправка ---
        if (!$this->confirm("Подтвердить отправку {$total} пользователям?")) {
            $this->line('Отменено.');
            return self::SUCCESS;
        }

        $sent   = 0;
        $errors = 0;

        User::orderBy('id')->chunk(200, function ($users) use ($notif, $title, $body, $push, $channels, &$sent, &$errors) {
            foreach ($users as $user) {
                try {
                    $notif->create(
                        userId:   $user->id,
                        type:     'activity_announce',
                        title:    $title,
                        body:     $body,
                        payload:  [
                            'push_body'  => $push,
                            'button_url' => 'https://volleyplay.club/personal_data_agreement#health-data',
                            'format'     => 'plain',
                        ],
                        channels: $channels,
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->warn("  user_id={$user->id} ошибка: " . $e->getMessage());
                }
            }
        });

        $this->info("Отправлено: {$sent}, ошибок: {$errors}");
        $this->newLine();

        return self::SUCCESS;
    }
}
