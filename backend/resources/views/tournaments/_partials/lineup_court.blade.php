{{-- Мини-площадка пульта: обе команды, физические зоны (с учётом переходов), подающий выделен.
     Данные — $lineup['mini'] (MatchLineupView). Тап — крупный вид (JS в rally_screen). Анимация перехода — FLIP по data-uid. --}}
@php
	$mc = $lineup['mini'];
	$size6 = (int) $mc['size'] === 6;
	// порядок зон по рядам (слева направо), у верхней команды — зеркально
	$rowsBottom = $size6 ? [[4, 3, 2], [5, 6, 1]] : [[3, 2], [4, 1]];     // передняя линия у сетки, затем задняя
	$rowsTop    = $size6 ? [[1, 6, 5], [2, 3, 4]] : [[1, 4], [2, 3]];     // задняя, затем передняя (зеркало)
	$layout = ['away' => $rowsTop, 'home' => $rowsBottom];
@endphp
<div class="mc-wrap mb-2" id="mc-wrap">
	<div class="mc" id="mc" data-size="{{ (int) $mc['size'] }}" role="button" tabindex="0" aria-label="{{ __('tournaments.mc_zoom') }}">
		@foreach(['away', 'home'] as $side)
		@php $tm = $mc['teams'][$side]; $byZone = []; foreach ($tm['cells'] as $c) { $byZone[(int) $c['zone']] = $c; } @endphp
		@if($side === 'home')<div class="mc-net"><span>{{ __('tournaments.lineup_net') }}</span></div>@endif
		<div class="mc-half mc-half--{{ $side }}" style="background:{{ $tm['color'] }};color:{{ $tm['text'] }}" data-side="{{ $side }}">
			@if($side === 'away')
			<div class="mc-title"><span class="mc-ball{{ $tm['serving'] ? ' on' : '' }}">🏐</span>{{ $tm['name'] }}</div>
			@endif
			@foreach($layout[$side] as $row)
			<div class="mc-row">
				@foreach($row as $z)
				@php $c = $byZone[$z] ?? null; @endphp
				<div class="mc-cell" @if($c) data-uid="{{ $c['id'] }}" @endif data-zone="{{ $z }}">
					<span class="mc-z">{{ $z }}</span>
					@if($c)
					<div class="mc-av{{ $c['libero'] ? ' mc-av--lib' : '' }}{{ $tm['serving'] && $z === 1 ? ' mc-av--srv' : '' }}" style="{{ $c['photo'] ? '' : 'background:rgba(0,0,0,.28)' }}">
						@if($c['photo'])<img src="{{ $c['photo'] }}" alt="" loading="lazy">@else<span class="mc-ini" style="color:{{ $tm['text'] === '#111' ? '#111' : '#fff' }}">{{ $c['initials'] }}</span>@endif
						@if($c['number'] !== null)<span class="mc-num">{{ $c['number'] }}</span>@endif
					</div>
					<div class="mc-nm">{{ $c['surname'] }}</div>
					<div class="mc-pos{{ $c['libero'] ? ' mc-pos--lib' : '' }}">{{ $c['pos_abbr'] }}</div>
					@endif
				</div>
				@endforeach
			</div>
			@endforeach
			@if($side === 'home')
			<div class="mc-title"><span class="mc-ball{{ $tm['serving'] ? ' on' : '' }}">🏐</span>{{ $tm['name'] }}</div>
			@endif
		</div>
		@endforeach
		<div class="mc-plate" id="mc-plate" aria-live="polite"></div>
	</div>
	<div class="mc-server">
		@if(!empty($mc['server']))
		{{ __('tournaments.mc_serves') }}: @if($mc['server']['number'] !== null)№{{ $mc['server']['number'] }} @endif{{ $mc['server']['surname'] }} · {{ $mc['server']['team'] }}
		@else
		{{ __('tournaments.mc_serves') }}: —
		@endif
	</div>
</div>
