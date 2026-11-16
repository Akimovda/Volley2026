{{-- Стоимость аренды площадки по дням недели и интервалам времени (публичный блок на странице площадки).
     Корты с одинаковой сеткой цен объединены, дни с одинаковыми интервалами — в диапазоны («Пн–Пт»).
     $location: Location. Если цен нет — блок не выводится. --}}
@php
    $ptDirs = $location->directions()->where('is_active', true)
        ->whereHas('courts', fn ($q) => $q->where('is_active', true))
        ->with(['location.city', 'workingHours', 'courts'])->get();
    $ptSvc = app(\App\Services\CourtPricingService::class);
    $ptTables = [];
    foreach ($ptDirs as $ptDir) {
        $ver = (string) \App\Models\CourtPriceRule::where('direction_id', $ptDir->id)->max('updated_at')
            . '|' . $ptDir->workingHours->max('updated_at') . '|' . $ptDir->courts->where('is_active', true)->pluck('id')->implode(',');
        $t = \Illuminate\Support\Facades\Cache::remember('rent_prices_v1_' . $ptDir->id . '_' . md5($ver), 600, fn () => $ptSvc->weeklyPriceTable($ptDir));
        if ($t) { $ptTables[] = ['dir' => $ptDir->direction, 'groups' => $t]; }
    }
    $ptMoney = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' ₽';
    $ptHm = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    $ptDays = function (array $days) {
        $a = __('club.dow_' . $days[0]);
        return count($days) === 1 ? $a : $a . '–' . __('club.dow_' . end($days));
    };
@endphp
@if($ptTables)
<div class="mb-2" id="rental-prices">
    <h3 class="mb-1"><x-ico name="wallet" /> {{ __('club.price_table_title') }}</h3>
    @foreach($ptTables as $tbl)
    <div class="card mb-1" style="height:auto">
        <div class="b-700 mb-1">
            @if($tbl['dir'] === 'beach')<x-ico name="beach" />@else<x-ico name="ball" />@endif
            {{ $tbl['dir'] === 'beach' ? __('club.direction_beach') : __('club.direction_classic') }}
            <span class="f-13 cd b-400">· {{ __('club.price_table_hour') }}</span>
        </div>
        @foreach($tbl['groups'] as $g)
            @if(count($tbl['groups']) > 1)
            <div class="b-600 f-14 mb-05">{{ implode(', ', $g['courts']) }}</div>
            @endif
            @foreach($g['rows'] as $row)
            <div class="f-14 mb-05">
                <b>{{ $ptDays($row['days']) }}:</b>
                @if($row['segments'] === null)
                    <span class="cd">{{ __('club.price_table_closed') }}</span>
                @else
                    @foreach($row['segments'] as $i => $seg)
                        {{ $ptHm($seg[0]) }}–{{ $ptHm($seg[1]) }} — <b>{{ $ptMoney($seg[2]) }}</b>@if(!$loop->last) · @endif
                    @endforeach
                @endif
            </div>
            @endforeach
        @endforeach
    </div>
    @endforeach
</div>
@endif
