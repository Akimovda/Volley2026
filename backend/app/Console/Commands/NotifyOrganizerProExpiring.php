<?php

namespace App\Console\Commands;

use App\Models\OrganizerSubscription;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Уведомления об окончании подписки Организатор Pro + перевод просроченных в status=expired.
 * Пороги зависят от длины тарифа: год/квартал — за 30 и 7 дней, месяц — за 7 и 1 день,
 * пробный — за 1 день; в день окончания — отдельное «подписка закончилась».
 * Повторы исключает колонка organizer_subscriptions.expiry_notice_days.
 */
class NotifyOrganizerProExpiring extends Command
{
    protected $signature   = 'organizer-pro:notify-expiring {--dry-run : Только показать, без отправки и изменений}';
    protected $description = 'Уведомить организаторов об окончании подписки Pro и закрыть просроченные';

    /** @return int[] пороги «осталось N дней» по убыванию */
    public static function thresholdsFor(string $plan): array
    {
        return match ($plan) {
            'year', 'quarter' => [30, 7],
            'trial'           => [1],
            default           => [7, 1], // month и любые нестандартные
        };
    }

    public function handle(UserNotificationService $notifications): int
    {
        $dry  = (bool) $this->option('dry-run');
        $now  = now()->timestamp;
        $sent = 0;
        $failed = 0;

        // 1) Предупреждения: берём подписки, которым осталось не больше максимального порога (30 дней)
        $expiring = OrganizerSubscription::query()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(30))
            ->with('user')
            ->get();

        foreach ($expiring as $sub) {
            if (!$sub->user) {
                continue; // удалён/смёржен
            }
            $secondsLeft = $sub->expires_at->timestamp - $now;

            // Применимые пороги — те, в которые мы уже вошли; шлём по самому близкому к окончанию
            $applicable = array_filter(
                self::thresholdsFor($sub->plan),
                fn (int $t) => $secondsLeft <= $t * 86400
            );
            if (!$applicable) {
                continue;
            }
            $target = min($applicable);
            $last   = $sub->expiry_notice_days;
            if ($last !== null && $last <= $target) {
                continue; // об этом (или более близком) пороге уже уведомляли
            }

            $this->line(($dry ? '[dry] ' : '') . "sub #{$sub->id} user #{$sub->user_id} plan={$sub->plan} осталось ≤{$target} дн. (до {$sub->expires_at})");
            if ($dry) {
                continue;
            }
            try {
                $notifications->createOrganizerProExpiringNotification($sub->user, $sub, $target);
                DB::table('organizer_subscriptions')->where('id', $sub->id)->update(['expiry_notice_days' => $target]);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('organizer_pro.expiring_notice_failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        // 2) Закончившиеся: уведомить и закрыть. Продлённые подписки уже cancelled (см. activate()) — сюда не попадают.
        $expired = OrganizerSubscription::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->with('user')
            ->get();

        foreach ($expired as $sub) {
            $this->line(($dry ? '[dry] ' : '') . "sub #{$sub->id} user #{$sub->user_id} plan={$sub->plan} закончилась ({$sub->expires_at})"
                . ($sub->expires_at->timestamp >= $now - 3 * 86400 ? '' : ' — давно, без уведомления'));
            if ($dry) {
                continue;
            }
            try {
                // Давно закончившиеся (например, при первом запуске команды) закрываем молча —
                // «подписка закончилась» спустя недели/месяцы только запутает организатора.
                $recent = $sub->expires_at->timestamp >= $now - 3 * 86400;
                if ($sub->user && $recent) {
                    $notifications->createOrganizerProExpiredNotification($sub->user, $sub);
                }
                DB::table('organizer_subscriptions')->where('id', $sub->id)->update([
                    'status'             => 'expired',
                    'expiry_notice_days' => 0,
                    'updated_at'         => now(),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('organizer_pro.expired_notice_failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Уведомлений: {$sent}, ошибок: {$failed}" . ($dry ? ' (dry-run)' : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
