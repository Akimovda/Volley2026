{{-- Табло-счёт (перекидные плитки): $home, $away; необязательно: $mini (компактно, для полосы счёта), $size ('sm' — для строк матчей,
     'tv' — крупное для ТВ-режима), $digits (число цифр на команду, по умолчанию 2: 00 : 00; для счёта по сетам — 1), $id (id корневого блока).
     Анимацию переворота рисует скрипт (rally_screen / flip_score_js). --}}
@php
	$__digits = max(1, (int) ($digits ?? 2));
	$__fd = fn ($n) => str_split(str_pad((string) max(0, (int) $n), $__digits, '0', STR_PAD_LEFT));
	$__cls = !empty($mini) ? ' rw-flipscore--mini' : (($size ?? null) === 'sm' ? ' rw-flipscore--sm' : (($size ?? null) === 'tv' ? ' rw-flipscore--tv' : ' px-2'));
@endphp
<div class="rw-flipscore{{ $__cls }}" @if(!empty($id)) id="{{ $id }}" @endif role="img" aria-label="{{ $home }} : {{ $away }}" data-home="{{ $home }}" data-away="{{ $away }}" data-digits="{{ $__digits }}">
	@foreach(['home' => $home, 'away' => $away] as $__side => $__val)
	@if($__side === 'away')<span class="rw-fcolon" aria-hidden="true">:</span>@endif
	<span class="rw-flipgroup" data-side="{{ $__side }}" aria-hidden="true">@foreach($__fd($__val) as $__d)<span class="rw-fd" data-d="{{ $__d }}"><span class="rw-fd-t"><i>{{ $__d }}</i></span><span class="rw-fd-b"><i>{{ $__d }}</i></span></span>@endforeach</span>
	@endforeach
</div>
