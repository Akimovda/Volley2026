{{-- Карточка игрока пары (пляжка): фото 56 px / инициалы на цвете пары, фамилия, имя, метка Бл/Защ (если пара «По амплуа»), номер — бейджем
     (в «Подаёт первым» — ещё поле «№» под карточкой). Тот же стиль, что экран стартовой расстановки классики (ls-card/ls-av/ls-num).
     Параметры: $p (карточка), $t (пара: team_id, color, text), $selected, $withInput --}}
<div class="ls-zone ls-card so-card{{ !empty($selected) ? ' so-sel' : '' }}" role="button" tabindex="0" data-so-pick data-team="{{ $t['team_id'] }}" data-uid="{{ $p['id'] }}">
	<span class="so-av-wrap">
		<span class="ls-av" style="background:{{ $p['photo'] ? 'transparent' : $t['color'] }};color:{{ $t['text'] }}">@if($p['photo'])<img src="{{ $p['photo'] }}" alt="" data-ini="{{ $p['initials'] }}" data-bg="{{ $t['color'] }}" data-fg="{{ $t['text'] }}">@else{{ $p['initials'] }}@endif</span>
		@if($p['number'] !== null)<span class="rw-pc-num">{{ $p['number'] }}</span>@endif
	</span>
	<span class="ls-zone-name">{{ $p['surname'] }}</span>
	<span class="ls-zone-first">{{ $p['first'] }}</span>
	@if(!empty($p['pos']))<span class="ls-zone-pos">{{ $p['pos'] }}</span>@endif
	@if(!empty($withInput))<input type="text" class="ls-num js-so-num" inputmode="numeric" maxlength="2" placeholder="{{ __('tournaments.lineup_jersey_ph') }}" value="{{ $p['number'] }}" data-uid="{{ $p['id'] }}">@endif
</div>
