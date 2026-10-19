<x-voll-layout body_class="tournament-rally-page">
<x-slot name="title">{{ __('tournaments.rally_title') }} — {{ $event->title }}</x-slot>
<x-slot name="h1">{{ __('tournaments.rally_h1') }}</x-slot>

<x-slot name="breadcrumbs">
	<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		<a href="{{ ($match->stage->type ?? null) === 'friendly' ? route('game.manage', $event) . '?occurrence=' . $match->stage->occurrence_id : route('tournament.setup', $event) . ($match->stage->occurrence_id ? '?occurrence_id=' . $match->stage->occurrence_id : '') }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
		<meta itemprop="position" content="2">
	</li>
	<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		<span itemprop="name">{{ __('tournaments.score_match_n', ['n' => $match->match_number]) }}</span>
		<meta itemprop="position" content="3">
	</li>
</x-slot>

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
		'ace' => 'Э', 'kill' => 'К', 'block' => 'Б',
		'opp_serve_error' => 'ошП', 'opp_attack_error' => 'ошА',
		'opp_block_error' => 'ошБ', 'opp_reception_error' => 'ошПр',
		'unattributed' => '•',
	];

	$sides = [
		'home' => ['team' => $match->teamHome, 'team_id' => (int) $match->team_home_id, 'opp_id' => (int) $match->team_away_id],
		'away' => ['team' => $match->teamAway, 'team_id' => (int) $match->team_away_id, 'opp_id' => (int) $match->team_home_id],
	];

	$rwData = [];
	foreach ($sides as $sk => $sd) {
		$rwData[$sk] = [
			'teamId'     => $sd['team_id'],
			'teamName'   => (string) ($sd['team']->name ?? 'TBD'),
			'players'    => array_values($board['players'][$sk]),
			'oppPlayers' => array_values($board['players'][$sk === 'home' ? 'away' : 'home']),
		];
	}
	$rwText = [
		'ace' => __('tournaments.rally_action_ace'), 'kill' => __('tournaments.rally_action_kill'), 'block' => __('tournaments.rally_action_block'),
		'opp_serve_error' => __('tournaments.rally_action_opp_serve_error'), 'opp_attack_error' => __('tournaments.rally_action_opp_attack_error'),
		'opp_block_error' => __('tournaments.rally_action_opp_block_error'), 'opp_reception_error' => __('tournaments.rally_action_opp_reception_error'),
		'unattributed' => __('tournaments.rally_action_unattributed'),
		'how' => __('tournaments.rally_wiz_how'), 'errorType' => __('tournaments.rally_wiz_error_type'),
		'whoScored' => __('tournaments.rally_wiz_who_scored'), 'oppWho' => __('tournaments.rally_wiz_opp_who'),
		'dig' => __('tournaments.rally_wiz_dig'), 'assist' => __('tournaments.rally_wiz_assist'),
		'skip' => __('tournaments.rally_wiz_skip'), 'back' => __('tournaments.rally_wiz_back'), 'cancel' => __('tournaments.rally_wiz_cancel'),
		'noPlayer' => __('tournaments.rally_no_player_option'),
	];
@endphp

