@php
$standings = $group->standings->sortBy('rank')->values();
$top3 = $standings->take(3);
$rest = $standings->slice(3);
$byRank = $top3->keyBy('rank');
$order = [2, 1, 3]; // визуальный порядок колонок: серебро - золото - бронза
$medal = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
$cls   = [1 => 'podium-gold', 2 => 'podium-silver', 3 => 'podium-bronze'];
@endphp

@if($top3->isNotEmpty())
<div class="podium-wrap">
	@foreach($order as $rank)
	@php $s = $byRank->get($rank); @endphp
	@if($s)
	<div class="podium-col {{ $cls[$rank] }}">
		<div class="podium-medal">{{ $medal[$rank] }}</div>
		<div class="podium-name">@include('tournaments._partials.team_name_link', ['team' => $s->team])</div>
		<div class="podium-sub">@include('tournaments._partials.team_roster_line', ['team' => $s->team, 'class' => 'podium-sub-line'])</div>
		<div class="podium-record">{{ $s->wins }}{{ __('tournaments.tv_w_short') }} · {{ $s->losses }}{{ __('tournaments.tv_l_short') }}</div>
		<div class="podium-bar">{{ $rank }}</div>
	</div>
	@endif
	@endforeach
</div>
@endif

@if($rest->isNotEmpty())
<div class="podium-rest">
	@foreach($rest as $s)
	<div class="d-flex gap-1" style="padding:4px 0">
		<span class="b-600" style="width:22px">{{ $s->rank }}.</span>
		<span>
			<div>@include('tournaments._partials.team_name_link', ['team' => $s->team])</div>
			@include('tournaments._partials.team_roster_line', ['team' => $s->team, 'class' => 'f-13'])
		</span>
		<span style="margin-left:auto">{{ $s->wins }}{{ __('tournaments.tv_w_short') }} {{ $s->losses }}{{ __('tournaments.tv_l_short') }}</span>
	</div>
	@endforeach
</div>
@endif
