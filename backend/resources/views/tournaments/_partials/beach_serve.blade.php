{{-- Порядок подачи в пляжке (2×2). $part = 'start' — «Подаёт первым» (две пары, в каждой две карточки игроков; тап = пара подаёт и этот игрок подаёт первым,
     под карточкой поле «№»); $part = 'panel' — заметная панель «Кто подаёт у <пара>?» над кнопками очков (вторая пара впервые получила подачу).
     Данные — $serve (BeachServeOrderService::forRally). JS — beach_serve_js. --}}
@if($part === 'start' && !empty($serve['can_pick_first']))
<div class="card mb-2 so-start">
	<div class="b-700 f-16 mb-05" style="text-align:center">{{ __('tournaments.so_first_title') }}</div>
	<div class="f-13 mb-1" style="text-align:center;opacity:.7">{{ __('tournaments.so_first_hint') }}</div>
	<div class="so-pairs">
		@foreach($serve['teams'] as $side => $t)
		<div class="so-pair" style="border-color:{{ $t['color'] }}">
			<div class="so-pair-name" style="background:{{ $t['color'] }};color:{{ $t['text'] }}">{!! str_replace('/', '/<wbr>', e($t['name'])) !!}</div>
			<div class="so-pair-cards">
				@foreach($t['players'] as $p)
				@include('tournaments._partials.beach_serve_card', ['p' => $p, 't' => $t, 'selected' => $t['serving'] && $t['first_id'] === $p['id'], 'withInput' => true])
				@endforeach
			</div>
		</div>
		@endforeach
	</div>
</div>
@elseif($part === 'panel' && !empty($serve['needs_first_side']) && empty($serve['can_pick_first']))
@php $nt = $serve['teams'][$serve['needs_first_side']]; @endphp
<div class="lu-hint so-first-panel" style="border-color:#2967BA;background:rgba(41,103,186,.07)">
	<div class="lu-hint-ttl" style="color:#2967BA">{{ __('tournaments.so_pick_title', ['team' => $nt['name']]) }}</div>
	<div class="f-13" style="opacity:.75;margin:2px 0 8px">{{ __('tournaments.so_pick_hint') }}</div>
	<div class="so-pair-cards">
		@foreach($nt['players'] as $p)
		@include('tournaments._partials.beach_serve_card', ['p' => $p, 't' => $nt, 'selected' => false, 'withInput' => false])
		@endforeach
	</div>
</div>
@endif
