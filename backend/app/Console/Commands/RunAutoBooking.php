<?php
namespace App\Console\Commands;

use App\Jobs\AutoBookingSubscriptionJob;
use App\Models\EventOccurrence;
use App\Models\SubscriptionAutoBooking;
use Illuminate\Console\Command;

class RunAutoBooking extends Command
{
    protected $signature   = 'subscriptions:auto-booking';
    protected $description = 'Запустить автозапись по абонементам для открывшихся мероприятий';

    public function handle(): void
    {
        $eventIds = SubscriptionAutoBooking::query()->distinct()->pluck('event_id');
        if ($eventIds->isEmpty()) {
            $this->info('Dispatched 0 jobs');
            return;
        }

        $from = now()->subMinutes(5);
        $to   = now();

        // «Момент открытия регистрации» — окно 5 минут (команда идёт каждые 5 минут):
        //  - registration_starts_at задан → он и есть момент открытия;
        //  - не задан (регистрация открыта сразу) → моментом считаем создание тура.
        // Раньше туры без окна попадали в выборку КАЖДЫЙ запуск до самого начала тура, и всем,
        // кому не хватило места, ошибка автозаписи приходила повторно каждые 5 минут.
        $occurrences = EventOccurrence::where('allow_registration', true)
            ->whereIn('event_id', $eventIds)
            ->where('starts_at', '>', now())
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('registration_starts_at', [$from, $to])
                  ->orWhere(function ($w) use ($from, $to) {
                      $w->whereNull('registration_starts_at')
                        ->whereBetween('created_at', [$from, $to]);
                  });
            })
            ->get();

        foreach ($occurrences as $occ) {
            AutoBookingSubscriptionJob::dispatch($occ->id);
            $this->line("Dispatched AutoBooking for occurrence #{$occ->id}");
        }

        $this->info("Dispatched {$occurrences->count()} jobs");
    }
}
