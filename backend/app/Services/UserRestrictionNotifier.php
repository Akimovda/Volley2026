<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserRestrictionNotifier
{
    public function notify(User $user, array $payload): void
    {
        $text = $this->buildText($payload);

        // 1) Telegram
        if (!empty($user->telegram_id)) {
            $this->sendTelegram((string)$user->telegram_id, $text);
            return;
        }

        // 2) VK (если нет телеги)
        if (!empty($user->vk_id)) {
            $this->sendVk((string)$user->vk_id, $text);
            return;
        }

        // 3) некуда слать
        Log::info('Restriction notify skipped: no tg/vk', ['user_id' => $user->id]);
    }

    private function buildText(array $payload): string
    {
        $type = (string)($payload['type'] ?? '');
        $until = (string)($payload['until'] ?? '');
        $reason = trim((string)($payload['reason'] ?? ''));

        return match ($type) {
            'cleared' => "✅ Ограничения вашей учетной записи сняты.",
            'events_set' => $this->withReason(
                "⚠️ У вашей учетной записи есть ограничения по мероприятиям.\n" .
                ($until !== '' ? "Срок действия: {$until}\n" : "") .
                "Подробности — в личном кабинете.",
                $reason
            ),
            'site_set' => $this->withReason(
                "⛔️ У вашей учетной записи есть ограничения!\n" .
                ($until !== '' ? "Срок действия: {$until}\n" : "") .
                "Подробности — в личном кабинете.",
                $reason
            ),
            default => $this->withReason(
                "⚠️ У вашей учетной записи обновились ограничения.\n" .
                ($until !== '' ? "Срок действия: {$until}\n" : "") .
                "Подробности — в личном кабинете.",
                $reason
            ),
        };
    }

    private function withReason(string $text, string $reason): string
    {
        if ($reason === '') return $text;
        return $text . "\nПричина: " . $reason;
    }

    private function sendTelegram(string $chatId, string $text): void
    {
        $token = config('services.telegram.bot_token');
        if (empty($token)) {
            Log::warning('Telegram notify failed: missing token');
            return;
        }

        try {
            $resp = Http::timeout(8)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);

            if (!$resp->ok()) {
                Log::warning('Telegram notify failed: http_not_ok', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram notify failed: exception', ['error' => $e->getMessage()]);
        }
    }

    private function sendVk(string $vkId, string $text): void
    {
        /**
         * Тут зависит от вашей реализации VK:
         * - если у вас есть VK bot/service — дерните его
         * - если планируете VK API messages.send — нужны access_token группы + user_id/vk_id mapping
         *
         * Пока не ломаем прод и просто логируем:
         */
        Log::info('VK notify stub (implement me)', [
            'vk_id' => $vkId,
            'text' => $text,
        ]);
    }
}
