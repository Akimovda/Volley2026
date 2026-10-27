@php
    $teams = $stats['teams'];
    $uid = $uid ?? 'p';
    $runPoints = fn (int $n) => trans_choice('match_stats.run_points', $n, ['n' => $n]);
    $o = $stats['origin'];
    $bars = [
        ['key' => 'attack', 'label' => __('match_stats.origin_attack'), 'sub' => false],
        ['key' => 'block', 'label' => __('match_stats.origin_block'), 'sub' => false],
        ['key' => 'ace', 'label' => __('match_stats.origin_ace'), 'sub' => false],
        ['key' => 'opp_total', 'label' => __('match_stats.origin_opp'), 'sub' => false],
        ['key' => 'opp_serve', 'label' => __('match_stats.origin_opp_serve'), 'sub' => true],
        ['key' => 'opp_attack', 'label' => __('match_stats.origin_opp_attack'), 'sub' => true],
        ['key' => 'opp_reception', 'label' => __('match_stats.origin_opp_reception'), 'sub' => true],
        ['key' => 'opp_block', 'label' => __('match_stats.origin_opp_block'), 'sub' => true],
        ['key' => 'unattributed', 'label' => __('match_stats.origin_unattributed'), 'sub' => false],
        ['key' => 'total', 'label' => __('match_stats.origin_total'), 'sub' => false],
    ];
    // разбивка атаки по подтипам (удар · накат · скидка) — только если в матче есть хотя бы одно уточнение
    $atLine = fn (array $x) => __('match_stats.at_breakdown', ['spike' => $x['spike'] ?? 0, 'shot' => $x['shot'] ?? 0, 'dump' => $x['dump'] ?? 0, 'none' => $x['none'] ?? 0]);
    $hasAt = collect(['home', 'away'])->contains(fn ($s) => (($o[$s]['at']['spike'] ?? 0) + ($o[$s]['at']['shot'] ?? 0) + ($o[$s]['at']['dump'] ?? 0)) > 0);
    $sv = $stats['serve'];
    $excluded = collect($stats['sets'])->filter(fn ($s) => !$s['serve']['reliable']);
@endphp

<div class="ramka">
    <h2 class="-mt-05">{{ __('match_stats.chart_title') }}</h2>
    <div class="mx-sub">{{ __('match_stats.chart_hint', ['home' => $teams['home']['name'], 'away' => $teams['away']['name']]) }}</div>
    @if(count($stats['sets']) > 1 && empty($print))
    <div class="mx-seg" data-mx-seg="chart">
        @foreach($stats['sets'] as $sn => $set)
        <button type="button" data-set="{{ $sn }}" class="{{ $loop->first ? 'is-active' : '' }}">{{ __('match_stats.set_n', ['n' => $sn]) }} · {{ $set['score']['home'] }}:{{ $set['score']['away'] }}</button>
        @endforeach
    </div>
    @endif
    @forelse($stats['sets'] as $sn => $set)
    <div class="mx-chart-wrap {{ $loop->first ? 'is-active' : '' }}" data-chart-set="{{ $sn }}">
        @if(!empty($print))<div class="mx-set-h">{{ __('match_stats.set_n', ['n' => $sn]) }} · {{ $set['score']['home'] }}:{{ $set['score']['away'] }}</div>@endif
        @include('matches.stats._chart', ['set' => $set, 'uid' => $uid])
        <div class="mx-caption">
            @foreach(['home', 'away'] as $side)
                @if($set['max_lead'][$side]['lead'] > 0)
                <div><span class="mx-dot mx-dot--{{ $side }}"></span>{{ __('match_stats.max_lead', ['team' => $teams[$side]['name'], 'n' => $set['max_lead'][$side]['lead'], 'score' => $set['max_lead'][$side]['home'] . ':' . $set['max_lead'][$side]['away']]) }}</div>
                @endif
            @endforeach
            @if($set['best_run'])
            <div>{{ __('match_stats.best_run', [
                'team'   => $teams[$set['best_run']['side']]['name'],
                'points' => $runPoints($set['best_run']['len']),
                'from'   => $set['best_run']['from'][0] . ':' . $set['best_run']['from'][1],
                'to'     => $set['best_run']['to'][0] . ':' . $set['best_run']['to'][1],
            ]) }}</div>
            @endif
        </div>
    </div>
    @empty
    <div class="mx-empty">{{ __('match_stats.no_rallies') }}</div>
    @endforelse
