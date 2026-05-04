<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PurgeInactiveUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private bool $dryRun = false) {}

    public function handle(): void
    {
        $cutoff = now()->subDays(14);

        // Пользователи без заполненного профиля, без активности, без привилегий
        $candidates = DB::table('users as u')
            ->select('u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.created_at')
            ->where('u.profile_completed_at', null)
            ->where('u.created_at', '<', $cutoff)
            ->whereNull('u.deleted_at')
            ->whereNull('u.merged_into_user_id')
            ->whereRaw('u.is_bot IS NOT TRUE')
            ->whereNotIn('u.role', ['admin', 'superadmin', 'organizer'])
            // Не стафф
            ->whereNotExists(function ($q) {
                $q->from('organizer_staff')->whereColumn('staff_user_id', 'u.id');
            })
            // Ни одной регистрации (даже отменённой) — значит никогда не участвовал
            ->whereNotExists(function ($q) {
                $q->from('event_registrations')->whereColumn('user_id', 'u.id');
            })
            // Нет платежей
            ->whereNotExists(function ($q) {
                $q->from('payments')->whereColumn('user_id', 'u.id');
            })
            // Нет баланса на кошельке
            ->whereNotExists(function ($q) {
                $q->from('virtual_wallets')
                    ->whereColumn('user_id', 'u.id')
                    ->where('balance_minor', '>', 0);
            })
            ->get();

        $count = $candidates->count();

        if ($count === 0) {
            Log::info('[PurgeInactiveUsersJob] Нет неактивных аккаунтов для удаления.');
            return;
        }

        $ids = $candidates->pluck('id')->toArray();

        if ($this->dryRun) {
            Log::info("[PurgeInactiveUsersJob] DRY RUN — будет удалено {$count} аккаунтов: " . implode(', ', $ids));
            return;
        }

        DB::table('users')->whereIn('id', $ids)->update(['deleted_at' => now()]);

        $preview = $candidates->take(10)->map(
            fn($u) => "#{$u->id} {$u->first_name} {$u->last_name}"
        )->implode(', ');

        if ($count > 10) {
            $preview .= " и ещё " . ($count - 10);
        }

        Log::info("[PurgeInactiveUsersJob] Мягко удалено {$count} неактивных аккаунтов: {$preview}");

        $this->sendTelegram($count, $preview);
    }

    private function sendTelegram(int $count, string $preview): void
    {
        $token  = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');

        if (!$token || !$chatId) {
            return;
        }

        $text = "🧹 <b>Автоочистка неактивных аккаунтов</b>\n"
            . "Мягко удалено: <b>{$count}</b> аккаунтов без заполненного профиля (>14 дней)\n"
            . "<i>{$preview}</i>\n\n"
            . "Критерии: нет профиля, нет регистраций, нет платежей, создан >14 дней назад.";

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'                  => $chatId,
                'text'                     => $text,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PurgeInactiveUsersJob] Ошибка отправки в Telegram: ' . $e->getMessage());
        }
    }
}
