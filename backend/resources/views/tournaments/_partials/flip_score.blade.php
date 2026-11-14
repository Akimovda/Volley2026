{{-- Табло-счёт (перекидные плитки): $home, $away; необязательно $mini (компактный вариант для полосы счёта). Анимацию переворота рисует скрипт в rally_screen. --}}
@php $__fd = fn ($n) => str_split(str_pad((string) max(0, (int) $n), 2, '0', STR_PAD_LEFT)); @endphp
<div class="rw-flipscore{{ !empty($mini) ? ' rw-flipscore--mini' : ' px-2' }}" role="img" aria-label="{{ $home }} : {{ $away }}" data-home="{{ $home }}" data-away="{{ $away }}">
	@foreach(['home' => $home, 'away' => $away] as $__side => $__val)
	@if($__side === 'away')<span class="rw-fcolon" aria-hidden="true">:</span>@endif
	<span class="rw-flipgroup" data-side="{{ $__side }}" aria-hidden="true">@foreach($__fd($__val) as $__d)<span class="rw-fd" data-d="{{ $__d }}"><span class="rw-fd-t"><i>{{ $__d }}</i></span><span class="rw-fd-b"><i>{{ $__d }}</i></span></span>@endforeach</span>
	@endforeach
</div>
