@php
/**
 * Лента "ход матча" — рендер структуры MatchProgressService::build()/buildForMatches().
 * Вызывающая сторона обязана проверить $progress['has_progress'] сама (пустое состояние
 * рендерится отдельно на месте вызова, этот партиал вызывается только когда есть данные).
 *
 * Макет: счёт — центральная ось строки (крупный, по центру), событие стянуто к счёту
 * с той стороны, чья команда набрала очко. Строка игрока — гендер + фамилия-ссылка
 * (на публичный состав команды, #member-{id}) + амплуа (для classic_team, кроме резерва).
 *
 * @var array $progress ['sets' => [setNumber => ['rallies' => [...], 'final_score' => [...]]]]
 * @var \App\Models\TournamentMatch $match
 * @var \App\Models\Event $event
 */
$setNumbers = array_keys($progress['sets']);
$defaultSet = end($setNumbers) ?: 1;
$posAbbr = [
    'setter'   => __('tournaments.pos_abbr_setter'),
    'outside'  => __('tournaments.pos_abbr_outside'),
    'opposite' => __('tournaments.pos_abbr_opposite'),
    'middle'   => __('tournaments.pos_abbr_middle'),
    'libero'   => __('tournaments.pos_abbr_libero'),
];
@endphp
<div class="rp-wrap">
	@if(count($setNumbers) > 1)
	<div class="rp-tabs">
		@foreach($setNumbers as $sn)
		<button type="button" class="rp-tab{{ $sn === $defaultSet ? ' rp-tab--active' : '' }}"
			onclick="rpShowSet({{ $match->id }}, {{ $sn }})" data-rp-tab="{{ $match->id }}-{{ $sn }}">
			{{ __('tournaments.rally_set_n', ['n' => $sn]) }}
		</button>
		@endforeach
	</div>
	@endif

	@foreach($progress['sets'] as $sn => $set)
	<div class="rp-set{{ $sn === $defaultSet ? '' : ' rp-set--hidden' }}" data-rp-set="{{ $match->id }}-{{ $sn }}">
		<div class="rp-set-header">
			{{ __('tournaments.rally_set_n', ['n' => $sn]) }} · {{ $set['final_score']['home'] }}:{{ $set['final_score']['away'] }}
		</div>
		<div class="rp-feed">
			@foreach($set['rallies'] as $r)
			@php
			$actionLabel = __('tournaments.rally_action_' . $r['action_type']);
			$player = $r['player'];
			$surname = $player ? trim(explode(' ', $player['name'])[0] ?? '') : null;
			$genderSign = $player ? ($player['gender'] === 'm' ? '♂' : ($player['gender'] === 'f' ? '♀' : null)) : null;

			$showPosition = $player && ($player['team_kind'] ?? null) === 'classic_team' && ($player['role_code'] ?? null) !== 'reserve';
			$positionLabel = $showPosition ? ($player['position_code'] ? ($posAbbr[$player['position_code']] ?? $player['position_code']) : '—') : null;
			@endphp
			<div class="rp-row" onclick="this.classList.toggle('rp-row--highlight')">
				@foreach(['home', 'away'] as $side)
				@php $filled = $r['team_side'] === $side; @endphp
				<div class="rp-cell rp-cell--{{ $side }}{{ $filled ? ' rp-cell--filled' : '' }}">
				@if($filled)
					<div class="rp-event">
						<div class="rp-action">{{ $actionLabel }}</div>
						@if($surname)
						<div class="rp-player-line{{ $r['is_own_action'] ? '' : ' rp-player-line--muted' }}">
							@if($genderSign)<span class="rp-gender">{{ $genderSign }}</span>@endif
							@if($player['team_id'])
							<a href="{{ route('tournament.public.team', [$event, $player['team_id']]) }}#member-{{ $player['id'] }}" class="rp-name-link blink">{{ $surname }}</a>
							@else
							<span class="rp-name-link">{{ $surname }}</span>
							@endif
							@if($positionLabel)<span class="rp-position">{{ $positionLabel }}</span>@endif
						</div>
						@endif
					</div>
				@endif
				</div>
				@endforeach
				<div class="rp-score">{{ $r['score_home'] }}:{{ $r['score_away'] }}</div>
			</div>
			@endforeach
		</div>
	</div>
	@endforeach
</div>
