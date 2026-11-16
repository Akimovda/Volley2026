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

        // Проверяем и подтверждённые каналы, и те, что были подтверждены раньше, но «потеряли» подтверждение
        // (user_bot_verified_at задан): раньше случайный сбой сети навсегда снимал подтверждение, а перепроверялись
        // только подтверждённые — канал уже не возвращался.
        $channels = UserNotificationChannel::query()
            ->where('bot_type', 'user')
            ->whereNotNull('user_bot_token')
            ->where(function ($q) {
                $q->where('is_verified', true)
                  ->orWhere(function ($w) {
                      $w->where('is_verified', false)->whereNotNull('user_bot_verified_at');
                  });
            })
            ->with('user')
            ->get();

        $this->info("Найдено каналов с персональным ботом: {$channels->count()}");

        $ok = 0; $failed = 0; $unknown = 0; $restored = 0;

        foreach ($channels as $channel) {
            try {
                $token = Crypt::decryptString($channel->user_bot_token);
            } catch (\Throwable $e) {
                $this->warn("  [#{$channel->id}] Не удалось расшифровать токен: {$e->getMessage()}");
                $failed++;
                continue;
            }

            // true — подтверждено; false — Telegram/MAX ТОЧНО отказали (нет прав, бот удалён, токен отозван);
            // null — проверить не удалось (сеть, 5xx, лимиты): статус не меняем
            $isValid = match ($channel->platform) {
                'telegram' => $this->verifyTelegram($token, $channel->chat_id),
                'max'      => $this->verifyMax($token, $channel->chat_id),
                default    => 'unsupported',
            };

            if ($isValid === 'unsupported') {
                $this->warn("  [#{$channel->id}] Платформа {$channel->platform} не поддерживается");
                continue;
            }

            if ($isValid === null) {
                $unknown++;
                $this->warn("  ⏳ [#{$channel->id}] {$channel->platform} «{$channel->title}» — проверить не удалось (сеть/API), статус не меняем");
                continue;
            }

            if ($isValid) {
                $ok++;
                $wasVerified = (bool) $channel->is_verified;
                $this->line("  ✅ [#{$channel->id}] {$channel->platform} «{$channel->title}» — OK" . ($wasVerified ? '' : ' (подтверждение восстановлено)'));

                if (!$dryRun) {
                    $channel->update(['is_verified' => true, 'user_bot_verified_at' => now()]);
                }
                if (!$wasVerified) {
                    $restored++;
                }
                continue;
            }

            // false — точный отказ
            if ($channel->is_verified) {
                $failed++;
                $this->warn("  ❌ [#{$channel->id}] {$channel->platform} «{$channel->title}» — бот потерял права!");

                if (!$dryRun) {
                    $channel->update([
                        'is_verified'          => false,
                        'user_bot_verified_at' => now(),
                    ]);

                    $this->notifyOrganizer($channel);
                }
            } else {
                $this->line("  ⛔ [#{$channel->id}] {$channel->platform} «{$channel->title}» — по-прежнему нет прав");
            }
        }

        $this->info("Итог: ✅ {$ok} OK (восстановлено: {$restored}), ❌ {$failed} проблем, ⏳ {$unknown} не проверено" . ($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    /** GET с повторами при сетевых сбоях; null — ответ не получен/не разобран (5xx, лимит, таймаут). */
    private function getJson(string $url, array $query = [], array $headers = []): ?array
    {
        try {
            $resp = Http::timeout(10)->retry(3, 700, throw: false)->withHeaders($headers)->get($url, $query);
        } catch (\Throwable $e) {
            return null;
        }
        $json = $resp->json();
        if (!is_array($json) || $resp->status() >= 500 || $resp->status() === 429) {
            return null;
        }

        return $json;
    }

    private function verifyTelegram(string $token, string $chatId): ?bool
    {
        $me = $this->getJson(\App\Services\TelegramApi::url($token, 'getMe'));
        if ($me === null) {
            return null;
        }
        if (empty($me['ok'])) {
            return in_array((int) ($me['error_code'] ?? 0), [401, 404], true) ? false : null; // токен отозван/неверен
        }
        $botId = $me['result']['id'] ?? null;
        if (!$botId) {
            return null;
        }

        $member = $this->getJson(\App\Services\TelegramApi::url($token, 'getChatMember'), ['chat_id' => $chatId, 'user_id' => $botId]);
        if ($member === null) {
            return null;
        }
        if (empty($member['ok'])) {
            // 400 (чат не найден), 403 (бот удалён/заблокирован) — точный отказ; остальное (лимиты и т.п.) — неизвестно
            return in_array((int) ($member['error_code'] ?? 0), [400, 403], true) ? false : null;
        }

        return in_array($member['result']['status'] ?? '', ['administrator', 'creator'], true);
    }

    private function verifyMax(string $token, string $chatId): ?bool
    {
        $base = (string) config('services.max.api_base_url');
        $h = ['Authorization' => $token];

        $me = $this->getJson($base . '/me', [], $h);
        if ($me === null) {
            return null;
        }
        if (empty($me['user_id'])) {
            return isset($me['code']) || isset($me['error']) ? false : null;
        }

        $chat = $this->getJson($base . '/chats/' . urlencode($chatId), [], $h);
        if ($chat === null) {
            return null;
        }

        return empty($chat['error']) && empty($chat['code']);
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
