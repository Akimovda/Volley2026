{{-- Таблицы групп над сеткой плей-офф (как блоки групп над сеткой в футбольных турнирах): цветная шапка группы, таблица И/В/П/+−/Очки,
     команды, прошедшие в плей-офф, подсвечены; под таблицей — результаты матчей группы табло-плитками.
     Нужны: $stages (с groups.standings и matches). Цвета групп — те же, что у меток «N место · гр. X» в сетке (_bracket). --}}
@php
	$__elimTypes = ['single_elim', 'double_elim'];
	$__groupStages = $stages->filter(fn ($st) => !in_array($st->type, $__elimTypes, true) && $st->groups->isNotEmpty());
	$__palette = ['#7c3aed', '#0d9488', '#dc2626', '#d97706', '#16a34a', '#2563eb', '#db2777', '#0891b2'];
	// команды, дошедшие до плей-офф (встречаются в матчах сетки)
	$__advanced = $stages->filter(fn ($st) => in_array($st->type, $__elimTypes, true))
		->flatMap(fn ($st) => $st->matches->flatMap(fn ($m) => [$m->team_home_id, $m->team_away_id]))
		->filter()->unique()->flip();
	$__allGroups = $__groupStages->flatMap(fn ($st) => $st->groups)->sortBy('sort_order')->values();
@endphp
@if($__groupStages->isNotEmpty())
@once
<style>
.tgrp-title{display:flex;align-items:center;gap:8px;font-weight:800;font-size:1.05rem;margin:0 0 12px}
.tgrp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:16px;margin-bottom:26px}
.tgrp-card{border-radius:18px;overflow:hidden;border:1px solid rgba(15,23,42,.08);background:linear-gradient(#fff,#fbfcff);box-shadow:0 3px 14px rgba(15,23,42,.08)}
.tgrp-head{background:linear-gradient(135deg,var(--gc),color-mix(in srgb,var(--gc) 70%,#000));color:#fff;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;font-size:.9rem}
.tgrp-head small{font-weight:600;opacity:.85;letter-spacing:.02em;text-transform:none}
.tgrp-table{width:100%;border-collapse:collapse;font-size:.85rem}
.tgrp-table th{padding:7px 6px;text-align:center;font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid rgba(15,23,42,.08)}
.tgrp-table th:nth-child(2),.tgrp-table td:nth-child(2){text-align:left}
.tgrp-table td{padding:7px 6px;text-align:center;border-bottom:1px solid rgba(15,23,42,.05)}
.tgrp-table tr:last-child td{border-bottom:none}
.tgrp-table td:first-child{width:34px;font-weight:800;color:#64748b}
.tgrp-table .tgrp-w{color:#10b981;font-weight:700}.tgrp-table .tgrp-l{color:#dc2626}.tgrp-table .tgrp-pts{font-weight:900;color:var(--gc)}
.tgrp-table tr.tgrp-q{background:linear-gradient(90deg,rgba(16,185,129,.12),rgba(16,185,129,0))}
.tgrp-table tr.tgrp-q td:first-child{box-shadow:inset 3px 0 0 #10b981;color:#047857}
.tgrp-team{font-weight:700;line-height:1.2}
.tgrp-matches{border-top:1px dashed rgba(15,23,42,.12);padding:8px 12px 12px}
.tgrp-mrow{display:grid;grid-template-columns:1fr auto 1fr;gap:8px;align-items:center;padding:5px 0;font-size:.78rem;border-bottom:1px solid rgba(15,23,42,.04)}
.tgrp-mrow:last-child{border-bottom:none}
.tgrp-mrow .tgrp-mt{min-width:0;overflow-wrap:anywhere;line-height:1.2}
.tgrp-mrow .tgrp-mt--r{text-align:right}
.tgrp-mrow .tgrp-mt.is-win{font-weight:800}
.tgrp-mrow .tgrp-ms{text-align:center;min-width:74px}
body.dark .tgrp-card{background:linear-gradient(#1e293b,#18222f);border-color:rgba(255,255,255,.1)}
body.dark .tgrp-table th{border-color:rgba(255,255,255,.1);color:#94a3b8}
body.dark .tgrp-table td{border-color:rgba(255,255,255,.06)}
body.dark .tgrp-table td:first-child{color:#94a3b8}
body.dark .tgrp-table tr.tgrp-q td:first-child{color:#34d399}
body.dark .tgrp-matches{border-color:rgba(255,255,255,.14)}
body.dark .tgrp-mrow{border-color:rgba(255,255,255,.06)}
</style>
@endonce
<div class="tgrp-title"><x-ico name="users" size="1.2em" /> {{ __('tournaments.bg_title') }}</div>
<div class="tgrp-grid">
	@foreach($__groupStages as $__gs)
	@foreach($__gs->groups->sortBy('sort_order') as $__g)
	@php
		$__idx = $__allGroups->search(fn ($x) => $x->id === $__g->id);
		$__color = $__palette[($__idx === false ? 0 : $__idx) % count($__palette)];
		$__gm = $__gs->matches->where('group_id', $__g->id)->sortBy([['round', 'asc'], ['match_number', 'asc']]);
		$__done = $__gm->where('status', 'completed')->count();
	@endphp
	<div class="tgrp-card" style="--gc:{{ $__color }}">
		<div class="tgrp-head"><span>{{ $__g->name }}</span><small>{{ $__done }}/{{ $__gm->count() }}</small></div>
		@if($__g->standings->isNotEmpty())
		<table class="tgrp-table">
			<thead><tr>
				<th>#</th><th>{{ __('tournaments.standings_col_team') }}</th>
				<th>{{ __('tournaments.standings_col_played') }}</th><th>{{ __('tournaments.standings_col_w') }}</th><th>{{ __('tournaments.standings_col_l') }}</th>
				<th>{{ __('tournaments.tv_diff_col') }}</th><th>{{ __('tournaments.tv_pts_col') }}</th>
			</tr></thead>
			<tbody>
			@foreach($__g->standings->sortBy('rank') as $__s)
			<tr class="{{ $__advanced->has($__s->team_id) ? 'tgrp-q' : '' }}">
				<td>{{ $__s->rank }}</td>
				<td><div class="tgrp-team">@include('tournaments._partials.team_name_link', ['team' => $__s->team])</div></td>
				<td>{{ $__s->played }}</td>
				<td class="tgrp-w">{{ $__s->wins }}</td>
				<td class="tgrp-l">{{ $__s->losses }}</td>
				<td>{{ $__s->pointDiff() > 0 ? '+' : '' }}{{ $__s->pointDiff() }}</td>
				<td class="tgrp-pts">{{ $__s->rating_points }}</td>
			</tr>
			@endforeach
			</tbody>
		</table>
		@endif
		@if($__gm->isNotEmpty())
		<div class="tgrp-matches">
			@foreach($__gm as $__m)
			<div class="tgrp-mrow">
				<div class="tgrp-mt tgrp-mt--r {{ $__m->winner_team_id && $__m->winner_team_id === $__m->team_home_id ? 'is-win' : '' }}">{{ $__m->teamHome?->name ?? 'TBD' }}</div>
				<div class="tgrp-ms">@include('tournaments._partials.match_score_board', ['m' => $__m])</div>
				<div class="tgrp-mt {{ $__m->winner_team_id && $__m->winner_team_id === $__m->team_away_id ? 'is-win' : '' }}">{{ $__m->teamAway?->name ?? 'TBD' }}</div>
			</div>
			@endforeach
		</div>
		@endif
	</div>
	@endforeach
	@endforeach
</div>
@endif
