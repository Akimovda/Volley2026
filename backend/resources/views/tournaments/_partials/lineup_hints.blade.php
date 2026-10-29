{{-- Подсказка по либеро «Вернуть X»: (а) либеро в передней линии; (б) подача, а в зоне 1 стоит либеро. Данные — $lineup['mini']['hints'].
     «Вернуть» = событие libero_out (не замена). «Позже» скрывает панель до следующего изменения расстановки (JS: lineup_fix_js). --}}
@if(!empty($lineup['mini']['hints']))
@foreach($lineup['mini']['hints'] as $h)
@php $lb = $h['libero']; $pt = $h['partner']; @endphp
<div class="lu-hint" data-key="{{ $h['key'] }}">
	<div class="lu-hint-top">
		<span class="lu-hint-av">@if($lb['photo'])<img src="{{ $lb['photo'] }}" alt="" data-ini="{{ $lb['initials'] }}">@else{{ $lb['initials'] }}@endif @if($lb['number'] !== null)<i>{{ $lb['number'] }}</i>@endif</span>
		<div class="lu-hint-txt">
			<div class="lu-hint-ttl">{{ $h['team'] }} · {{ $h['reason'] === 'front' ? __('tournaments.bx_hint_front') : __('tournaments.bx_hint_serve') }}</div>
			<div class="lu-hint-q">{{ __('tournaments.bx_hint_return', ['who' => trim(($pt['number'] !== null ? '№' . $pt['number'] . ' ' : '') . $pt['surname'] . ($pt['pos_abbr'] ? ' (' . $pt['pos_abbr'] . ')' : ''))]) }}</div>
		</div>
	</div>
	<div class="lu-hint-btns">
		<button type="button" class="btn js-lu-hint-back" data-team="{{ $h['team_id'] }}" style="background:#7C3AED;color:#fff;border-color:#7C3AED">{{ __('tournaments.bx_hint_btn', ['name' => $pt['surname']]) }}</button>
		<button type="button" class="btn btn-secondary js-lu-hint-later" data-key="{{ $h['key'] }}">{{ __('tournaments.bx_hint_later') }}</button>
	</div>
	<div class="lu-hint-note">{{ __('tournaments.bx_hint_note') }}</div>
</div>
@endforeach
@endif
