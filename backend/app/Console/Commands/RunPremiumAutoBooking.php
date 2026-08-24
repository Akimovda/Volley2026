<?php

namespace App\Console\Commands;

use App\Jobs\PremiumAutoBookingJob;
use App\Models\EventOccurrence;
use App\Models\PremiumAutoBooking;
use Illuminate\Console\Command;

class RunPremiumAutoBooking extends Command
{
    protected $signature = 'premium:auto-booking';

    protected $description = 'Диспатчит PremiumAutoBookingJob для occurrences, у которых только что открылась регистрация';

    public function handle(): int
    {
        $eventIds = PremiumAutoBooking::query()->distinct()->pluck('event_id');
        if ($eventIds->isEmpty()) {
            return self::SUCCESS;
        }

        $occurrences = EventOccurrence::query()
            ->where('allow_registration', true)
            ->whereIn('event_id', $eventIds)
            ->whereNotNull('registration_starts_at')
            ->whereBetween('registration_starts_at', [now()->subMinutes(5), now()])
            ->where('starts_at', '>', now())
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->get();

        foreach ($occurrences as $occurrence) {
            PremiumAutoBookingJob::dispatch($occurrence->id);
        }

        $this->info("PremiumAutoBooking: dispatched for {$occurrences->count()} occurrence(s).");

        return self::SUCCESS;
    }
}
