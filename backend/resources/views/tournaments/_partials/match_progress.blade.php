@php
/**
 * Лента "ход матча" — рендер структуры MatchProgressService::build()/buildForMatches().
 * Вызывающая сторона обязана проверить $progress['has_progress'] сама (пустое состояние
 * рендерится отдельно на месте вызова, этот партиал вызывается только когда есть данные).
 *
 * Макет: счёт — центральная ось строки (крупный, по центру), событие стянуто к счёту
 * с той стороны, чья команда набрала очко. Строка игрока — аватар + гендер + фамилия-ссылка
 * (на публичный состав команды, #member-{id}) + полное амплуа (для classic_team, кроме резерва;
 * полностью, без сокращений — как в rally_player_card/team.blade.php).
 *
 * @var array $progress ['sets' => [setNumber => ['rallies' => [...], 'final_score' => [...]]]]
 * @var \App\Models\TournamentMatch $match
 * @var \App\Models\Event $event
 */
$setNumbers = array_keys($progress['sets']);
$defaultSet = end($setNumbers) ?: 1;
$posFull = [
    'setter'   => __('tournaments.pos_full_setter'),
    'outside'  => __('tournaments.pos_full_outside'),
    'opposite' => __('tournaments.pos_full_opposite'),
    'middle'   => __('tournaments.pos_full_middle'),
    'libero'   => __('tournaments.pos_full_libero'),
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
			$actionLabel = $r['action_type'] === 'unattributed'
				? __('tournaments.rally_team_point')
				: __('tournaments.rally_action_' . $r['action_type']);
			$player = $r['player'];
			$surname = $player ? trim(explode(' ', $player['name'])[0] ?? '') : null;
			$genderClass = $player ? ($player['gender'] === 'm' ? 'rp-gender--m' : ($player['gender'] === 'f' ? 'rp-gender--f' : null)) : null;
			$genderSign = $player ? ($player['gender'] === 'm' ? '♂' : ($player['gender'] === 'f' ? '♀' : null)) : null;

			$showPosition = $player && ($player['team_kind'] ?? null) === 'classic_team' && ($player['role_code'] ?? null) !== 'reserve';
			$positionLabel = $showPosition ? ($player['position_code'] ? ($posFull[$player['position_code']] ?? $player['position_code']) : '—') : null;
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
							<img src="{{ $player['avatar_url'] }}" alt="" class="rp-avatar" loading="lazy">
							<div class="rp-player-text">
								<div class="rp-name-row">
									@if($player['team_id'])
									<a href="{{ route('tournament.public.team', [$event, $player['team_id']]) }}#member-{{ $player['id'] }}" class="rp-name-link blink">{{ $surname }}</a>
									@else
									<span class="rp-name-link">{{ $surname }}</span>
									@endif
								</div>
								@if($positionLabel)<div class="rp-position">{{ $positionLabel }}</div>@endif
								@if($genderSign)
								<div class="rp-gender-row">{{ __('tournaments.rally_gender_label') }}: <span class="rp-gender {{ $genderClass }}">{{ $genderSign }}</span></div>
								@endif
							</div>
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
