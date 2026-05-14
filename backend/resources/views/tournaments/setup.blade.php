<x-voll-layout body_class="tournament-setup-page">
	@php
    $direction = $event->direction ?? 'classic';
    $isBeach = $direction === 'beach';
	@endphp
	<x-slot name="title">{{ __('tournaments.setup_title_with', ['title' => $event->title]) }}</x-slot>
	
    <x-slot name="h1">{{ __('tournaments.setup_title_with', ['title' => $event->title]) }}</x-slot>
	
	
{{-- Активный тур --}}
@if($selectedOccurrence)
@php
$occDate = \Carbon\Carbon::parse($selectedOccurrence->starts_at)->setTimezone($event->timezone ?? 'Europe/Moscow');
$tourNumber = $seasonData['occurrences']->search(fn($occ) => $occ->id === $selectedOccurrence->id) + 1;
@endphp
<x-slot name="h2">
		{{ __('tournaments.setup_round_n', ['n' => $tourNumber, 'date' => $occDate->format('d.m.Y')]) }}
</x-slot>
@endif	
	
	
	
	@if($seasonData)
    <x-slot name="t_description">
				{{ $seasonData['season']->name }}
				/ {{ $seasonData['league']->name ?? __('tournaments.setup_league_default') }}
	</x-slot>
	@endif
	
	
	

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('events.show', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ __('tournaments.setup_breadcrumb') }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>
	
	<x-slot name="d_description">
		<div class="d-flex flex-wrap gap-1 m-center">
			<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
				<button class="btn ufilter-btn">{{ __('tournaments.setup_pick_round') }}</button>
			</div>
			<!--
			<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
				
			</div>	
			-->
		</div>
	</x-slot>	
	
	<div class="users-filter">
		<div class="container">
			<div class="ramka">	
				{{-- Выбор тура --}}
				@if($seasonData && $seasonData['occurrences']->count() > 1)
				<h2 class="-mt-05">{{ __('tournaments.setup_round_label') }}</h2>
				<div class="d-flex text-center" style="gap:1rem; flex-wrap:wrap;">
					@foreach($seasonData['occurrences'] as $occ)
					@php
					$isSelected = $selectedOccurrence && $selectedOccurrence->id === $occ->id;
					$occDate = \Carbon\Carbon::parse($occ->starts_at)->setTimezone($event->timezone ?? 'Europe/Moscow');
					@endphp
					<a href="{{ route('tournament.setup', $event) }}?occurrence_id={{ $occ->id }}"
					class="btn {{ !$isSelected ? 'btn-secondary' : '' }}">
						<span class="b-600">{{ $loop->iteration }}</span> - ({{ $occDate->format('d.m') }})
					</a>
					@endforeach
				</div>
				@endif		
			</div>
		</div>
	</div>
	
	<div class="container form">
		
		{{-- ========================= ЗАЯВКИ ========================= --}}
		{{-- Показываем блок если:
		     - application_mode=manual: все pending+incomplete заявки
		     - application_mode=auto: только incomplete (висят без auto-approval до сбора состава) --}}
		@php
			$mode = $applicationMode ?? 'manual';
			$visibleApps = isset($pendingApplications)
				? ($mode === 'manual'
					? $pendingApplications
					: $pendingApplications->where('status', 'incomplete'))
				: collect();
		@endphp
		@if($visibleApps->count())
		<div class="ramka">
			<h2 class="-mt-05">{{ __('tournaments.apps_h2', ['n' => $visibleApps->count()]) }}</h2>

			@if($mode === 'manual')
			<div class="alert alert-info mb-2">
				{!! __('tournaments.apps_mode_manual') !!}
			</div>
			@else
			<div class="alert alert-warning mb-2">
				{{ __('events.tapp_incomplete_body', ['team' => '—', 'event' => $event->title]) }}
			</div>
			@endif
			
			@foreach($visibleApps as $app)
			@php $isIncomplete = $app->status === 'incomplete'; @endphp
			<div class="card mb-1">
				<div class="d-flex fvc" style="justify-content:space-between;flex-wrap:wrap;gap:.5rem">
					<div>
						<div class="d-flex fvc" style="gap:.5rem;flex-wrap:wrap">
							<div class="b-700 f-17">{{ $app->team->name ?? '?' }}</div>
							@if($isIncomplete)
							<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef9c3;color:#854d0e">
								{{ __('events.tapp_status_incomplete') }}
							</span>
							@endif
						</div>
						<div class="f-13" style="opacity:.6">
							{{ __('tournaments.apps_captain') }}
							<a class="blink" href="{{ route('users.show', $app->team->captain_user_id) }}">
								{{ trim(($app->team->captain->last_name ?? '') . ' ' . ($app->team->captain->first_name ?? '')) ?: $app->team->captain->name ?? '?' }}
							</a>
							&middot; {{ __('tournaments.apps_applied_at') }} {{ $app->applied_at?->format('d.m.Y H:i') }}
						</div>
						@if($app->team->members->count())
						<div class="f-13 mt-05">
							{{ __('tournaments.setup_apps_lineup') }}
							@foreach($app->team->members as $m)
							<a class="blink" href="{{ route('users.show', $m->user_id) }}">{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}</a>@if(!$loop->last), @endif
							@endforeach
						</div>
						@endif
					</div>
					<div class="d-flex" style="gap:.5rem">
						@unless($isIncomplete)
						<form method="POST" action="{{ route('tournament.application.approve', [$event, $app]) }}">
							@csrf
							<button type="submit" class="btn btn-small btn-primary btn-alert" data-title="{{ __('tournaments.apps_confirm_approve') }}" data-icon="question" data-confirm-text="{{ __('tournaments.setup_apps_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_apps_btn_approve') }}</button>
						</form>
						@endunless
						<form method="POST" action="{{ route('tournament.application.reject', [$event, $app]) }}">
							@csrf
							<button type="submit" class="btn btn-small btn-secondary btn-alert" data-title="{{ __('tournaments.apps_confirm_reject') }}" data-icon="warning" data-confirm-text="{{ __('tournaments.setup_apps_no') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_apps_btn_reject') }}</button>
						</form>
					</div>
				</div>
			</div>
			@endforeach
		</div>
		@elseif(($applicationMode ?? 'manual') === 'auto')
		<div class="ramka">
			<div class="alert alert-success">
				{!! __('tournaments.setup_apps_auto_mode') !!}
			</div>
		</div>
		@endif
		
		
		
		@if(session('success') || session('error'))
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				if (typeof Swal !== 'undefined') {
					Swal.fire({
						icon: '{{ session("success") ? "success" : "error" }}',
						title: @json(session("success") ? __('tournaments.setup_swal_done') : __('tournaments.setup_swal_error')),
						text: {!! json_encode(session('success') ?: session('error')) !!},
						timer: 3000,
						showConfirmButton: false,
						toast: true,
						position: 'top-end',
					});
				}
			});
		</script>
		@endif
		@if($errors->any())
		<div class="ramka">
			<div class="alert alert-error">
				@foreach($errors->all() as $err)
                {{ $err }}<br>
				@endforeach
			</div>
		</div>
		@endif
		
		
		
		{{-- ============================================================
		{{ __('tournaments.setup_series_h2') }}
		============================================================ --}}
		@if($seasonData)
		<div class="ramka" id="season_league_management">
			<style>
				.league-table { width:100%; border-collapse:collapse; }
				.league-table th { text-align:left; padding:8px 6px; border-bottom:2px solid #e5e7eb; font-size:13px; color:#6b7280; }
				.league-table td { padding:10px 6px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
				.league-table tr:last-child td { border-bottom:none; }
				.league-table .team-name { font-weight:600; font-size:15px; }
				.league-table .team-members { font-size:13px; color:#6b7280; margin-top:2px; }
				.league-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; }
				.league-badge-active { background:#dcfce7; color:#166534; }
				.league-badge-reserve { background:#fef3c7; color:#92400e; }
				.league-badge-pending { background:#dbeafe; color:#1e40af; }
				.league-btn { padding:5px 12px; border-radius:8px; font-size:12px; border:1px solid #d1d5db; background:#fff; cursor:pointer; font-weight:500; }
				.league-btn:hover { background:#f9fafb; }
				.league-btn-danger { color:#dc2626; border-color:#fca5a5; }
				.league-btn-danger:hover { background:#fef2f2; }
				.league-btn-success { color:#16a34a; border-color:#86efac; }
				.league-btn-success:hover { background:#f0fdf4; }
				.tour-btn { display:inline-block; padding:6px 14px; border-radius:8px; font-size:13px; border:1px solid #d1d5db; background:#fff; color:#374151; text-decoration:none; font-weight:500; }
				.tour-btn:hover { background:#f3f4f6; text-decoration:none; }
				.tour-btn-active { background:#4f46e5; color:#fff; border-color:#4f46e5; }
				.tour-btn-active:hover { background:#4338ca; }
				@media (max-width:640px) {
				.league-table th:nth-child(1), .league-table td:nth-child(1) { display:none; }
				.league-table th:nth-child(3) { width:70px; }
				.league-table th:nth-child(4) { width:90px; }
				.league-table .team-name { font-size:14px; }
				.league-table .team-members { font-size:12px; }
				.league-badge { padding:2px 8px; font-size:11px; }
				.league-btn { padding:4px 8px; font-size:11px; }
				.tour-btn { padding:5px 10px; font-size:12px; }
				}
			</style>

			
			{{-- Состав лиги --}}
			@if($leagueTeams->count())
			<div class="">
				<h2 class="-mt-05">
					{{ __('tournaments.setup_series_lineup') }} 
					<span class="cd">
						— {{ $leagueTeams->where('status', 'active')->count() }} {{ __('tournaments.setup_series_active') }}
						@if($leagueTeams->where('status', 'reserve')->count())
						/ {{ $leagueTeams->where('status', 'reserve')->count() }} {{ __('tournaments.setup_series_reserve') }}
						@endif
					</span>
				</h2>
				<div class="table-scrollable">
					<div class="table-drag-indicator"></div>				
					<table class="table">
						<thead>
							<tr>
								<th style="width:30px">#</th>
								<th>{{ __('tournaments.setup_col_team') }}</th>
								<th>{{ __('tournaments.setup_col_status') }}</th>
								<th>{{ __('tournaments.setup_col_action') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($leagueTeams as $lt)
							<tr>
								<td>{{ $loop->iteration }}</td>
								<td>
									@if($lt->team)
									<div class="team-name cd b-600">{{ $lt->team->name }}</div>
									<div class="team-members">
										@php
										$members = $lt->team->members->map(function($m) {
										$u = $m->user;
										return $u ? ($u->first_name . ' ' . $u->last_name) : '?';
										})->implode(' / ');
										@endphp
										{{ $members }}
									</div>
									@elseif($lt->user)
									<div class="team-name">{{ $lt->user->first_name }} {{ $lt->user->last_name }}</div>
									@else
									—
									@endif
								</td>
								<td class="text-center">
									@if($lt->status === 'active')
									<span class="alert-success p-1 pt-05 pb-05">{{ __('tournaments.setup_st_active') }}</span>
									@elseif($lt->status === 'reserve')
									<span class="alert-warning p-1 pt-05 pb-05">{{ __('tournaments.setup_st_reserve_n', ['n' => $lt->reserve_position]) }}</span>
									@elseif($lt->status === 'pending_confirmation')
									<span class="alert-info p-1 pt-05 pb-05">{{ __('tournaments.setup_st_pending') }}</span>
									@else
									<span class="league-badge">{{ $lt->status }}</span>
									@endif
								</td>
								<td class="text-center">
									@if($lt->status === 'active')
									<form method="POST" action="{{ route('divisions.teams.toReserve', $lt) }}" style="display:inline">
										@csrf
										<button type="submit" class="btn btn-secondary btn-alert btn-small" data-title="{{ __('tournaments.setup_to_reserve_title') }}" data-icon="warning" data-confirm-text="{{ __('tournaments.yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_to_reserve') }}</button>
									</form>
									@elseif($lt->status === 'reserve')
									<form method="POST" action="{{ route('divisions.teams.activate', $lt) }}" style="display:inline">
										@csrf
										<button type="submit" class="btn btn-secondary btn-alert btn-small" data-title="{{ __('tournaments.setup_activate_title') }}" data-icon="info" data-confirm-text="{{ __('tournaments.yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_activate') }}</button>
									</form>
									@endif
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
			@else
			<div class="alert alert-info">{{ __('tournaments.setup_no_teams_in_league') }}</div>
			@endif
			
			@php
			$_tourAllCompleted = $stages->isNotEmpty() && $stages->every(fn($s) => $s->status === 'completed');
			@endphp
			<div class="mt-2 d-flex text-center gap-1 flex-wrap">
				<a class="btn" href="{{ route('seasons.show', $seasonData['season']) }}">{{ __('tournaments.setup_btn_season_page') }}</a>
				<form method="POST" action="{{ route('tournament.syncLeague', $event) }}" style="margin:0">
					@csrf
					<button type="submit" class="btn">{{ __('tournaments.setup_btn_sync_teams') }}</button>
				</form>
				@if($_tourAllCompleted)
				<form method="POST" action="{{ route('tournament.applyPromotion', $event) }}" style="margin:0">
					@csrf
					<button type="submit" class="btn btn-alert" data-title="{{ __('tournaments.setup_promote_title') }}" data-icon="info" data-confirm-text="{{ __('tournaments.setup_promote_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">
						{{ __('tournaments.setup_btn_promote') }}
					</button>
				</form>
				@endif
			</div>
		</div>
		@endif
		
		
		{{-- ============================================================
		Команды
		============================================================ --}}
		<div class="ramka">
			<h2 class="-mt-05">{{ __('tournaments.setup_teams_h2', ['n' => $teams->count()]) }}</h2>
			@if($teams->isEmpty())
			<div class="alert alert-info">{{ __('tournaments.setup_teams_empty') }}</div>
			@else
			<div class="row">
				@foreach($teams as $team)
				<div class="col-md-6 col-xl-3">
					<div class="card">
						<a href="{{ route('tournamentTeams.show', [$event, $team]) }}" class="blink b-600 d-block mb-1">
							{{ $team->name }}
						</a>
						@php $members = $team->members->load('user'); @endphp
						@if($members->count() <= 2)
						@foreach($members as $m)
						<div>{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}</div>
						@endforeach
						@else
						@foreach($members->take(2) as $m)
						<div>{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}</div>
						@endforeach
						<div style="font-style:italic">{{ __('tournaments.setup_team_others') }}</div>
						@endif
						<div class="mt-1 d-flex between fvc">
							<div class="mt-05 cd b-600">{{ __('tournaments.setup_team_persons', ['n' => $members->count()]) }}</div>
							<form method="POST" action="{{ route('tournamentTeams.destroy', [$event, $team]) }}" class="mt-1">
								@csrf @method('DELETE')
								<button type="submit" class="icon-delete btn-alert btn btn-danger btn-svg" data-title="{{ __('tournaments.setup_team_delete_title', ['name' => $team->name]) }}" data-icon="warning" data-confirm-text="{{ __('tournaments.btn_delete') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">
								</button>
							</form>
						</div>
					</div>
				</div>
				@endforeach
			</div>
			@endif
			
			{{-- Создать команду организатором --}}
			<div class="mt-1">
				<details>
					<summary class="btn btn-secondary">{{ __('tournaments.setup_btn_create_team') }}</summary>
                    <form method="POST" action="{{ route('tournamentTeams.store', $event) }}">
                        @csrf
						@if($selectedOccurrence)
						<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence->id }}">
						@endif
						<div class="mt-2">
							<div class="row">
								<div class="col-md-6">
									<div class="card">
										<label>{{ __('tournaments.setup_team_label_name') }}</label>
										<input type="text" name="name" placeholder="{{ __('tournaments.setup_team_ph_name') }}">
									</div>
								</div>
								<div class="col-md-6">
									<div class="card">
										<label>{{ __('tournaments.setup_team_label_captain') }}</label>
										<div style="position:relative" id="org-captain-ac-wrap">
											<input type="text" id="org-captain-search" placeholder="{{ __('tournaments.setup_team_ph_captain') }}" autocomplete="off">
											<input type="hidden" name="captain_user_id" id="org-captain-id">
											<div id="org-captain-dd" class="form-select-dropdown trainer_dd"></div>
										</div>
									</div>
								</div>
								<div class="col-md-12 text-center">
									<button type="submit" class="btn">{{ __('tournaments.setup_btn_create') }}</button>
								</div>
							</div>
						</div>
					</form>
				</details>
			</div>		
			
			
		</div>
		
		
		
		{{-- ============================================================
		Создание стадии (сворачивается если стадии уже есть)
		============================================================ --}}
		@php $hasStages = $event->tournamentStages->isNotEmpty(); @endphp
		<div class="ramka">
			<h2 class="-mt-05">{{ __('tournaments.setup_add_stage_h2') }}</h2>
			<div class="btn btn-secondary" style="cursor:pointer" onclick="var b=this.nextElementSibling;b.style.display=b.style.display==='none'?'':'none';this.querySelector('.toggle-icon').textContent=b.style.display==='none'?'+':'-'">{{ __('tournaments.setup_btn_add_stage') }}			
			</div>
			<div style="{{ $hasStages ? 'display:none' : '' }}">
				<form class="mt-2" method="POST" action="{{ route('tournament.stages.store', $event) }}">
					@csrf
					@if($selectedOccurrence)
					<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence->id }}">
					@endif
					<div class="row">
						<div class="col-md-6">
							<div class="card">
								<label>{{ __('tournaments.setup_stage_type') }}</label>
								<select name="type" id="stage_type_select">
									<option value="round_robin">{{ __('tournaments.setup_stage_round_robin') }}</option>
									<option value="groups_playoff">{{ __('tournaments.setup_stage_groups_playoff') }}</option>
									<option value="single_elim">{{ __('tournaments.setup_stage_single_elim') }}</option>
									<option value="swiss">{{ __('tournaments.setup_stage_swiss') }}</option>
									<option value="double_elim">{{ __('tournaments.setup_stage_double_elim') }}</option>
									<option value="king_of_court">{{ __('tournaments.setup_stage_king_of_court') }}</option>
									<option value="thai">{{ __('tournaments.setup_stage_thai') }}</option>
								</select>
								<a href="{{ route('tournament_formats') }}" target="_blank" class="f-16 blink mt-1">{{ __('tournaments.setup_stage_formats_link') }}</a>
								
								<label class="mt-2">{{ __('tournaments.setup_stage_label_name') }}</label>
								<input name="name" value="{{ old('name', __('tournaments.setup_stage_default_name')) }}" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="card">
								<label>{{ __('tournaments.setup_stage_match_format') }}</label>
								<select name="match_format" id="match_format_select">
									<option value="bo3">Best of 3 (Bo3)</option>
									<option value="bo1">Best of 1 (Bo1)</option>
									@if(!$isBeach)
									<option value="bo5">Best of 5 (Bo5)</option>
									@endif
								</select>
								<div id="match_format_hint" class="f-16 mt-1"></div>
								<script>
									(function(){
										var hints = {
											bo1: @json(__('tournaments.setup_stage_bo1_hint')),
											bo3: @json(__('tournaments.setup_stage_bo3_hint')),
											bo5: @json(__('tournaments.setup_stage_bo5_hint'))
										};
										var sel = document.getElementById('match_format_select');
										var hint = document.getElementById('match_format_hint');
										function upd() { hint.textContent = hints[sel.value] || ''; }
										sel.addEventListener('change', upd);
										sel.addEventListener('change', function() {
											var wrap = document.getElementById('deciding_set_wrap');
											if (wrap) wrap.style.display = (sel.value === 'bo1') ? 'none' : '';
										});
										upd();
										var _dsw = document.getElementById('deciding_set_wrap'); if (_dsw && sel.value === 'bo1') _dsw.style.display = 'none';
									})();
								</script>
								
								<div class="row row2">
									<div class="col-md-6">
										<label class="mt-2">{{ __('tournaments.setup_stage_set_pts') }}</label>
										<select name="set_points">
											@if(!$isBeach)
											<option value="25" selected>{{ __('tournaments.setup_stage_set_pts_25') }}</option>
											@endif
											<option value="21" @if($isBeach) selected @endif>{{ __('tournaments.setup_stage_set_pts_21') }}</option>
											<option value="15">{{ __('tournaments.setup_stage_set_pts_15') }}</option>
										</select>
									</div>
									<div class="col-md-6" id="deciding_set_wrap">
										<label class="mt-2">{{ __('tournaments.setup_stage_deciding_set') }}</label>
										<select name="deciding_set_points">
											<option value="15" selected>15</option>
											@if(!$isBeach)
											<option value="25">25</option>
											@endif
										</select>
									</div>
								</div>								
							</div>
						</div>
						
					</div>
					<div class="mt-2" id="group_fields">
						<div class="row">
							<div class="col-lg-3 col-md-6">
								<div class="card"><label>{{ __('tournaments.setup_stage_groups_count') }}</label>
									<input name="groups_count" type="number" value="2" min="1" max="16">
								</div>
							</div>
							<div class="col-lg-3 col-md-6">
								<div class="card"><label>{{ __('tournaments.setup_stage_groups_advance') }}</label>
									<input name="advance_count" type="number" value="2" min="1" max="8">
								</div>
							</div>
							<div class="col-lg-3 col-md-6">
								<div class="card"><label>{{ __('tournaments.setup_stage_third_place') }}</label>
									<select name="third_place_match">
										<option value="0">{{ __('tournaments.no') }}</option>
										<option value="1">{{ __('tournaments.yes') }}</option>
									</select>
								</div>
							</div>
							<div class="col-lg-3 col-md-6">
								<div class="card">
									<label>{{ __('tournaments.setup_stage_courts_count') }}</label>
									<select name="courts_count" id="courts_count_select">
										<option value="0">—</option>
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="5">5</option>
										<option value="6">6</option>
										<option value="7">7</option>
										<option value="8">8</option>
										<option value="9">9</option>
										<option value="10">10</option>
										<option value="11">11</option>
										<option value="12">12</option>
										<option value="13">13</option>
										<option value="14">14</option>
										<option value="15">15</option>
										<option value="16">16</option>
										<option value="17">17</option>
										<option value="18">18</option>
										<option value="19">19</option>
										<option value="20">20</option>
									</select>
									<input type="hidden" name="courts" id="courts_hidden" value="">
								</div>
							</div>
						</div>
						
						{{-- Назначение кортов группам (динамическое) --}}
						<div class="mt-2" id="courts_group_assign" style="display:none">
							<div class="card">
								<label>{{ __('tournaments.setup_stage_courts_for_groups') }}</label>
								<hr class="mb-1">
								<div id="courts_group_boxes" class="row"></div>
							</div>	
						</div>
						{{-- Жеребьёвка --}}
						<div class="row mt-2">
							<div class="col-xl-3">
								<div class="card">
									<label>{{ __('tournaments.setup_stage_seed') }}</label>
									<select name="draw_mode" id="draw_mode_select">
										<option value="random">{{ __('tournaments.setup_stage_seed_random') }}</option>
										<option value="seeded">{{ __('tournaments.setup_stage_seed_seeded') }}</option>
										<option value="manual">{{ __('tournaments.setup_stage_seed_manual') }}</option>
									</select>
								</div>
							</div>
							
							<div class="col-xl-9">	
								{{-- Расписание (опционально) --}}
								<div id="schedule_fields">
									<div class="card">
										<label>{{ __('tournaments.setup_stage_schedule') }}</label>
										<hr class="mb-1">
										<div class="row">
											<div class="col-md-4">
												<label>{{ __('tournaments.setup_stage_start') }}</label>
												<input type="datetime-local" name="schedule_start" value="">
											</div>
											<div class="col-md-4">
												<label>{{ __('tournaments.setup_stage_match_min') }}</label>
												<input type="number" name="schedule_match_duration" value="30" min="15" max="180">
											</div>
											<div class="col-md-4">
												<label>{{ __('tournaments.setup_stage_break_min') }}</label>
												<input type="number" name="schedule_break_duration" value="5" min="0" max="60">
											</div>
										</div>
										<ul class="list f-16 mt-1">
											<li>{{ __('tournaments.setup_stage_schedule_hint') }}</li>
										</ul>										
										
									</div>
								</div>							
							</div>	
						</div>
						
						{{-- Ручное распределение --}}
						<div class="mt-2" id="manual_draw_block" style="display:none">
							<div class="card">
								<label>{{ __('tournaments.setup_stage_manual_distribution') }}</label>
								<p>{{ __('tournaments.setup_stage_manual_pick_group') }}</p>
								@if($teams->isNotEmpty())
								<div class="table-scrollable no-overflow">
									<div class="table-drag-indicator"></div>				
									<table class="table">
										<thead>
											<tr>
												<th>{{ __('tournaments.setup_col_team') }}</th>
												<th>{{ __('tournaments.setup_stage_col_group') }}</th>
											</tr>
										</thead>
										<tbody>
											@foreach($teams as $team)
											<tr>
												<td>
													<div class="b-600">{{ $team->name }}</div>
													@if($team->members->count())
													<div class="f-16">{{ $team->members->map(fn($m) => trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')))->implode(' / ') }}</div>
													@endif
												</td>
												<td class="text-center">
													<select name="manual_teams[{{ $team->id }}]" class="manual-group-select" >
														<option value="">—</option>
														<option value="A">{{ __('tournaments.setup_stage_group_letter', ['l' => 'A']) }}</option>
														<option value="B">{{ __('tournaments.setup_stage_group_letter', ['l' => 'B']) }}</option>
													</select>
												</td>
											</tr>
											@endforeach
										</tbody>
									</table>
								</div>
								@else
								<div class="alert alert-info">{{ __('tournaments.setup_stage_no_teams_distribute') }}</div>
								@endif
							</div>
						</div>
						
						
						<div class="text-center">
							<button type="submit" class="btn btn-primary mt-2">{{ __('tournaments.setup_stage_btn_create_seed') }}</button>
						</div>
						<script>
							(function(){
								var courtsSel = document.getElementById("courts_count_select");
								var groupsSel = document.querySelector('input[name="groups_count"]');
								var hidden = document.getElementById("courts_hidden");
								var assignBlock = document.getElementById("courts_group_assign");
								var boxesDiv = document.getElementById("courts_group_boxes");
								
								function rebuild() {
									var n = parseInt(courtsSel.value) || 0;
									var g = parseInt(groupsSel ? groupsSel.value : 0) || 0;
									
									var names = [];
									for (var i = 1; i <= n; i++) names.push(@json(__('tournaments.setup_court_n', ['n' => 'X'])).replace('X', i));
									hidden.value = names.join(", ");
									
									if (n === 0 || g === 0) {
										assignBlock.style.display = "none";
										boxesDiv.innerHTML = "";
										return;
									}
									
									assignBlock.style.display = "";
									var groupLabels = [];
									for (var gi = 0; gi < g; gi++) {
										groupLabels.push(String.fromCharCode(65 + gi)); // A, B, C...
									}
									
									var colSize = Math.floor(12 / g);
									if (colSize < 3) colSize = 3;
									var html = "";
									groupLabels.forEach(function(label) {
										html += '<div class="col-md-' + colSize + ' mb-2">';
										html += '<label>' + @json(__('tournaments.setup_group_label', ['label' => 'X'])).replace('X', label) + '</label>';
										html += '<div class="d-flex" style="flex-wrap:wrap;gap:1rem">';
										names.forEach(function(court) {
											html += '<label class="checkbox-item f-13" style="min-width: 12rem; margin:0">';
											html += '<input type="checkbox" name="group_courts[' + label + '][]" value="' + court + '">';
											html += '<div class="custom-checkbox"></div>';
											html += '<span>' + court + '</span>';
											html += '</label>';
										});
										html += '</div></div>';
									});
									boxesDiv.innerHTML = html;
								}
								
								courtsSel.addEventListener("change", rebuild);
								if (groupsSel) groupsSel.addEventListener("input", rebuild);
								rebuild();
							})();
						</script>
					</div>
				</div>
			</form>
		</div>
		
		{{-- ============================================================
		Стадии
		============================================================ --}}
		
		{{-- MVP турнира --}}
		@php
		$allCompleted = $stages->isNotEmpty() && $stages->every(fn($s) => $s->status === 'completed');
		// Если сезонный турнир с 2+ группами, но групп Hard/Lite ещё нет — турнир не завершён
		if ($allCompleted && $event->season_id) {
		$groupStage = $stages->firstWhere('type', 'round_robin');
		if ($groupStage && $groupStage->groups->count() >= 2) {
		$hasDivisions = $stages->contains(fn($s) => str_starts_with($s->name, 'Группа '));
		if (!$hasDivisions) {
		$allCompleted = false;
		}
		}
		}
		$participants = collect();
		if ($allCompleted) {
		$participants = \App\Models\PlayerTournamentStats::where('event_id', $event->id)
		->with('user')
		->orderByDesc('match_win_rate')
		->get();
		}
		@endphp
		@if($allCompleted && $participants->isNotEmpty())
		<div class="ramka">
			<h2 class="-mt-05">{{ __('tournaments.setup_mvp_h2') }}</h2>
			@if($event->tournament_mvp_user_id)
			@php $currentMvp = \App\Models\User::find($event->tournament_mvp_user_id); @endphp
			<div class="card" style="text-align:center;background:rgba(231,97,47,.06);border:1px solid rgba(231,97,47,.2)">
				<div class="f-13 b-600 mb-1">{{ __('tournaments.setup_mvp_current') }}</div>
				<div class="f-20 b-800">⭐
					<a href="{{ route('users.show', $currentMvp) }}" class="blink">
						{{ trim(($currentMvp->last_name ?? '') . ' ' . ($currentMvp->first_name ?? '')) ?: $currentMvp->name ?? '?' }}
					</a>
				</div>
			</div>
			@endif
			<form method="POST" action="{{ route('tournament.mvp', $event) }}">
				@csrf
				<div class="card p-3">
					<label class="f-13 b-600 mb-2 d-block">{{ __('tournaments.setup_mvp_pick') }}</label>
					<table style="width:100%;border-collapse:collapse;font-size:14px">
						<thead>
							<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
								<th class="p-1" style="width:30px"></th>
								<th class="p-1" style="text-align:left">{{ __('tournaments.setup_mvp_col_player') }}</th>
								<th class="p-1" style="text-align:center">WinRate</th>
								<th class="p-1" style="text-align:center">{{ __('tournaments.setup_mvp_col_matches') }}</th>
								<th class="p-1" style="text-align:center">{{ __('tournaments.setup_mvp_col_sets') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($participants as $ps)
							<tr style="border-bottom:1px solid rgba(128,128,128,.1);{{ $event->tournament_mvp_user_id == $ps->user_id ? 'background:rgba(231,97,47,.06)' : '' }}">
								<td class="p-1" style="text-align:center">
									<input type="radio" name="mvp_user_id" value="{{ $ps->user_id }}" {{ $event->tournament_mvp_user_id == $ps->user_id ? 'checked' : '' }}>
								</td>
								<td class="p-1">
									<a href="{{ route('users.show', $ps->user_id) }}" class="blink b-600">
										{{ trim(($ps->user->last_name ?? '') . ' ' . ($ps->user->first_name ?? '')) ?: $ps->user->name ?? '?' }}
									</a>
								</td>
								<td class="p-1 b-700" style="text-align:center;color:#E7612F">{{ $ps->match_win_rate }}%</td>
								<td class="p-1" style="text-align:center">{{ $ps->matches_won }}/{{ $ps->matches_played }}</td>
								<td class="p-1" style="text-align:center">{{ $ps->sets_won }}:{{ $ps->sets_lost }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
					<div class="text-center mt-2">
						<button type="submit" class="btn btn-primary">{{ __('tournaments.setup_mvp_btn_assign') }}</button>
					</div>
				</div>
			</form>
		</div>
		@endif
		
		{{-- Фото турнира --}}
		@if($allCompleted)
		<div class="ramka">
			<h2 class="-mt-05">{{ __('tournaments.setup_photos_h2') }}</h2>
			
			@php
			$tournamentPhotos = $event->getMedia('tournament_photos');
			$currentPhotoIds = $tournamentPhotos->pluck('id')->toArray();
			@endphp
			
			@if($tournamentPhotos->isNotEmpty())
			<div class="d-flex mb-2" style="flex-wrap:wrap;gap:2rem">
				@foreach($tournamentPhotos as $media)
				<div style="position:relative;width:20%;">
					<img src="{{ $media->getUrl('thumb') }}" style="width:100%; aspect-ratio: 16/9;object-fit:cover;border-radius:8px">
					<form method="POST" action="{{ route('tournament.photos.destroy', [$event, $media->id]) }}" style="position:absolute; bottom:1rem; right:1rem">
						@csrf @method('DELETE')
						<button type="submit" 
						class="icon-delete btn-alert btn btn-danger btn-svg"
						data-title="{{ __('tournaments.setup_photos_delete_title') }}"
						data-icon="warning"
						data-confirm-text="{{ __('tournaments.btn_delete') }}"
						data-cancel-text="{{ __('tournaments.btn_cancel') }}">
						</button>										
					</form>
				</div>
				@endforeach
			</div>
			@endif
			
			@if(($userEventPhotos ?? collect())->count() > 0)
			<div class="card">
				<label>{{ __('tournaments.setup_photos_pick') }}</label>
				
				<div class="event-photos-selector" id="tournament-photos-selector"
				data-selected='{{ json_encode($currentPhotoIds) }}'>
					<div class="swiper tournamentPhotosSwiper">
						<div class="swiper-wrapper">
							@foreach($userEventPhotos as $photo)
							<div class="swiper-slide">
								<div class="hover-image mb-1">
									<img src="{{ $photo->getUrl('event_thumb') }}" alt="photo" loading="lazy"/>
								</div>
								<div class="mt-1 d-flex between fvc">
									<label class="checkbox-item mb-0">
										<input type="checkbox" class="t-photo-select" value="{{ $photo->id }}">
										<div class="custom-checkbox"></div>
										<span>{{ __('tournaments.setup_photos_select') }}</span>
									</label>
									<div class="photo-order-badge f-16 b-600 cd"></div>
								</div>
							</div>
							@endforeach
						</div>
						<div class="swiper-pagination"></div>
					</div>
					
					<ul class="list f-16 mt-1">
						<li>{{ __('tournaments.setup_photos_hint_1') }}</li>
						<li>{{ __('tournaments.setup_photos_hint_2', ['link' => '<a target="_blank" href="' . route('user.photos') . '">' . __('tournaments.setup_photos_hint_2_link') . '</a>']) }}</li>
					</ul>
				</div>
			</div>	
			<div class="text-center">
				<form method="POST" action="{{ route('tournament.photos.store', $event) }}" id="tournament-photos-form" class="mt-2">
					@csrf
					<input type="hidden" name="photo_ids" id="tournament_photos_input" value="">
					<button type="submit" class="btn btn-primary" id="tournament-photos-submit" style="display:none">{{ __('tournaments.setup_photos_save') }}</button>
				</form>
			</div>
			@else
			<div class="alert alert-info">
				{{ __('tournaments.setup_photos_empty', ['link' => '<a href="' . route('user.photos') . '" target="_blank">' . __('tournaments.setup_photos_empty_link') . '</a>']) }}
			</div>
			@endif
		</div>
		@endif
		
		@foreach($stages as $stage)
		@php
		$borderColor = $stage->isCompleted() ? '#10b981' : ($stage->isInProgress() ? '#2967BA' : '#555');
		$_isDivStage = str_starts_with($stage->name, 'Группа ');
		$stageHasDivDistribution = !$_isDivStage && $stages->contains(fn($s) => str_starts_with($s->name, 'Группа ') && $s->occurrence_id == $stage->occurrence_id);
		@endphp
		<div class="ramka" id="stage_{{ $stage->id }}">
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
					<p>
						@php
						$stageTypeLabels = [
						'round_robin' => __('tournaments.setup_stage_lbl_round_robin'),
						'groups_playoff' => __('tournaments.setup_stage_lbl_groups_playoff'),
						'single_elim' => __('tournaments.setup_stage_lbl_single_elim'),
						'swiss' => __('tournaments.setup_stage_lbl_swiss'),
						'double_elim' => __('tournaments.setup_stage_lbl_double_elim'),
						'king_of_court' => __('tournaments.setup_stage_lbl_king_of_court'),
						'thai' => __('tournaments.setup_stage_lbl_thai'),
						];
						$matchFormatLabels = ['bo1' => 'Best of 1', 'bo3' => 'Best of 3', 'bo5' => 'Best of 5'];
						@endphp
						{{ $stageTypeLabels[$stage->type] ?? $stage->type }} · {{ $matchFormatLabels[$stage->matchFormat()] ?? strtoupper($stage->matchFormat()) }} · {{ __('tournaments.score_to_pts') }} {{ $stage->setPoints() }} {{ __('tournaments.pub_pts_label') }}
					</p>
				</div>
				<div class="d-flex" style="gap:6px">
					@if($stage->isInProgress() || $stage->isCompleted())
					<form method="POST" action="{{ route('tournament.stages.revert', $stage) }}">
						@csrf
						<button class="btn btn-secondary f-12 btn-alert" data-title="{{ __('tournaments.setup_rollback_title') }}" data-icon="warning" data-confirm-text="{{ __('tournaments.setup_rollback_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_rollback') }}</button>
					</form>
					@endif
					<form method="POST" action="{{ route('tournament.stages.destroy', $stage) }}">
						@csrf @method('DELETE')
						<button class="btn btn-danger f-12 btn-alert" data-title="{{ __('tournaments.setup_delete_stage_title') }}" data-icon="warning" data-confirm-text="{{ __('tournaments.btn_delete') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_delete_stage') }}</button>
					</form>
				</div>
			</div>
			
			{{-- Группы --}}
			@if($stage->groups->isNotEmpty())
			<div class="tabs-content mt-2">
				<div class="tabs">
					@foreach($stage->groups as $index => $group)
					<div class="tab" data-tab="group{{ $group->id }}">{{ $group->name }}</div>
					@endforeach
					<div class="tab-highlight"></div>
				</div>
				
				<div class="tab-panes">
					@foreach($stage->groups as $index => $group)
					<div class="tab-pane" id="group{{ $group->id }}">
						{{-- Содержимое группы --}}
						@if($group->standings->isNotEmpty())
						<div class="table-scrollable">
							<div class="table-drag-indicator"></div>
							<table class="table">
								<thead>
									<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
										<th class="p-1" style="text-align:center;width:30px">{{ __('tournaments.setup_standings_col_pos') }}</th>
										<th class="p-1" style="text-align:left">{{ __('tournaments.standings_col_team') }}</th>
										<th class="p-1" style="text-align:center">{{ __('tournaments.standings_col_played') }}</th>
										<th class="p-1" style="text-align:center">{{ __('tournaments.standings_col_w') }}</th>
										<th class="p-1" style="text-align:center">{{ __('tournaments.standings_col_l') }}</th>
										<th class="p-1" style="text-align:center">{{ __('tournaments.setup_standings_col_pts') }}</th>
										<th class="p-1" style="text-align:center" title="{{ __('tournaments.setup_standings_col_diff_title') }}">{{ __('tournaments.tv_diff_col') }}</th>
									</tr>
								</thead>
								<tbody>
									@php
									$groupOutsiders = $outsidersByGroup[$group->id] ?? [];
									$groupClean     = $cleanStatsByGroup[$group->id] ?? [];
									$fmtDiff = fn($v) => ($v > 0 ? '+' : '') . $v;
									@endphp
									@foreach($group->standings->sortBy('rank') as $standing)
									@php
									$isOutsider = in_array((int) $standing->team_id, $groupOutsiders, true);
									$fullDiff   = $standing->points_scored - $standing->points_conceded;
									$cleanPs    = $groupClean[$standing->team_id]['points_scored']   ?? $standing->points_scored;
									$cleanPc    = $groupClean[$standing->team_id]['points_conceded'] ?? $standing->points_conceded;
									$cleanDiff  = $cleanPs - $cleanPc;
									@endphp
									<tr>
										<td style="text-align:center">{{ $standing->rank }}</td>
										<td>
											<div class="b-600 cd">{{ $standing->team->name ?? '—' }}@if($isOutsider) <span class="f-16">{{ __('tournaments.setup_outsider_label') }}</span>@endif</div>
											@if($standing->team && $standing->team->members->count())
											<div class="f-16">{{ $standing->team->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') }}</div>
											@endif
										</td>
										<td style="text-align:center"><span class="b-600 alert-info pt-05 pb-05 p-1">{{ $standing->played }}</span></td>
										<td style="text-align:center;"><span class="b-600 alert-success pt-05 pb-05 p-1">{{ $standing->wins }}</span></td>
										<td style="text-align:center;"><span class="b-600 alert-danger pt-05 pb-05 p-1">{{ $standing->losses }}</span></td>
										<td class="b-600" style="text-align:center">{{ $standing->rating_points }}</td>
										<td style="text-align:center" title="{{ __('tournaments.setup_standings_col_diff_short_title') }}">
											@if($cleanDiff === $fullDiff)
											{{ $fmtDiff($fullDiff) }}
											@else
											<span class="b-600">{{ $fmtDiff($cleanDiff) }}</span><span style="color:#6b7280">&nbsp;/&nbsp;({{ $fmtDiff($fullDiff) }})</span>
											@endif
										</td>
									</tr>
									@endforeach
								</tbody>
							</table>
						</div>
						@elseif($group->teams->isNotEmpty())
						<div class="d-flex" style="flex-wrap:wrap;gap:6px">
							@foreach($group->teams as $team)
							<span class="p-1 px-2 f-12 b-600" style="background:rgba(41,103,186,.15);border-radius:6px">{{ $team->name }}</span>
							@endforeach
						</div>
						@endif
						
						{{-- Tiebreaker sets (множественные связки команд) --}}
						@php
						$groupSets = $tiebreakerSets[$group->id] ?? collect();
						$pendingSets  = $groupSets->where('status', 'pending');
						$resolvedSets = $groupSets->where('status', 'resolved');
						$teamNames = $group->standings->pluck('team.name', 'team_id');
						@endphp
						
						@if($pendingSets->isNotEmpty())
						<div class="mb-2 alert alert-info">
							<div class="b-600 mb-1">{{ __('tournaments.setup_tb_required') }}</div>
							@foreach($pendingSets as $tset)
							@php
							$tids = array_map('intval', $tset->team_ids ?? []);
							$labels = array_map(fn($tid) => $teamNames[$tid] ?? ('#' . $tid), $tids);
							@endphp
							
							<div class="b-600 mb-1">{{ implode(' = ', $labels) }}</div>
							
							@if($tset->method === 'match')
							<p>{{ __('tournaments.setup_tb_match_created') }}</p>
							@php $ms = $tset->match_settings ?? []; @endphp
							<p>{{ __('tournaments.setup_tb_rules', ['pts' => $ms['points_to_win'] ?? '?', 'margin' => !empty($ms['two_point_margin']) ? __('tournaments.setup_tb_two_point_margin') : '']) }}</p>
							@else
							<p>{{ __('tournaments.setup_tb_choose_method') }}</p>
							<div class="d-flex" style="gap:8px;flex-wrap:wrap;align-items:flex-start">
								{{-- Вариант 1: учесть матчи с аутсайдером (full diff) --}}
								<form method="POST" action="{{ route('tournament.tiebreaker.set.fullDiff', $tset) }}" style="display:inline">
									@csrf
									<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
									<button type="submit" class="btn btn-secondary btn-alert"
									data-title="{{ __('tournaments.setup_tb_full_diff_title') }}" data-icon="info"
									data-confirm-text="{{ __('tournaments.setup_tb_btn_apply') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">
										{{ __('tournaments.setup_tb_btn_full_diff') }}
									</button>
								</form>
								
								{{-- Вариант 2: сыграть мини-матчи --}}
								<button type="button" class="btn btn-secondary"
								onclick="document.getElementById('tbset-match-{{ $tset->id }}').style.display='block';this.style.display='none'">
									{{ __('tournaments.setup_tb_btn_match') }}
								</button>
								
								{{-- Вариант 3: жребий --}}
								<button type="button" class="btn btn-secondary"
								onclick="document.getElementById('tbset-lot-{{ $tset->id }}').style.display='block';this.style.display='none'">
									{{ __('tournaments.setup_tb_btn_lottery') }}
								</button>
							</div>
							
							{{-- Форма мини-матчей --}}
							<div id="tbset-match-{{ $tset->id }}" style="display:none;margin-top:8px">
								<form method="POST" action="{{ route('tournament.tiebreaker.set.matches', $tset) }}" class="d-flex" style="gap:8px;align-items:center;flex-wrap:wrap">
									@csrf
									<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
									<label class="f-12 fvc" style="gap:4px">{{ __('tournaments.setup_tb_to') }}
										<input type="number" name="points_to_win" value="15" min="1" max="30" class="form-control f-13" style="width:70px;padding:2px 6px" required>
									{{ __('tournaments.setup_tb_pts_short') }}</label>
									<label class="fvc" style="gap:4px">
										<input type="checkbox" name="two_point_margin" value="1"> {{ __('tournaments.setup_tb_two_pt') }}
									</label>
									<button type="submit" class="btn btn-primary">{{ __('tournaments.setup_tb_btn_create_matches') }}</button>
								</form>
								<div class="f-11 mt-1" style="opacity:.6">{{ __('tournaments.setup_tb_match_count', ['n' => count($tids) * (count($tids) - 1) / 2]) }}</div>
							</div>
							
							{{-- Форма жребия --}}
							<div id="tbset-lot-{{ $tset->id }}" style="display:none;margin-top:8px">
								<form method="POST" action="{{ route('tournament.tiebreaker.set.lottery', $tset) }}" class="d-flex" style="gap:6px;align-items:center;flex-wrap:wrap">
									@csrf
									<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
									<span class="f-12" style="opacity:.7">{{ __('tournaments.setup_tb_order') }}</span>
									@foreach($tids as $i => $tid)
									<select name="order[]" class="form-control f-12" style="width:auto;min-width:120px;padding:2px 6px" required>
										<option value="">{{ __('tournaments.setup_tb_place_n', ['n' => $i + 1]) }}</option>
										@foreach($tids as $tid2)
										<option value="{{ $tid2 }}">{{ $teamNames[$tid2] ?? ('#' . $tid2) }}</option>
										@endforeach
									</select>
									@endforeach
									<button type="submit" class="btn btn-primary f-12" style="padding:4px 12px">{{ __('tournaments.setup_tb_btn_confirm') }}</button>
								</form>
							</div>
							@endif
							
							@endforeach
						</div>
						@endif
						
						@if($resolvedSets->isNotEmpty())
						<div class="mb-2 alert alert-success">
							@foreach($resolvedSets as $rset)
							@php
							$order  = $rset->resolved_order ?: [];
							$labels = array_map(fn($tid) => $teamNames[(int) $tid] ?? ('#' . $tid), $order);
							$methodLabel = ['full_diff' => __('tournaments.setup_tb_method_full_diff'), 'match' => __('tournaments.setup_tb_method_match'), 'lottery' => __('tournaments.setup_tb_method_lottery')][$rset->method] ?? $rset->method;
							@endphp
							<p>{{ __('tournaments.setup_tb_resolved', ['method' => $methodLabel, 'order' => implode(' → ', $labels)]) }}</p>
							@endforeach
						</div>
						@endif
					</div>
					@endforeach
				</div>
			</div>
			@endif
			
			
			
			{{-- Матчи --}}
			@if($stage->matches->isNotEmpty())
			@php
			$matchesByGroup = $stage->matches->sortBy(["round", "match_number"])->groupBy('group_id');
			$hasGroups = $stage->groups->count() > 1;
			@endphp
			
			<div class="tabs-content">
				<div class="tabs">
					@foreach($matchesByGroup as $groupId => $groupMatches)
					@php $groupName = $stage->groups->firstWhere('id', $groupId)?->name ?? ''; @endphp
					<div class="tab" data-tab="matches-group{{ $groupId }}">{{ $groupName ? __('tournaments.setup_tab_matches_group', ['name' => $groupName]) : __('tournaments.setup_tab_matches') }}</div>
					@endforeach
					<div class="tab-highlight"></div>
				</div>
				
				<div class="tab-panes">
					@foreach($matchesByGroup as $groupId => $groupMatches)
					@php
					$groupName       = $stage->groups->firstWhere('id', $groupId)?->name ?? '';
					$groupForCross   = $stage->groups->firstWhere('id', $groupId);
					$crossClean      = $cleanStatsByGroup[$groupId] ?? [];
					$crossOutsiders  = $outsidersByGroup[$groupId] ?? [];
					$hasCrosstable   = $groupForCross && $groupForCross->standings->isNotEmpty();
					@endphp
					<div class="tab-pane" id="matches-group{{ $groupId }}">

						@if($hasCrosstable)
						<div class="d-flex fvc mb-2" style="gap:6px">
							<button class="btn btn-small btn-secondary ct-view-btn ct-view-btn--active" data-group="{{ $groupId }}" data-view="list" style="font-size:12px">📋 {{ __('tournaments.view_list') }}</button>
							<button class="btn btn-small btn-secondary ct-view-btn" data-group="{{ $groupId }}" data-view="crosstable" style="font-size:12px">📊 {{ __('tournaments.view_crosstable') }}</button>
						</div>
						@endif

						<div class="ct-view-list" data-group="{{ $groupId }}">
							<div class="table-scrollable">
								<div class="table-drag-indicator"></div>
								<table class="table">
									<thead>
										<tr style="border-bottom:2px solid rgba(128,128,128,.2)">
											<th class="p-1" style="text-align:left">#</th>
											<th class="p-1" style="text-align:left">{{ __('tournaments.setup_matches_col_round') }}</th>
											<th class="p-1" style="text-align:left">{{ __('tournaments.setup_matches_col_home') }}</th>
											<th class="p-1" style="text-align:left">{{ __('tournaments.setup_matches_col_away') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_mvp_col_sets') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_matches_col_score') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_matches_col_time') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_matches_col_court') }}</th>
											<th class="p-1" style="text-align:center">{{ __('tournaments.setup_matches_col_status') }}</th>
											<th class="p-1"></th>
										</tr>
									</thead>
									<tbody>
										@foreach($groupMatches as $match)
										<tr>
											<td>{{ $match->match_number }}</td>
											<td>R{{ $match->round }}</td>
											<td>
												<div class="{{ $match->winner_team_id === $match->team_home_id ? 'cd b-600' : '' }}">{{ $match->teamHome->name ?? 'TBD' }}</div>
												@if($match->teamHome && $match->teamHome->members->count())
												<div class="f-13">{{ $match->teamHome->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') }}</div>
												@endif
											</td>
											<td>
												<div class="{{ $match->winner_team_id === $match->team_away_id ? 'cd b-600' : '' }}">{{ $match->teamAway->name ?? 'TBD' }}</div>
												@if($match->teamAway && $match->teamAway->members->count())
												<div class="f-13">{{ $match->teamAway->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') }}</div>
												@endif
											</td>
											<td style="text-align:center">{{ $match->setsScore() ?? '—' }}</td>
											<td style="text-align:center">{{ $match->detailedScore() ?: '—' }}</td>
											<td style="text-align:center">{{ $match->scheduled_at ? $match->scheduled_at->setTimezone($event->timezone ?? 'Europe/Moscow')->format('H:i') : '—' }}</td>
											<td style="text-align:center">{{ $match->court ?? '—' }}</td>
											<td style="text-align:center">
												<div class="text-center d-flex gap-1">
													@if($match->isCompleted())
													<span class="b-600 alert-success pt-05 pb-05 p-1">✓</span>
													@if(!$stageHasDivDistribution)
													<a href="{{ route('tournament.matches.score.form', $match) }}?edit=1" class="btn icon-edit btn-svg btn-secondary" title="{{ __('tournaments.setup_match_fix_title') }}"></a>
													@endif
													@elseif($match->status === 'live')
													<span class="b-600 alert-danger pt-05 pb-05 p-1">LIVE</span>
													@else
													<span class="b-600 alert-warning pt-05 pb-05 p-1">{{ __('tournaments.setup_match_pending') }}</span>
													@endif
												</div>	
											</td>
											<td class="p-1">
												@if($match->isScheduled() && $match->hasTeams())
												<a href="{{ route('tournament.matches.score.form', $match) }}" class="btn btn-primary btn-small">
													{{ __('tournaments.setup_match_btn_score') }}
												</a>
												@endif
												@if($match->isCompleted())
												<a href="{{ route('tournament.matches.player_stats.form', $match) }}" class="btn btn-secondary btn-small" title="{{ __('tournaments.setup_match_player_stats_title') }}">
													📊
												</a>
												@endif
											</td>
										</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>

						@if($hasCrosstable)
						<div class="ct-view-crosstable" data-group="{{ $groupId }}" style="display:none">
							@include('tournaments._partials.group_crosstable', [
								'group'          => $groupForCross,
								'groupMatches'   => $groupMatches,
								'groupClean'     => $crossClean,
								'groupOutsiders' => $crossOutsiders,
							])
						</div>
						@endif

					</div>
					@endforeach
				</div>
			</div>
			
			
			@php
			$hasUnplayed = $stage->matches->where('status', 'scheduled')
			->filter(fn($m) => $m->team_home_id && $m->team_away_id)->isNotEmpty();
			@endphp
			@if($hasUnplayed)
			<div class="mt-2 mb-3" style="text-align:center">
				<a href="{{ route('tournament.start_scoring', $event) }}" class="btn btn-primary p-3 f-16" style="display:inline-block">
					{{ __('tournaments.setup_btn_start_results') }}
				</a>
			</div>
			@endif
			
			
			{{-- Следующий тур (Swiss/King) --}}
			@if($stage->isInProgress() && in_array($stage->type, ['swiss', 'king_of_court']))
			<div class="p-3 mt-2" style="background:rgba(231,97,47,.08);border-radius:10px">
				<form method="POST" action="{{ route('tournament.stages.nextRound', $stage) }}" class="d-flex fvc" style="gap:10px">
					@csrf
					<div class="b-600">
						{{ $stage->type === 'swiss' ? __('tournaments.setup_btn_swiss_next') : __('tournaments.setup_btn_koc_next') }}
					</div>
					<button type="submit" class="btn btn-primary">{{ __('tournaments.setup_btn_next_arrow') }}</button>
				</form>
			</div>
			@endif
			
		</div>
		
		@endif
		{{-- Продвижение / Группы --}}
		@if($stage->isCompleted() && in_array($stage->type, ['round_robin', 'groups_playoff']))
		
		{{-- Сезонный турнир → группы Hard/Lite --}}
		@if($event->season_id && $stage->groups->count() >= 2)
		@php $hasDivStages = $stages->filter(fn($s) => str_starts_with($s->name, 'Группа '))->isNotEmpty(); @endphp
		<div class="ramka">
			<div class="d-flex between fvc" style="cursor:pointer" onclick="var b=this.nextElementSibling;b.style.display=b.style.display==='none'?'':'none';this.querySelector('.toggle-icon').textContent=b.style.display==='none'?'+':'-'">
				<h2 class="-mt-05">{{ __('tournaments.setup_groups_h2') }}</h2>
				<span class="toggle-icon b-600 f-20">{{ $hasDivStages ? '+' : '-' }}</span>
			</div>
			<div style="{{ $hasDivStages ? 'display:none' : '' }}">
				@php
				$groupsCount = $stage->groups->count();
				$divisionNames = match($groupsCount) {
				2 => ['Hard', 'Lite'],
				3 => ['Hard', 'Medium', 'Lite'],
				default => array_merge(['Hard'], array_map(fn($i) => 'Medium-' . $i, range(1, max(1, $groupsCount - 2))), ['Lite']),
				};
				$advanceCount = (int) $stage->cfg('advance_count', 2);
				$availCourts = $stage->cfg('courts', []);
				@endphp
				
				<p>
					{{ __('tournaments.setup_groups_redistribute', ['n' => count($divisionNames), 'plural' => count($divisionNames) > 2 ? '' : '']) }}
					<strong>{{ implode(', ', $divisionNames) }}</strong>
				</p>
				
				<form method="POST" action="{{ route('tournament.stages.formDivisions', $stage) }}">
					@csrf
					
					{{-- Ряд 1: Кол-во + форматы --}}
					<div class="row">
						<div class="col-md-3 mb-2">
							<div class="card">
								<label>{{ __('tournaments.setup_groups_advance_to_div', ['name' => 'Hard']) }}</label>
								<input name="advance_per_group" type="number" value="{{ $advanceCount }}" min="1" max="8" style="width:70px">
								<p>{{ __('tournaments.setup_groups_per_group') }}</p>
							</div>	
						</div>
						@foreach($divisionNames as $dn)
						<div class="col-md-3 mb-2">
							<div class="card">
								<label>{{ __('tournaments.setup_groups_format_for', ['name' => $dn]) }}</label>
								<select name="div_format_{{ strtolower($dn) }}" class="f-13" style="width:100%">
									<option value="">{{ __('tournaments.setup_groups_format_default') }}</option>
									<option value="bo1">Bo1</option>
									<option value="bo3">Bo3</option>
								</select>
							</div>	
						</div>
						@endforeach
					</div>
					
					{{-- Ряд 2: Площадки --}}
					@if(count($availCourts) > 0)
					<div class="card mb-2">
						<label>{{ __('tournaments.setup_stage_courts_for_groups') }}</label>
						<hr class="mb-1">
						<div class="row">
							@foreach($divisionNames as $dn)
							<div class="col-md-{{ (int)(12 / count($divisionNames)) }} mb-2">
								<label>{{ $dn }}:</label>
								<div class="d-flex" style="flex-wrap:wrap;gap:6px">
									@foreach($availCourts as $court)
									<label class="checkbox-item f-13 pr-2" style="margin:0">
										<input type="checkbox" name="div_courts_{{ strtolower($dn) }}[]" value="{{ $court }}">
										<div class="custom-checkbox"></div>
										<span>{{ $court }}</span>
									</label>
									@endforeach
								</div>
							</div>
							@endforeach
						</div>
					</div>
					@endif
					
					{{-- Расписание --}}
					<div class="card mb-2">
						<label>{{ __('tournaments.setup_stage_schedule') }}</label>
						<p>{{ __('tournaments.setup_groups_schedule_hint') }}</p>
						<hr class="mb-1">
						<div class="d-flex" style="gap:12px;flex-wrap:wrap;align-items:flex-end">
							<div>
								<label>{{ __('tournaments.setup_stage_start') }}</label>
								<input type="datetime-local" name="schedule_start" value="{{ \Carbon\Carbon::now($event->timezone ?? 'Europe/Moscow')->format('Y-m-d\TH:i') }}">
							</div>
							<div>
								<label>{{ __('tournaments.setup_stage_match_min') }}</label>
								<input type="number" name="schedule_match_duration" value="30" min="15" max="180">
							</div>
							<div>
								<label>{{ __('tournaments.setup_stage_break_min') }}</label>
								<input type="number" name="schedule_break_duration" value="5" min="0" max="60">
							</div>
						</div>
					</div>
					
					<button type="submit" class="btn btn-primary btn-alert" data-title="{{ __('tournaments.setup_groups_create_title') }}" data-icon="question" data-confirm-text="{{ __('tournaments.setup_groups_create_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_groups_btn_create') }}</button>
				</form>
			</div>
		</div>
		@else
		{{-- Обычный → плей-офф --}}
		@php $nextStages = $stages->where('type', 'single_elim')->where('status', 'pending'); @endphp
		@if($nextStages->isNotEmpty())
		<div class="p-3 mt-2" style="background:rgba(41,103,186,.08);border-radius:10px">
			<div class="b-700 mb-2">{{ __('tournaments.setup_promote_to_playoff') }}</div>
			<form method="POST" action="{{ route('tournament.stages.advance', $stage) }}" class="d-flex fvc" style="gap:10px;flex-wrap:wrap">
				@csrf
				<div>
					<label class="f-13 b-600 mb-1 d-block">{{ __('tournaments.setup_promote_stage') }}</label>
					<select name="playoff_stage_id">
						@foreach($nextStages as $ns)
						<option value="{{ $ns->id }}">{{ $ns->name }}</option>
						@endforeach
					</select>
				</div>
				<div>
					<label class="f-13 b-600 mb-1 d-block">{{ __('tournaments.setup_promote_advance') }}</label>
					<input name="advance_per_group" type="number" value="{{ $stage->cfg('advance_count', 2) }}" min="1" max="8" style="width:120px">
				</div>
				<button type="submit" class="btn btn-primary">{{ __('tournaments.setup_promote_btn') }}</button>
			</form>
		</div>
		@endif
		@endif
		@endif
		
		@endforeach
		
		{{-- Промоушен после групп --}}
		<div id="promotion_block"></div>
		@if($event->season_id && $stages->isNotEmpty())
		@php
		$divStages = $stages->filter(fn($s) => str_starts_with($s->name, 'Группа '));
		$allDivsCompleted = $divStages->isNotEmpty() && $divStages->every(fn($s) => $s->status === 'completed');
		$hasMedium = $divStages->contains(fn($s) => str_contains($s->name, 'Medium'));
		@endphp
		@if($allDivsCompleted)
		<div class="ramka">
			<h3 style="margin:0 0 8px">{{ __('tournaments.setup_groups_completed_h3') }}</h3>
			<p class="f-14" style="color:#6b7280;margin-bottom:12px">
				{{ __('tournaments.setup_groups_completed_text', ['medium' => $hasMedium ? __('tournaments.setup_groups_with_medium') : '']) }}
			</p>
			<form method="POST" action="{{ route('tournament.applyPromotion', $event) }}">
				@csrf
				<button type="submit" class="btn btn-primary btn-alert" data-title="{{ __('tournaments.setup_groups_apply_promotion_title') }}" data-icon="question" data-confirm-text="{{ __('tournaments.setup_groups_apply_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_groups_apply_promotion') }}</button>
			</form>
		</div>
		@endif
		
		{{-- Кнопка результатов тура --}}
		@if($divStages->isNotEmpty())
		<div class="ramka" style="text-align:center">
			<a href="{{ route('tournament.public.show', $event) }}{{ $selectedOccurrence ? '?occurrence_id=' . $selectedOccurrence->id : '' }}" class="btn btn-primary p-3 f-16" style="display:inline-block">
				{{ __('tournaments.setup_btn_round_results') }}
			</a>
		</div>
		@endif
		@endif
		
		
		@if($stages->isEmpty())
		<div class="ramka" style="text-align:center">
			<p class="f-18 b-600">{{ __('tournaments.setup_empty_h2') }}</p>
			<p>{{ __('tournaments.setup_empty_text') }}</p>
		</div>
		@endif
		
		
	</div>
	
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var typeSelect = document.getElementById('stage_type_select');
			var groupFields = document.getElementById('group_fields');
			if (typeSelect && groupFields) {
				function toggle() {
					var t = typeSelect.value;
					groupFields.style.display = (t === 'round_robin' || t === 'groups_playoff' || t === 'thai') ? '' : 'none';
				}
				typeSelect.addEventListener('change', toggle);
				toggle();
			}
		});
	</script>
	
	
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.draw-mode-select').forEach(function(sel) {
				sel.addEventListener('change', function() {
					var stageId = this.dataset.stage;
					var block = document.querySelector('.manual-draw-block[data-stage="' + stageId + '"]');
					if (block) {
						block.style.display = this.value === 'manual' ? '' : 'none';
					}
				});
			});
		});
	</script>
	
	
	<script>
		(function(){
			var inp = document.getElementById('org-captain-search');
			var hidden = document.getElementById('org-captain-id');
			var dd = document.getElementById('org-captain-dd');
			var wrap = document.getElementById('org-captain-ac-wrap');
			if (!inp || !dd || !hidden) return;
			var timer = null;
			
			function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
			function showDd() { dd.classList.add('form-select-dropdown--active'); }
			function hideDd() { dd.classList.remove('form-select-dropdown--active'); }
			
			inp.addEventListener('input', function() {
				clearTimeout(timer);
				var q = inp.value.trim();
				if (q.length < 2) { hideDd(); dd.innerHTML = ''; return; }
				dd.innerHTML = '<div class="city-message">' + @json(__('tournaments.setup_search_loading')) + '</div>';
				showDd();
				timer = setTimeout(function() {
					fetch('/api/users/search?q=' + encodeURIComponent(q), {
						headers: { 'Accept': 'application/json' },
						credentials: 'same-origin'
					})
					.then(function(r) { return r.json(); })
					.then(function(data) {
						dd.innerHTML = '';
						var items = data.items || data || [];
						if (!items.length) {
							dd.innerHTML = '<div class="city-message">' + @json(__('tournaments.setup_search_no_results')) + '</div>';
							showDd();
							return;
						}
						items.slice(0, 8).forEach(function(u) {
							var label = u.label || u.name || '#' + u.id;
							var div = document.createElement('div');
							div.className = 'trainer-item form-select-option';
							var botBadge = u.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">🤖 бот</span>' : '';
							div.innerHTML = '<div class="text-sm">' + esc(label) + botBadge + '</div>';
							div.addEventListener('click', function() {
								inp.value = label;
								hidden.value = String(u.id);
								hideDd();
							});
							dd.appendChild(div);
						});
						showDd();
					})
					.catch(function() {
						dd.innerHTML = '<div class="city-message">' + @json(__('tournaments.setup_search_load_err')) + '</div>';
						showDd();
					});
				}, 250);
			});
			
			inp.addEventListener('keydown', function(e) { if (e.key === 'Escape') hideDd(); });
			
			document.addEventListener('click', function(e) {
				if (wrap && !wrap.contains(e.target)) hideDd();
			});
		})();
	</script>
	
	
	<script src="/assets/fas.js"></script>  
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Tournament Photos Swiper
			if (document.querySelector('.tournamentPhotosSwiper')) {
				new Swiper('.tournamentPhotosSwiper', {
					slidesPerView: 3,
					spaceBetween: 20,
					pagination: { el: '.tournamentPhotosSwiper .swiper-pagination', clickable: true },
					breakpoints: {
						320: { slidesPerView: 2 },
						640: { slidesPerView: 3 },
						1024: { slidesPerView: 4 }
					}
				});
				
				var container = document.getElementById('tournament-photos-selector');
				if (container) {
					var savedPhotos = JSON.parse(container.dataset.selected || '[]');
					var selectedPhotos = savedPhotos.slice();
					
					function updateTournamentUI() {
						document.querySelectorAll('.t-photo-select').forEach(function(cb) {
							var id = parseInt(cb.value);
							var isSelected = selectedPhotos.indexOf(id) !== -1;
							cb.checked = isSelected;
							var badge = cb.closest('.swiper-slide').querySelector('.photo-order-badge');
							if (isSelected) {
								var order = selectedPhotos.indexOf(id) + 1;
								badge.textContent = order === 1 ? @json(__('tournaments.setup_photo_main')) : (@json(__('tournaments.setup_photo_pos_n', ['n' => ''])) + order);
								} else {
								badge.textContent = '';
							}
						});
						document.getElementById('tournament_photos_input').value = JSON.stringify(selectedPhotos);
						var btn = document.getElementById('tournament-photos-submit');
						btn.style.display = selectedPhotos.length > 0 ? '' : 'none';
					}
					
					document.querySelectorAll('.t-photo-select').forEach(function(cb) {
						cb.addEventListener('change', function() {
							var id = parseInt(this.value);
							if (this.checked) {
								selectedPhotos.push(id);
								} else {
								var idx = selectedPhotos.indexOf(id);
								if (idx !== -1) selectedPhotos.splice(idx, 1);
							}
							updateTournamentUI();
						});
					});
					
					updateTournamentUI();
				}
			}
		});
	</script>
	
	<script>
		// Инжектируем occurrence_id во все формы на странице
		(function() {
			var params = new URLSearchParams(window.location.search);
			var occId = params.get('occurrence_id');
			if (!occId) return;
			document.querySelectorAll('form[method="POST"]').forEach(function(form) {
				if (form.querySelector('input[name="occurrence_id"]')) return;
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'occurrence_id';
				input.value = occId;
				form.appendChild(input);
			});
			// Сохраняем позицию прокрутки перед отправкой формы
			document.querySelectorAll('form[method="POST"]').forEach(function(form) {
				form.addEventListener('submit', function() {
					try { window.name = 'scrollY:' + window.scrollY; } catch(e) {}
				});
			});
			
			// Восстанавливаем после перезагрузки
			try {
				if (window.name && window.name.indexOf('scrollY:') === 0) {
					var y = parseInt(window.name.split(':')[1]);
					window.name = '';
					if (y > 0) {
						setTimeout(function() { window.scrollTo(0, y); }, 100);
					}
				}
			} catch(e) {}
			
			// Прокрутка к якорю (если есть hash)
			if (window.location.hash) {
				setTimeout(function() {
					var el = document.querySelector(window.location.hash);
					if (el) el.scrollIntoView({behavior: 'smooth', block: 'start'});
				}, 300);
			}
		})(); // inject_occurrence_id
	</script>
	<script>
		// Manual draw — show/hide + update group options
		(function(){
			var drawSel = document.getElementById('draw_mode_select');
			var manualBlock = document.getElementById('manual_draw_block');
			var groupsInput = document.querySelector('input[name="groups_count"]');
			if (!drawSel || !manualBlock) return;
			
			function updateManual() {
				manualBlock.style.display = (drawSel.value === 'manual') ? '' : 'none';
			}
			
			function updateGroupOptions() {
				var g = parseInt(groupsInput ? groupsInput.value : 2) || 2;
				var selects = manualBlock.querySelectorAll('.manual-group-select');
				selects.forEach(function(sel) {
					var current = sel.value;
					// Remove all except first option
					while (sel.options.length > 1) sel.remove(1);
					for (var i = 0; i < g; i++) {
						var label = String.fromCharCode(65 + i);
						var opt = document.createElement('option');
						opt.value = label;
						opt.textContent = '\u0413\u0440\u0443\u043f\u043f\u0430 ' + label;
						sel.appendChild(opt);
					}
					// Restore selection if still valid
					if (current && current.charCodeAt(0) - 65 < g) sel.value = current;
				});
			}
			
			drawSel.addEventListener('change', updateManual);
			if (groupsInput) groupsInput.addEventListener('input', updateGroupOptions);
			updateManual();
			updateGroupOptions();
		})();
	</script>
	<script>
		// Переключатель Список / Шахматка
		(function () {
			var LS_KEY = 'ct_view_pref';

			function setView(groupId, view) {
				var listEl  = document.querySelector('.ct-view-list[data-group="' + groupId + '"]');
				var crossEl = document.querySelector('.ct-view-crosstable[data-group="' + groupId + '"]');
				var btns    = document.querySelectorAll('.ct-view-btn[data-group="' + groupId + '"]');
				if (!listEl) return;
				listEl.style.display  = view === 'list'       ? '' : 'none';
				if (crossEl) crossEl.style.display = view === 'crosstable' ? '' : 'none';
				btns.forEach(function (b) {
					b.classList.toggle('ct-view-btn--active', b.dataset.view === view);
				});
				try { localStorage.setItem(LS_KEY, view); } catch(e) {}
			}

			// Восстановить сохранённый вид
			var savedView = 'list';
			try { savedView = localStorage.getItem(LS_KEY) || 'list'; } catch(e) {}
			if (savedView === 'crosstable') {
				document.querySelectorAll('.ct-view-list[data-group]').forEach(function (el) {
					setView(el.dataset.group, 'crosstable');
				});
			}

			document.addEventListener('click', function (e) {
				var btn = e.target.closest('.ct-view-btn');
				if (!btn) return;
				setView(btn.dataset.group, btn.dataset.view);
			});
		})();
	</script>
	<style>
		.ct-view-btn { opacity: .55; }
		.ct-view-btn--active { opacity: 1; }
	</style>
</x-voll-layout>
