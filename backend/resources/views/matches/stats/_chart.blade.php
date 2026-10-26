@php
    /** Ход сета: разница в счёте после каждого розыгрыша (inline SVG, без JS-библиотек). */
    $diff = $set['diff'];
    $n = max(1, count($diff) - 1);
    $W = 420; $H = 240; $pl = 30; $pr = 8; $pt = 18; $pb = 24;
    $maxAbs = max(4, collect($diff)->map(fn ($d) => abs($d))->max());
    $plotH = $H - $pt - $pb;
    $mid = $pt + $plotH / 2;
    $half = $plotH / 2 * 0.9;
    $xs = fn ($i) => round($pl + $i / $n * ($W - $pl - $pr), 1);
    $ys = fn ($d) => round($mid - $d / $maxAbs * $half, 1);
    $pts = [];
    foreach ($diff as $i => $d) { $pts[] = $xs($i) . ',' . $ys($d); }
    $line = 'M' . implode(' L', $pts);
    $area = 'M' . $xs(0) . ',' . $mid . ' L' . implode(' L', $pts) . ' L' . $xs($n) . ',' . $mid . ' Z';
    $cid = 'mx' . $set['number'] . '-' . ($uid ?? 'p');
    $ticks = [];
    for ($t = 10; $t < $n; $t += 10) { $ticks[] = $t; }
@endphp
<svg class="mx-chart" viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ __('match_stats.chart_title') }}">
    <defs>
        <clipPath id="{{ $cid }}-top"><rect x="0" y="0" width="{{ $W }}" height="{{ $mid }}"/></clipPath>
        <clipPath id="{{ $cid }}-bot"><rect x="0" y="{{ $mid }}" width="{{ $W }}" height="{{ $H - $mid }}"/></clipPath>
    </defs>
    @foreach([-1, -0.5, 0.5, 1] as $g)
        <line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ round($mid - $g * $half, 1) }}" y2="{{ round($mid - $g * $half, 1) }}" stroke="currentColor" stroke-opacity=".08"/>
        <text x="{{ $pl - 4 }}" y="{{ round($mid - $g * $half + 4, 1) }}" text-anchor="end">{{ ($g > 0 ? '+' : '−') . (int) round(abs($g) * $maxAbs) }}</text>
    @endforeach
    <line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ $mid }}" y2="{{ $mid }}" stroke="currentColor" stroke-opacity=".35" stroke-dasharray="4 3"/>
    <text x="{{ $pl - 4 }}" y="{{ $mid + 4 }}" text-anchor="end">0</text>
    <path d="{{ $area }}" fill="#2967BA" fill-opacity=".22" clip-path="url(#{{ $cid }}-top)"/>
    <path d="{{ $area }}" fill="#E7612F" fill-opacity=".22" clip-path="url(#{{ $cid }}-bot)"/>
    <path d="{{ $line }}" fill="none" stroke="#2967BA" stroke-width="2" stroke-linejoin="round" clip-path="url(#{{ $cid }}-top)"/>
    <path d="{{ $line }}" fill="none" stroke="#E7612F" stroke-width="2" stroke-linejoin="round" clip-path="url(#{{ $cid }}-bot)"/>
    @foreach($ticks as $t)
        <text x="{{ $xs($t) }}" y="{{ $H - 7 }}" text-anchor="middle">{{ $t }}</text>
    @endforeach
    @foreach(['home' => '#2967BA', 'away' => '#E7612F'] as $side => $color)
        @php $ml = $set['max_lead'][$side]; @endphp
        @if($ml['lead'] > 0)
            @php $cx = $xs($ml['rally']); $cy = $ys($side === 'home' ? $ml['lead'] : -$ml['lead']); $ly = $side === 'home' ? $cy - 8 : $cy + 16; @endphp
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="4.5" fill="{{ $color }}" stroke="#fff" stroke-width="1.5"/>
            <text class="mx-lead-txt" x="{{ min(max($cx, $pl + 14), $W - $pr - 14) }}" y="{{ $ly }}" text-anchor="middle" style="fill:{{ $color }}">+{{ $ml['lead'] }}</text>
        @endif
    @endforeach
</svg>
