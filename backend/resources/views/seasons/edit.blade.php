<x-voll-layout body_class="seasons-page">
	<x-slot name="title">{{ $season->name }} — {{ __('seasons.btn_manage') }}</x-slot>
	<x-slot name="h1">{{ $season->name }}</x-slot>
	
	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.index') }}" itemprop="item"><span itemprop="name">{{ __('seasons.leagues_idx_breadcrumb') }}</span></a>
			<meta itemprop="position" content="2">
		</li>
		@if($season->league)
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.edit', $season->league) }}" itemprop="item"><span itemprop="name">{{ $season->league->name }}</span></a>
			<meta itemprop="position" content="3">
		</li>
		@endif
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ $season->name }}</span>
			<meta itemprop="position" content="{{ $season->league ? 4 : 3 }}">
		</li>
	</x-slot>
	<x-slot name="h2">
		{{ $season->direction === 'beach' ? __('seasons.leagues_dir_beach_short') : __('seasons.leagues_dir_classic_short') }}
		
	</x-slot>	
	<x-slot name="t_description">
		{{ $season->starts_at?->format('d.m.Y') ?? '—' }} — {{ $season->ends_at?->format('d.m.Y') ?? '...' }}
		
	</x-slot>

	<x-slot name="d_description">
	
		<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
			<span class="d-inline-block pt-1 pb-1 alert alert-{{
			substr($season->status, 0, 1) === 'a' ? 'success' : 
			(substr($season->status, 0, 1) === 'c' ? 'danger' : 'warning')
			}}">
				{{ $season->status === 'active' ? __('seasons.status_active') : ($season->status === 'completed' ? __('seasons.status_completed') : __('seasons.status_draft')) }}
			</span>
		</div>		
	
	</x-slot>
	
	
	<div class="container">
		
		@if(session('success'))
		<div class="ramka">	
			<div class="alert alert-success">{{ session('success') }} ✅ </div>
		</div>
		@endif
		@if(session('error'))
		<div class="ramka">	
			<div class="alert alert-danger">{{ session('error') }} ❌</div>
		</div>
		@endif
		
		<div class="row row2 form">
			<div class="col-lg-4">
				{{-- Настройки --}}
				<div class="ramka">
					<h2 class="-mt-05">{{ __('seasons.edit_settings_h2') }}</h2>
					<form action="{{ route('seasons.update', $season) }}" method="POST">
						@csrf @method('PUT')
						
						<div class="card mb-2">
							<label>{{ __('seasons.leagues_label_name_short') }}</label>
							<input type="text" name="name" value="{{ $season->name }}" required>
						</div>
						

						
						<div class="card mb-2">
							<label>{{ __('seasons.label_start') }}</label>
							<input type="date" name="starts_at" value="{{ $season->starts_at?->format('Y-m-d') }}">
						</div>
						<div class="card mb-2">
							<label>{{ __('seasons.edit_label_end_short') }}</label>
							<input type="date" name="ends_at" value="{{ $season->ends_at?->format('Y-m-d') }}">
						</div>
						
						<button type="submit" class="btn btn-primary w-100">{{ __('seasons.btn_save') }}</button>
					</form>
				</div>
				
				{{-- Действия --}}
				<div class="ramka">
					<div class="d-flex" style="gap:1rem;flex-wrap:wrap">
						@if($season->isDraft())
						<form action="{{ route('seasons.activate', $season) }}" method="POST" style="flex:1">
							@csrf
							<button class="btn w-100">{{ __('seasons.btn_activate') }}</button>
						</form>
						<form action="{{ route('seasons.destroy', $season) }}" method="POST" style="flex:1"
						onsubmit="return confirm({!! json_encode(__('seasons.confirm_delete_season')) !!})">
							@csrf @method('DELETE')
							<button class="btn btn-danger btn-alert w-100"
							data-title="{{ __('seasons.confirm_delete_season') }}"
							data-text="{{ __('seasons.delete_season_text') }}"
							data-icon="warning"
							data-confirm-text="{{ __('seasons.btn_delete') }}"
							data-cancel-text="{{ __('seasons.btn_cancel') }}">{{ __('seasons.btn_delete') }}</button>
						</form>
						@elseif($season->isActive())
						<form action="{{ route('seasons.complete', $season) }}" method="POST" style="flex:1"
						onsubmit="return confirm({!! json_encode(__('seasons.confirm_complete_season')) !!})">
							@csrf
							<button class="btn btn-secondary w-100">{{ __('seasons.btn_complete_season') }}</button>
						</form>
						@endif
					</div>
					
					<div class="mt-2">
						{{ __('seasons.leagues_public_link_label') }} <br><a class="blink" href="{{ route('seasons.show.slug', [$season->league?->slug ?? 'league', $season->slug]) }}" target="_blank">/l/{{ $season->league?->slug ?? 'league' }}/s/{{ $season->slug }}</a>
					</div>
				</div>
				
			</div>
			<div class="col-lg-8">
				
				{{-- Добавить лигу --}}
				<div class="ramka">
					<h2 class="-mt-05">{{ __('seasons.add_division_h2') }}</h2>
					<form action="{{ route('seasons.divisions.store', $season) }}" method="POST">
						@csrf
						<div class="row">
							<div class="col-md-8">
								<div class="card">
									<label>{{ __('seasons.label_division_name') }}</label>
									<input type="text" name="name" placeholder="Hard / Medium / Lite" required>
								</div>
							</div>
							<div class="col-md-4">
								<div class="card">
									<label>{{ __('seasons.label_max_teams') }}</label>
									<input type="number" name="max_teams" min="2" placeholder="—">
								</div>
							</div>
						</div>	
						<div class="text-center mt-2">
							<button type="submit" class="btn btn-primary">{{ __('seasons.btn_add') }}</button>
						</div>
					</form>
				</div>
				
				{{-- Список лиг --}}
				
				@forelse($season->leagues as $league)
				<div class="ramka">
					<div class="d-flex between fvc mb-2">
						<h2 class="-mt-05 mb-0">{{ __('seasons.division_label') }} {{ $league->name }}</h2>
						<div>
							<strong class="cd">{{ $league->activeTeams->count() }}</strong> {{ __('seasons.teams_short') }} {!! $league->max_teams ? ' / ' . __('seasons.max_short') . ' <strong class="cd">' . $league->max_teams . '</strong>' : '' !!}
							
							@if($league->reserveTeams->count() > 0)
							· {{ __('seasons.reserve_label') }} <strong class="cd">{{ $league->reserveTeams->count() }}</strong>
							@endif
						</div>
					</div>
					
					{{-- Активные команды --}}
					@if($league->activeTeams->isNotEmpty())
					@foreach($league->activeTeams as $lt)
					<div class="card d-flex between fvc mb-1" style="padding:8px 12px">
						<div>
							@if($lt->team)
							<span class="b-600">{{ $lt->team->name }}</span>
							@if($lt->team->captain)
                            <span class="f-16">({{ $lt->team->captain->name }})</span>
							@endif
							@elseif($lt->user)
							<span class="b-600">{{ $lt->user->name }}</span>
							@endif
						</div>
						<form action="{{ route('divisions.teams.destroy', $lt) }}" method="POST"
						onsubmit="return confirm({!! json_encode(__('seasons.confirm_remove_team')) !!})">
							@csrf @method('DELETE')
							<button class="icon-delete btn btn-danger btn-alert btn-svg"
							data-title="{{ __('seasons.confirm_remove_team') }}"
							data-icon="warning"
							data-confirm-text="{{ __('seasons.btn_remove_yes') }}"
							data-cancel-text="{{ __('seasons.btn_cancel') }}"></button>
						</form>
					</div>
					@endforeach
					@else
					<div class="alert alert-info">{{ __('seasons.no_teams') }}</div>
					@endif
					
					{{-- Резерв --}}
					@if($league->reserveTeams->isNotEmpty())
					<div class="mt-2 p-2" style="background:rgba(128,128,128,.06);border-radius:8px">
						<span class="f-13 b-600">{{ __('seasons.reserve_label_short') }}</span>
						@foreach($league->reserveTeams as $lt)
						<span class="f-13 ml-1 px-2 py-1" style="background:rgba(128,128,128,.12);border-radius:6px;display:inline-block;margin:2px">
							#{{ $lt->reserve_position }} {{ $lt->team?->name ?? $lt->user?->name ?? '—' }}
						</span>
						@endforeach
					</div>
					@endif
					
					<div class="d-flex between fvc mt-1">
						<span>
							{{ __('seasons.promotion_label') }} <strong class="cd">{{ $league->promoteCount() }}</strong> · {{ __('seasons.relegation_label') }} <strong class="cd">{{ $league->eliminateCount() }}</strong>
						</span>
						<form action="{{ route('divisions.destroy', $league) }}" method="POST"
						onsubmit="return confirm({!! json_encode(__('seasons.confirm_delete_division', ['name' => '__N__'])) !!}.replace('__N__', @json($league->name)))">
							@csrf @method('DELETE')
							<button class="btn btn-danger btn-alert w-100"
							data-title="{{ __('seasons.delete_division_title') }}"
							data-icon="warning"
							data-confirm-text="{{ __('seasons.btn_delete') }}"
							data-cancel-text="{{ __('seasons.btn_cancel') }}">{{ __('seasons.btn_delete') }}</button>
						</form>
					</div>
				</div>
				@empty
				<div class="ramka">
					<h2 class="-mt-05">{{ __('seasons.divisions_section_h2') }}</h2>					
					<div class="alert alert-info">
						{{ __('seasons.divisions_add_hint') }}
					</div>
				</div>
				@endforelse
				
				{{-- Привязанные турниры --}}
				<div class="ramka">
					<h2 class="-mt-05">{{ __('seasons.tournaments_section_h2') }}</h2>
					
					@if($season->seasonEvents->isNotEmpty())
					@foreach($season->seasonEvents->sortBy('round_number') as $se)
					<div class="card d-flex between fvc mb-1" style="padding:8px 12px;flex-wrap:wrap;gap:8px">
						<div>
							<span class="b-600">{{ __('seasons.round_n_short', ['n' => $se->round_number]) }}</span>
							<a href="{{ route('events.show', $se->event) }}" class="blink ml-1">{{ $se->event->title }}</a>
							<span class="f-16 ml-1">· {{ $se->league->name }}</span>
						</div>
						<div class="d-flex fvc" style="gap:1rem">
							<span style="padding: 0.5rem 1rem;border-radius: 1rem;" class="f-15 b-600 {{ $se->isCompleted() ? 'alert-success' : 'alert-info' }}">
								{!! $se->isCompleted() ? '&#10003; ' . __('seasons.tournament_completed') : __('seasons.tournament_pending') !!}
							</span>
							<form action="{{ route('seasons.events.detach', [$season, $se->event]) }}" method="POST"
							onsubmit="return confirm({!! json_encode(__('seasons.confirm_unlink_tournament')) !!})">
								@csrf @method('DELETE')
							<button class="icon-delete btn btn-danger btn-alert btn-svg"
							data-title="{{ __('seasons.confirm_unlink_tournament') }}"
							data-icon="warning"
							data-confirm-text="{{ __('seasons.btn_unlink_yes') }}"
							data-cancel-text="{{ __('seasons.btn_cancel') }}"></button>
							</form>
						</div>
					</div>
					@endforeach
					@else
					<div class="alert alert-info">{{ __('seasons.no_linked_tournaments') }}</div>
					@endif
					
					{{-- Привязать новый --}}
					@if($availableEvents->isNotEmpty() && $season->leagues->isNotEmpty())
					<div class="mt-2 pt-2" style="border-top:1px solid rgba(128,128,128,.15)">
						<form action="{{ route('seasons.events.attach', $season) }}" method="POST">
							@csrf
							<div class="d-flex" style="gap:10px;flex-wrap:wrap;align-items:flex-end">
								<div style="flex:2">
									<label class="f-13 b-600 mb-1">{{ __('seasons.btn_tournament_short') }}</label>
									<select name="event_id" required>
										<option value="">{{ __('seasons.opt_choose') }}</option>
										@foreach($availableEvents as $ev)
										<option value="{{ $ev->id }}">{{ $ev->title }}</option>
										@endforeach
									</select>
								</div>
								<div style="flex:1">
									<label class="f-13 b-600 mb-1">{{ __('seasons.label_league') }}</label>
									<select name="league_id" required>
										@foreach($season->leagues as $lg)
										<option value="{{ $lg->id }}">{{ $lg->name }}</option>
										@endforeach
									</select>
								</div>
								<div style="width:70px">
									<label class="f-13 b-600 mb-1">{{ __('seasons.label_round_n') }}</label>
									<input type="number" name="round_number" min="1" value="{{ $season->currentRound() + 1 }}">
								</div>
								<div>
									<button type="submit" class="btn btn-primary" style="padding:8px 16px">{{ __('seasons.btn_link') }}</button>
								</div>
							</div>
						</form>
					</div>
					@endif
				</div>
				
			</div>
		</div>
		
	</div>
</x-voll-layout>
