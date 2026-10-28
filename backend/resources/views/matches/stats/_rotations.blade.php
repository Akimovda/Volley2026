{{-- Вкладка/блок PDF «Расстановки» (блок C): по каждой команде Р1…РN — розыгрыши, BP %, SO %, +/–, эйсы, ошибки, подающий.
     Данные — $stats['rotations'] (MatchRotationStatsService; null — учёт состава не велся → блок не подключается). --}}
@php
    $rot = $stats['rotations'];
    $print = $print ?? false;
    $teamsInfo = $stats['teams'];
    $styles = app(\App\Services\Broadcast\TeamStyleService::class);
    $colorOf = function (string $side) use ($teamsInfo, $styles) {
        $t = \App\Models\EventTeam::find($teamsInfo[$side]['id'] ?? 0);
        return $t ? $styles->colorFor($t) : ($side === 'home' ? '#2967BA' : '#E7612F');
    };
    $scopes = $print ? ['all'] : array_merge(['all'], array_map('strval', $rot['sets']));
    $lbl = fn (string $side, int $k) => $rot['method'][$side] === 'setter'
        ? __('match_stats.rot_label_setter', ['k' => $k]) : __('match_stats.rot_label_turn', ['k' => $k]);
    $pct = fn ($v) => $v === null ? '—' : $v . '%';
