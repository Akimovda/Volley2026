<x-voll-layout body_class="club-analytics-page">
<x-slot name="title">{{ __('club.analytics') }}</x-slot>
<x-slot name="h1">{{ __('club.analytics') }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ __('club.analytics') }}</span>
        <meta itemprop="position" content="2">
    </li>
</x-slot>

@php
    $occLevel = function (float $pct) {
        if ($pct < 30) return 'low';
        if ($pct > 70) return 'high';
        return 'mid';
    };
    $fmtMoney = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' ₽';

    $periodLabel = '';
    if ($location) {
        $qNum = intdiv($periodStart->month - 1, 3) + 1;
        $hNum = $periodStart->month <= 6 ? 1 : 2;
        $periodLabel = match ($period) {
            'quarter' => __('club.period_label_quarter', ['n' => $qNum, 'year' => $periodStart->year]),
            'half_year' => __('club.period_label_half_year', ['n' => $hNum, 'year' => $periodStart->year]),
            'year' => (string) $periodStart->year,
            default => \Illuminate\Support\Str::ucfirst($periodStart->locale(app()->getLocale())->isoFormat('MMMM YYYY')),
        };
    }

    $periods = [
        'month' => __('club.period_month'),
        'quarter' => __('club.period_quarter'),
        'half_year' => __('club.period_half_year'),
        'year' => __('club.period_year'),
    ];

    $urlFor = fn ($overrides) => $location
        ? route('club.analytics.index', array_merge([
            'location_id' => $location->id,
            'period' => $period,
            'anchor' => $periodStart->toDateString(),
        ], $overrides))
        : '#';
@endphp

<div class="container">
    <div class="ramka">
        @if($locations->isEmpty())
            <div class="alert alert-info">{{ __('club.analytics_no_locations') }}</div>
        @else
            <div class="d-flex between fvc mb-2" style="flex-wrap:wrap;gap:10px">
                @if($locations->count() > 1)
                <div>
                    <label class="d-block mb-1 f-14 cd">{{ __('club.booking_location_label') }}</label>
                    <select id="caLocationSelect" class="btn-small">
                        @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" @selected($loc->id === $location->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                <div class="b-600">{{ $location->name }}</div>
                @endif
            </div>

            <div class="d-flex between fvc mb-3" style="flex-wrap:wrap;gap:10px">
                <div class="d-flex gap-1 fvc" style="flex-wrap:wrap">
                    <a href="{{ $urlFor(['anchor' => $prevAnchor->toDateString()]) }}" class="btn btn-small btn-secondary">{{ __('club.period_prev') }}</a>
                    <span class="b-600">{{ $periodLabel }}</span>
                    <a href="{{ $urlFor(['anchor' => $nextAnchor->toDateString()]) }}" class="btn btn-small btn-secondary">{{ __('club.period_next') }}</a>
                </div>
                <div class="d-flex gap-1" style="flex-wrap:wrap">
                    @foreach($periods as $key => $label)
                    <a href="{{ $urlFor(['period' => $key]) }}" class="btn btn-small {{ $period === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            @php $center = $stats['center']; @endphp
            <div class="club-analytics-summary">
                <h2 class="-mt-05" style="margin:0 0 10px">{{ __('club.whole_center') }}</h2>
                <div class="club-occ-row" style="margin-bottom:0">
                    <div class="club-occ-row__name f-16">{{ __('club.occupancy') }}: <strong>{{ $center['occupancy_pct'] }}%</strong></div>
                    <div class="club-occ-bar" style="flex:1 1 160px">
                        <div class="club-occ-bar__fill club-occ-bar__fill--{{ $occLevel($center['occupancy_pct']) }}" style="width: {{ min(100, $center['occupancy_pct']) }}%"></div>
                    </div>
                    <div class="f-16" style="flex:0 0 auto">{{ __('club.revenue') }}: <strong>{{ $fmtMoney($center['revenue']) }}</strong></div>
                </div>
                @if($center['revenue_online'] > 0 && $center['revenue_on_site'] > 0)
                <div class="f-14 cd mt-1">
                    {{ __('club.paid_online') }}: {{ $fmtMoney($center['revenue_online']) }} ·
                    {{ __('club.paid_on_site') }}: {{ $fmtMoney($center['revenue_on_site']) }}
                </div>
                @endif
            </div>

            @forelse($stats['directions'] as $dir)
                <h3 class="mb-2">
                    {{ $dir['direction'] === 'beach' ? __('club.direction_beach') : __('club.direction_classic') }}
                    — {{ $dir['occupancy_pct'] }}% · {{ $fmtMoney($dir['revenue']) }}
                </h3>
                @forelse($dir['courts'] as $court)
                <div class="club-occ-row">
                    <div class="club-occ-row__name f-14">{{ $court['name'] }}</div>
                    <div class="club-occ-bar">
                        <div class="club-occ-bar__fill club-occ-bar__fill--{{ $occLevel($court['occupancy_pct']) }}" style="width: {{ min(100, $court['occupancy_pct']) }}%"></div>
                    </div>
                    <div class="club-occ-row__pct f-14">{{ $court['occupancy_pct'] }}%</div>
                    <div class="club-occ-row__revenue f-14">{{ $fmtMoney($court['revenue']) }}</div>
                </div>
                @empty
                <div class="alert alert-info mb-2">{{ __('club.analytics_no_courts') }}</div>
                @endforelse
            @empty
                <div class="alert alert-info">{{ __('club.analytics_no_courts') }}</div>
            @endforelse
        @endif
    </div>
</div>

@if($locations->count() > 1)
<x-slot name="script">
    <script>
        document.getElementById('caLocationSelect').addEventListener('change', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('location_id', this.value);
            window.location.href = url.toString();
        });
    </script>
</x-slot>
@endif
</x-voll-layout>
