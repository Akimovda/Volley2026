<x-voll-layout body_class="activity-show-page">
    @php $user = auth()->user(); @endphp

    <x-slot name="title">{{ $sessionTitle }} — {{ __('activity.dashboard_title') }} — VolleyPlay</x-slot>
    <x-slot name="h1">{{ __('activity.dashboard_title') }}</x-slot>
    <x-slot name="h2">{{ $sessionTitle }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">{{ __('profile.show_title') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('activity.index') }}" itemprop="item"><span itemprop="name">{{ __('activity.dashboard_title') }}</span></a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ $sessionTitle }}</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    @php
        $syncStatus = $session->sync_status;
        $dur = $session->duration_sec ?? 0;
        $durStr = $dur >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($dur, 3600), intdiv($dur % 3600, 60), $dur % 60)
            : sprintf('%d:%02d', intdiv($dur, 60), $dur % 60);

        $zoneColors = ['z1' => '#4caf50', 'z2' => '#8bc34a', 'z3' => '#ffc107', 'z4' => '#ff7043', 'z5' => '#f44336'];
        $zoneNames  = [
            'z1' => __('activity.z1_name'),
            'z2' => __('activity.z2_name'),
            'z3' => __('activity.z3_name'),
            'z4' => __('activity.z4_name'),
            'z5' => __('activity.z5_name'),
        ];
        $totalZoneSec = collect($session->time_in_zone ?? [])->sum();
    @endphp

    <div class="container">
        <div class="row row2">

            {{-- Боковое меню --}}
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka">
                        @include('profile._menu', ['activeMenu' => 'activity'])
                    </div>
                </div>
            </div>

            {{-- Основной контент --}}
            <div class="col-lg-8 col-xl-9 order-1">

                {{-- Заголовок + дата --}}
                <div class="ramka">
                    <div class="d-flex justify-between align-center">
                        <div>
                            <div class="b-600 f-18">{{ $sessionTitle }}</div>
                            <div class="f-13" style="opacity:.6">
                                {{ $session->started_at?->setTimezone($userTimezone)->format('d.m.Y H:i') }}
                                @if($session->direction)
                                    · <span class="badge badge-sm {{ $session->direction === 'beach' ? 'badge-orange' : 'badge-blue' }}">
                                        {{ __('activity.filter_' . $session->direction) }}
                                    </span>
                                @endif
                                @if($syncStatus === 'pending')
                                    · <span class="badge badge-sm" style="background:rgba(41,103,186,.15);color:#2967BA">⏳ {{ __('activity.sync_pending') }}</span>
                                @elseif($syncStatus === 'stale')
                                    · <span class="badge badge-sm" style="background:rgba(239,68,68,.15);color:#ef4444">{{ __('activity.sync_stale') }}</span>
                                @elseif($syncStatus === 'settling')
                                    · <span class="badge badge-sm" style="background:rgba(41,103,186,.15);color:#2967BA">⏳ {{ __('activity.sync_settling') }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('activity.index') }}" class="btn btn-sm btn-secondary">← {{ __('activity.back_to_list') }}</a>
                    </div>
                </div>

                @if($syncStatus === 'pending' || $syncStatus === 'stale')
                <div class="ramka mb-1 text-center" style="opacity:.85">
                    <div style="font-size:1.3rem">
                        @if($syncStatus === 'pending') ⏳ {{ __('activity.sync_pending') }} @else {{ __('activity.sync_stale') }} @endif
                    </div>
                    <div class="f-13 mt-1" style="opacity:.6">
                        {{ $syncStatus === 'pending' ? __('activity.sync_pending_hint') : __('activity.sync_stale_hint') }}
                    </div>
                </div>
                @else

                @if($syncStatus === 'settling')
                <div class="ramka mb-1" style="background:rgba(41,103,186,.08)">
                    <div class="f-13" style="color:#2967BA">
                        ⏳ {{ __('activity.sync_settling') }} — {{ __('activity.sync_settling_hint') }}
                    </div>
                </div>
                @endif

                {{-- Скалярные метрики --}}
                <div class="row row2 mb-1">
                    <div class="col-6 col-md-4">
                        <div class="ramka text-center">
                            <div class="f-13" style="opacity:.65">{{ __('activity.avg_hr') }}</div>
                            <div class="b-700 cd" style="font-size:2rem">{{ $session->avg_hr ?? '—' }}</div>
                            <div class="f-12" style="opacity:.5">{{ __('activity.live_bpm') }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="ramka text-center">
                            <div class="f-13" style="opacity:.65">{{ __('activity.max_hr') }}</div>
                            <div class="b-700 cd" style="font-size:2rem">{{ $session->max_hr ?? '—' }}</div>
                            <div class="f-12" style="opacity:.5">{{ __('activity.live_bpm') }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="ramka text-center">
                            <div class="f-13" style="opacity:.65">{{ __('activity.min_hr') }}</div>
                            <div class="b-700 cd" style="font-size:2rem">{{ $session->min_hr ?? '—' }}</div>
                            <div class="f-12" style="opacity:.5">{{ __('activity.live_bpm') }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="ramka text-center">
                            <div class="f-13" style="opacity:.65">{{ __('activity.duration') }}</div>
                            <div class="b-700 cd" style="font-size:1.6rem">{{ $durStr }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="ramka text-center">
                            <div class="f-13" style="opacity:.65">{{ __('activity.load_score') }}</div>
                            <div class="b-700 cd" style="font-size:2rem">
                                {{ $session->load_score ? number_format($session->load_score, 0) : '—' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="ramka text-center">
                            <div class="f-13" style="opacity:.65">{{ __('activity.calories') }}</div>
                            @if($session->calories_kcal !== null)
                                @if($session->calorie_source === 'healthkit')
                                    {{-- Измерено Apple Watch — без знака ≈ --}}
                                    <div class="b-700 cd" style="font-size:2rem">{{ number_format($session->calories_kcal, 0) }}</div>
                                    <div class="f-12" style="opacity:.65">{{ __('activity.calories_measured', ['n' => '']) }}</div>
                                @else
                                    {{-- Расчётно по Keytel (source='keytel' или NULL у старых сессий) --}}
                                    <div class="b-700 cd" style="font-size:2rem">≈{{ number_format($session->calories_kcal, 0) }}</div>
                                    <div class="f-12" style="opacity:.5">ккал</div>
                                @endif
                            @else
                                <div class="b-700 cd" style="font-size:2rem">—</div>
                                <div class="f-12">
                                    <a href="{{ route('profile.athlete') }}">{{ __('activity.set_weight_hint') }}</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Прыжки (capability-aware) --}}
                @if($hasJumps)
                <div class="ramka mb-1">
                    <h3 class="-mt-05">{{ __('activity.jumps_count') }}</h3>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="f-13" style="opacity:.65">{{ __('activity.jumps_count') }}</div>
                            <div class="b-700 cd" style="font-size:2rem">{{ $session->jump_count ?? 0 }}</div>
                        </div>
                        @if($session->jump_avg_height_cm)
                        <div class="col-4">
                            <div class="f-13" style="opacity:.65">{{ __('activity.jump_avg_height') }}</div>
                            <div class="b-700 cd" style="font-size:2rem">{{ number_format($session->jump_avg_height_cm, 1) }}</div>
                            <div class="f-12" style="opacity:.5">см</div>
                        </div>
                        @endif
                        @if($session->jump_max_height_cm)
                        <div class="col-4">
                            <div class="f-13" style="opacity:.65">{{ __('activity.jump_max_height') }}</div>
                            <div class="b-700 cd" style="font-size:2rem">{{ number_format($session->jump_max_height_cm, 1) }}</div>
                            <div class="f-12" style="opacity:.5">см</div>
                        </div>
                        @endif
                    </div>
                </div>
                {{-- График прыжков --}}
                @if($hasJumps && $jumpEvents->count() > 0)
                <div class="ramka mb-1">
                    <h3 class="-mt-05">{{ __('activity.jump_chart_title') }}</h3>
                    <div style="position:relative;height:220px">
                        <canvas id="jump-chart"></canvas>
                    </div>
                </div>
                @endif
                @else
                <div class="ramka mb-1" style="opacity:.55;font-size:.9rem">
                    {{ __('activity.jumps_not_tracked') }}
                </div>
                @endif

                {{-- Шаги --}}
                @if(($session->steps ?? 0) > 0)
                <div class="ramka mb-1 text-center">
                    <div class="f-13" style="opacity:.65">{{ __('activity.steps_label') }}</div>
                    <div class="b-700 cd" style="font-size:2rem">{{ number_format($session->steps, 0, ',', ' ') }}</div>
                </div>
                @endif

                {{-- Кривая ЧСС --}}
                @if(count($samples) > 0)
                <div class="ramka mb-1">
                    <h3 class="-mt-05">{{ __('activity.hr_curve') }}</h3>
                    <div style="position:relative;height:220px">
                        <canvas id="hr-chart"></canvas>
                    </div>
                </div>
                @endif

                {{-- Время в зонах --}}
                @if($session->time_in_zone && $totalZoneSec > 0)
                <div class="ramka mb-1">
                    <h3 class="-mt-05">{{ __('activity.time_in_zones') }}</h3>
                    @foreach(['z1','z2','z3','z4','z5'] as $zKey)
                    @php
                        $sec  = $session->time_in_zone[$zKey] ?? 0;
                        $pct  = $totalZoneSec > 0 ? round($sec / $totalZoneSec * 100) : 0;
                        $minS = sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
                        $zoneRange = ($zones[$zKey]['low'] ?? '?') . '–' . ($zones[$zKey]['high'] ?? '?');
                    @endphp
                    <div class="mb-1">
                        <div class="d-flex justify-between f-13 mb-half">
                            <span>
                                <span class="b-600">{{ $zoneNames[$zKey] }}</span>
                                <span style="opacity:.55;margin-left:4px">{{ $zoneRange }} {{ __('activity.live_bpm') }}</span>
                            </span>
                            <span style="opacity:.7">{{ $minS }} ({{ $pct }}%)</span>
                        </div>
                        <div style="background:rgba(0,0,0,.08);border-radius:4px;height:10px;overflow:hidden">
                            <div style="width:{{ $pct }}%;height:100%;background:{{ $zoneColors[$zKey] }};border-radius:4px;transition:width .4s"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                @endif{{-- /$syncStatus === 'completed' || 'settling' --}}

            </div>{{-- col-lg-8 --}}
        </div>{{-- row --}}
    </div>{{-- container --}}
</x-voll-layout>

@if(count($samples) > 0 || ($hasJumps && $jumpEvents->count() > 0))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Общие данные для обоих графиков
var _sessionStartUtc = @json($session->started_at ? $session->started_at->timestamp : null);
var _userTz          = @json($userTimezone);

// UTC + offset → фактическое время суток в TZ пользователя
function fmtClock(offsetSec) {
    if (_sessionStartUtc === null) return String(offsetSec);
    var ms = (_sessionStartUtc + offsetSec) * 1000;
    return new Intl.DateTimeFormat('ru-RU', {
        hour: '2-digit', minute: '2-digit', hour12: false,
        timeZone: _userTz || 'UTC'
    }).format(new Date(ms));
}
</script>
@endif

@if($hasJumps && $jumpEvents->count() > 0)
<script>
(function() {
    var jumps = @json($jumpEvents->values());

    // linear-ось: x = t_offset_ms/1000 (секунды), ticks и tooltip форматируются через fmtClock
    var jumpData = jumps.map(function(j) {
        return { x: j.t_offset_ms / 1000, y: j.height_cm !== null ? parseFloat(j.height_cm) : null };
    });

    new Chart(document.getElementById('jump-chart').getContext('2d'), {
        type: 'line',
        data: {
            datasets: [{
                data: jumpData,
                borderColor: '#2967BA',
                backgroundColor: 'rgba(41,103,186,.12)',
                borderWidth: 2,
                pointRadius: 4,
                pointBackgroundColor: '#2967BA',
                fill: true,
                tension: 0.3,
                spanGaps: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function(items) { return fmtClock(items[0].parsed.x); },
                        label: function(ctx) { return ctx.parsed.y + ' {{ __('activity.jump_chart_tooltip') }}'; }
                    }
                }
            },
            scales: {
                x: {
                    type: 'linear',
                    ticks: {
                        maxTicksLimit: 8,
                        maxRotation: 0,
                        font: { size: 11 },
                        callback: function(s) { return fmtClock(s); }
                    },
                    title: { display: true, text: '{{ __('activity.jump_chart_x_axis') }}', font: { size: 11 } },
                    grid: { display: false }
                },
                y: {
                    ticks: { font: { size: 11 } },
                    title: { display: true, text: '{{ __('activity.jump_chart_y_axis') }}', font: { size: 11 } },
                    grid: { color: 'rgba(0,0,0,.06)' },
                    beginAtZero: true,
                }
            }
        }
    });
})();
</script>
@endif

@if(count($samples) > 0)
@php
$samplesJson    = json_encode(array_map(fn($s) => ['t' => $s['t_offset_sec'], 'b' => $s['bpm']], $samples));
$zonesJson      = json_encode($zones);
$zoneColorsJson = json_encode($zoneColors);
@endphp
<script>
(function() {
    var samples    = {!! $samplesJson !!};
    var zones      = {!! $zonesJson !!};
    var zoneColors = {!! $zoneColorsJson !!};

    // Ось X — фактическое время суток через fmtClock (та же функция что у jump-chart)
    var labels = samples.map(function(s) { return fmtClock(s.t); });
    var data   = samples.map(function(s) { return s.b; });

    // Градиент по зонам: цвет точки по bpm
    function colorForBpm(bpm) {
        if (bpm >= zones.z5.low) return zoneColors.z5;
        if (bpm >= zones.z4.low) return zoneColors.z4;
        if (bpm >= zones.z3.low) return zoneColors.z3;
        if (bpm >= zones.z2.low) return zoneColors.z2;
        if (bpm >= zones.z1.low) return zoneColors.z1;
        return '#90a4ae';
    }

    var ctx = document.getElementById('hr-chart').getContext('2d');

    // Создаём градиент для заливки
    var gradient = ctx.createLinearGradient(0, 0, 0, 220);
    gradient.addColorStop(0, 'rgba(244,67,54,.25)');
    gradient.addColorStop(1, 'rgba(76,175,80,.05)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                borderColor: '#e53935',
                borderWidth: 1.5,
                pointRadius: 0,
                fill: true,
                backgroundColor: gradient,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function(items) { return fmtClock(samples[items[0].dataIndex].t); },
                        label: function(ctx) { return ctx.parsed.y + ' {{ __('activity.live_bpm') }}'; }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxTicksLimit: 8,
                        maxRotation: 0,
                        font: { size: 11 }
                    },
                    title: { display: true, text: '{{ __('activity.hr_chart_x_axis') }}', font: { size: 11 } },
                    grid: { display: false }
                },
                y: {
                    ticks: { font: { size: 11 } },
                    grid: { color: 'rgba(0,0,0,.06)' }
                }
            }
        }
    });
})();
</script>
@endif
