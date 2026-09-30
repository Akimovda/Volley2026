<?php

namespace App\Console\Commands;

use App\Models\PlayerFollow;
use App\Models\PremiumAutoBooking;
use App\Models\PremiumSubscription;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Напоминания игрокам об окончании Premium. Пороги зависят от длины тарифа:
 * год/квартал — за 30 и 7 дней, месяц — за 7 и 1 день, пробный — за 1 день.
 * Повторы исключает premium_subscriptions.expiry_notice_days. Уведомление «закончился» — в PremiumService::expireAll().
 */
class NotifyPremiumExpiring extends Command
{
    protected $signature   = 'premium:notify-expiring {--dry-run : Только показать, без отправки}';
    protected $description = 'Напомнить игрокам об скором окончании Premium';

    /** @return int[] */
    public static function thresholdsFor(string $plan): array
    {
        return match ($plan) {
            'year', 'quarter' => [30, 7],
            'trial'           => [1],
            default           => [7, 1],
        };
    }

    public function handle(UserNotificationService $notifications): int
    {
        $dry    = (bool) $this->option('dry-run');
        $now    = now()->timestamp;
        $sent   = 0;
        $failed = 0;

        $subs = PremiumSubscription::query()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(30))
            ->with('user')
            ->get();

        foreach ($subs as $sub) {
            if (!$sub->user) {
                continue;
            }
            $secondsLeft = $sub->expires_at->timestamp - $now;
            $applicable  = array_filter(self::thresholdsFor($sub->plan), fn (int $t) => $secondsLeft <= $t * 86400);
            if (!$applicable) {
                continue;
            }
            $target = min($applicable);
            $last   = $sub->expiry_notice_days;
            if ($last !== null && $last <= $target) {
                continue;
            }

            $this->line(($dry ? '[dry] ' : '') . "sub #{$sub->id} user #{$sub->user_id} plan={$sub->plan} осталось ≤{$target} дн. (до {$sub->expires_at})");
            if ($dry) {
                continue;
            }
            try {
                $notifications->createPremiumExpiringNotification(
                    $sub->user, $sub, $target,
                    PlayerFollow::where('follower_user_id', $sub->user_id)->count(),
                    PremiumAutoBooking::where('user_id', $sub->user_id)->count()
                );
                DB::table('premium_subscriptions')->where('id', $sub->id)->update(['expiry_notice_days' => $target]);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('premium.expiring_notice_failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Уведомлений: {$sent}, ошибок: {$failed}" . ($dry ? ' (dry-run)' : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
