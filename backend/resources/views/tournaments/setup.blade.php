<x-voll-layout body_class="tournament-setup-page">
	@php
    $direction = $event->direction ?? 'classic';
    $isBeach = $direction === 'beach';
    // Терминология уровней игроков на этой странице — по географии СОБЫТИЯ
    // (турнир проходит в конкретном городе), а не по городу каждого игрока —
    // иначе в одном ростере была бы смесь терминологий.
    $levelScope = level_terminology_scope_for_event($event);
	@endphp
	<x-slot name="title">{{ __('tournaments.setup_title_with', ['title' => $event->title]) }}</x-slot>
	
    <x-slot name="h1">{{ __('tournaments.setup_title_with', ['title' => $event->title]) }}</x-slot>

	<x-slot name="style">
		<link href="/css/cropper.min.css" rel="stylesheet">
		<style>
			.cropper-modal-overlay {
				position: fixed; top: 0; bottom: 0; left: 0; right: 0;
				text-align: center; display: flex; flex-flow: column;
				align-items: center; justify-content: center;
				font-size: 0; overflow: hidden; z-index: 10000;
				pointer-events: none; opacity: 0; transition: opacity 0.3s ease;
			}
			.cropper-modal-overlay--active { opacity: 1; pointer-events: auto; }
			.cropper-modal-overlay:before, .cropper-modal-overlay:after {
				content: ""; position: absolute; top: 100vh; width: 100%; height: 100%;
				background: #fff; opacity: 0.8; transition-duration: 0.4s;
				transition-property: all;
				transition-timing-function: cubic-bezier(.47, 0, .74, .71);
				clip-path: polygon(100% 80%, 100% 100%, 0% 100%, 0% 20%);
			}
			.cropper-modal-overlay:after {
				clip-path: polygon(100% 0%, 100% 80%, 0% 20%, 0% 0%);
				top: -100vh; opacity: 0.5;
			}
			.cropper-modal-overlay--active:before, .cropper-modal-overlay--active:after {
				top: 0; left: 0;
				transition-timing-function: cubic-bezier(.22, .61, .36, 1);
			}
			body.dark .cropper-modal-overlay:before, body.dark .cropper-modal-overlay:after { background: #000; }
			.cropper-modal-container {
				position: relative; z-index: 10001; background: #fff;
				border-radius: 1.6rem; padding: 2rem; width: 90vw;
				max-width: 100rem; max-height: 90vh; display: flex;
				flex-direction: column;
				box-shadow: rgba(0,0,0,.1) 0px 1rem 2.2rem, rgba(0,0,0,.05) 0px .5rem 1.2rem;
				transform: scale(0.95); transition: transform 0.3s ease; overflow: hidden;
			}
			.cropper-modal-overlay--active .cropper-modal-container { transform: scale(1); }
			body.dark .cropper-modal-container { background: #2a2b3a; color: #e9ecef; }
			.cropper-image-wrapper {
				background: #f5f5f5; border-radius: 8px; overflow: hidden;
				margin-bottom: 1rem; flex: 1; min-height: 0;
				display: flex; align-items: center; justify-content: center;
			}
			body.dark .cropper-image-wrapper { background: #1e1e2a; }
			.cropper-image-wrapper img {
				max-width: 100%; max-height: 100%; width: auto; height: auto;
				display: block; margin: 0 auto; cursor: move;
			}
			.cropper-modal-container h3 {
				margin: 0 0 2rem 0; text-align: center; flex-shrink: 0; font-size: 2rem;
			}
			.cropper-buttons {
				display: flex; gap: 1rem; justify-content: center; flex-shrink: 0; margin-top: 1rem;
			}
			.cropper-modal-overlay .fancybox-loading {
				position: absolute; top: calc(50% - 75px); left: calc(50% - 75px);
				width: 150px; height: 150px; display: none; z-index: 10002;
			}
			.cropper-modal-overlay.loading .cropper-modal-container * { pointer-events: none; }
			.cropper-modal-overlay.loading .fancybox-loading { display: block !important; }
		</style>
	</x-slot>


{{-- Активный тур --}}
@if($selectedOccurrence)
@php
$occDate = \Carbon\Carbon::parse($selectedOccurrence->starts_at)->setTimezone($event->timezone ?? 'Europe/Moscow');
$tourNumber = $seasonData
    ? ($seasonData['occurrences']->search(fn($occ) => $occ->id === $selectedOccurrence->id) + 1)
    : 1;
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
			<a href="{{ route('events.show', $event) }}{{ $selectedOccurrence ? '?occurrence=' . $selectedOccurrence->id : '' }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ __('tournaments.setup_breadcrumb') }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>
	
	@if($seasonData && $seasonData['occurrences']->count() > 1)
	<x-slot name="d_description">
		<div class="d-flex flex-wrap gap-1 m-center">
			<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
				<button class="btn ufilter-btn">{{ __('tournaments.setup_pick_round') }}</button>
			</div>
		</div>
	</x-slot>
	@endif	
	
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
			@php
				$isIncomplete = $app->status === 'incomplete';
				$canApproveIncomplete = $isIncomplete && ($app->team->is_complete ?? false);
			@endphp
			<div class="card mb-1">
				<div class="d-flex fvc" style="justify-content:space-between;flex-wrap:wrap;gap:.5rem">
					<div>
						<div class="d-flex fvc" style="gap:.5rem;flex-wrap:wrap">
							<a class="b-700 f-17 blink" href="{{ route('tournamentTeams.show', [$event, $app->team]) }}">{{ $app->team->name ?? '?' }}</a>
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
						@if(!$isIncomplete || $canApproveIncomplete)
						<form method="POST" action="{{ route('tournament.application.approve', [$event, $app]) }}">
							@csrf
							<button type="submit" class="btn btn-small btn-primary btn-alert" data-title="{{ __('tournaments.apps_confirm_approve') }}" data-icon="question" data-confirm-text="{{ __('tournaments.setup_apps_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_apps_btn_approve') }}</button>
						</form>
						@endif
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
		@php
		$leagueForSubs    = null;
		$occSubstitutions = collect();
		$_tourStarted     = $selectedOccurrence && now('UTC')->gte($selectedOccurrence->starts_at);
		$_reserveForSubs  = collect();
		$hasStages        = $stages->isNotEmpty();
		@endphp
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

			
			{{-- Загрузка замен для текущего тура --}}
			@php
			$occSubstitutions = collect();
			$leagueForSubs = null;
			if($selectedOccurrence && $leagueTeams->count()) {
				$_seasonEvtForSubs = \App\Models\TournamentSeasonEvent::where('occurrence_id', $selectedOccurrence->id)->first();
				if($_seasonEvtForSubs?->league_id) {
					$leagueForSubs = \App\Models\TournamentLeague::find($_seasonEvtForSubs->league_id);
					$_activeTeamIds = $leagueTeams->where('status','active')->pluck('team_id')->filter();
					$occSubstitutions = \App\Models\TeamSubstitution::whereIn('team_id', $_activeTeamIds)
						->where('occurrence_id', $selectedOccurrence->id)
						->whereIn('status', ['pending','confirmed'])
						->with(['originalPlayer:id,first_name,last_name','substitutePlayer:id,first_name,last_name'])
						->get()->keyBy('team_id');
					// Список резервистов для модалки
					$_reserveForSubs = $leagueTeams->where('status','reserve')->filter(fn($lt)=>$lt->user_id);
				}
			}
			$_tourStarted = $selectedOccurrence && now('UTC')->gte($selectedOccurrence->starts_at);
			@endphp

			@php $hasStages = $stages->isNotEmpty(); @endphp
			{{-- Состав лиги --}}
			@if($leagueTeams->count())
			@php
				$_activeTeams  = $leagueTeams->where('status', 'active');
				$_reserveTeams = $leagueTeams->whereIn('status', ['reserve', 'pending_confirmation'])
				                             ->sortBy('reserve_position');
			@endphp
			<div class="">
				<h2 class="-mt-05" style="cursor:pointer;user-select:none" onclick="var b=document.getElementById('league-teams-body');b.style.display=b.style.display==='none'?'':'none';this.querySelector('.toggle-icon').textContent=b.style.display==='none'?'▶':'▼'">
					{{ __('tournaments.setup_series_lineup') }}
					<span class="cd">
						— {{ $_activeTeams->count() }} {{ __('tournaments.setup_series_active') }}
						@if($_reserveTeams->count())
						/ {{ $_reserveTeams->count() }} {{ __('tournaments.setup_series_reserve') }} ({{ __('tournaments.setup_series_waitlist_h2') }})
						@endif
					</span>
					<span class="toggle-icon" style="margin-left:8px;font-size:14px">{{ $hasStages ? '▶' : '▼' }}</span>
				</h2>
				<div id="league-teams-body" style="{{ $hasStages ? 'display:none' : '' }}">

				{{-- ===== ОСНОВНОЙ СОСТАВ ===== --}}
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
							@foreach($_activeTeams as $lt)
							<tr>
								<td>{{ $loop->iteration }}</td>
								<td>
									@if($lt->team)
									<div class="team-name b-600">
										<a class="blink" href="{{ route('tournamentTeams.show', [$event, $lt->team]) }}">{{ $lt->team->name }}</a>
									</div>
									<div class="team-members">
										@php
										$members = $lt->team->members->map(function($m) {
										$u = $m->user;
										return $u ? ($u->last_name . ' ' . $u->first_name) : '?';
										})->implode(' / ');
										@endphp
										{{ $members }}
									</div>
									@if($leagueForSubs)
									@php $existingSub = $occSubstitutions[$lt->team_id] ?? null; @endphp
									@if($existingSub)
									<div class="f-12 mt-025 d-flex gap-1 align-items-center flex-wrap">
										@if($existingSub->status === 'confirmed')
										<span class="alert-success p-1 pt-025 pb-025">✓ {{ __('tournaments.substitution_confirmed') }}:</span>
										@else
										<span class="alert-warning p-1 pt-025 pb-025">⏳ {{ __('tournaments.awaiting_confirmation') }}:</span>
										@endif
										<span>{{ $existingSub->substitutePlayer->last_name }} {{ $existingSub->substitutePlayer->first_name }}</span>
										<span style="opacity:.5">{{ __('tournaments.sub_instead_of', ['name' => $existingSub->originalPlayer->last_name.' '.$existingSub->originalPlayer->first_name]) }}</span>
										@if(!$_tourStarted)
										@if($existingSub->status === 'pending')
										<form method="POST" action="{{ route('substitutions.confirm', $existingSub) }}" style="display:inline">@csrf
											<button type="submit" class="btn btn-small" style="padding:1px 6px;font-size:11px">✓</button>
										</form>
										@endif
										<form method="POST" action="{{ route('substitutions.cancel', $existingSub) }}" style="display:inline">@csrf
											<button type="submit" class="btn btn-small btn-secondary" style="padding:1px 6px;font-size:11px">✕</button>
										</form>
										@endif
									</div>
									@elseif(!$_tourStarted)
									<div class="mt-025">
										<button type="button" class="btn btn-small btn-secondary" style="font-size:11px;padding:2px 8px"
											data-sub-team="{{ $lt->team_id }}"
											data-sub-league="{{ $leagueForSubs->id }}"
											data-sub-occurrence="{{ $selectedOccurrence->id }}"
											data-sub-members="{{ $lt->team->members->filter(fn($m)=>$m->user)->map(fn($m)=>['id'=>$m->user_id,'name'=>($m->user->last_name.' '.$m->user->first_name)])->values()->toJson() }}"
											onclick="openSubModal(this)">
											{{ __('tournaments.btn_find_sub') }}
										</button>
									</div>
									@endif
									@endif
									@elseif($lt->user)
									<div class="team-name">{{ $lt->user->last_name }} {{ $lt->user->first_name }}</div>
									<div class="f-12 cd mt-025">
										<form method="POST" action="{{ route('tournament.syncLeague', $event) }}" style="display:inline">
											@csrf
											<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
											<button type="submit" class="btn btn-small btn-secondary" style="font-size:11px;padding:2px 8px">Создать команду</button>
										</form>
									</div>
									@else
									—
									@endif
								</td>
								<td class="text-center">
									<span class="alert-success p-1 pt-05 pb-05">{{ __('tournaments.setup_st_active') }}</span>
								</td>
								<td class="text-center" style="white-space:nowrap">
									<form method="POST" action="{{ route('divisions.teams.toReserve', $lt) }}" style="display:inline">
										@csrf
										<button type="submit" class="btn btn-secondary btn-alert btn-small" data-title="{{ __('tournaments.setup_to_reserve_title') }}" data-icon="warning" data-confirm-text="{{ __('tournaments.yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_to_reserve') }}</button>
									</form>
									<form method="POST" action="{{ route('divisions.teams.destroy', $lt) }}" style="display:inline">
										@csrf
										@method('DELETE')
										<button type="submit" class="btn btn-danger btn-alert btn-small" data-title="{{ __('tournaments.setup_team_delete_title', ['name' => $lt->team?->name ?? '—']) }}" data-icon="warning" data-confirm-text="{{ __('tournaments.btn_delete') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.btn_delete') }}</button>
									</form>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>

				{{-- ===== ЛИСТ ОЖИДАНИЯ ===== --}}
				@if($_reserveTeams->count())
				<h3 class="mt-2 mb-05">⏳ {{ __('tournaments.setup_series_waitlist_h2') }} ({{ $_reserveTeams->count() }})</h3>
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
							@foreach($_reserveTeams as $lt)
							<tr>
								<td>{{ $loop->iteration }}</td>
								<td>
									@if($lt->team)
									<div class="team-name b-600">
										<a class="blink" href="{{ route('tournamentTeams.show', [$event, $lt->team]) }}">{{ $lt->team->name }}</a>
									</div>
									<div class="team-members">
										@php
										$members = $lt->team->members->map(function($m) {
										$u = $m->user;
										return $u ? ($u->last_name . ' ' . $u->first_name) : '?';
										})->implode(' / ');
										@endphp
										{{ $members }}
									</div>
									@elseif($lt->user)
									<div class="team-name">{{ $lt->user->last_name }} {{ $lt->user->first_name }}</div>
									<div class="f-12 cd mt-025">
										<form method="POST" action="{{ route('tournament.syncLeague', $event) }}" style="display:inline">
											@csrf
											<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
											<button type="submit" class="btn btn-small btn-secondary" style="font-size:11px;padding:2px 8px">Создать команду</button>
										</form>
									</div>
									@else
									—
									@endif
								</td>
								<td class="text-center">
									@if($lt->status === 'reserve')
									<span class="alert-warning p-1 pt-05 pb-05">{{ __('tournaments.setup_st_reserve_n', ['n' => $lt->reserve_position]) }}</span>
									@elseif($lt->status === 'pending_confirmation')
									<span class="alert-info p-1 pt-05 pb-05">{{ __('tournaments.setup_st_pending') }}</span>
									@else
									<span class="league-badge">{{ $lt->status }}</span>
									@endif
								</td>
								<td class="text-center" style="white-space:nowrap">
									<form method="POST" action="{{ route('divisions.teams.activate', $lt) }}" style="display:inline">
										@csrf
										<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
										<button type="submit" class="btn btn-secondary btn-alert btn-small" data-title="{{ __('tournaments.setup_activate_title') }}" data-icon="info" data-confirm-text="{{ __('tournaments.yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.setup_btn_activate') }}</button>
									</form>
									@if($lt->status === 'reserve')
									<form method="POST" action="{{ route('divisions.teams.moveReserve', $lt) }}" style="display:inline">
										@csrf
										<input type="hidden" name="direction" value="up">
										<button type="submit" class="btn btn-secondary btn-small" title="Вверх по очереди">↑</button>
									</form>
									<form method="POST" action="{{ route('divisions.teams.moveReserve', $lt) }}" style="display:inline">
										@csrf
										<input type="hidden" name="direction" value="down">
										<button type="submit" class="btn btn-secondary btn-small" title="Вниз по очереди">↓</button>
									</form>
									@endif
									<form method="POST" action="{{ route('divisions.teams.destroy', $lt) }}" style="display:inline">
										@csrf
										@method('DELETE')
										<button type="submit" class="btn btn-danger btn-alert btn-small" data-title="{{ __('tournaments.setup_team_delete_title', ['name' => $lt->team?->name ?? '—']) }}" data-icon="warning" data-confirm-text="{{ __('tournaments.btn_delete') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">{{ __('tournaments.btn_delete') }}</button>
									</form>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				@endif
			</div>
			@else
			<div class="alert alert-info">{{ __('tournaments.setup_no_teams_in_league') }}</div>
			@endif

			@if(!$hasStages)
			@if(!$seasonData['league'])
			<div class="alert alert-info mt-1">{!! __('tournaments.setup_no_division_yet', ['url' => route('seasons.edit', $seasonData['season'])]) !!}</div>
			@else
			{{-- Добавить команду вручную в дивизион --}}
			<div class="mt-1">
				<details id="add-to-league-details">
					@php
					$_addLeagueMax     = $seasonData['league']->max_teams ?? null;
					$_addLeagueActive  = $leagueTeams->where('status', 'active')->count();
					$_addLeagueFull    = $_addLeagueMax && $_addLeagueActive >= $_addLeagueMax;
					@endphp
				<summary class="btn btn-secondary">➕ Добавить в состав / резерв</summary>
					<form method="POST" action="{{ route('divisions.createAndAdd', $seasonData['league']) }}" class="mt-2 form">
						@csrf
						@if($selectedOccurrence)
						<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence->id }}">
						@endif
						<div class="row">
							<div class="col-md-6">
								<div class="card">
									<label>Капитан / игрок</label>
									<div style="position:relative" id="add-league-captain-wrap">
										<input type="text" id="add-league-captain-search" placeholder="Поиск по имени..." autocomplete="off">
										<input type="hidden" name="captain_user_id" id="add-league-captain-id">
										<div id="add-league-captain-dd" class="form-select-dropdown trainer_dd"></div>
									</div>
								</div>
							</div>
							@if($isBeach)
							<div class="col-md-6">
								<div class="card">
									<label>Партнёр</label>
									<div style="position:relative" id="add-league-partner-wrap">
										<input type="text" id="add-league-partner-search" placeholder="Поиск по имени..." autocomplete="off">
										<input type="hidden" name="partner_user_id" id="add-league-partner-id">
										<div id="add-league-partner-dd" class="form-select-dropdown trainer_dd"></div>
									</div>
								</div>
							</div>
							@endif
							<div class="col-md-6">
								<div class="card">
									<label>Название команды <span class="cd">(необязательно)</span></label>
									<input type="text" name="name" placeholder="Авто по фамилии капитана">
								</div>
							</div>
							<div class="col-md-6">
								<div class="card">
									<label>
									Место
									@if($_addLeagueMax)
									<span class="cd" id="league-cap-hint">— {{ $_addLeagueActive }} / {{ $_addLeagueMax }} команд</span>
									@endif
								</label>
									<select name="target_status" id="add-league-target-status"
										data-max="{{ $_addLeagueMax ?? '' }}"
										data-current="{{ $_addLeagueActive }}">
										<option value="active">Основной состав</option>
										<option value="reserve"{{ $_addLeagueFull ? ' selected' : '' }}>Резерв</option>
									</select>
									@if($_addLeagueFull)
									<div class="alert alert-warning mt-1" id="league-cap-warning" style="font-size:13px;padding-top:6px;padding-bottom:6px;margin:6px 0 0;line-height:1.4">
										⚠ Основной состав заполнен ({{ $_addLeagueActive }}/{{ $_addLeagueMax }}). Добавление переведёт в резерв или вернёт ошибку.
									</div>
									@else
									<div class="alert alert-warning mt-1" id="league-cap-warning" style="font-size:13px;padding-top:6px;padding-bottom:6px;margin:6px 0 0;line-height:1.4;display:none">
										⚠ Основной состав заполнен ({{ $_addLeagueActive }}/{{ $_addLeagueMax ?? '∞' }}). Добавление переведёт в резерв или вернёт ошибку.
									</div>
									@endif
								</div>
							</div>
							<div class="col-md-12 text-center">
								<button type="submit" class="btn">Добавить</button>
							</div>
						</div>
					</form>
				</details>
			</div>
			@endif

			@php
			$_tourAllCompleted = $stages->isNotEmpty() && $stages->every(fn($s) => $s->status === 'completed');
			@endphp
			<div class="mt-2 d-flex text-center gap-1 flex-wrap">
				<a class="btn" href="{{ route('seasons.show', $seasonData['season']) }}">{{ __('tournaments.setup_btn_season_page') }}</a>
				<form method="POST" action="{{ route('tournament.syncLeague', $event) }}" style="margin:0">
					@csrf
					<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
					<button type="submit" class="btn">{{ __('tournaments.setup_btn_sync_teams') }}</button>
				</form>
				@if($_tourAllCompleted)
				<form method="POST" action="{{ route('tournament.applyPromotion', $event) }}" style="margin:0">
					@csrf
					<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence?->id }}">
					<button type="submit" class="btn btn-alert" data-title="{{ __('tournaments.setup_promote_title') }}" data-icon="info" data-confirm-text="{{ __('tournaments.setup_promote_yes') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">
						{{ __('tournaments.setup_btn_promote') }}
					</button>
				</form>
				@endif
				</div>
			@endif
			</div>
		</div>
		@endif

		{{-- ============================================================
		Модалка замены
		============================================================ --}}
		@if($leagueForSubs && $selectedOccurrence && !$_tourStarted)
		<div id="subModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
			<div class="card p-3" style="max-width:480px;width:95%;max-height:90vh;overflow-y:auto;position:relative">
				<button onclick="closeSubModal()" style="position:absolute;top:10px;right:12px;background:none;border:none;font-size:18px;cursor:pointer">✕</button>
				<h3 class="-mt-05 mb-2" id="subModalTitle">{{ __('tournaments.find_substitute') }}</h3>

				{{-- Шаг 1: выбор кого заменяем --}}
				<div id="subStep1">
					<div class="f-13 mb-2" style="opacity:.7">{{ __('tournaments.invite_substitute') }}:</div>
					<div id="subMemberList"></div>
				</div>

				{{-- Шаг 2: кем заменяем --}}
				<div id="subStep2" style="display:none">
					<div class="f-13 mb-1" style="opacity:.5" id="subReplacingLabel"></div>

					{{-- Вкладки --}}
					<div class="d-flex gap-1 mb-2">
						<button class="btn sub-tab-btn" data-tab="reserve" onclick="switchSubTab('reserve')">{{ __('tournaments.substitute_from_reserve') }}</button>
						<button class="btn btn-secondary sub-tab-btn" data-tab="external" onclick="switchSubTab('external')">{{ __('tournaments.substitute_external') }}</button>
					</div>

					{{-- Из резерва --}}
					<div id="subTabReserve">
						@if(isset($_reserveForSubs) && $_reserveForSubs->isNotEmpty())
						@foreach($_reserveForSubs as $rlt)
						@if($rlt->user)
						<div class="d-flex" style="padding:5px 0;border-bottom:1px solid rgba(128,128,128,.08);align-items:center;gap:8px">
							<span style="flex:1">{{ $rlt->user->last_name }} {{ $rlt->user->first_name }}</span>
							<button type="button" class="btn btn-small"
								onclick="selectSubstitute({{ $rlt->user_id }}, '{{ addslashes($rlt->user->last_name.' '.$rlt->user->first_name) }}', 'reserve')">
								{{ __('tournaments.invite_substitute') }}
							</button>
						</div>
						@endif
						@endforeach
						@else
						<div class="f-13" style="opacity:.5">Резерв пуст</div>
						@endif
					</div>

					{{-- Поиск внешнего --}}
					<div id="subTabExternal" style="display:none">
						<input type="text" id="subSearchInput" class="form-control mb-1" placeholder="Поиск игрока..." autocomplete="off">
						<div id="subSearchResults"></div>
					</div>
				</div>

				{{-- Форма (скрытая) --}}
				<form method="POST" id="subForm" action="{{ route('leagues.substitutions.store', $leagueForSubs) }}" style="display:none">
					@csrf
					<input type="hidden" name="occurrence_id" value="{{ $selectedOccurrence->id }}">
					<input type="hidden" name="team_id" id="subTeamId">
					<input type="hidden" name="original_player_id" id="subOriginalId">
					<input type="hidden" name="substitute_player_id" id="subSubstituteId">
					<input type="hidden" name="substitute_source" id="subSource">
					<div class="mt-3 p-2" style="background:rgba(128,128,128,.08);border-radius:8px" id="subConfirmBlock">
						<div class="f-13 mb-2" id="subConfirmText"></div>
						<button type="submit" class="btn w-100">{{ __('tournaments.invite_substitute') }}</button>
					</div>
				</form>
			</div>
		</div>
		<script>
		var _subTeamId = null, _subOriginalId = null, _subOccurrenceId = {{ $selectedOccurrence->id }};
		function openSubModal(btn) {
			_subTeamId = btn.dataset.subTeam;
			var members = JSON.parse(btn.dataset.subMembers || '[]');
			var list = document.getElementById('subMemberList');
			list.innerHTML = '';
			members.forEach(function(m) {
				var d = document.createElement('div');
				d.style.cssText = 'padding:8px 0;border-bottom:1px solid rgba(128,128,128,.08);display:flex;align-items:center;gap:8px';
				d.innerHTML = '<span style="flex:1">'+m.name+'</span><button type="button" class="btn btn-small" onclick="chooseOriginal('+m.id+',\''+m.name.replace(/'/g,"\\'")+'\')">' + '{{ __("tournaments.invite_substitute") }}' + '</button>';
				list.appendChild(d);
			});
			document.getElementById('subTeamId').value = _subTeamId;
			document.getElementById('subStep1').style.display = '';
			document.getElementById('subStep2').style.display = 'none';
			document.getElementById('subForm').style.display = 'none';
			document.getElementById('subModal').style.display = 'flex';
		}
		function closeSubModal() { document.getElementById('subModal').style.display = 'none'; }
		function chooseOriginal(id, name) {
			_subOriginalId = id;
			document.getElementById('subOriginalId').value = id;
			document.getElementById('subReplacingLabel').textContent = '{{ __("tournaments.replacement_for", ["name" => ""]) }}' + name;
			document.getElementById('subStep1').style.display = 'none';
			document.getElementById('subStep2').style.display = '';
			document.getElementById('subForm').style.display = 'none';
			switchSubTab('reserve');
		}
		function switchSubTab(tab) {
			document.querySelectorAll('.sub-tab-btn').forEach(function(b){ b.classList.toggle('btn-secondary', b.dataset.tab !== tab); });
			document.getElementById('subTabReserve').style.display = tab==='reserve' ? '' : 'none';
			document.getElementById('subTabExternal').style.display = tab==='external' ? '' : 'none';
		}
		function selectSubstitute(id, name, source) {
			document.getElementById('subSubstituteId').value = id;
			document.getElementById('subSource').value = source;
			document.getElementById('subConfirmText').textContent = name;
			document.getElementById('subForm').style.display = '';
			document.getElementById('subConfirmBlock').style.display = '';
		}
		// Поиск внешнего игрока
		(function(){
			var t; document.getElementById('subSearchInput')?.addEventListener('input', function(){
				clearTimeout(t); var q = this.value.trim();
				if(q.length < 2) return;
				t = setTimeout(function(){
					jQuery.ajax({url:'/api/users/search', data:{q:q}, success:function(r){
						var el = document.getElementById('subSearchResults'); el.innerHTML='';
						(r.items||[]).forEach(function(u){
							var d=document.createElement('div');
							d.style.cssText='padding:5px 0;border-bottom:1px solid rgba(128,128,128,.08);display:flex;align-items:center;gap:8px;cursor:pointer';
							d.innerHTML='<span style="flex:1">'+(u.label||u.name)+'</span><button type="button" class="btn btn-small" onclick="selectSubstitute('+u.id+',\''+(u.label||u.name).replace(/'/g,"\\'")+'\',\'external\')">{{ __("tournaments.invite_substitute") }}</button>';
							el.appendChild(d);
						});
					}});
				}, 300);
			});
		})();
		document.getElementById('subModal').addEventListener('click', function(e){ if(e.target===this) closeSubModal(); });
		</script>
		@endif


		{{-- ============================================================
		Команды
		============================================================ --}}
		<div class="ramka">
			@php
				$completeTeams   = $teams->filter(fn($t) => $t->is_complete);
				$incompleteTeams = $teams->filter(fn($t) => !$t->is_complete);
				$isIndividualTournament = ($event->registration_mode ?? '') === 'tournament_individual';
				$teamsHeaderKey = $isIndividualTournament ? 'tournaments.setup_teams_h2_individual' : 'tournaments.setup_teams_h2';
			@endphp
			<h2 class="-mt-05" style="cursor:pointer;user-select:none" onclick="var b=document.getElementById('teams-body');b.style.display=b.style.display==='none'?'':'none';this.querySelector('.toggle-icon').textContent=b.style.display==='none'?'▶':'▼'">{{ __($teamsHeaderKey, ['n' => $completeTeams->count()]) }} <span class="toggle-icon" style="font-size:14px">{{ $hasStages ? '▶' : '▼' }}</span></h2>
			<div id="teams-body" style="{{ $hasStages ? 'display:none' : '' }}">
			@if($completeTeams->isEmpty())
			<div class="alert alert-info">{{ __('tournaments.setup_teams_empty') }}</div>
			@else
			<div class="row">
				@foreach($completeTeams as $team)
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
							<div style="display:flex;gap:4px;align-items:center">
								<form method="POST" action="{{ route('tournamentTeams.sendToWaitlist', [$event, $team]) }}" class="mt-1">
									@csrf
									<button type="submit" class="btn btn-secondary btn-small btn-alert" data-title="Переместить «{{ $team->name }}» в резерв?" data-icon="warning" data-confirm-text="В резерв" data-cancel-text="{{ __('tournaments.btn_cancel') }}" title="В резерв">⏳</button>
								</form>
								<form method="POST" action="{{ route('tournamentTeams.destroy', [$event, $team]) }}" class="mt-1">
									@csrf @method('DELETE')
									<button type="submit" class="icon-delete btn-alert btn btn-danger btn-svg" data-title="{{ __('tournaments.setup_team_delete_title', ['name' => $team->name]) }}" data-icon="warning" data-confirm-text="{{ __('tournaments.btn_delete') }}" data-cancel-text="{{ __('tournaments.btn_cancel') }}">
									</button>
								</form>
							</div>
						</div>
					</div>
				</div>
				@endforeach
			</div>
			@endif

			@if($incompleteTeams->isNotEmpty())
			<h3 class="mt-2 mb-05">⏳ Ищут партнёра ({{ $incompleteTeams->count() }})</h3>
			<div class="row">
				@foreach($incompleteTeams as $team)
				<div class="col-md-6 col-xl-3">
					<div class="card" style="opacity:.8;border-style:dashed">
						<a href="{{ route('tournamentTeams.show', [$event, $team]) }}" class="blink b-600 d-block mb-1">
							{{ $team->name }}
						</a>
						@php $members = $team->members->load('user'); @endphp
						@foreach($members as $m)
						<div>{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}</div>
						@endforeach
						<div class="mt-1 d-flex between fvc">
							<div class="mt-05 cd b-600" style="color:#92400e">Ищет партнёра</div>
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

			@if($isIndividualTournament)
			<h3 class="mt-2 mb-05">{{ __('tournaments.setup_unassigned_h3', ['n' => $unassignedPlayers->count()]) }}</h3>
			@if($unassignedPlayers->isEmpty())
			<div class="alert alert-info">{{ __('tournaments.setup_unassigned_empty') }}</div>
			@else
			<div class="row">
				@foreach($unassignedPlayers as $p)
				@php
					$pLevel = ($event->direction === 'beach' ? $p->beach_level : $p->classic_level);
					$pLevel = !is_null($pLevel) && $pLevel !== '' ? (int) $pLevel : null;
					$pGenderColor = $p->gender === 'f' ? '#e5395e' : '#2967BA';
					$pGenderSign = $p->gender === 'f' ? '♀' : '♂';
				@endphp
				<div class="col-md-6 col-xl-3">
					<div class="card" style="opacity:.9">
						<div style="display:flex;align-items:center;gap:10px">
							<img src="{{ $p->profile_photo_url }}" alt="" loading="lazy" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0">
							<div style="min-width:0">
								<div class="b-600" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: ($p->name ?? '?') }}</div>
								<div class="f-13" style="opacity:.85">
									<span style="color:{{ $pGenderColor }};font-weight:700">{{ $pGenderSign }}</span> ·
									{{ __('tournaments.setup_unassigned_level') }}:
									@if($pLevel)
									<span class="levelmark levelmark--event level-{{ $pLevel }}">{{ level_name_short($pLevel, $levelScope) }}</span>
									@else
									<span class="levelmark levelmark--event level-na">!?</span>
									@endif
								</div>
							</div>
						</div>
					</div>
				</div>
				@endforeach
			</div>
			@endif
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
									<div class="card" style="overflow:visible">
										<label>{{ __('tournaments.setup_team_label_captain') }}</label>
										@if($isIndividualTournament && $unassignedPlayers->isNotEmpty())
										<div style="position:relative" id="manual-captain-ac-wrap">
											<input type="text" id="manual-captain-search" placeholder="{{ __('tournaments.setup_team_ph_captain') }}" autocomplete="off">
											<input type="hidden" name="captain_user_id" id="manual-captain-id">
											<div id="manual-captain-dd" class="form-select-dropdown trainer_dd"></div>
										</div>
										@else
										<div style="position:relative" id="org-captain-ac-wrap">
											<input type="text" id="org-captain-search" placeholder="{{ __('tournaments.setup_team_ph_captain') }}" autocomplete="off">
											<input type="hidden" name="captain_user_id" id="org-captain-id">
											<div id="org-captain-dd" class="form-select-dropdown trainer_dd"></div>
										</div>
										@endif
									</div>
								</div>
								@if($isIndividualTournament && $unassignedPlayers->isNotEmpty())
								<div class="col-md-12">
									<div class="card" style="overflow:visible">
										<label>{{ __('tournaments.setup_team_label_members') }}</label>
										<div id="manual-members-list" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:.5rem">
											@foreach($unassignedPlayers as $p)
											<label class="checkbox-item" data-user-id="{{ $p->id }}" style="display:flex;align-items:center;gap:6px;margin:0">
												<input type="checkbox" name="member_user_ids[]" value="{{ $p->id }}">
												<div class="custom-checkbox"></div>
												<span>{{ trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: ($p->name ?? '?') }} ({{ $p->gender === 'f' ? '♀' : '♂' }})</span>
											</label>
											@endforeach
										</div>
									</div>
								</div>
								@endif
								<div class="col-md-12 text-center">
									<button type="submit" class="btn">{{ __('tournaments.setup_btn_create') }}</button>
								</div>
							</div>
						</div>
					</form>
				</details>
			</div>

			@if($isIndividualTournament && $unassignedPlayers->isNotEmpty())
			<script>
			(function(){
				var players = @json($unassignedPlayers->map(fn($p) => [
					'id' => $p->id,
					'label' => trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: ($p->name ?? ('#' . $p->id)),
				])->values());
				var inp = document.getElementById('manual-captain-search');
				var hidden = document.getElementById('manual-captain-id');
				var dd = document.getElementById('manual-captain-dd');
				var wrap = document.getElementById('manual-captain-ac-wrap');
				if (!inp || !dd || !hidden) return;

				function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
				function showDd() { dd.classList.add('form-select-dropdown--active'); }
				function hideDd() { dd.classList.remove('form-select-dropdown--active'); }

				function setCaptain(id, label) {
					inp.value = label;
					hidden.value = String(id);
					hideDd();
					document.querySelectorAll('#manual-members-list [data-user-id]').forEach(function(row) {
						var cb = row.querySelector('input[type=checkbox]');
						if (!cb) return;
						var isCaptain = row.dataset.userId === String(id);
						cb.disabled = isCaptain;
						if (isCaptain) cb.checked = false;
					});
				}

				inp.addEventListener('input', function() {
					hidden.value = '';
					document.querySelectorAll('#manual-members-list input[type=checkbox]').forEach(function(cb) { cb.disabled = false; });
					var q = inp.value.trim().toLowerCase();
					if (q.length < 1) { hideDd(); dd.innerHTML = ''; return; }
					var matches = players.filter(function(p) { return p.label.toLowerCase().indexOf(q) !== -1; });
					dd.innerHTML = '';
					if (!matches.length) {
						dd.innerHTML = '<div class="city-message">' + @json(__('tournaments.setup_search_no_results')) + '</div>';
						showDd();
						return;
					}
					matches.slice(0, 8).forEach(function(p) {
						var div = document.createElement('div');
						div.className = 'trainer-item form-select-option';
						div.innerHTML = '<div class="text-sm">' + esc(p.label) + '</div>';
						div.addEventListener('click', function() { setCaptain(p.id, p.label); });
						dd.appendChild(div);
					});
					showDd();
				});

				inp.addEventListener('keydown', function(e) { if (e.key === 'Escape') hideDd(); });
				document.addEventListener('click', function(e) { if (wrap && !wrap.contains(e.target)) hideDd(); });
			})();
			</script>
			@endif

			@if($isIndividualTournament)
			{{-- Случайное распределение игроков по командам (только индивидуальная запись) --}}
			@php
				$remainingTeamsCount = max(0, ($event->tournament_teams_count ?? 0) - ($completeTeams->count() + $incompleteTeams->count()));
				$distributeConfirmText = __('events.tournament_distribute_confirm', [
					'n' => $remainingTeamsCount,
					'p' => $unassignedPlayers->count(),
				]);
			@endphp
			<div class="mt-1">
				<button type="button" id="distribute-teams-btn" class="btn btn-secondary"
					data-event-id="{{ $event->id }}"
					data-occurrence-id="{{ $selectedOccurrence?->id }}">
					{{ __('events.tournament_distribute_random_btn') }}
				</button>
			</div>
			<script>
			(function() {
				var btn = document.getElementById('distribute-teams-btn');
				if (!btn) return;
				var defaultText = btn.textContent.trim();
				btn.addEventListener('click', function() {
					var eventId = btn.dataset.eventId;
					var occurrenceId = btn.dataset.occurrenceId;

					swal({
						title: @json(__('events.tournament_distribute_random_btn')),
						text: @json($distributeConfirmText),
						icon: 'warning',
						buttons: {
							cancel: { text: @json(__('tournaments.btn_cancel')), value: null, visible: true, closeModal: true },
							confirm: { text: @json(__('events.tournament_distribute_btn')), value: true, visible: true, closeModal: true },
						},
						dangerMode: true,
					}).then(function(confirmed) {
						if (!confirmed) return;

						btn.disabled = true;
						btn.textContent = '...';
						fetch('/events/' + eventId + '/distribute-individual', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
								'Accept': 'application/json',
							},
							body: JSON.stringify({ occurrence_id: occurrenceId ? parseInt(occurrenceId) : null }),
							credentials: 'same-origin',
						})
						.then(function(r) { return r.json(); })
						.then(function(data) {
							if (data.ok) {
								location.reload();
							} else {
								swal({ title: 'Ошибка', text: data.message || 'Не удалось распределить игроков.', icon: 'error', button: 'Понятно' });
								btn.disabled = false;
								btn.textContent = defaultText;
							}
						})
						.catch(function() {
							swal({ title: 'Ошибка', text: 'Ошибка соединения.', icon: 'error', button: 'Понятно' });
							btn.disabled = false;
							btn.textContent = defaultText;
						});
					});
				});
			})();
			</script>
			@endif

			</div>{{-- /teams-body --}}
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
				<form class="mt-2 form" method="POST" action="{{ route('tournament.stages.store', $event) }}">
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
									<option value="king_beach">{{ __('tournaments.setup_stage_king_beach') }}</option>
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
					{{-- King of the Beach: специфичные настройки --}}
					<div class="mt-2" id="king_beach_fields" style="display:none">
						<div class="row">
							<div class="col-md-4">
								<div class="card">
									<label>{{ __('tournaments.setup_stage_kb_group_size') }}</label>
									<select name="kb_group_size" id="kb_group_size_select">
										<option value="4">{{ __('tournaments.setup_stage_kb_group_size_4') }}</option>
										<option value="6">{{ __('tournaments.setup_stage_kb_group_size_6') }}</option>
									</select>
									<p class="f-16">{{ __('tournaments.setup_stage_kb_group_size_hint') }}</p>
								</div>
							</div>
							<div class="col-md-4">
								<div class="card">
									<label>{{ __('tournaments.setup_stage_kb_draw') }}</label>
									<select name="draw_mode" id="kb_draw_mode_select">
										<option value="random">{{ __('tournaments.setup_stage_seed_random') }}</option>
										<option value="seeded">{{ __('tournaments.setup_stage_seed_seeded') }}</option>
									</select>
									<p class="f-16">{{ __('tournaments.setup_stage_kb_draw_manual_hint') }}</p>
								</div>
							</div>
							<div class="col-md-4">
								<div class="card">
									<label>{{ __('tournaments.setup_stage_kb_players') }}</label>
									<p class="f-16">{{ __('tournaments.setup_stage_kb_players_hint') }}</p>
								</div>
							</div>
						</div>
					</div>

					<div class="mt-2" id="group_fields">
						<div class="row">
							<div class="col-lg-4 col-md-6">
								<div class="card"><label>{{ __('tournaments.setup_stage_groups_count') }}</label>
									<input name="groups_count" type="number" value="2" min="1" max="16">
								</div>
							</div>
							<div class="col-lg-4 col-md-6">
								<div class="card"><label>{{ __('tournaments.setup_stage_groups_advance') }}</label>
									<input name="advance_count" type="number" value="2" min="1" max="8">
									<p class="f-16">{{ __('tournaments.setup_stage_groups_advance_hint') }}</p>
								</div>
							</div>
							<div class="col-lg-4 col-md-6">
								<div class="card"><label>{{ __('tournaments.setup_stage_third_place') }}</label>
									<select name="third_place_match">
										<option value="0">{{ __('tournaments.no') }}</option>
										<option value="1">{{ __('tournaments.yes') }}</option>
									</select>
								</div>
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
												<th>{{ __('tournaments.setup_stage_manual_position_col') }}</th>
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
													<select name="manual_teams[{{ $team->id }}][group]" class="manual-group-select" >
														<option value="">—</option>
														<option value="A">{{ __('tournaments.setup_stage_group_letter', ['l' => 'A']) }}</option>
														<option value="B">{{ __('tournaments.setup_stage_group_letter', ['l' => 'B']) }}</option>
													</select>
												</td>
												<td class="text-center">
													<input type="number" name="manual_teams[{{ $team->id }}][position]" min="1" max="{{ $teams->count() }}" style="width:4.5rem" placeholder="—">
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
					</div>

					{{-- Корты — общий блок для группового этапа и King of the Beach --}}
					<div class="mt-2" id="courts_shared_fields" style="overflow:visible">
						<div class="row">
							<div class="col-lg-4 col-md-6">
								<div class="card" style="overflow:visible">
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

						{{-- Назначение кортов группам (динамическое, только для форматов с группами) --}}
						<div class="mt-2" id="courts_group_assign" style="display:none">
							<div class="card">
								<label>{{ __('tournaments.setup_stage_courts_for_groups') }}</label>
								<hr class="mb-1">
								<div id="courts_group_boxes" class="row"></div>
							</div>	
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
							var typeSel = document.getElementById("stage_type_select");
							
							function rebuild() {
								var n = parseInt(courtsSel.value) || 0;
								var isGroupType = typeSel && ['round_robin', 'groups_playoff', 'thai'].indexOf(typeSel.value) !== -1;
								var g = isGroupType ? (parseInt(groupsSel ? groupsSel.value : 0) || 0) : 0;
								
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
							if (typeSel) typeSel.addEventListener("change", rebuild);
							rebuild();
						})();
					</script>
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
			
			@php $userTournamentGallery = $userEventPhotos ?? collect(); @endphp

			<div class="card">
				<label>{{ __('tournaments.setup_photos_pick') }}</label>

				<div id="tournament-photos-swiper-wrap" @if($userTournamentGallery->count() === 0) style="display:none" @endif>
					<div class="event-photos-selector" id="tournament-photos-selector"
					data-selected='{{ json_encode($currentPhotoIds) }}'>
						<div class="swiper tournamentPhotosSwiper">
							<div class="swiper-wrapper">
								@foreach($userTournamentGallery as $photo)
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
						</ul>
					</div>
				</div>

				<div class="mt-1">
					<input type="file" id="tournament-photo-upload" accept="image/*" style="display:none">
					<button type="button" id="tournament-upload-photo-btn" class="btn btn-secondary f-13" style="padding:6px 14px">
						+ {{ __('tournaments.setup_photos_add') }}
					</button>
					<div class="f-13 cd mt-05">
						{!! __('tournaments.setup_photos_hint_2', ['link' => '<a target="_blank" href="' . route('user.photos') . '">' . __('tournaments.setup_photos_hint_2_link') . '</a>']) !!}
					</div>
				</div>
			</div>

			<div class="text-center mt-2">
				<form method="POST" action="{{ route('tournament.photos.store', $event) }}" id="tournament-photos-form">
					@csrf
					<input type="hidden" name="photo_ids" id="tournament_photos_input" value="">
					<button type="submit" class="btn btn-primary" id="tournament-photos-submit" style="display:none">{{ __('tournaments.setup_photos_save') }}</button>
				</form>
			</div>
		</div>
		@endif
		
		@foreach($stages as $stage)
		@php
		$borderColor = $stage->isCompleted() ? '#10b981' : ($stage->isInProgress() ? '#2967BA' : '#555');
		$_isDivStage = str_starts_with($stage->name, 'Группа ');
		$stageHasDivDistribution = !$_isDivStage && $stages->contains(fn($s) => str_starts_with($s->name, 'Группа ') && $s->occurrence_id == $stage->occurrence_id);
		@endphp
		{{-- King of the Beach: отдельный рендеринг --}}
		@if($stage->type === 'king_beach')
		@include('tournaments._partials.king_beach_stage', ['stage' => $stage, 'event' => $event, 'selectedOccurrence' => $selectedOccurrence])
		@continue
		@endif
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
						'king_beach' => __('tournaments.setup_stage_lbl_king_beach'),
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
											<div class="b-600 cd">@include('tournaments._partials.team_name_link', ['team' => $standing->team])@if($isOutsider) <span class="f-16">{{ __('tournaments.setup_outsider_label') }}</span>@endif</div>
											@include('tournaments._partials.team_roster_line', ['team' => $standing->team, 'class' => 'f-16'])
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
												<div class="{{ $match->winner_team_id === $match->team_home_id ? 'cd b-600' : '' }}">@include('tournaments._partials.team_name_link', ['team' => $match->teamHome, 'fallback' => 'TBD'])</div>
												@include('tournaments._partials.team_roster_line', ['team' => $match->teamHome, 'class' => 'f-13'])
											</td>
											<td>
												<div class="{{ $match->winner_team_id === $match->team_away_id ? 'cd b-600' : '' }}">@include('tournaments._partials.team_name_link', ['team' => $match->teamAway, 'fallback' => 'TBD'])</div>
												@include('tournaments._partials.team_roster_line', ['team' => $match->teamAway, 'class' => 'f-13'])
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
												@if(($match->isScheduled() || $match->isLive()) && $match->hasTeams())
												<a href="{{ route('tournament.matches.score.form', $match) }}" class="btn btn-primary btn-small">
													{{ __('tournaments.setup_match_btn_score') }}
												</a>
												@endif
												@if($match->isCompleted())
												<a href="{{ route('tournament.matches.player_stats.form', $match) }}" class="btn btn-secondary btn-small" title="{{ __('tournaments.setup_match_player_stats_title') }}">
													📊
												</a>
												<a href="{{ route('tournament.matches.pdf_stats', $match) }}" class="btn btn-secondary btn-small" title="{{ __('tournaments.rally_btn_pdf_stats') }}">
													📊 PDF
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
				
				<form method="POST" action="{{ route('tournament.stages.formDivisions', $stage) }}" class="form">
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

		{{-- Кнопка результатов турнира (без сезона — обычный/standalone турнир без дивизионов) --}}
		@if(!$event->season_id && $stages->isNotEmpty() && $stages->every(fn($s) => $s->status === 'completed'))
		<div class="ramka" style="text-align:center">
			<a href="{{ route('tournament.public.show', $event) }}{{ $selectedOccurrence ? '?occurrence_id=' . $selectedOccurrence->id : '' }}" class="btn btn-primary p-3 f-16" style="display:inline-block">
				{{ __('tournaments.setup_btn_tournament_results') }}
			</a>
		</div>
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
			var kbFields = document.getElementById('king_beach_fields');
			var courtsFields = document.getElementById('courts_shared_fields');
			// group_fields и king_beach_fields содержат поля с ОДИНАКОВЫМИ name (draw_mode) —
			// display:none не мешает браузеру отправить их оба на сервер. Отключаем инпуты
			// скрытого блока через disabled, чтобы в форму попадали только видимые поля.
			function setBlockActive(block, active) {
				if (!block) return;
				block.style.display = active ? '' : 'none';
				block.querySelectorAll('input, select, textarea').forEach(function(el) {
					el.disabled = !active;
				});
			}
			if (typeSelect) {
				function toggle() {
					var t = typeSelect.value;
					var showGroup = (t === 'round_robin' || t === 'groups_playoff' || t === 'thai');
					var showKb = (t === 'king_beach');
					setBlockActive(groupFields, showGroup);
					setBlockActive(kbFields, showKb);
					// Корты — общий блок для групповых форматов и King of the Beach
					setBlockActive(courtsFields, showGroup || showKb);
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
	
	
	<script>
	(function(){
		function makeAC(inputId, hiddenId, ddId, wrapId) {
			var inp = document.getElementById(inputId);
			var hidden = document.getElementById(hiddenId);
			var dd = document.getElementById(ddId);
			var wrap = document.getElementById(wrapId);
			if (!inp || !dd || !hidden) return;
			var timer = null;
			function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
			function showDd() { dd.classList.add('form-select-dropdown--active'); }
			function hideDd() { dd.classList.remove('form-select-dropdown--active'); }
			inp.addEventListener('input', function() {
				clearTimeout(timer);
				hidden.value = '';
				var q = inp.value.trim();
				if (q.length < 2) { hideDd(); dd.innerHTML = ''; return; }
				dd.innerHTML = '<div class="city-message">Загрузка...</div>';
				showDd();
				timer = setTimeout(function() {
					fetch('/api/users/search?q=' + encodeURIComponent(q), {
						headers: {'Accept':'application/json'}, credentials:'same-origin'
					})
					.then(function(r){ return r.json(); })
					.then(function(data){
						dd.innerHTML = '';
						var items = data.items || data || [];
						if (!items.length) { dd.innerHTML = '<div class="city-message">Не найдено</div>'; showDd(); return; }
						items.slice(0,8).forEach(function(u) {
							var label = u.label || u.name || '#'+u.id;
							var div = document.createElement('div');
							div.className = 'trainer-item form-select-option';
							var botBadge = u.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">🤖 бот</span>' : '';
							div.innerHTML = '<div class="text-sm">'+esc(label)+botBadge+'</div>';
							div.addEventListener('click', function() {
								inp.value = label; hidden.value = String(u.id); hideDd();
							});
							dd.appendChild(div);
						});
						showDd();
					})
					.catch(function(){ dd.innerHTML = '<div class="city-message">Ошибка загрузки</div>'; showDd(); });
				}, 250);
			});
			inp.addEventListener('keydown', function(e){ if(e.key==='Escape') hideDd(); });
			document.addEventListener('click', function(e){ if(wrap && !wrap.contains(e.target)) hideDd(); });
		}
		makeAC('add-league-captain-search','add-league-captain-id','add-league-captain-dd','add-league-captain-wrap');
		makeAC('add-league-partner-search','add-league-partner-id','add-league-partner-dd','add-league-partner-wrap');

		// Предупреждение о лимите дивизиона
		(function(){
			var sel = document.getElementById('add-league-target-status');
			var warn = document.getElementById('league-cap-warning');
			if (!sel || !warn) return;
			var max = parseInt(sel.dataset.max) || 0;
			var cur = parseInt(sel.dataset.current) || 0;
			sel.addEventListener('change', function(){
				if (max && cur >= max && this.value === 'active') {
					warn.style.display = '';
				} else {
					warn.style.display = 'none';
				}
			});
		})();
	})();
	</script>

	<script src="/assets/fas.js"></script>
	<script src="/js/cropper.min.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Tournament Photos Swiper
			var tournamentPhotosSwiper = null;
			if (document.querySelector('.tournamentPhotosSwiper .swiper-wrapper')) {
				tournamentPhotosSwiper = new Swiper('.tournamentPhotosSwiper', {
					slidesPerView: 3,
					spaceBetween: 20,
					pagination: { el: '.tournamentPhotosSwiper .swiper-pagination', clickable: true },
					breakpoints: {
						320: { slidesPerView: 2 },
						640: { slidesPerView: 3 },
						1024: { slidesPerView: 4 }
					}
				});
			}

			var selectorEl = document.getElementById('tournament-photos-selector');
			var savedPhotos = selectorEl ? JSON.parse(selectorEl.dataset.selected || '[]') : [];
			var selectedPhotos = savedPhotos.slice();
			var tPhotoSelectLabel = @json(__('tournaments.setup_photos_select'));
			var tPhotoMainLabel   = @json(__('tournaments.setup_photo_main'));
			var tPhotoPosLabel    = @json(__('tournaments.setup_photo_pos_n', ['n' => '']));

			function updateTournamentUI() {
				document.querySelectorAll('.t-photo-select').forEach(function(cb) {
					var id = parseInt(cb.value);
					var isSelected = selectedPhotos.indexOf(id) !== -1;
					cb.checked = isSelected;
					var badge = cb.closest('.swiper-slide').querySelector('.photo-order-badge');
					if (isSelected) {
						var order = selectedPhotos.indexOf(id) + 1;
						badge.textContent = order === 1 ? tPhotoMainLabel : (tPhotoPosLabel + order);
					} else {
						badge.textContent = '';
					}
				});
				var inp = document.getElementById('tournament_photos_input');
				if (inp) inp.value = JSON.stringify(selectedPhotos);
				var btn = document.getElementById('tournament-photos-submit');
				if (btn) btn.style.display = selectedPhotos.length > 0 ? '' : 'none';
			}

			function bindTournamentCheckbox(cb) {
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
			}

			document.querySelectorAll('.t-photo-select').forEach(bindTournamentCheckbox);
			updateTournamentUI();

			// --- Загрузка фото с кропом 4:3 (800×600) в турнирный альбом ---
			var tournamentCropper = null;

			function supportsWebPT() {
				try {
					var c = document.createElement('canvas');
					return c.toDataURL('image/webp').indexOf('data:image/webp') === 0;
				} catch(e) { return false; }
			}

			function processImageT(file, callback) {
				var url = URL.createObjectURL(file);
				var img = new Image();
				img.onload = function() {
					var w = img.width, h = img.height, maxSize = 1920;
					if (w > maxSize || h > maxSize) {
						var r = Math.min(maxSize / w, maxSize / h);
						w = Math.round(w * r); h = Math.round(h * r);
					}
					var canvas = document.createElement('canvas');
					canvas.width = w; canvas.height = h;
					canvas.getContext('2d').drawImage(img, 0, 0, w, h);
					var fmt = supportsWebPT() ? 'image/webp' : 'image/jpeg';
					canvas.toBlob(function(blob) { callback(blob, fmt); }, fmt, 0.85);
				};
				img.src = url;
			}

			function showTournamentCropperModal(imageUrl, onCropComplete) {
				var modal = document.createElement('div');
				modal.className = 'cropper-modal-overlay';
				var mc = document.createElement('div');
				mc.className = 'cropper-modal-container';
				var mt = document.createElement('h3');
				mt.textContent = 'Обрезать фото';
				var imgWrapper = document.createElement('div');
				imgWrapper.className = 'cropper-image-wrapper';
				var img = document.createElement('img');
				img.src = imageUrl;
				imgWrapper.appendChild(img);
				var bc = document.createElement('div');
				bc.className = 'cropper-buttons';
				var saveBtn = document.createElement('button');
				saveBtn.textContent = 'Добавить'; saveBtn.type = 'button'; saveBtn.className = 'btn';
				var cancelBtn = document.createElement('button');
				cancelBtn.textContent = 'Отмена'; cancelBtn.type = 'button'; cancelBtn.className = 'btn btn-secondary';
				bc.appendChild(saveBtn); bc.appendChild(cancelBtn);
				var loading = document.createElement('div');
				loading.className = 'fancybox-loading'; loading.style.display = 'none';
				modal.appendChild(loading);
				mc.appendChild(mt); mc.appendChild(imgWrapper); mc.appendChild(bc);
				modal.appendChild(mc);
				document.body.appendChild(modal);
				modal.offsetHeight;
				requestAnimationFrame(function() { modal.classList.add('cropper-modal-overlay--active'); });
				img.onload = function() {
					if (tournamentCropper) tournamentCropper.destroy();
					tournamentCropper = new Cropper(img, {
						aspectRatio: 4 / 3,
						viewMode: 1, background: true, dragMode: 'crop',
						autoCropArea: 0.8, cropBoxMovable: true, cropBoxResizable: true,
						zoomable: true, zoomOnWheel: true, wheelZoomRatio: 0.1,
						movable: true, guides: true, center: true, highlight: true,
						responsive: true, restore: false,
					});
				};
				saveBtn.onclick = function() {
					if (!tournamentCropper) return;
					modal.classList.add('loading');
					saveBtn.disabled = true; cancelBtn.disabled = true;
					var canvas = tournamentCropper.getCroppedCanvas({ width: 800, height: 600 });
					var fmt = supportsWebPT() ? 'image/webp' : 'image/jpeg';
					canvas.toBlob(function(blob) { onCropComplete(blob, fmt); }, fmt, 0.90);
				};
				cancelBtn.onclick = function() {
					modal.remove();
					if (tournamentCropper) { tournamentCropper.destroy(); tournamentCropper = null; }
					document.getElementById('tournament-photo-upload').value = '';
				};
				modal.onclick = function(e) { if (e.target === modal) cancelBtn.onclick(); };
			}

			function sendTournamentPhoto(originalBlob, croppedBlob, format) {
				var ext = format === 'image/webp' ? 'webp' : 'jpg';
				var ts = Date.now();
				var fd = new FormData();
				fd.append('photo_original', originalBlob, 'original_' + ts + '.' + ext);
				fd.append('photo_cropped',  croppedBlob, 'thumb_' + ts + '.' + ext);
				fd.append('photo_type', 'tournament_photos');
				fd.append('make_avatar', '0');
				fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
				fetch('/user/photos', { method: 'POST', body: fd })
					.then(function(r) {
						return r.json().then(function(data) {
							var modal = document.querySelector('.cropper-modal-overlay');
							if (r.ok && data.success) {
								if (modal) modal.remove();
								onTournamentPhotoUploaded(data.media_id, data.thumb_url);
							} else {
								if (modal) modal.remove();
								swal({ title: 'Ошибка', text: data.error || 'Не удалось загрузить фото', icon: 'error', button: 'Понятно' });
							}
						});
					})
					.catch(function() {
						var modal = document.querySelector('.cropper-modal-overlay');
						if (modal) modal.remove();
						swal({ title: 'Ошибка', text: 'Ошибка сети. Попробуйте ещё раз.', icon: 'error', button: 'Понятно' });
					});
			}

			function onTournamentPhotoUploaded(mediaId, thumbUrl) {
				var slideHtml = '<div class="swiper-slide">' +
					'<div class="hover-image mb-1">' +
					'<img src="' + thumbUrl + '" alt="photo" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px"/>' +
					'</div>' +
					'<div class="mt-1 d-flex between fvc">' +
					'<label class="checkbox-item mb-0">' +
					'<input type="checkbox" class="t-photo-select" value="' + mediaId + '">' +
					'<div class="custom-checkbox"></div>' +
					'<span>' + tPhotoSelectLabel + '</span>' +
					'</label>' +
					'<div class="photo-order-badge f-16 b-600 cd"></div>' +
					'</div></div>';

				var swiperWrap = document.getElementById('tournament-photos-swiper-wrap');
				if (swiperWrap) swiperWrap.style.display = '';

				if (!tournamentPhotosSwiper) {
					tournamentPhotosSwiper = new Swiper('.tournamentPhotosSwiper', {
						slidesPerView: 3, spaceBetween: 20,
						pagination: { el: '.tournamentPhotosSwiper .swiper-pagination', clickable: true },
						breakpoints: { 320: { slidesPerView: 2 }, 640: { slidesPerView: 3 }, 1024: { slidesPerView: 4 } }
					});
				}

				tournamentPhotosSwiper.prependSlide(slideHtml);
				tournamentPhotosSwiper.slideTo(0);

				var newCb = document.querySelector('.t-photo-select[value="' + mediaId + '"]');
				if (newCb) {
					bindTournamentCheckbox(newCb);
					selectedPhotos.unshift(mediaId);
					updateTournamentUI();
				}

				document.getElementById('tournament-photo-upload').value = '';
			}

			var uploadBtn = document.getElementById('tournament-upload-photo-btn');
			var uploadInput = document.getElementById('tournament-photo-upload');
			if (uploadBtn && uploadInput) {
				uploadBtn.addEventListener('click', function() { uploadInput.click(); });
				uploadInput.addEventListener('change', function(e) {
					var file = e.target.files[0];
					if (!file) return;
					if (!file.type.startsWith('image/')) {
						swal({ title: 'Ошибка', text: 'Пожалуйста, выберите изображение', icon: 'error', button: 'Понятно' });
						this.value = ''; return;
					}
					if (file.size > 15 * 1024 * 1024) {
						swal({ title: 'Ошибка', text: 'Файл слишком большой. Максимум 15 МБ.', icon: 'error', button: 'Понятно' });
						this.value = ''; return;
					}
					processImageT(file, function(blob, fmt) {
						var url = URL.createObjectURL(blob);
						showTournamentCropperModal(url, function(croppedBlob, cropFmt) {
							sendTournamentPhoto(blob, croppedBlob, cropFmt);
						});
					});
				});
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
