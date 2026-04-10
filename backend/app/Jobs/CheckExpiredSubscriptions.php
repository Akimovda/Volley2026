<?php
namespace App\Jobs;

use App\Services\SubscriptionService;
use App\Services\CouponService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckExpiredSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SubscriptionService $subService, CouponService $couponService): void
    {
        $expiredSubs    = $subService->expireOldSubscriptions();
        $expiredCoupons = $couponService->expireOldCoupons();

        \Log::info("CheckExpiredSubscriptions: абонементов={$expiredSubs}, купонов={$expiredCoupons}");
    }
}
