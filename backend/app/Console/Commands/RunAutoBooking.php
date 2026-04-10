<?php
namespace App\Console\Commands;

use App\Jobs\AutoBookingSubscriptionJob;
use App\Models\EventOccurrence;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunAutoBooking extends Command
{
    protected $signature   = 'subscriptions:auto-booking';
    protected $description = 'Запустить автозапись по абонементам для открывшихся мероприятий';

    public function handle(): void
    {
        // Мероприятия у которых регистрация открылась в последние 5 минут
        $occurrences = EventOccurrence::where('allow_registration', true)
            ->where(function ($q) {
                $q->whereNull('registration_starts_at')
                  ->orWhereBetween('registration_starts_at', [
                      now()->subMinutes(5),
                      now(),
                  ]);
            })
            ->where('starts_at', '>', now())
            ->get();

        foreach ($occurrences as $occ) {
            AutoBookingSubscriptionJob::dispatch($occ->id);
            $this->line("Dispatched AutoBooking for occurrence #{$occ->id}");
        }

        $this->info("Dispatched {$occurrences->count()} jobs");
    }
}
