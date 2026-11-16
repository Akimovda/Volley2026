<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CourtPriceRule;
use App\Models\Location;
use App\Models\LocationDirection;
use App\Models\RentalWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Данные площадок для виджета аренды (iframe / JS / предпросмотр): только локации, где пользователь — владелец.
 */
class RentalWidgetService
{
    public function getLocations(RentalWidget $widget, int $userId): array
    {
        $ids = array_values(array_filter(array_map('intval', (array) $widget->getSetting('location_ids', []))));

        $cacheKey = 'rental_widget_v1_' . $userId . '_' . md5(json_encode($ids)) . '_' . app()->getLocale();

        return Cache::remember($cacheKey, 120, fn () => $this->build($userId, $ids));
    }

    private function build(int $userId, array $ids): array
    {
        $locations = Location::query()
            ->where('owner_id', $userId)
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->with(['city:id,name,timezone', 'media'])
            ->orderBy('name')
            ->limit(50)
            ->get();

        if ($locations->isEmpty()) {
            return [];
        }

        $directions = LocationDirection::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->where('is_active', true)
            ->with(['courts' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'), 'workingHours'])
            ->get()
            ->groupBy('location_id');

        $dirIds = $directions->flatten()->pluck('id');
        $minPrice = CourtPriceRule::query()
            ->whereIn('direction_id', $dirIds)
            ->selectRaw('direction_id, MIN(price_per_hour) as p')
            ->groupBy('direction_id')
            ->pluck('p', 'direction_id');

        $out = [];
        foreach ($locations as $loc) {
            $dirs = ($directions[$loc->id] ?? collect())->filter(fn ($d) => $d->courts->isNotEmpty());
            if ($dirs->isEmpty()) {
                continue; // без активных направлений и кортов арендовать нечего
            }

            $today = Carbon::now($loc->effectiveTimezone());
            $dow = $today->dayOfWeekIso - 1; // как в TimelineService: 0 = понедельник

            $dirRows = [];
            $opens = null; $closes = null; $anyOpen = false;
            foreach ($dirs as $d) {
                $wh = $d->workingHours->firstWhere('day_of_week', $dow);
                $open = $wh && !$wh->is_day_off;
                if ($open) {
                    $anyOpen = true;
                    $o = substr((string) $wh->opens_at, 0, 5);
                    $c = substr((string) $wh->closes_at, 0, 5);
                    $opens = $opens === null || $o < $opens ? $o : $opens;
                    $closes = $closes === null || $c > $closes ? $c : $closes;
                }
                $price = $minPrice[$d->id] ?? null;
                $dirRows[] = [
                    'direction'   => $d->direction,
                    'courts'      => $d->courts->count(),
                    'court_names' => $d->courts->pluck('name')->all(),
                    'price_from'  => $price !== null ? (float) $price : null,
                ];
            }

            $prices = array_filter(array_column($dirRows, 'price_from'), fn ($p) => $p !== null);
            $photo = $loc->getFirstMediaUrl('photos', 'thumb') ?: $loc->getFirstMediaUrl('photos');

            $out[] = [
                'id'          => $loc->id,
                'name'        => $loc->name,
                'address'     => trim(($loc->city?->name ? $loc->city->name . ', ' : '') . (string) $loc->address, ', '),
                'desc'        => (string) ($loc->short_text ?? ''),
                'url'         => $loc->public_url,
                'photo'       => $photo ? $this->absoluteUrl($photo) : $this->absoluteUrl('/img/' . ($dirs->contains(fn ($d) => $d->direction === 'beach') && !$dirs->contains(fn ($d) => $d->direction === 'classic') ? 'beach.webp' : 'classic.webp')),
                'directions'  => $dirRows,
                'courts_total' => array_sum(array_column($dirRows, 'courts')),
                'price_from'  => $prices ? (float) min($prices) : null,
                'open_today'  => $anyOpen,
                'hours_today' => $anyOpen ? ['from' => $opens, 'to' => $closes] : null,
            ];
        }

        return $out;
    }

    private function absoluteUrl(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : rtrim((string) config('app.url'), '/') . '/' . ltrim($url, '/');
    }

    /** Демо-данные для предпросмотра, когда у владельца нет подходящих площадок. */
    public function sampleLocations(): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [[
            'id' => 0, 'name' => 'Спорткомплекс «Волна»', 'address' => 'Москва, ул. Примерная, 1',
            'desc' => 'Крытые и пляжные корты, раздевалки, парковка',
            'url' => $base, 'photo' => $base . '/img/beach.webp',
            'directions' => [
                ['direction' => 'classic', 'courts' => 2, 'court_names' => ['Корт 1', 'Корт 2'], 'price_from' => 1800.0],
                ['direction' => 'beach', 'courts' => 4, 'court_names' => ['Пляж 1', 'Пляж 2', 'Пляж 3', 'Пляж 4'], 'price_from' => 1200.0],
            ],
            'courts_total' => 6, 'price_from' => 1200.0, 'open_today' => true, 'hours_today' => ['from' => '08:00', 'to' => '23:00'],
        ]];
    }
}