<div class="container">

	@if(session('error'))
	<div class="alert alert-error">{{ session('error') }}</div>
	@endif
	@if(session('success'))
	<div class="alert alert-success">{{ session('success') }}</div>
	@endif

	<div class="ramka">

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
					<div class="b-700 f-18">@include('tournaments._partials.team_name_link', ['team' => $match->teamHome, 'fallback' => 'TBD'])</div>
					@include('tournaments._partials.team_roster_line', ['team' => $match->teamHome, 'class' => 'f-13', 'style' => 'opacity:.7'])
				</div>
				<div class="f-24 b-800 px-2">{{ $board['score']['home'] }} : {{ $board['score']['away'] }}</div>
				<div style="flex:1">
					<div class="b-700 f-18">@include('tournaments._partials.team_name_link', ['team' => $match->teamAway, 'fallback' => 'TBD'])</div>
					@include('tournaments._partials.team_roster_line', ['team' => $match->teamAway, 'class' => 'f-13', 'style' => 'opacity:.7'])
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
				<a href="{{ route('tournament.matches.rally.form', [$match, 'set' => $i]) }}"
				   class="btn btn-small {{ $i === $setNumber ? 'btn-primary' : 'btn-secondary' }}">
					{{ __('tournaments.rally_set_n', ['n' => $i]) }}
				</a>
				@endfor
			</div>
		</div>

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

		@foreach($sides as $sideKey => $side)
		@php
			$teamId = $side['team_id'];
			$players = $board['players'][$sideKey];
			$oppPlayers = $board['players'][$sideKey === 'home' ? 'away' : 'home'];
		@endphp
		<div class="card mb-2">
			<div class="b-700 f-16 mb-1">@include('tournaments._partials.team_name_link', ['team' => $side['team'], 'fallback' => 'TBD'])</div>

			@if(!$board['decided'])
			<div class="mt-2">
				<div class="f-13 b-600 mb-1">{{ __('tournaments.rally_add_point_title') }}</div>
				<div class="d-flex gap-1" style="flex-wrap:wrap">

					<button type="button" class="btn btn-primary js-rw-start" data-side="{{ $sideKey }}" data-kind="own" style="flex:1;min-width:16rem">🏐 {{ __('tournaments.rally_own_action_btn') }}</button>
					<button type="button" class="btn btn-secondary js-rw-start" data-side="{{ $sideKey }}" data-kind="opp" style="flex:1;min-width:16rem;background:rgba(220,38,38,.1)">⚠ {{ __('tournaments.rally_opp_error_row') }}</button>

				</div>
			</div>
			@endif
		</div>
		@endforeach

		<div class="d-flex text-center gap-1 mt-2" style="flex-wrap:wrap">
			<form method="POST" action="{{ route('tournament.matches.rally.undo', $match) }}" style="flex:1">
				@csrf
				<input type="hidden" name="set_number" value="{{ $setNumber }}">
				<button type="submit" class="btn btn-secondary w-100" {{ $board['can_undo'] ? '' : 'disabled' }}>
					↩ {{ __('tournaments.rally_btn_undo') }}
				</button>
			</form>
			<form method="POST" action="{{ route('tournament.matches.rally.finalize', $match) }}" style="flex:1">
				@csrf
				<button type="submit" class="btn btn-primary w-100" {{ $matchReady ? '' : 'disabled' }}>
					💾 {{ __('tournaments.rally_btn_finalize') }}
				</button>
			</form>
			<a href="{{ route('tournament.matches.score.form', $match) }}" class="btn btn-secondary" style="flex:1;text-align:center">
				{{ __('tournaments.btn_back') }}
			</a>
			@if($match->isCompleted())
			<a href="{{ route('tournament.matches.pdf_stats', $match) }}" data-file-download class="btn btn-secondary btn-small" title="{{ __('tournaments.rally_btn_pdf_stats') }}">
				📊 PDF
			</a>
			@endif
		</div>

	</div>
</div>

{{-- Мобильная эргономика: html{font-size} на этом сайте схлопывается до 50% на ≤480px
     (см. style.css), из-за чего .form select реально рендерится ~14.4px — ниже порога 16px,
     вызывающего авто-zoom при фокусе в iOS Safari. Страница используется с телефона у площадки —
     фикс только для этой страницы, без изменения общего .form select/.btn-small. --}}
<style>
.tournament-rally-page .form select {
	font-size: 16px;
}
.tournament-rally-page .btn-small {
	font-size: 13px;
}
</style>

<form id="rw-form" method="POST" action="{{ route('tournament.matches.rally.point', $match) }}" style="display:none">
	@csrf
	<input type="hidden" name="set_number" value="{{ $setNumber }}">
	<input type="hidden" name="team_id">
	<input type="hidden" name="action_type">
	<input type="hidden" name="player_id">
	<input type="hidden" name="dig_user_id">
	<input type="hidden" name="assist_user_id">
</form>

