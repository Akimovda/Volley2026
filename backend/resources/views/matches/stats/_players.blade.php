@php
    $teams = $stats['teams'];
    $showRecv = $stats['has_reception_errors'];
    $showBlk = $stats['has_block_errors'];
    $scopes = array_merge(['all'], $stats['set_numbers']);
    $cols = array_filter([
        'attack' => __('match_stats.col_attack'), 'block' => __('match_stats.col_block'), 'ace' => __('match_stats.col_ace'),
        'serve_err' => __('match_stats.col_err_serve'), 'attack_err' => __('match_stats.col_err_attack'),
        'reception_err' => $showRecv ? __('match_stats.col_err_reception') : null,
        'block_err' => $showBlk ? __('match_stats.col_err_block') : null,
    ]);
    $print = $print ?? false;
    if ($print) { $scopes = ['all']; }
    // подсказка в ячейке «Атака»: из них удар · накат · скидка · без уточнения (если в матче есть хотя бы одно уточнение)
    $atKeys = \App\Models\MatchRallyEvent::ATTACK_TYPES;
    $atTitle = fn (array $x) => __('match_stats.at_breakdown', collect($atKeys)->mapWithKeys(fn ($k) => [$k => $x[$k] ?? 0])->all() + ['none' => $x['none'] ?? 0]);
    $hasAt = collect(['home', 'away'])->flatMap(fn ($s) => $stats['players'][$s])->contains(fn ($r) => collect($atKeys)->sum(fn ($k) => $r['scopes']['all']['at'][$k] ?? 0) > 0);
@endphp
@if(!$print && count($scopes) > 2)
<div class="mx-seg" data-mx-seg="players">
    <button type="button" data-scope="all" class="is-active">{{ __('match_stats.scope_match') }}</button>
    @foreach($stats['set_numbers'] as $sn)
    <button type="button" data-scope="{{ $sn }}">{{ __('match_stats.set_n', ['n' => $sn]) }}</button>
    @endforeach
</div>
@endif
<div class="mx-sub">{{ __('match_stats.players_balance_hint') }}</div>

@foreach(['home', 'away'] as $side)
<div class="mx-team-title mx-team-title--{{ $side }}">{{ $teams[$side]['name'] }}</div>
<div class="mx-scroll">
<table class="mx-table" data-mx-ptable>
    <thead>
        <tr>
            <th>{{ __('match_stats.col_player') }}</th>
            @foreach($cols as $k => $label)<th>{{ $label }}</th>@endforeach
            <th @if(!$print) class="mx-sort" data-mx-sort @endif>{{ __('match_stats.col_balance') }}@if(!$print) ↓@endif</th>
        </tr>
    </thead>
    @foreach($scopes as $scope)
    @php
        $rows = collect($stats['players'][$side])
            ->map(fn ($r) => ['r' => $r, 'c' => $r['scopes'][$scope] ?? null])
            ->filter(fn ($x) => $scope === 'all' || ($x['c'] && ($x['c']['plus'] + $x['c']['minus']) > 0))
            ->sortByDesc(fn ($x) => [$x['c']['balance'] ?? 0, $x['c']['plus'] ?? 0])
            ->values();
    @endphp
    <tbody data-scope="{{ $scope }}" @if($scope !== 'all') style="display:none" @endif>
        @forelse($rows as $x)
        @php $c = $x['c'] ?? array_fill_keys(['attack','block','ace','serve_err','attack_err','reception_err','block_err','plus','minus','balance'], 0); @endphp
        <tr data-player="{{ $x['r']['user_id'] }}" class="{{ ($c['plus'] + $c['minus']) === 0 ? 'mx-muted' : '' }} {{ ($highlight ?? null) === $x['r']['user_id'] ? 'mx-row--hl' : '' }}">
            <td><a href="{{ route('users.show', $x['r']['user_id']) }}" class="blink">{{ $x['r']['name'] }}</a>@if(!empty($x['r']['role'])) <span class="mx-role" style="opacity:.6;font-size:.85em">{{ $x['r']['role'] }}</span>@endif</td>
            @foreach($cols as $k => $label)
            <td class="{{ str_ends_with($k, '_err') && $c[$k] > 0 ? 'is-err' : ($c[$k] === 0 ? 'is-zero' : '') }}"@if($k === 'attack' && $hasAt && $c['attack'] > 0) title="{{ $atTitle($c['at'] ?? []) }}"@endif>@if($k === 'attack' && $hasAt && $c['attack'] > 0)<span class="mx-has-tip">{{ $c[$k] }}</span>@else{{ $c[$k] }}@endif</td>
            @endforeach
            <td class="mx-bal {{ $c['balance'] < 0 ? 'is-neg' : '' }}" data-bal="{{ $c['balance'] }}">{{ $c['balance'] > 0 ? '+' . $c['balance'] : $c['balance'] }}</td>
        </tr>
        @empty
        <tr><td colspan="{{ count($cols) + 2 }}" class="mx-empty">{{ __('match_stats.players_empty') }}</td></tr>
        @endforelse
    </tbody>
    @endforeach
</table>
</div>
@endforeach
