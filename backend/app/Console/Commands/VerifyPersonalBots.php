<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserNotificationChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyPersonalBots extends Command
{
    protected $signature   = 'channels:verify-bots {--dry-run : Только показать, без изменений}';
    protected $description = 'Перепроверяет персональных ботов организаторов (права в канале)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $channels = UserNotificationChannel::query()
            ->where('bot_type', 'user')
            ->where('is_verified', true)
            ->whereNotNull('user_bot_token')
            ->with('user')
            ->get();

        $this->info("Найдено каналов с персональным ботом: {$channels->count()}");

        $ok     = 0;
        $failed = 0;

        foreach ($channels as $channel) {
            try {
                $token = Crypt::decryptString($channel->user_bot_token);
            } catch (\Throwable $e) {
                $this->warn("  [#{$channel->id}] Не удалось расшифровать токен: {$e->getMessage()}");
                $failed++;
                continue;
            }

            $isValid = match ($channel->platform) {
                'telegram' => $this->verifyTelegram($token, $channel->chat_id),
                'max'      => $this->verifyMax($token, $channel->chat_id),
                default    => null,
            };

            if ($isValid === null) {
                $this->warn("  [#{$channel->id}] Платформа {$channel->platform} не поддерживается");
                continue;
            }

            if ($isValid) {
                $ok++;
                $this->line("  ✅ [#{$channel->id}] {$channel->platform} «{$channel->title}» — OK");

                if (!$dryRun) {
                    $channel->update(['user_bot_verified_at' => now()]);
                }
            } else {
                $failed++;
                $this->warn("  ❌ [#{$channel->id}] {$channel->platform} «{$channel->title}» — бот потерял права!");

                if (!$dryRun) {
                    $channel->update([
                        'is_verified'          => false,
                        'user_bot_verified_at' => now(),
                    ]);

                    $this->notifyOrganizer($channel);
                }
            }
        }

        $this->info("Итог: ✅ {$ok} OK, ❌ {$failed} проблем" . ($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    private function verifyTelegram(string $token, string $chatId): bool
    {
        try {
            // getMe — проверяем что токен валиден
            $me = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getMe")
                ->json();

            if (empty($me['ok'])) {
                return false;
            }

            $botId = $me['result']['id'] ?? null;
            if (!$botId) {
                return false;
            }

            // getChatMember — проверяем статус в чате
            $member = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getChatMember", [
                    'chat_id' => $chatId,
                    'user_id' => $botId,
                ])
                ->json();

            if (empty($member['ok'])) {
                return false;
            }

            $status = $member['result']['status'] ?? '';
            return in_array($status, ['administrator', 'creator'], true);

        } catch (\Throwable $e) {
            Log::warning('VerifyPersonalBots: Telegram check failed', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function verifyMax(string $token, string $chatId): bool
    {
        try {
            // getMe — проверяем токен
            $me = Http::timeout(10)
                ->withHeaders(['Authorization' => $token])
                ->get('https://platform-api.max.ru/me')
                ->json();

            if (empty($me['user_id'])) {
                return false;
            }

            // getChat — проверяем доступ к чату
            $chat = Http::timeout(10)
                ->withHeaders(['Authorization' => $token])
                ->get('https://platform-api.max.ru/chats/' . urlencode($chatId))
                ->json();

            return empty($chat['error']);

        } catch (\Throwable $e) {
            Log::warning('VerifyPersonalBots: MAX check failed', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function notifyOrganizer(UserNotificationChannel $channel): void
    {
        try {
            $user = $channel->user;
            if (!$user) {
                return;
            }

            $text = "⚠️ Ваш персональный бот потерял права администратора!\n\n"
                . "Канал: *{$channel->title}*\n"
                . "Платформа: " . strtoupper($channel->platform) . "\n\n"
                . "Бот отключён от канала. Восстановите права администратора и пересоздайте подключение в профиле:\n"
                . route('profile.notification_channels');

            // Telegram уведомление
            if ($user->telegram_id) {
                app(\App\Services\TelegramBotService::class)
                    ->sendMessage($user->telegram_id, $text, ['parse_mode' => 'Markdown']);
            }

            // Системное уведомление
            app(\App\Services\UserNotificationService::class)->create(
                userId: $user->id,
                type:   'personal_bot_revoked',
                title:  '⚠️ Бот потерял доступ к каналу',
                body:   "Ваш персональный бот потерял права администратора в канале «{$channel->title}». Восстановите подключение в профиле.",
                payload: [
                    'channel_id'  => $channel->id,
                    'platform'    => $channel->platform,
                    'channel_url' => route('profile.notification_channels'),
                ],
            );

        } catch (\Throwable $e) {
            Log::error('VerifyPersonalBots: notify failed', ['error' => $e->getMessage()]);
        }
    }
}
