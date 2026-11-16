{{-- Медаль с цифрой места (1–3): золото, серебро, бронза. <x-rank-medal :rank="1" size="3rem" /> --}}
@props(['rank' => 1, 'size' => '3rem'])
@php
    $r = max(1, min(3, (int) $rank));
    $pal = [
        1 => ['from' => '#fff3b0', 'to' => '#f59e0b', 'ring' => '#b45309', 'ink' => '#7c4a03', 'ribbon' => ['#ef4444', '#b91c1c']],
        2 => ['from' => '#ffffff', 'to' => '#9ca3af', 'ring' => '#6b7280', 'ink' => '#374151', 'ribbon' => ['#3b82f6', '#1d4ed8']],
        3 => ['from' => '#ffd9b0', 'to' => '#c2763a', 'ring' => '#7c2d12', 'ink' => '#5a2208', 'ribbon' => ['#22c55e', '#15803d']],
    ][$r];
    $gid = 'rm' . $r;
@endphp
<span {{ $attributes->merge(['class' => 'rank-medal']) }} style="display:inline-flex;width:calc({{ $size }} * .86);height:{{ $size }};vertical-align:middle;flex-shrink:0" role="img" aria-label="{{ $r }}">
<svg viewBox="0 0 26 30" width="100%" height="100%" aria-hidden="true" style="fill:none">
    <defs><linearGradient id="{{ $gid }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{{ $pal['from'] }}"/><stop offset="1" stop-color="{{ $pal['to'] }}"/></linearGradient></defs>
    <path d="M7 1h5l3.2 8.6-5 1.4z" fill="{{ $pal['ribbon'][0] }}" style="fill:{{ $pal['ribbon'][0] }}"/>
    <path d="M19 1h-5l-3.2 8.6 5 1.4z" fill="{{ $pal['ribbon'][1] }}" style="fill:{{ $pal['ribbon'][1] }}"/>
    <circle cx="13" cy="19" r="9.4" fill="url(#{{ $gid }})" stroke="{{ $pal['ring'] }}" stroke-width="1.2" style="fill:url(#{{ $gid }})"/>
    <circle cx="13" cy="19" r="7" fill="none" stroke="rgba(255,255,255,.55)" stroke-width=".8"/>
    <text x="13" y="23.4" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="12.5" font-weight="800" style="fill:{{ $pal['ink'] }}">{{ $r }}</text>
</svg>
</span>
