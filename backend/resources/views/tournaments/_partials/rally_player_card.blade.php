@php
/**
 * Компактная карточка игрока для ленты "ход матча" (и где ещё понадобится).
 *
 * @var array{id:int,name:string,avatar_url:string,gender:?string,position_code:?string} $player
 * @var bool $muted — приглушённый показ (игрок соперника, ошибка которого дала очко)
 */
$muted = $muted ?? false;

$posFull = [
    'setter'   => __('tournaments.pos_full_setter'),
    'outside'  => __('tournaments.pos_full_outside'),
    'opposite' => __('tournaments.pos_full_opposite'),
    'middle'   => __('tournaments.pos_full_middle'),
    'libero'   => __('tournaments.pos_full_libero'),
];

$positionLabel = $player['position_code'] ? ($posFull[$player['position_code']] ?? null) : null;
$genderSign = $player['gender'] === 'f' ? '♀' : ($player['gender'] === 'm' ? '♂' : null);
@endphp
<div class="rp-player{{ $muted ? ' rp-player--muted' : '' }}">
	<img src="{{ $player['avatar_url'] }}" alt="" class="rp-player-avatar" loading="lazy">
	<div class="rp-player-info">
		<div class="rp-player-name">{{ $player['name'] }}</div>
		@if($genderSign || $positionLabel)
		<div class="rp-player-meta">
			@if($genderSign)<span>{{ $genderSign }}</span>@endif
			@if($genderSign && $positionLabel) · @endif
			@if($positionLabel)<span>{{ $positionLabel }}</span>@endif
		</div>
		@endif
	</div>
</div>
