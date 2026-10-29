@php
	$format = $stage->matchFormat();
	$maxSets = match($format) { 'bo1' => 1, 'bo3' => 3, 'bo5' => 5, default => 3 };

	$statLabels = [
		'serves_total'     => __('tournaments.stat_serves_short'),
		'aces'             => __('tournaments.stat_aces_short'),
		'serve_errors'     => __('tournaments.stat_serve_err_short'),
		'attacks_total'    => __('tournaments.stat_attacks_short'),
		'kills'            => __('tournaments.stat_kills_short'),
		'attack_errors'    => __('tournaments.stat_attack_err_short'),
		'blocks'           => __('tournaments.stat_blocks_short'),
		'block_errors'     => __('tournaments.stat_block_err_short'),
		'digs'             => __('tournaments.stat_digs_short'),
		'reception_errors' => __('tournaments.stat_dig_err_short'),
		'assists'          => __('tournaments.stat_assists_short'),
	];

	$actionMarkers = [
		'ace' => 'Э', 'kill' => 'А', 'block' => 'Б',
		'opp_serve_error' => 'ошП', 'opp_attack_error' => 'ошА',
		'opp_block_error' => 'ошБ', 'opp_reception_error' => 'ошПр', 'opp_rotation_error' => 'ошПер', 'opp_position_error' => 'ошРас',
		'unattributed' => '•',
	];

	$sides = [
		'home' => ['team' => $match->teamHome, 'team_id' => (int) $match->team_home_id, 'opp_id' => (int) $match->team_away_id],
		'away' => ['team' => $match->teamAway, 'team_id' => (int) $match->team_away_id, 'opp_id' => (int) $match->team_home_id],
	];

	$luOn = !empty($lineup['enabled']);
	$luStarted = $luOn && $lineup['has_start'];
	// Для матчей с учётом состава в попапе очка — только игроки НА ПЛОЩАДКЕ сейчас (включая вышедшего либеро)
	// карточка игрока (фото/инициалы/номер/амплуа) из мини-площадки — для панели «Подаёт не тот игрок?» и чипов виновника
	$cardsOf = [];
	foreach (($lineup['mini']['teams'] ?? []) as $cSide => $cTeam) {
		foreach ($cTeam['cells'] as $cc) {
			$cardsOf[$cSide][$cc['id']] = ['surname' => $cc['surname'], 'initials' => $cc['initials'], 'photo' => $cc['photo'], 'number' => $cc['number'],
				'pos' => $cc['pos_abbr'], 'libero' => $cc['libero'], 'zone' => $cc['zone']];
		}
	}
	$courtPlayers = function (string $sk) use ($lineup, $board, $cardsOf) {
		return array_map(
			fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'role' => $c['role'] ?? null, 'card' => $cardsOf[$sk][$c['id']] ?? null],
			$lineup['teams'][$sk]['court']
		);
	};
	// Подающий игрок (учёт состава): эйс подающей команды записывается на него без вопроса «кто набрал очко»
	$srv = $luStarted ? app(\App\Services\MatchLineupService::class)->currentServer($match, (int) $setNumber) : null;
	$rwData = [];
	// Пляжка: у пары (ровно 2 игрока) шаг «Кто принимал?» не нужен — партнёр подставляется сам (см. rally_screen.nextAfterPlayer)
	$isBeachMatch = ($match->stage?->event?->direction ?? null) === 'beach';
	foreach ($sides as $sk => $sd) {
		$oppSk = $sk === 'home' ? 'away' : 'home';
		$ownPlayers = $luStarted ? $courtPlayers($sk) : array_values($board['players'][$sk]);
		$rwData[$sk] = [
			'teamId'     => $sd['team_id'],
			'teamName'   => (string) ($sd['team']->name ?? 'TBD'),
			'players'    => $ownPlayers,
			'serverId'   => ($srv && $srv['user_id'] && (int) $srv['team_id'] === (int) $sd['team_id'] && collect($ownPlayers)->contains(fn ($p) => (int) $p['id'] === (int) $srv['user_id']))
				? (string) $srv['user_id'] : null,
			'oppPlayers' => $luStarted ? $courtPlayers($oppSk) : array_values($board['players'][$oppSk]),
			'pairMode'   => $isBeachMatch && count($ownPlayers) === 2,
			// подаёт ли эта команда по расстановке (даже если подающий игрок не определён — например, на его месте либеро)
			'serving'    => (bool) ($srv && (int) $srv['team_id'] === (int) $sd['team_id']),
		];
	}
