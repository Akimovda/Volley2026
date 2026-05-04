<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\UserMergeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckUserDuplicatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(UserMergeService $mergeService): void
    {
        $duplicates = $mergeService->findDuplicates();

        $total = count($duplicates);

        if ($total === 0) {
            Log::info('[CheckUserDuplicatesJob] Дублей не найдено.');
            return;
        }

        $red    = count(array_filter($duplicates, fn($d) => $d['level'] === 'red'));
        $yellow = $total - $red;

        $lines = ["👥 <b>Найдены дубли пользователей: {$total} групп</b>"];
        if ($red > 0) {
            $lines[] = "🔴 Высокая уверенность (фамилия + телефон): <b>{$red}</b>";
        }
        if ($yellow > 0) {
            $lines[] = "🟡 Требует проверки (телефон / имя+фамилия): <b>{$yellow}</b>";
        }

        $url    = config('app.url') . '/admin/users/duplicates';
        $lines[] = "\n<a href=\"{$url}\">Открыть в панели</a>";

        $this->sendTelegram(implode("\n", $lines));

        Log::info("[CheckUserDuplicatesJob] Найдено {$total} групп дублей (red={$red}, yellow={$yellow}).");
    }

    private function sendTelegram(string $text): void
    {
        $token  = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');

        if (!$token || !$chatId) {
            Log::warning('[CheckUserDuplicatesJob] Telegram не настроен (TELEGRAM_BOT_TOKEN / TELEGRAM_ADMIN_CHAT_ID).');
            return;
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'                  => $chatId,
                'text'                     => $text,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('[CheckUserDuplicatesJob] Ошибка отправки в Telegram: ' . $e->getMessage());
        }
    }
}