</div>

<div class="ramka">
    <h2 class="-mt-05">{{ __('match_stats.origin_title') }}</h2>
    <div class="ms-bars mx-origin">
        @foreach($bars as $bar)
        @php
            $hv = $o['home'][$bar['key']]; $av = $o['away'][$bar['key']];
            $max = max($hv, $av, 1);
            $isErr = str_starts_with($bar['key'], 'opp');
        @endphp
        <div class="ms-bar-row {{ $bar['sub'] ? 'mx-origin-sub' : '' }}">
            <div class="ms-bar-value ms-bar-value--home">{{ $hv }}</div>
            <div class="ms-bar-track ms-bar-track--home"><div class="ms-bar-fill ms-bar-fill--home" style="width:{{ round($hv / $max * 100) }}%"></div></div>
            <div class="ms-bar-label">{{ $bar['label'] }}</div>
            <div class="ms-bar-track ms-bar-track--away"><div class="ms-bar-fill ms-bar-fill--away" style="width:{{ round($av / $max * 100) }}%"></div></div>
            <div class="ms-bar-value ms-bar-value--away">{{ $av }}</div>
        </div>
        @if($bar['key'] === 'attack' && $hasAt)
        <div class="mx-at-line">
            @foreach(['home', 'away'] as $side)
            @if(($o[$side]['attack'] ?? 0) > 0)<div><span class="mx-dot mx-dot--{{ $side }}"></span>{{ $teams[$side]['name'] }} — {{ $atLine($o[$side]['at'] ?? []) }}</div>@endif
            @endforeach
        </div>
        @endif
        @endforeach
    </div>
    <div class="mx-legend">
        <span><span class="mx-dot mx-dot--home"></span>{{ $teams['home']['name'] }}</span>
        <span><span class="mx-dot mx-dot--away"></span>{{ $teams['away']['name'] }}</span>
    </div>
</div>

<div class="ramka">
    <h2 class="-mt-05">{{ __('match_stats.serve_title') }}</h2>
    @if($sv['sets_used'] === 0)
        <div class="mx-empty">{{ __('match_stats.serve_no_data') }}</div>
    @else
        <div class="mx-sub">{{ __('match_stats.serve_sets_used', ['used' => $sv['sets_used'], 'total' => $sv['sets_total']]) }}</div>
        @foreach([['serve', 'serve_on_serve'], ['recv', 'serve_on_recv']] as [$k, $label])
        <div class="mx-sbar-row">
            <div class="mx-sbar-title"><span>{{ __('match_stats.' . $label) }}</span></div>
            @foreach(['home', 'away'] as $side)
            @php $t = $sv['teams'][$side]; $pct = $t[$k . '_pct']; @endphp
            <div class="mx-sbar-line">
                <div class="mx-sbar-val">{{ $pct === null ? '—' : $pct . '%' }}</div>
                <div>
                    <div class="mx-sbar-track"><div class="mx-sbar-fill mx-sbar-fill--{{ $side }}" style="width:{{ $pct ?? 0 }}%"></div></div>
                    <small>{{ $teams[$side]['name'] }} · {{ __('match_stats.serve_of', ['won' => $t[$k . '_won'], 'total' => $t[$k . '_total']]) }}</small>
                </div>
            </div>
            @endforeach
        </div>
        @endforeach
    @endif
    @foreach($excluded as $sn => $set)
        <div class="mx-note mx-note--warn">{{ __('match_stats.serve_excluded', ['n' => $sn, 'reason' => __('match_stats.reason_' . $set['serve']['reason'])]) }}</div>
    @endforeach
    <div class="mx-note">{{ __('match_stats.serve_note') }}</div>
</div>