@endphp
@php
	$luCfg = null;
	if ($luOn) {
		$luTeams = [];
		foreach ($sides as $sk => $sd) {
			$luTeams[$sk] = $lineup['teams'][$sk] + ['name' => (string) ($sd['team']->name ?? 'TBD')];
		}
		$luCfg = [
			'setNumber' => $setNumber,
			'startUrl'  => $urls['lineup_start'],
			'jerseyUrl' => $urls['jersey'] ?? null,
			'eventUrl'  => $urls['lineup_event'],
			'size'      => $lineup['profile']['on_court_size'],
			'hasLibero' => $lineup['profile']['has_libero'],
			'positions' => $lineup['profile']['positions'],
			'hasStart'  => $lineup['has_start'],
			'teams'     => $luTeams,
			'setup'     => $lineup['setup'],
		];
	}
	// B5: сигнал смены сторон/техтайм-аута на текущем счёте (пляжка); клиент показывает один раз на key
	$ssSignal = (!$board['decided'])
		? \App\Services\SideSwitchService::signal(\App\Services\SideSwitchService::rules($match->stage), (int) $match->id, (int) $setNumber,
			(int) $board['score']['home'], (int) $board['score']['away'], (bool) ($board['is_deciding_set'] ?? false))
		: null;
	// mc — сигнатура переходов для анимации на клиенте (сравнивается между подменами фрагмента)
	$rwLiveData = ['rw' => $rwData, 'lu' => $luCfg, 'ss' => $ssSignal, 'fx' => $lineup['mini'] ?? null, 'mc' => !empty($lineup['mini']) ? [
		'set' => $setNumber, 'rot' => $lineup['mini']['rot'], 'rotated_side' => $lineup['mini']['rotated_side'],
		'names' => ['home' => $lineup['mini']['teams']['home']['name'], 'away' => $lineup['mini']['teams']['away']['name']],
	] : null];
