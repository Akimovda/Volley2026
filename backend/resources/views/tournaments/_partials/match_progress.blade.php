@php
/**
 * Лента "ход матча" — рендер структуры MatchProgressService::build()/buildForMatches().
 * Вызывающая сторона обязана проверить $progress['has_progress'] сама (пустое состояние
 * рендерится отдельно на месте вызова, этот партиал вызывается только когда есть данные).
 *
 * Строка компактная (название действия + кружок со счётом команды + фамилия) — без аватара,
 * без игрового номера (в БД такого поля нет, см. CLAUDE.md). Полная карточка игрока
 * (rally_player_card) сюда не подключается — она для составов/деталей, не для построчной ленты.
 *
 * @var array $progress ['sets' => [setNumber => ['rallies' => [...], 'final_score' => [...]]]]
 * @var \App\Models\TournamentMatch $match
 */
$setNumbers = array_keys($progress['sets']);
$defaultSet = end($setNumbers) ?: 1;
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
			$sideScore = $r['team_side'] === 'home' ? $r['score_home'] : $r['score_away'];
			$surname = $r['player'] ? trim(explode(' ', $r['player']['name'])[0] ?? '') : null;
			@endphp
			<div class="rp-row" onclick="this.classList.toggle('rp-row--highlight')">
				@foreach(['home', 'away'] as $side)
				@php $filled = $r['team_side'] === $side; @endphp
				<div class="rp-cell rp-cell--{{ $side }} {{ $filled ? 'rp-cell--filled' : 'rp-cell--empty' }}">
				@if($filled)
					<span class="rp-point-circle rp-point-circle--{{ $side }}">{{ $sideScore }}</span>
					<div class="rp-event">
						<div class="rp-action">{{ $actionLabel }}</div>
						@if($surname)
						<div class="rp-surname{{ $r['is_own_action'] ? '' : ' rp-surname--muted' }}">{{ $surname }}</div>
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
