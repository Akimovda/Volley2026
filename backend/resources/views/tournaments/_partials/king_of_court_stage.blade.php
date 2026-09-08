@php
use App\Models\EventTeam;
use App\Models\TournamentStanding;
use App\Services\TournamentKingService;

$kocCourtTeamIds = (array) $stage->cfg('court_team_ids', []);
$kocAssigned = !empty($kocCourtTeamIds);
$kocRoundStatus = $stage->cfg('round_status', 'pending');
$kocCurrentRound = (int) $stage->cfg('current_round', 0);
$kocTotalRounds = (int) $stage->cfg('total_rounds', TournamentKingService::TOTAL_ROUNDS);
$kocHistory = $stage->cfg('rounds_history', []);
$kocColors = (array) $stage->cfg('team_colors', []);

$kocTeamsById = $kocAssigned
    ? EventTeam::whereIn('id', $kocCourtTeamIds)->get()->keyBy('id')
    : collect();

$kocStandings = $stage->isCompleted() || $kocHistory
    ? TournamentStanding::where('stage_id', $stage->id)->where('group_id', null)
        ->orderByDesc('points_scored')->get()
    : collect();

// Групповой этап (несколько кортов + финал среди победителей) — необязательная
// фича поверх одиночного корта, см. TournamentKingService::formCourts()/formFinal().
$kocBatchId = $stage->cfg('koc_batch_id');
$kocIsFinal = (bool) $stage->cfg('koc_final_of_batch');
$batchInfo = $batchInfo ?? null;
$kocShowFormFinalBtn = $kocBatchId && $stage->isCompleted() && $batchInfo && $batchInfo['allCompleted'] && !$batchInfo['hasFinal'];
@endphp

