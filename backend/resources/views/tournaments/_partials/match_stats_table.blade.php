@php
$fields = [
	'serves_total' => __('tournaments.stat_serves_short'),
	'aces' => __('tournaments.stat_aces_short'),
	'serve_errors' => __('tournaments.stat_serve_err_short'),
	'attacks_total' => __('tournaments.stat_attacks_short'),
	'kills' => __('tournaments.stat_kills_short'),
	'attack_errors' => __('tournaments.stat_attack_err_short'),
	'blocks' => __('tournaments.stat_blocks_short'),
	'block_errors' => __('tournaments.stat_block_err_short'),
	'digs' => __('tournaments.stat_digs_short'),
	'reception_errors' => __('tournaments.stat_dig_err_short'),
	'assists' => __('tournaments.stat_assists_short'),
];
$getVal = fn($totals, $f) => is_array($totals) ? ($totals[$f] ?? 0) : ($totals->$f ?? 0);
@endphp
<div class="row">
	@foreach(['home' => $match->teamHome, 'away' => $match->teamAway] as $side => $sideTeam)
	<div class="col-md-6">
		<div class="b-600 mb-1">{{ $sideTeam->name ?? '—' }}</div>
		<div class="table-scrollable">
			<table class="table f-13">
				<thead>
					<tr>
						<th style="text-align:left">{{ __('tournaments.stats_col_player') }}</th>
						@foreach($fields as $label)
						<th style="text-align:center">{{ $label }}</th>
						@endforeach
						<th style="text-align:center">{{ __('tournaments.stats_col_pts') }}</th>
					</tr>
				</thead>
				<tbody>
					@forelse(($statsData[$side] ?? []) as $p)
					<tr>
						<td><a href="{{ route('users.show', $p['user_id']) }}" class="blink">{{ $p['user_name'] }}</a></td>
						@foreach(array_keys($fields) as $f)
						<td style="text-align:center">{{ $getVal($p['totals'], $f) }}</td>
						@endforeach
						<td style="text-align:center" class="b-700">{{ $getVal($p['totals'], 'points_scored') }}</td>
					</tr>
					@empty
					<tr><td colspan="{{ count($fields) + 2 }}" style="text-align:center;opacity:.5">—</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
	@endforeach
</div>