@endphp
<div class="mx-rot" data-mx-rot>
    <div class="mx-sub">
        @if($rot['method']['home'] === 'setter' && $rot['method']['away'] === 'setter')
            {{ __('match_stats.rot_hint_setter') }}
        @elseif($rot['method']['home'] === 'rotation' && $rot['method']['away'] === 'rotation')
            {{ __('match_stats.rot_hint_turn') }}
        @else
            {{ __('match_stats.rot_hint_setter') }} · {{ __('match_stats.rot_hint_turn') }}
        @endif
    </div>
    @if(!$print)
    <div class="mx-seg" data-mx-seg="rot-team">
        @foreach(['home', 'away'] as $side)
        @php $c = $colorOf($side); @endphp
        <button type="button" data-rot-team="{{ $side }}" class="{{ $side === 'home' ? 'is-active' : '' }}" style="--rot-c:{{ $c }}">{{ $teamsInfo[$side]['name'] }}</button>
        @endforeach
    </div>
    @if(count($scopes) > 2)
    <div class="mx-seg" data-mx-seg="rot-scope">
        <button type="button" data-rot-scope="all" class="is-active">{{ __('match_stats.rot_all') }}</button>
        @foreach($rot['sets'] as $sn)
        <button type="button" data-rot-scope="{{ $sn }}">{{ __('match_stats.rot_set', ['n' => $sn]) }}</button>
        @endforeach
    </div>
    @endif
    @endif

    @foreach(['home', 'away'] as $side)
    @foreach($scopes as $scope)
    @php $d = $rot['teams'][$side]['scopes'][$scope]; $total = array_sum(array_column($d['rows'], 'plays')); @endphp
    <div class="mx-rot-pane" data-rot-pane data-rot-pane-team="{{ $side }}" data-rot-pane-scope="{{ $scope }}" @if(!$print && !($side === 'home' && $scope === 'all')) style="display:none" @endif>
        @if($print)<div class="mx-team-title mx-team-title--{{ $side }}">{{ $teamsInfo[$side]['name'] }}</div>@endif
        <div class="mx-sub">{{ $rot['method'][$side] === 'setter' ? __('match_stats.rot_method_setter') : __('match_stats.rot_method_turn') }}</div>
        @if($d['insight']['weak'])
        <div class="mx-rot-ins"><b>{{ __('match_stats.rot_weak', ['k' => $d['insight']['weak']['k'], 'pct' => $d['insight']['weak']['pct'], 'won' => $d['insight']['weak']['won'], 'plays' => $d['insight']['weak']['plays']]) }}</b><br>
            {{ __('match_stats.rot_strong', ['k' => $d['insight']['strong']['k'], 'pct' => $d['insight']['strong']['pct'], 'won' => $d['insight']['strong']['won'], 'plays' => $d['insight']['strong']['plays']]) }}</div>
        @endif
        @if($total === 0)
        <div class="mx-note">{{ __('match_stats.rot_empty_team') }}</div>
        @else
        <div class="mx-scroll">
        <table class="mx-table mx-rot-table">
            <thead>
                <tr class="mx-rot-grp">
                    <th rowspan="2"></th><th rowspan="2">{{ __('match_stats.rot_col_plays') }}</th>
                    <th colspan="3">{{ __('match_stats.rot_col_serve') }}</th><th colspan="3">{{ __('match_stats.rot_col_recv') }}</th>
                    <th rowspan="2">{{ __('match_stats.rot_col_diff') }}</th><th rowspan="2">{{ __('match_stats.rot_col_aces') }}</th>
                    <th rowspan="2">{{ __('match_stats.rot_col_missed') }}</th><th rowspan="2">{{ __('match_stats.rot_col_errors') }}</th><th rowspan="2">{{ __('match_stats.rot_col_server') }}</th>
                </tr>
                <tr>
                    <th>{{ __('match_stats.rot_col_plays') }}</th><th>{{ __('match_stats.rot_col_won') }}</th><th>{{ __('match_stats.rot_col_bp') }}</th>
                    <th>{{ __('match_stats.rot_col_plays') }}</th><th>{{ __('match_stats.rot_col_won') }}</th><th>{{ __('match_stats.rot_col_so') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($d['rows'] as $r)
                @php
                    $empty = $r['plays'] === 0;
                    $isWeak = $d['insight']['weak'] && $d['insight']['weak']['k'] === $r['k'];
                    $isStrong = $d['insight']['strong'] && $d['insight']['strong']['k'] === $r['k'];
                @endphp
                <tr class="{{ $empty ? 'mx-rot-empty' : '' }} {{ $isWeak ? 'mx-rot-weak' : '' }} {{ $isStrong ? 'mx-rot-strong' : '' }}">
                    <td>{{ $lbl($side, $r['k']) }}@if($isWeak) <small class="mx-rot-mark">{{ __('match_stats.rot_weak_mark') }}</small>@elseif($isStrong) <small class="mx-rot-mark">{{ __('match_stats.rot_strong_mark') }}</small>@endif</td>
                    @if($empty)
                    @for($i = 0; $i < 11; $i++)<td>—</td>@endfor
                    @else
                    <td>{{ $r['plays'] }}</td>
                    <td>{{ $r['serve_plays'] }}</td><td>{{ $r['serve_won'] }}</td><td>{{ $pct($r['serve_pct']) }}</td>
                    <td>{{ $r['recv_plays'] }}</td><td>{{ $r['recv_won'] }}</td><td>{{ $pct($r['recv_pct']) }}</td>
                    <td class="mx-bal {{ $r['diff'] < 0 ? 'is-neg' : '' }}">{{ $r['diff'] > 0 ? '+' . $r['diff'] : $r['diff'] }}</td>
                    <td>{{ $r['aces'] }}</td><td>{{ $r['missed_aces'] }}</td><td class="{{ $r['errors'] > 0 ? 'is-err' : '' }}">{{ $r['errors'] }}</td>
                    <td class="mx-rot-srv" @if(!empty($r['servers_other'])) title="{{ implode(', ', $r['servers_other']) }}" @endif>{{ $r['server'] ?? '—' }}@if(!empty($r['servers_other'])) <small>+{{ count($r['servers_other']) }}</small>@endif</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
    @endforeach
    @endforeach
    <div class="mx-note">{{ __('match_stats.rot_legend', ['n' => \App\Services\MatchRotationStatsService::MIN_RECV_FOR_INSIGHT]) }}</div>
</div>
@if(!$print)
<script>
(function () {
    if (window.mxRotApply) { return; }
    var st = {team: 'home', scope: 'all'};
    function apply() {
        document.querySelectorAll('[data-mx-seg="rot-team"] button').forEach(function (b) {
            var on = b.getAttribute('data-rot-team') === st.team; b.classList.toggle('is-active', on);
            b.style.background = on ? b.style.getPropertyValue('--rot-c') : ''; b.style.borderColor = b.style.getPropertyValue('--rot-c'); b.style.color = on ? '#fff' : '';
        });
        var sb = document.querySelectorAll('[data-mx-seg="rot-scope"] button');
        if (sb.length && !Array.prototype.some.call(sb, function (b) { return b.getAttribute('data-rot-scope') === st.scope; })) { st.scope = 'all'; }
        sb.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-rot-scope') === st.scope); });
        document.querySelectorAll('[data-rot-pane]').forEach(function (p) {
            p.style.display = (p.getAttribute('data-rot-pane-team') === st.team && p.getAttribute('data-rot-pane-scope') === st.scope) ? '' : 'none';
        });
    }
    window.mxRotApply = apply;
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target : null; if (!t) { return; }
        var tb = t.closest('[data-rot-team]'); if (tb) { st.team = tb.getAttribute('data-rot-team'); apply(); return; }
        var sc = t.closest('[data-rot-scope]'); if (sc) { st.scope = sc.getAttribute('data-rot-scope'); apply(); }
    });
    apply();
})();
</script>
@endif