<div class="ramka" id="stage_{{ $stage->id }}">
	{{-- Шапка стадии --}}
	<div class="d-flex between fvc" style="flex-wrap:wrap;gap:8px">
		<div>
			<h2 class="-mt-05">
				{{ $stage->name }}
				@if($stage->isCompleted())
				<span class="f-18 alert-warning pt-05 pb-05 p-1">{{ __('tournaments.setup_st_completed') }}</span>
				@elseif($stage->isInProgress())
				<span class="f-18 alert-success pt-05 pb-05 p-1">{{ __('tournaments.setup_st_in_progress') }}</span>
				@else
				<span class="f-18 alert-info pt-05 pb-05 p-1">{{ __('tournaments.setup_st_waiting') }}</span>
				@endif
			</h2>
			<p>{{ __('tournaments.koc_lbl_title') }}
				@if($kocIsFinal)
				· {{ __('tournaments.koc_lbl_final_stage') }}
				@elseif($kocBatchId)
				· {{ __('tournaments.koc_lbl_qualifier_batch') }}
				@endif
				@if($kocAssigned)
				· {{ __('tournaments.koc_lbl_round', ['n' => max($kocCurrentRound, 1), 'total' => $kocTotalRounds]) }}
				@endif
			</p>
		</div>
		<div class="d-flex" style="gap:6px">
			<form method="POST" action="{{ route('tournament.stages.destroy', $stage) }}">
				@csrf @method('DELETE')
				<button class="btn btn-danger f-12 btn-alert"
					data-title="{{ __('tournaments.setup_delete_stage_title') }}"
					data-icon="warning"
					data-confirm-text="{{ __('tournaments.btn_delete') }}"
					data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_delete_stage') }}</button>
			</form>
		</div>
	</div>

	@if(!$kocAssigned)
	{{-- Корт ещё не собран --}}
	<div class="card mt-2 p-3" style="text-align:center">
		<p class="f-16 mb-1">{{ __('tournaments.koc_hint_not_assigned') }}</p>
		<a href="{{ route('tournament.kingOfCourt.assignForm', $stage) }}" class="btn btn-primary">{{ __('tournaments.koc_btn_assign') }}</a>
	</div>
	@else

	{{-- Состав корта --}}
	<div class="card mt-2 p-3">
		<div class="b-700 f-16 mb-1">{{ __('tournaments.koc_lbl_court_teams') }}</div>
		<div class="d-flex koc-court-teams" style="gap:8px;flex-wrap:wrap">
			@foreach($kocCourtTeamIds as $tId)
			@php $t = $kocTeamsById->get($tId); @endphp
			<span class="badge badge-sm" style="{{ !empty($kocColors[$tId]) ? 'background:'.$kocColors[$tId].';color:#fff' : '' }}">
				@include('tournaments._partials.team_name_link', ['team' => $t, 'fallback' => '?'])
				@if(in_array($tId, (array) $stage->cfg('eliminated_team_ids', [])))
				· {{ __('tournaments.koc_lbl_eliminated') }}
				@endif
			</span>
			@endforeach
		</div>

		{{-- Цвета команд (для TV-режима) --}}
		<details class="mt-2">
			<summary class="f-13" style="cursor:pointer;opacity:.7">{{ __('tournaments.koc_lbl_colors') }}</summary>
			<form method="POST" action="{{ route('tournament.kingOfCourt.saveColors', $stage) }}" class="form mt-1">
				@csrf
				<div class="d-flex" style="gap:14px;flex-wrap:wrap">
					@foreach($kocCourtTeamIds as $tId)
					@php $t = $kocTeamsById->get($tId); $tColorInit = $kocColors[$tId] ?? '#2967BA'; @endphp
					<label class="d-flex fvc" style="gap:8px">
						<input type="color" name="colors[{{ $tId }}]" value="{{ $tColorInit }}"
							style="width:2.6rem;height:2.6rem;padding:0;border:none;cursor:pointer"
							oninput="this.nextElementSibling.style.borderBottom='4px solid '+this.value">
						<span class="f-14 b-600" style="border-bottom:4px solid {{ $tColorInit }};padding-bottom:2px">{{ $t?->name ?? '?' }}</span>
					</label>
					@endforeach
				</div>
				<button type="submit" class="btn btn-secondary btn-small mt-1">{{ __('tournaments.btn_save') }}</button>
			</form>
		</details>
	</div>

	{{-- Текущий раунд --}}
	@if($kocRoundStatus === 'in_progress')
	@php $kocState = app(TournamentKingService::class)->currentState($stage); @endphp
	@if($kocState)
	<div class="card mt-2 p-3" style="text-align:center">
		<div class="koc-vs-row">
			<div class="koc-vs-side">
				<div class="f-12" style="opacity:.6">👑 {{ __('tournaments.koc_lbl_king') }}</div>
				<div class="b-700 f-18">{{ $kocTeamsById->get($kocState['king_team_id'])?->name ?? '?' }}</div>
				<div class="f-14">{{ (int) ($kocState['round_points'][$kocState['king_team_id']] ?? 0) }} {{ __('tournaments.pub_pts_label') }}</div>
			</div>
			<div class="koc-vs-divider"></div>
			<div class="koc-vs-side">
				<div class="f-12" style="opacity:.6">🙋 {{ __('tournaments.koc_lbl_challenger') }}</div>
				<div class="b-700 f-18">{{ $kocTeamsById->get($kocState['challenger_team_id'])?->name ?? '?' }}</div>
			</div>
		</div>
		<a href="{{ route('tournament.kingOfCourt.scoreForm', $stage) }}" class="btn btn-primary mt-2">{{ __('tournaments.koc_btn_score') }}</a>
	</div>
	@endif
	@elseif($stage->isInProgress() && $kocRoundStatus === 'finished' && $kocCurrentRound < $kocTotalRounds)
	<div class="card mt-2 p-3" style="text-align:center">
		<p class="f-16">{{ __('tournaments.koc_hint_round_finished') }}</p>
		<form method="POST" action="{{ route('tournament.kingOfCourt.nextRound', $stage) }}">
			@csrf
			<button type="submit" class="btn btn-primary">{{ __('tournaments.koc_btn_next_round', ['n' => $kocCurrentRound + 1]) }}</button>
		</form>
	</div>
	@endif

	{{-- История раундов --}}
	@if(!empty($kocHistory))
	<div class="card mt-2 p-3">
		<div class="b-700 f-16 mb-1">{{ __('tournaments.koc_lbl_history') }}</div>
		<div class="table-scrollable">
			<table class="table f-13">
				<thead>
					<tr>
						<th style="text-align:left">{{ __('tournaments.koc_lbl_round_n') }}</th>
						@foreach($kocCourtTeamIds as $tId)
						<th style="text-align:center">{{ $kocTeamsById->get($tId)?->name ?? '?' }}</th>
						@endforeach
					</tr>
				</thead>
				<tbody>
					@foreach($kocHistory as $round)
					<tr>
						<td class="b-700">
							{{ __('tournaments.koc_lbl_round_n') }} {{ $round['round'] }}
							@if(!empty($round['eliminated_team_id']))
							<div class="f-11" style="opacity:.6">{{ __('tournaments.koc_lbl_eliminated') }}: {{ $kocTeamsById->get($round['eliminated_team_id'])?->name ?? '?' }}</div>
							@endif
						</td>
						@foreach($kocCourtTeamIds as $tId)
						<td style="text-align:center">{{ $round['points'][$tId] ?? '—' }}</td>
						@endforeach
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
	@endif

	{{-- Итоговый лидерборд (без мест 1-2-3-4 — только рейтинг по очкам) --}}
	@if($stage->isCompleted() && $kocStandings->isNotEmpty())
	<div class="card mt-2 p-3">
		<div class="b-700 f-16 mb-1">🏆 {{ __('tournaments.koc_lbl_final_standings') }}</div>
		<div class="table-scrollable">
			<table class="table f-14">
				<tbody>
					@foreach($kocStandings as $st)
					<tr>
						<td class="b-700" style="width:2rem">{{ $loop->iteration }}</td>
						<td class="koc-court-teams">
							<span class="badge badge-sm" style="{{ !empty($kocColors[$st->team_id]) ? 'background:'.$kocColors[$st->team_id].';color:#fff' : '' }}">
								@include('tournaments._partials.team_name_link', ['team' => $kocTeamsById->get($st->team_id), 'fallback' => '?'])
							</span>
						</td>
						<td class="b-700" style="text-align:right">{{ $st->points_scored }} {{ __('tournaments.pub_pts_label') }}</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
	@endif

	{{-- Групповой этап: все корты батча завершены, финал ещё не создан. --}}
	@if($kocShowFormFinalBtn)
	<div class="card mt-2 p-3" style="text-align:center">
		<p class="f-16 mb-1">{{ __('tournaments.koc_hint_batch_completed') }}</p>
		<form method="POST" action="{{ route('tournament.kingOfCourt.formFinal', $stage) }}">
			@csrf
			<button type="submit" class="btn btn-primary">🏆 {{ __('tournaments.koc_btn_form_final') }}</button>
		</form>
	</div>
	@endif

	@endif
</div>
