<?php

namespace App\Console\Commands;

use App\Services\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshWeather extends Command
{
    protected $signature = 'weather:refresh {--dry-run}';
    protected $description = 'Прогрев кеша прогноза погоды для локаций ближайших мероприятий на улице (OpenWeather, 5 дней)';

    public function handle(WeatherService $weather): int
    {
        if (!config('services.openweather.key')) {
            $this->warn('OPENWEATHER_API_KEY не задан — пропуск.');
            return self::SUCCESS;
        }

        $locations = DB::table('event_occurrences as o')
            ->join('events as e', 'e.id', '=', 'o.event_id')
            ->join('locations as l', 'l.id', '=', 'o.location_id')
            ->where('e.is_outdoor', true)
            ->whereRaw('(o.is_cancelled IS NULL OR o.is_cancelled = false)')
            ->whereNotNull('l.lat')->whereNotNull('l.lng')
            ->whereBetween('o.starts_at', [now(), now()->addDays(5)])
            ->select('l.id', 'l.lat', 'l.lng')
            ->distinct()
            ->get();

        $ok = 0;
        $fail = 0;
        foreach ($locations as $loc) {
            if ($this->option('dry-run')) {
                $this->line("location {$loc->id}");
                continue;
            }
            $weather->refreshLocation((int) $loc->id, (float) $loc->lat, (float) $loc->lng) ? $ok++ : $fail++;
        }

        $this->info("locations={$locations->count()} ok={$ok} fail={$fail}");
        return $fail > 0 && $ok === 0 && $locations->count() > 0 ? self::FAILURE : self::SUCCESS;
    }
}
