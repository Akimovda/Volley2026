<x-voll-layout body_class="tournament-rally-page">
<x-slot name="title">{{ __('tournaments.rally_title') }} — {{ $event->title }}</x-slot>
<x-slot name="h1">{{ __('tournaments.rally_h1') }}</x-slot>

<x-slot name="breadcrumbs">
	<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		<a href="{{ route('tournament.setup', $event) }}{{ $match->stage->occurrence_id ? '?occurrence_id=' . $match->stage->occurrence_id : '' }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
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
			<div class="f-13 mb-2">
				{{ __('tournaments.score_match_header', ['n' => $match->match_number, 'r' => $match->round]) }} · {{ strtoupper($format) }}
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
			<div class="f-13" style="opacity:.6">
				{{ __('tournaments.rally_set_n', ['n' => $setNumber]) }} · {{ __('tournaments.score_to_pts') }} {{ $board['target_points'] }}
				@if($board['decided']) · {{ __('tournaments.rally_set_decided') }} @endif
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

		<div class="alert alert-info f-13">{{ __('tournaments.rally_hint_two_ways') }}</div>
		<div class="alert alert-info f-13">{{ __('tournaments.rally_no_attempts_note') }}</div>

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

					{{-- Шаг 1а: своё результативное действие --}}
					<details style="flex:1;min-width:16rem">
						<summary class="btn btn-primary" style="cursor:pointer;display:block;text-align:center">🏐 {{ __('tournaments.rally_own_action_btn') }}</summary>
						<form method="POST" action="{{ route('tournament.matches.rally.point', $match) }}" class="form mt-1">
							@csrf
							<input type="hidden" name="set_number" value="{{ $setNumber }}">
							<input type="hidden" name="team_id" value="{{ $teamId }}">
							<select name="player_id" class="mb-1" required>
								<option value="">{{ __('tournaments.rally_who_scored') }}</option>
								@foreach($players as $pp)
								<option value="{{ $pp['id'] }}">{{ $pp['name'] }}</option>
								@endforeach
							</select>
							<select name="dig_user_id" class="mb-1">
								<option value="">{{ __('tournaments.rally_tag_dig') }}</option>
								@foreach($players as $pp)
								<option value="{{ $pp['id'] }}">{{ $pp['name'] }}</option>
								@endforeach
							</select>
							<select name="assist_user_id" class="mb-1">
								<option value="">{{ __('tournaments.rally_tag_assist') }}</option>
								@foreach($players as $pp)
								<option value="{{ $pp['id'] }}">{{ $pp['name'] }}</option>
								@endforeach
							</select>
							<div class="d-flex gap-1" style="flex-wrap:wrap">
								<button type="submit" name="action_type" value="ace" class="btn btn-small btn-primary">{{ __('tournaments.rally_action_ace') }}</button>
								<button type="submit" name="action_type" value="kill" class="btn btn-small btn-primary">{{ __('tournaments.rally_action_kill') }}</button>
								<button type="submit" name="action_type" value="block" class="btn btn-small btn-primary">{{ __('tournaments.rally_action_block') }}</button>
							</div>
						</form>
					</details>

					{{-- Шаг 1б: ошибка соперника --}}
					<details style="flex:1;min-width:16rem">
						<summary class="btn btn-secondary" style="cursor:pointer;display:block;text-align:center;background:rgba(220,38,38,.1)">⚠ {{ __('tournaments.rally_opp_error_row') }}</summary>
						<form method="POST" action="{{ route('tournament.matches.rally.point', $match) }}" class="form mt-1">
							@csrf
							<input type="hidden" name="set_number" value="{{ $setNumber }}">
							<input type="hidden" name="team_id" value="{{ $teamId }}">
							<select name="player_id" class="mb-1">
								<option value="">{{ __('tournaments.rally_no_player_option') }}</option>
								@foreach($oppPlayers as $pp)
								<option value="{{ $pp['id'] }}">{{ $pp['name'] }}</option>
								@endforeach
							</select>
							<div class="d-flex gap-1" style="flex-wrap:wrap">
								<button type="submit" name="action_type" value="opp_serve_error" class="btn btn-small btn-secondary">{{ __('tournaments.rally_action_opp_serve_error') }}</button>
								<button type="submit" name="action_type" value="opp_attack_error" class="btn btn-small btn-secondary">{{ __('tournaments.rally_action_opp_attack_error') }}</button>
								<button type="submit" name="action_type" value="opp_block_error" class="btn btn-small btn-secondary">{{ __('tournaments.rally_action_opp_block_error') }}</button>
								<button type="submit" name="action_type" value="opp_reception_error" class="btn btn-small btn-secondary">{{ __('tournaments.rally_action_opp_reception_error') }}</button>
								<button type="submit" name="action_type" value="unattributed" class="btn btn-small btn-secondary">{{ __('tournaments.rally_action_unattributed') }}</button>
							</div>
						</form>
					</details>

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
		</div>

	</div>
</div>
</x-voll-layout>
