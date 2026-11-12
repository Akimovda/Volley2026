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
    $fmtHours = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', ' '), '0'), ',');
    $noArrows = fn (string $t) => trim(str_replace(['←', '→'], '', $t));

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

    $exportQuery = $location ? [
        'location_id' => $location->id,
        'period' => $period,
        'anchor' => $periodStart->toDateString(),
    ] : [];

    if ($location) {
        $center = $stats['center'];
        $allCourts = array_merge(...array_map(fn ($d) => $d['courts'], $stats['directions'] ?: [[]]));
        $busyHours = array_sum(array_column($allCourts, 'occupied_hours'));
        $totalHours = array_sum(array_column($allCourts, 'available_hours'));
        $onlineShare = $center['revenue'] > 0 ? round($center['revenue_online'] / $center['revenue'] * 100) : 0;
    }
@endphp

<div class="container">
    <div class="ramka">
        @if($locations->isEmpty())
            <div class="alert alert-info">{{ __('club.analytics_no_locations') }}</div>
        @else
            <div class="ca-toolbar">
                <div class="ca-toolbar__loc">
                    @if($locations->count() > 1)
                    <label class="d-block mb-1 f-14 cd"><x-ico name="building" /> {{ __('club.booking_location_label') }}</label>
                    <select id="caLocationSelect" class="btn-small">
                        @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" @selected($loc->id === $location->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                    @else
                    <div class="b-600 f-18"><x-ico name="building" /> {{ $location->name }}</div>
                    @endif
                </div>

                <div class="ca-toolbar__export">
                    @if($canExport)
                    <a href="{{ route('club.analytics.export_pdf', $exportQuery) }}" class="btn btn-small btn-secondary" title="{{ __('club.export_title') }} PDF"><x-ico name="file-text" /> {{ __('club.export_pdf') }}</a>
                    <a href="{{ route('club.analytics.export_xlsx', $exportQuery) }}" class="btn btn-small btn-secondary" title="{{ __('club.export_title') }} Excel"><x-ico name="download" /> {{ __('club.export_xlsx') }}</a>
                    @else
                    <a href="{{ route('organizer_pro.index') }}" class="btn btn-small btn-secondary ca-export-lock" title="{{ __('club.export_pro_hint') }}"><x-ico name="lock" /> {{ __('club.export_pdf') }}</a>
                    <a href="{{ route('organizer_pro.index') }}" class="btn btn-small btn-secondary ca-export-lock" title="{{ __('club.export_pro_hint') }}"><x-ico name="lock" /> {{ __('club.export_xlsx') }}</a>
                    @endif
                </div>
            </div>
            @unless($canExport)
            <div class="f-13 cd mb-2"><x-ico name="star" /> {{ __('club.export_pro_hint') }} — <a href="{{ route('organizer_pro.index') }}" class="blink">{{ __('club.export_pro_btn') }}</a></div>
            @endunless

            <div class="d-flex between fvc mb-3" style="flex-wrap:wrap;gap:10px">
                <div class="d-flex gap-1 fvc" style="flex-wrap:wrap">
                    <a href="{{ $urlFor(['anchor' => $prevAnchor->toDateString()]) }}" class="btn btn-small btn-secondary" title="{{ $noArrows(__('club.period_prev')) }}" aria-label="{{ $noArrows(__('club.period_prev')) }}"><x-ico name="arrow-left" /></a>
                    <span class="b-600 f-18"><x-ico name="calendar" /> {{ $periodLabel }}</span>
                    <a href="{{ $urlFor(['anchor' => $nextAnchor->toDateString()]) }}" class="btn btn-small btn-secondary" title="{{ $noArrows(__('club.period_next')) }}" aria-label="{{ $noArrows(__('club.period_next')) }}"><x-ico name="arrow-right" /></a>
                </div>
                <div class="d-flex gap-1" style="flex-wrap:wrap">
                    @foreach($periods as $key => $label)
                    <a href="{{ $urlFor(['period' => $key]) }}" class="btn btn-small {{ $period === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <h2 class="-mt-05 ca-h"><x-ico name="chart" /> {{ __('club.whole_center') }}</h2>
            <div class="ca-kpis">
                <div class="ca-kpi">
                    <div class="ca-kpi__label"><x-ico name="chart" /> {{ __('club.kpi_occupancy') }}</div>
                    <div class="ca-kpi__value">{{ $center['occupancy_pct'] }}%</div>
                    <div class="club-occ-bar"><div class="club-occ-bar__fill club-occ-bar__fill--{{ $occLevel($center['occupancy_pct']) }}" style="width: {{ min(100, $center['occupancy_pct']) }}%"></div></div>
                </div>
                <div class="ca-kpi">
                    <div class="ca-kpi__label"><x-ico name="wallet" /> {{ __('club.kpi_revenue') }}</div>
                    <div class="ca-kpi__value">{{ $fmtMoney($center['revenue']) }}</div>
                </div>
                <div class="ca-kpi">
                    <div class="ca-kpi__label"><x-ico name="card" /> {{ __('club.kpi_online') }}</div>
                    <div class="ca-kpi__value">{{ $fmtMoney($center['revenue_online']) }}</div>
                    @if($center['revenue'] > 0)<div class="ca-kpi__sub">{{ $onlineShare }}%</div>@endif
                </div>
                <div class="ca-kpi">
                    <div class="ca-kpi__label"><x-ico name="pin" /> {{ __('club.kpi_on_site') }}</div>
                    <div class="ca-kpi__value">{{ $fmtMoney($center['revenue_on_site']) }}</div>
                    @if($center['revenue'] > 0)<div class="ca-kpi__sub">{{ 100 - $onlineShare }}%</div>@endif
                </div>
                <div class="ca-kpi">
                    <div class="ca-kpi__label"><x-ico name="clock" /> {{ __('club.kpi_hours') }}</div>
                    <div class="ca-kpi__value">{{ $fmtHours($busyHours) }}</div>
                    <div class="ca-kpi__sub">{{ __('club.kpi_hours_of', ['busy' => $fmtHours($busyHours), 'total' => $fmtHours($totalHours)]) }}</div>
                </div>
            </div>

            <div class="ca-legend">
                <span class="cd">{{ __('club.legend_title') }}:</span>
                <span><i class="ca-dot ca-dot--low"></i> {{ __('club.legend_low') }}</span>
                <span><i class="ca-dot ca-dot--mid"></i> {{ __('club.legend_mid') }}</span>
                <span><i class="ca-dot ca-dot--high"></i> {{ __('club.legend_high') }}</span>
            </div>

            @forelse($stats['directions'] as $dir)
                @php
                    $courtsPct = array_column($dir['courts'], 'occupancy_pct');
                    $bestIdx = count($courtsPct) > 1 ? array_search(max($courtsPct), $courtsPct) : null;
                    $weakIdx = count($courtsPct) > 1 ? array_search(min($courtsPct), $courtsPct) : null;
                    if ($bestIdx === $weakIdx) { $bestIdx = $weakIdx = null; }
                @endphp
                <h3 class="mb-2 ca-h">
                    @if($dir['direction'] === 'beach')<x-ico name="beach" />@else<x-ico name="ball" />@endif
                    {{ $dir['direction'] === 'beach' ? __('club.direction_beach') : __('club.direction_classic') }}
                    <span class="ca-pill">{{ $dir['occupancy_pct'] }}%</span>
                    <span class="ca-pill ca-pill--money">{{ $fmtMoney($dir['revenue']) }}</span>
                    <span class="ca-pill ca-pill--muted">{{ __('club.courts_count', ['n' => count($dir['courts'])]) }}</span>
                </h3>
                @forelse($dir['courts'] as $i => $court)
                <div class="club-occ-row">
                    <div class="club-occ-row__name f-14">
                        {{ $court['name'] }}
                        @if($i === $bestIdx)<span title="{{ __('club.best_court') }}"><x-ico name="trophy" /></span>@endif
                        @if($i === $weakIdx)<span title="{{ __('club.weak_court') }}"><x-ico name="eye-off" /></span>@endif
                    </div>
                    <div class="club-occ-bar">
                        <div class="club-occ-bar__fill club-occ-bar__fill--{{ $occLevel($court['occupancy_pct']) }}" style="width: {{ min(100, $court['occupancy_pct']) }}%"></div>
                    </div>
                    <div class="club-occ-row__pct f-14">{{ $court['occupancy_pct'] }}%</div>
                    <div class="club-occ-row__hours f-13 cd">{{ $fmtHours($court['occupied_hours']) }} / {{ $fmtHours($court['available_hours']) }} {{ __('club.hours_short') }}</div>
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
