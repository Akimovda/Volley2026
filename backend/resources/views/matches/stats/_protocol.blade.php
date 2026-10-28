@php
    $teams = $stats['teams'];
    $players = collect($stats['players']['home'])->merge($stats['players']['away'])
        ->filter(fn ($r) => $r['scopes']['all']['plus'] + $r['scopes']['all']['minus'] > 0)->sortBy('name')->values();
    $score = fn ($e) => $e['score_home'] . ':' . $e['score_away'];
@endphp
<div class="mx-filters">
    <select data-mx-filter="team">
        <option value="">{{ __('match_stats.proto_all_teams') }}</option>
        <option value="home">{{ $teams['home']['name'] }}</option>
        <option value="away">{{ $teams['away']['name'] }}</option>
    </select>
    <select data-mx-filter="player">
        <option value="">{{ __('match_stats.proto_all_players') }}</option>
        @foreach($players as $p)
        <option value="{{ $p['user_id'] }}">{{ $p['name'] }}</option>
        @endforeach
    </select>
</div>

@forelse($stats['sets'] as $sn => $set)
@php $isOpen = $loop->last; @endphp
<div class="mx-set-h mx-set-toggle {{ $isOpen ? 'is-open' : '' }}" data-set-h="{{ $sn }}" role="button" tabindex="0" aria-expanded="{{ $isOpen ? 'true' : 'false' }}">{{ __('match_stats.set_n', ['n' => $sn]) }} · {{ $set['score']['home'] }}:{{ $set['score']['away'] }}</div>
<div class="mx-set-body {{ $isOpen ? '' : 'is-collapsed' }}" data-set-body="{{ $sn }}">
@foreach($set['rallies'] as $ev)
    @if($ev['kind'] === 'point')
    @php
        $side = $ev['side'] ?? 'home';
        $who = $ev['player'];
        if ($ev['is_error']) {
            $errSide = $ev['player_side'] ?? ($side === 'home' ? 'away' : 'home');
            $whoTxt = $who ? $who . ' (' . $teams[$errSide]['name'] . ')' : $teams[$errSide]['name'];
        } else {
            $whoTxt = $who ?: $teams[$side]['name'];
        }
        $text = $ev['action'] === 'unattributed' ? __('match_stats.act_unattributed') : __('match_stats.act_' . $ev['action'], ['who' => $whoTxt]);
        if (($ev['action'] === 'kill') && !empty($ev['attack_type'])) {
            // «Атака (накат) — Игрок»
            $text = __('match_stats.act_kill_t', ['type' => __('match_stats.at_' . $ev['attack_type']), 'who' => $whoTxt]);
        }
    @endphp
    <div class="mx-ev mx-ev--{{ $side }}" data-set="{{ $sn }}" data-side="{{ $side }}" data-player="{{ $ev['player_id'] }}">
        <div class="mx-ev-n">{{ $ev['rn'] }}</div>
        <div class="mx-ev-score"><span class="{{ $side === 'home' ? 'w' : '' }}">{{ $ev['score_home'] }}</span>:<span class="{{ $side === 'away' ? 'w' : '' }}">{{ $ev['score_away'] }}</span></div>
        <div class="mx-ev-txt"><span class="t t--{{ $side }}">{{ $teams[$side]['name'] }}</span> · <span class="{{ $ev['is_error'] ? 'err' : '' }}">{{ $text }}</span>@if(!empty($canSeeRecorder) && $ev['by'])<span class="mx-ev-by">{{ __('match_stats.recorded_by', ['name' => $ev['by']]) }}</span>@endif</div>
    </div>
    @else
    @php
        $text = match ($ev['type']) {
            'substitution' => __('match_stats.lu_sub', ['in' => $ev['in'] ?? '—', 'out' => $ev['out'] ?? '—']),
            'libero_in'    => __('match_stats.lu_libero_in', ['in' => $ev['in'] ?? '—', 'out' => $ev['out'] ?? '—']),
            'rotate'       => __('match_stats.lu_rotate', ['delta' => ($ev['delta'] ?? 0) > 0 ? '+' . $ev['delta'] : (string) ($ev['delta'] ?? 0)]),
            'swap'         => __('match_stats.lu_swap', ['a' => $ev['out'] ?? '—', 'b' => $ev['in'] ?? '—']),
            default        => __('match_stats.lu_libero_out', ['in' => $ev['in'] ?? '—', 'out' => $ev['out'] ?? '—']),
        };
    @endphp
    <div class="mx-ev mx-ev--lineup" data-set="{{ $sn }}" data-side="{{ $ev['side'] }}" data-players="{{ $ev['in_id'] }},{{ $ev['out_id'] }}">
        <div class="mx-ev-n">⇄</div>
        <div class="mx-ev-score">{{ $score($ev) }}</div>
        <div class="mx-ev-txt"><span class="t t--{{ $ev['side'] }}">{{ $teams[$ev['side']]['name'] }}</span> · {{ $text }}@if(!empty($canSeeRecorder) && $ev['by'])<span class="mx-ev-by">{{ __('match_stats.recorded_by', ['name' => $ev['by']]) }}</span>@endif</div>
    </div>
    @endif
@endforeach
</div>
@empty
<div class="mx-empty">{{ __('match_stats.no_rallies') }}</div>
@endforelse
<div class="mx-empty" data-mx-proto-empty style="display:none">{{ __('match_stats.proto_empty') }}</div>
