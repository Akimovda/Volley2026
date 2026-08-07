@php
// Два режима вызова:
// 1) $group (+ опционально $stage) — как раньше, для вкладки «Обзор»: standings
//    берутся из группы, isFinalStage считается структурно (division_tier/
//    bracket/placement). Медали — только для стадий, где реально разыгрываются
//    призовые места турнира. Квалификационный групповой этап («Группа A»/«Группа
//    B» без division_tier) — просто ранжированный список без медалей-эмодзи,
//    места 1-2-3 подсвечены цветом строки. См. CLAUDE.md, диагностика пьедесталов.
// 2) $items — готовый список (Collection из stdClass: ->rank, ->team,
//    ->team_name_fallback, ->wins, ->losses — последние два опциональны) для
//    вкладки «Результаты»: там источник — calculateFinalClassification(), а не
//    TournamentStanding группы, но это ВСЕГДА уже финальная классификация (финал/
//    финальный дивизион), поэтому isFinalStage форсируется true, structural-проверка
//    по стадии не нужна.
if (isset($items)) {
    $podiumItems = $items->values();
    $isFinalStage = true;
} else {
    $stage = $stage ?? $group->stage;
    $isFinalStage = $stage
        && ($stage->division_tier !== null || $stage->isPlacementFinal() || $stage->isBracketStage());
    $podiumItems = $group->standings->sortBy('rank')->values();
}

$hasRecord = fn($s) => isset($s->wins) && isset($s->losses);
@endphp

@if($isFinalStage)
@php
$top3 = $podiumItems->take(3);
$rest = $podiumItems->slice(3);
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
		<div class="podium-name">@include('tournaments._partials.team_name_link', ['team' => $s->team, 'fallback' => $s->team_name_fallback ?? null])</div>
		<div class="podium-sub">@include('tournaments._partials.team_roster_line', ['team' => $s->team, 'class' => 'podium-sub-line'])</div>
		@if($hasRecord($s))
		<div class="podium-record">{{ $s->wins }}{{ __('tournaments.tv_w_short') }} · {{ $s->losses }}{{ __('tournaments.tv_l_short') }}</div>
		@endif
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
			<div>@include('tournaments._partials.team_name_link', ['team' => $s->team, 'fallback' => $s->team_name_fallback ?? null])</div>
			@include('tournaments._partials.team_roster_line', ['team' => $s->team, 'class' => 'f-13'])
		</span>
		@if($hasRecord($s))
		<span style="margin-left:auto">{{ $s->wins }}{{ __('tournaments.tv_w_short') }} {{ $s->losses }}{{ __('tournaments.tv_l_short') }}</span>
		@endif
	</div>
	@endforeach
</div>
@endif

@else
{{-- Квалификационная группа — без пьедестала/медалей, цветной ранжированный
     список: места 1-2-3 подсвечены цветом строки (та же палитра, что и медали
     дивизиона), остальные — обычным списком. --}}
@php
$rankBg = [1 => 'rgba(212,175,55,.12)', 2 => 'rgba(180,180,180,.14)', 3 => 'rgba(176,141,87,.12)'];
$rankColor = [1 => '#a07c10', 2 => '#555', 3 => '#8b5e1a'];
@endphp
<div class="podium-rest">
	@foreach($podiumItems as $s)
	@php $rc = (int) $s->rank; @endphp
	<div class="d-flex gap-1" style="padding:4px 6px;{{ isset($rankBg[$rc]) ? 'background:' . $rankBg[$rc] . ';border-radius:6px' : '' }}">
		<span class="b-600" style="width:22px;{{ isset($rankColor[$rc]) ? 'color:' . $rankColor[$rc] : '' }}">{{ $s->rank }}.</span>
		<span>
			<div>@include('tournaments._partials.team_name_link', ['team' => $s->team, 'fallback' => $s->team_name_fallback ?? null])</div>
			@include('tournaments._partials.team_roster_line', ['team' => $s->team, 'class' => 'f-13'])
		</span>
		@if($hasRecord($s))
		<span style="margin-left:auto">{{ $s->wins }}{{ __('tournaments.tv_w_short') }} {{ $s->losses }}{{ __('tournaments.tv_l_short') }}</span>
		@endif
	</div>
	@endforeach
</div>
@endif
