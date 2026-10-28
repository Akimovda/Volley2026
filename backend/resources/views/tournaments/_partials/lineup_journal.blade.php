{{-- Журнал партии: очки и события состава по seq, счёт «на момент». Тап по замене — попап. --}}
@php
	$jnTeam = ['home' => $match->teamHome->name ?? 'TBD', 'away' => $match->teamAway->name ?? 'TBD'];
@endphp
<div class="card mb-2">
	<div class="b-700 f-16 mb-1">{{ __('tournaments.lineup_journal_title') }}</div>
	<div class="jn-list" id="jn-list">
		@forelse($journal as $r)
			@if(($r['kind'] ?? 'point') === 'point')
			@php
				$jnAction = $r['action_type'] === 'unattributed' ? __('tournaments.rally_team_point') : __('tournaments.rally_action_' . $r['action_type']);
				$jnSur = $r['player'] ? trim(explode(' ', $r['player']['name'])[0] ?? '') : '';
			@endphp
			<div class="jn-row">
				<span class="jn-score">{{ $r['score_home'] }}:{{ $r['score_away'] }}</span>
				<span class="jn-body"><b>{{ $jnTeam[$r['team_side']] }}</b> · {{ $jnAction }}{{ $jnSur ? ' · ' . $jnSur : '' }}@if(!empty($r['by'])) <span class="jn-by">✍ {{ $r['by'] }}</span>@endif</span>
			</div>
			@else
			@php $jnKind = in_array($r['kind'], ['substitution', 'rotate', 'swap'], true) ? 'sub' : 'libero'; @endphp
			<div class="jn-row jn-row--lineup" role="button" tabindex="0" data-lineup-popup="{{ json_encode($r['popup'], JSON_UNESCAPED_UNICODE) }}">
				<span class="jn-score">{{ $r['score_home'] }}:{{ $r['score_away'] }}</span>
				<span class="jn-body">@include('tournaments._partials.icon_sub', ['kind' => $jnKind, 'size' => 16]) <b>{{ $jnTeam[$r['team_side']] }}</b> · {{ $r['label'] }} · {{ $r['summary'] }}@if(!empty($r['by'])) <span class="jn-by">✍ {{ $r['by'] }}</span>@endif</span>
			</div>
			@endif
		@empty
		<div class="f-13" style="opacity:.55">{{ __('tournaments.lineup_journal_empty') }}</div>
		@endforelse
	</div>
</div>
<script>(function(){var l=document.getElementById('jn-list');if(l)l.scrollTop=l.scrollHeight;})();</script>
