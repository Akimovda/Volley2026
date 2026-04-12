<?php

namespace App\Console\Commands;

use App\Services\PremiumService;
use Illuminate\Console\Command;

class ExpirePremiumSubscriptions extends Command
{
    protected $signature   = 'premium:expire';
    protected $description = 'Deactivate expired premium subscriptions';

    public function handle(PremiumService $service): void
    {
        $count = $service->expireAll();
        $this->info("Expired: {$count} subscriptions");
    }
}
