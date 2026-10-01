{{-- Итоги игрового вечера: пьедестал + таблица игроков. @var array $board ['rows','podium'] --}}
@php
$__name = fn($u) => $u ? (trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) ?: ($u->name ?? '?')) : '?';
$__medals = ['🥇', '🥈', '🥉'];
@endphp

@if(empty($board['rows']))
<div class="f-14" style="opacity:.6">{{ __('games.board_empty') }}</div>
@else
<div class="gm-podium mb-2">
	@if(($board['podium']['type'] ?? 'players') === 'teams')
	@foreach($board['podium']['items'] as $i => $t)
	<div class="gm-podium-item gm-podium-{{ $i + 1 }}">
		<div class="gm-podium-top">
		<div class="gm-medal">{{ $__medals[$i] }}</div>
		<span class="gm-team-tag gm-c-{{ $t['color'] }}">{{ $t['label'] }}</span>
		<div class="f-13 mt-05" style="opacity:.8">{{ $t['users']->map(fn($u) => $__name($u))->implode(', ') }}</div>
		<div class="f-13 mt-05" style="opacity:.7">{{ $t['wins'] }} {{ __('games.podium_wins') }} · {{ $t['win_rate'] }}%</div>
		</div>
		<div class="gm-step">{{ $i + 1 }}</div>
	</div>
	@endforeach
	@else
	@foreach($board['podium']['items'] ?? [] as $i => $p)
	<div class="gm-podium-item gm-podium-{{ $i + 1 }}">
		<div class="gm-podium-top">
		<div class="gm-medal">{{ $__medals[$i] }}</div>
		@if($p['user'])
		<a href="{{ route('users.show', $p['user_id']) }}"><img class="gm-ava" src="{{ $p['user']->profile_photo_url }}" alt=""></a>
		@endif
		<div class="b-600 f-15">{{ $__name($p['user']) }}</div>
		<div class="f-13" style="opacity:.7">{{ $p['wins'] }} {{ __('games.podium_wins') }} · {{ $p['win_rate'] }}%</div>
		</div>
		<div class="gm-step">{{ $i + 1 }}</div>
	</div>
	@endforeach
	@endif
</div>

@if(!empty($board['top_scorers']))
<h3 class="mt-2 mb-1">{{ __('games.top_scorers_title') }}</h3>
<div class="f-13 mb-1" style="opacity:.55">{{ __('games.top_scorers_hint') }}</div>
<div class="gm-scorers mb-2">
	@foreach($board['top_scorers'] as $i => $p)
	<div class="gm-scorer gm-scorer-{{ $i + 1 }}">
		<div class="gm-scorer-n">{{ $i + 1 }}</div>
		@if($p['user'])<a href="{{ route('users.show', $p['user_id']) }}"><img src="{{ $p['user']->profile_photo_url }}" alt=""></a>@endif
		<div class="b-600 f-15">{{ $__name($p['user']) }}</div>
		<div class="gm-scorer-pts">{{ $p['points_scored'] }} {{ __('games.top_scorers_points') }}</div>
	</div>
	@endforeach
</div>
@endif

<div class="f-13 mb-1" style="opacity:.55">{{ __('games.board_hint') }}</div>
<div class="table-scrollable mb-0">
	<div class="table-drag-indicator"></div>
	<table class="table f-14">
		<thead>
			<tr>
				<th>#</th>
				<th>{{ __('games.col_player') }}</th>
				<th>{{ __('games.col_games') }}</th>
				<th>{{ __('games.col_wins') }}</th>
				<th>{{ __('games.col_losses') }}</th>
				<th>{{ __('games.col_rate') }}</th>
				<th>{{ __('games.col_diff') }}</th>
				<th>{{ __('games.col_points') }}</th>
				<th>{{ __('games.col_aces') }}</th>
				<th>{{ __('games.col_kills') }}</th>
				<th>{{ __('games.col_blocks') }}</th>
			</tr>
		</thead>
		<tbody>
			@foreach($board['rows'] as $i => $p)
			<tr>
				<td>{{ $i + 1 }}</td>
				<td>@if($p['user'])<a href="{{ route('users.show', $p['user_id']) }}">{{ $__name($p['user']) }}</a>@else —@endif</td>
				<td>{{ $p['games'] }}</td>
				<td class="b-600">{{ $p['wins'] }}</td>
				<td>{{ $p['losses'] }}</td>
				<td>{{ $p['win_rate'] }}%</td>
				<td>{{ $p['diff'] > 0 ? '+' : '' }}{{ $p['diff'] }}</td>
				<td>{{ $p['points_scored'] ?: '—' }}</td>
				<td>{{ $p['aces'] ?: '—' }}</td>
				<td>{{ $p['kills'] ?: '—' }}</td>
				<td>{{ $p['blocks'] ?: '—' }}</td>
			</tr>
			@endforeach
		</tbody>
	</table>
</div>
@endif