<style>
.rw-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:10000;display:none;align-items:flex-end;justify-content:center}
.rw-overlay.rw-open{display:flex}
.rw-box{background:#fff;color:#2c2c2c;width:100%;max-width:520px;max-height:90vh;overflow:auto;border-radius:16px 16px 0 0;padding:16px}
body.dark .rw-box{background:#222333;color:#cacaca}
@media(min-width:600px){.rw-overlay{align-items:center}.rw-box{border-radius:16px}}
.rw-title{font-weight:700;font-size:17px;margin-bottom:4px}
.rw-sub{font-size:13px;opacity:.65;margin-bottom:12px;min-height:1em}
.rw-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.rw-grid .btn{width:100%;font-size:15px;padding:14px 8px;text-align:center;white-space:normal;line-height:1.2}
.rw-grid .rw-wide{grid-column:1/-1}
.rw-foot{display:flex;gap:8px;margin-top:14px}
.rw-foot .btn{flex:1;font-size:14px}
</style>

<script>
(function () {
	var DATA = @json($rwData);
	var T = @json($rwText);
	var SELF = ['ace', 'kill', 'block'];
	var OPP = ['opp_serve_error', 'opp_attack_error', 'opp_block_error', 'opp_reception_error'];
	var OPP_REQ = ['opp_serve_error', 'opp_attack_error'];
	var form = document.getElementById('rw-form');
	var st = null, history = [], sending = false;

	var overlay = document.createElement('div');
	overlay.className = 'rw-overlay';
	overlay.innerHTML = '<div class="rw-box"><div class="rw-title"></div><div class="rw-sub"></div><div class="rw-grid"></div><div class="rw-foot"></div></div>';
	document.body.appendChild(overlay);
	var elTitle = overlay.querySelector('.rw-title'), elSub = overlay.querySelector('.rw-sub'),
		elGrid = overlay.querySelector('.rw-grid'), elFoot = overlay.querySelector('.rw-foot');

	function btn(label, cls, onClick, wide) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'btn ' + cls + (wide ? ' rw-wide' : '');
		b.textContent = label;
		b.addEventListener('click', onClick);
		return b;
	}
	function nameOf(list, id) {
		for (var i = 0; i < list.length; i++) if (String(list[i].id) === String(id)) return list[i].name;
		return '';
	}
	function close() { overlay.classList.remove('rw-open'); st = null; history = []; }

	function submit() {
		if (sending) return;
		sending = true;
		form.elements.team_id.value = DATA[st.side].teamId;
		form.elements.action_type.value = st.action || '';
		form.elements.player_id.value = st.player || '';
		form.elements.dig_user_id.value = st.dig || '';
		form.elements.assist_user_id.value = st.assist || '';
		form.submit();
	}

	function go(step) { history.push(st.step); st.step = step; render(); }
	function back() { if (history.length) { st.step = history.pop(); render(); } else close(); }

	function nextAfterPlayer() {
		// ace — прямой выигрыш на подаче: приёма/передачи своей команды нет
		if (st.kind === 'own' && st.action !== 'ace') { go('dig'); } else { submit(); }
	}

	function render() {
		var d = DATA[st.side], players = d.players;
		elGrid.innerHTML = ''; elFoot.innerHTML = '';
		var crumbs = [d.teamName];
		if (st.action) crumbs.push(T[st.action]);
		if (st.player) crumbs.push(nameOf(st.kind === 'own' ? d.players : d.oppPlayers, st.player));
		if (st.dig) crumbs.push('⤵ ' + nameOf(players, st.dig));
		elSub.textContent = crumbs.join(' · ');

		if (st.step === 'action') {
			elTitle.textContent = st.kind === 'own' ? T.how : T.errorType;
			var list = st.kind === 'own' ? SELF : OPP.concat(['unattributed']);
			list.forEach(function (a) {
				elGrid.appendChild(btn(T[a], st.kind === 'own' ? 'btn-primary' : 'btn-secondary', function () {
					st.action = a;
					if (a === 'unattributed') { submit(); } else { go('player'); }
				}, list.length % 2 === 1 && a === list[list.length - 1]));
			});
		} else if (st.step === 'player') {
			var pl = st.kind === 'own' ? d.players : d.oppPlayers;
			elTitle.textContent = st.kind === 'own' ? T.whoScored : T.oppWho;
			pl.forEach(function (p) {
				elGrid.appendChild(btn(p.name, 'btn-primary', function () { st.player = String(p.id); nextAfterPlayer(); }));
			});
			if (st.kind === 'opp' && OPP_REQ.indexOf(st.action) === -1) {
				elGrid.appendChild(btn(T.noPlayer, 'btn-secondary', function () { st.player = ''; submit(); }, true));
			}
		} else if (st.step === 'dig' || st.step === 'assist') {
			var isDig = st.step === 'dig';
			elTitle.textContent = isDig ? T.dig : T.assist;
			var left = players.filter(function (p) {
				return String(p.id) !== String(st.player) && (isDig || String(p.id) !== String(st.dig));
			});
			if (!left.length) { if (isDig) { st.step = 'assist'; return render(); } return submit(); }
			left.forEach(function (p) {
				elGrid.appendChild(btn(p.name, 'btn-primary', function () {
					if (isDig) { st.dig = String(p.id); go('assist'); } else { st.assist = String(p.id); submit(); }
				}));
			});
			elGrid.appendChild(btn(T.skip, 'btn-secondary', function () {
				if (isDig) { go('assist'); } else { submit(); }
			}, true));
		}

		elFoot.appendChild(btn(T.back, 'btn-secondary', back));
		elFoot.appendChild(btn(T.cancel, 'btn-secondary', close));
	}

	document.querySelectorAll('.js-rw-start').forEach(function (b) {
		b.addEventListener('click', function () {
			st = { side: this.dataset.side, kind: this.dataset.kind, step: 'action', action: '', player: '', dig: '', assist: '' };
			history = [];
			overlay.classList.add('rw-open');
			render();
		});
	});
	overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && st) close(); });
})();
</script>
</x-voll-layout>