@endphp

		{{-- Шапка матча + табы сетов --}}
		<div class="card p-3 mb-2" style="text-align:center">
			@php $roundLabel = $stage->roundLabelFor($match); @endphp
			<div class="mb-2">
				<span class="score-pill score-pill--blue">{{ __('tournaments.score_pill_match', ['n' => $match->match_number]) }}</span>
				<span class="score-pill score-pill--green">{{ $roundLabel ?? __('tournaments.score_pill_round', ['r' => $match->round]) }}</span>
				<span class="score-pill score-pill--orange">{{ strtoupper($format) }}</span>
			</div>
			<div class="d-flex between fvc mb-2">
				<div style="flex:1">
					<div class="b-700 f-18">@include('tournaments._partials.team_pair_names', ['team' => $match->teamHome, 'fallback' => 'TBD'])</div>
					@unless(app(\App\Services\TeamDisplayNames::class)->pair($match->teamHome))
					@include('tournaments._partials.team_roster_line', ['team' => $match->teamHome, 'class' => 'f-13', 'style' => 'opacity:.7'])
					@endunless
				</div>
				<div class="f-24 b-800 px-2">{{ $board['score']['home'] }} : {{ $board['score']['away'] }}</div>
				<div style="flex:1">
					<div class="b-700 f-18">@include('tournaments._partials.team_pair_names', ['team' => $match->teamAway, 'fallback' => 'TBD'])</div>
					@unless(app(\App\Services\TeamDisplayNames::class)->pair($match->teamAway))
					@include('tournaments._partials.team_roster_line', ['team' => $match->teamAway, 'class' => 'f-13', 'style' => 'opacity:.7'])
					@endunless
				</div>
			</div>
			<div class="mb-2">
				<span class="score-pill score-pill--blue">{{ __('tournaments.rally_set_n', ['n' => $setNumber]) }}</span>
				<span class="score-pill score-pill--red">{{ __('tournaments.score_to_pts') }} {{ $board['target_points'] }}</span>
				@if($board['decided'])
				<span class="score-pill score-pill--green">{{ __('tournaments.rally_set_decided') }}</span>
				@endif
			</div>
			<div class="d-flex gap-1 text-center mt-2" style="flex-wrap:wrap;justify-content:center">
				@for($i = 1; $i <= $maxSets; $i++)
				<a href="{{ \App\Support\RallyUrls::setUrl($urls, $i) }}"
				   class="btn btn-small {{ $i === $setNumber ? 'btn-primary' : 'btn-secondary' }}">
					{{ __('tournaments.rally_set_n', ['n' => $i]) }}
				</a>
				@endfor
			</div>
		</div>

		@if($luOn && !empty($lineup['mini']))
		@include('tournaments._partials.lineup_court')
		@endif

		@if(!empty($canMatchStart) && !empty($urls['match_start']))
		<div class="card mb-2" style="text-align:center">
			<form method="POST" action="{{ $urls['match_start'] }}" class="js-rw-match-start" data-set="{{ $setNumber }}">
				@csrf
				<button type="submit" class="btn btn-primary w-100" style="font-size:16px;padding:14px 8px">{{ __('broadcast.match_start_btn') }}</button>
			</form>
		</div>
		@elseif(!empty($canMatchUnstart) && !empty($urls['match_unstart']))
		<div class="f-13 mb-2" style="text-align:center;opacity:.8">
			{{ __('broadcast.match_started_note') }}
			<form method="POST" action="{{ $urls['match_unstart'] }}" class="js-rw-match-unstart" data-set="{{ $setNumber }}" style="display:inline">
				@csrf
				<button type="submit" class="link-btn" style="background:none;border:0;padding:0;color:inherit;text-decoration:underline;cursor:pointer;font-size:inherit">{{ __('broadcast.match_unstart_link') }}</button>
			</form>
		</div>
		@endif

		@if(!empty($canServeMark))
		<div class="card mb-2" style="text-align:center">
			<div class="f-13 mb-1" style="font-size:calc(1.3rem + 2.5px);font-weight:700">{{ __('broadcast.serve_mark_label') }}</div>
			<div class="d-flex gap-1" style="justify-content:center;flex-wrap:wrap">
				@foreach($sides as $sd)
				@php
					// цвет команды — тот же, что на табло (стиль команды → цвет friendly-команды → палитра)
					$svColor  = $sd['team'] ? app(\App\Services\Broadcast\TeamStyleService::class)->colorFor($sd['team']) : '#2967BA';
					$svHex    = ltrim($svColor, '#');
					$svLum    = (0.299 * hexdec(substr($svHex, 0, 2)) + 0.587 * hexdec(substr($svHex, 2, 2)) + 0.114 * hexdec(substr($svHex, 4, 2))) / 255;
					$svLight  = $svLum > 0.8;                      // белая/жёлтая команда: контур цветом не виден — берём тёмно-синий и тёмный текст
					$svBorder = $svLight ? '#B5C3D9' : $svColor;
					$svSel    = (int) $serveMarkTeamId === $sd['team_id'];
				@endphp
				<form method="POST" action="{{ $urls['serve_mark'] }}" class="js-rw-serve-mark" data-set="{{ $setNumber }}" data-team="{{ $sd['team_id'] }}" style="flex:1;min-width:120px">
					@csrf
					<button type="submit" class="btn w-100 rw-serve-btn{{ $svSel ? ' on' : '' }}" style="padding:10px 8px;border:2px solid {{ $svBorder }};@if($svSel)background:{{ $svColor }};color:{{ $svLight ? '#14306B' : '#fff' }};@endif">{{ $svSel ? '● ' : '' }}{!! str_replace('/', '/<wbr>', e($sd['team']->name ?? 'TBD')) !!}@unless($isBeachMatch)@include('tournaments._partials.team_sub_lines', ['team' => $sd['team'], 'inline' => true])@endunless</button>
				</form>
				@endforeach
			</div>
		</div>
		@endif

		<div class="alert alert-info f-13 mb-1">{{ __('tournaments.rally_hint_two_ways') }}</div>
		<div class="alert alert-info f-13 mb-2">{{ __('tournaments.rally_no_attempts_note') }}</div>

		@php $maxColumns = max($board['columns']['home'], $board['columns']['away']); @endphp

		{{-- Сводная таблица: по вертикали — команды (2 строки), по горизонтали — итоги + очки.
		     Разбивка по игрокам — на странице результатов турнира. --}}
		<div class="card mb-2">
			<div class="table-scrollable">
				<table class="table f-13">
					<thead>
						<tr>
							<th style="text-align:left">{{ __('tournaments.setup_col_team') }}</th>
							@foreach($statLabels as $label)
							<th style="text-align:center">{{ $label }}</th>
							@endforeach
							<th style="text-align:center">{{ __('tournaments.stats_col_pts') }}</th>
							@for($c = 1; $c <= $maxColumns; $c++)
							<th style="text-align:center;min-width:2.4rem">{{ $c }}</th>
							@endfor
						</tr>
					</thead>
					<tbody>
						@foreach($sides as $sideKey => $side)
						@php
							$teamAggregates = $board['aggregates'][$sideKey];
							$teamCells = $board['cells'][$sideKey];
						@endphp
						<tr>
							<td class="b-700">@include('tournaments._partials.team_name_link', ['team' => $side['team'], 'fallback' => 'TBD'])</td>
							@foreach(array_keys($statLabels) as $f)
							<td style="text-align:center">{{ array_sum(array_column($teamAggregates, $f)) }}</td>
							@endforeach
							<td style="text-align:center" class="b-700">{{ array_sum(array_column($teamAggregates, 'points_scored')) }}</td>
							@for($c = 1; $c <= $maxColumns; $c++)
							@php $cell = $teamCells[$c] ?? null; @endphp
							<td style="text-align:center">
								@if($cell)
									<span class="b-700">{{ $actionMarkers[$cell['action_type']] ?? '?' }}</span>
								@else
									<span style="opacity:.25">·</span>
								@endif
							</td>
							@endfor
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>

		@if($luOn && !$board['decided'])
		@include('tournaments._partials.lineup_setup_panel')
		@endif

		@if($luOn && !$board['decided'])
		@include('tournaments._partials.lineup_hints')
		@endif

		@foreach($sides as $sideKey => $side)
		@php
			$teamId = $side['team_id'];
			$players = $board['players'][$sideKey];
			$oppPlayers = $board['players'][$sideKey === 'home' ? 'away' : 'home'];
		@endphp
		<div class="card mb-2">
			<div class="b-700 f-16 mb-1">@include('tournaments._partials.team_name_link', ['team' => $side['team'], 'fallback' => 'TBD'])</div>
			@include('tournaments._partials.team_sub_lines', ['team' => $side['team']])

			@if(!$board['decided'] && $luOn && $lineup['needs_start'])
			<div class="alert alert-info f-13 mt-2">{{ __('tournaments.lineup_need_setup') }}</div>
			@elseif(!$board['decided'])
			<div class="mt-2">
				<div class="f-13 b-600 mb-1">{{ __('tournaments.rally_add_point_title') }}</div>
				<div class="d-flex gap-1" style="flex-wrap:wrap">

					<button type="button" class="btn btn-primary js-rw-start" data-side="{{ $sideKey }}" data-kind="own" style="flex:1;min-width:16rem"><span class="rw-bi">@include('tournaments._partials.ball_icon')</span> {{ __('tournaments.rally_own_action_btn') }}</button>
					<button type="button" class="btn btn-secondary js-rw-start" data-side="{{ $sideKey }}" data-kind="opp" style="flex:1;min-width:16rem;background:rgba(220,38,38,.1)">⚠ {{ __('tournaments.rally_opp_error_row') }}</button>
					@if($luStarted)
					<button type="button" class="btn btn-secondary js-lu-sub" data-side="{{ $sideKey }}" style="min-width:8rem" title="{{ __('tournaments.lineup_btn_sub') }}" aria-label="{{ __('tournaments.lineup_btn_sub') }}">@include('tournaments._partials.icon_sub', ['kind' => 'sub', 'size' => 22]) <span>{{ __('tournaments.lineup_btn_sub') }}</span></button>
					<button type="button" class="btn btn-secondary js-lu-fix" data-side="{{ $sideKey }}" style="min-width:8rem" title="{{ __('tournaments.bx_fix_btn') }}">{{ __('tournaments.bx_fix_btn') }}</button>
					@endif

				</div>
			</div>
			@endif
		</div>
		@endforeach

		@if($luOn)
		@include('tournaments._partials.lineup_journal')
		@endif

		<div class="d-flex text-center gap-1 mt-2" style="flex-wrap:wrap">
			<form method="POST" action="{{ $urls['undo'] }}" style="flex:1" class="js-rw-undo">
				@csrf
				<input type="hidden" name="set_number" value="{{ $setNumber }}">
				<input type="hidden" name="expected_last_seq" value="{{ $board['last_action_seq'] }}">
				<button type="submit" class="btn btn-secondary w-100" {{ $board['can_undo'] ? '' : 'disabled' }}>
					↩ {{ $luOn ? __('tournaments.lineup_btn_undo') : __('tournaments.rally_btn_undo') }}
				</button>
			</form>
			@if($matchReady && !$match->isCompleted())
			<div class="alert alert-success w-100" style="flex-basis:100%;margin:0 0 .5rem">
				{{ ($match->stage?->type === \App\Models\TournamentStage::TYPE_FRIENDLY) ? __('tournaments.rally_match_decided_friendly') : __('tournaments.rally_match_decided') }}
			</div>
			@endif
			<form method="POST" action="{{ $urls['finalize'] }}" style="flex:1" class="{{ $urls['mode'] === 'scorer' ? 'js-rw-finalize-ajax' : 'js-rw-finalize' }}" data-unattributed="{{ $unattributed }}" data-score="{{ $finalScore }}">
				@csrf
				<button type="submit" class="btn btn-primary w-100" {{ $matchReady ? '' : 'disabled' }}>
					💾 {{ __('tournaments.rally_btn_finalize') }}
				</button>
			</form>
			@if($urls['back'])
			<a href="{{ $urls['back'] }}" class="btn btn-secondary" style="flex:1;text-align:center">
				{{ __('tournaments.btn_back') }}
			</a>
			@endif
			@if($match->isCompleted() && $urls['pdf'])
			<a href="{{ $urls['pdf'] }}" data-file-download class="btn btn-secondary btn-small" title="{{ __('tournaments.rally_btn_pdf_stats') }}">
				📊 PDF
			</a>
			@endif
		</div>


<script type="application/json" id="rw-live-data">@json($rwLiveData)</script>
