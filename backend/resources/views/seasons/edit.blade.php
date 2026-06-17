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

						{{-- Настройки промоушена --}}
						<div class="card mb-2" style="background:rgba(231,97,47,.04);border:1px solid rgba(231,97,47,.15)">
							<label class="f-14 b-600 mb-1">{{ __('tournaments.promotion_settings') }}</label>

							<label class="checkbox-item mb-1">
								<input type="checkbox" name="config[auto_promotion]" value="1" {{ $season->isAutoPromotion() ? 'checked' : '' }}>
								<div class="custom-checkbox"></div>
								<span class="f-13">{{ __('tournaments.auto_promotion') }}</span>
							</label>
							<div class="f-13 cd mb-2">{{ __('tournaments.auto_promotion_hint') }}</div>

							<label class="f-13 b-600">{{ __('tournaments.promotion_trigger') }}</label>
							<select name="config[promotion_trigger]" class="mb-2">
								<option value="manual" {{ $season->getPromotionTrigger() === 'manual' ? 'selected' : '' }}>{{ __('tournaments.trigger_manual') }}</option>
								<option value="after_tour" {{ $season->getPromotionTrigger() === 'after_tour' ? 'selected' : '' }}>{{ __('tournaments.trigger_after_tour') }}</option>
							</select>

							<div class="mt-2 pt-2" style="border-top:1px solid rgba(128,128,128,.12)">
								<label class="checkbox-item mb-1">
									<input type="checkbox" name="config[queue_entry_enabled]" value="1" {{ $season->isQueueEntryEnabled() ? 'checked' : '' }}>
									<div class="custom-checkbox"></div>
									<span class="f-13">{{ __('tournaments.queue_entry_enabled') }}</span>
								</label>
								<div class="mb-2">
									<label class="f-13 b-600">{{ __('tournaments.queue_entry_slots') }}</label>
									<input type="number" name="config[queue_entry_slots]" min="0" max="10" value="{{ $season->getQueueEntrySlots() }}" style="width:100%">
								</div>
							</div>

							@if($season->league && $season->league->hasFeeder())
							<div class="d-flex fvc mb-2" style="gap:8px">
								<span class="f-13 b-600">{{ __('tournaments.feeder_promote_slots') }} <span class="cd">({{ $season->league->feederLeague->name }})</span>:</span>
								<input type="number" name="config[feeder_promote_slots]" min="0" max="10" value="{{ $season->getFeederPromoteSlots() }}" style="width:70px">
							</div>
							@endif

							<label class="f-13 b-600">{{ __('tournaments.relegation_penalty') }}</label>
							<select name="config[relegation_penalty]" class="mb-1">
								<option value="">{{ __('tournaments.no_penalty') }}</option>
								<option value="saturday_07:00" {{ $season->getRelegationPenalty() === 'saturday_07:00' ? 'selected' : '' }}>{{ __('tournaments.penalty_saturday_7') }}</option>
								<option value="sunday_07:00" {{ $season->getRelegationPenalty() === 'sunday_07:00' ? 'selected' : '' }}>{{ __('tournaments.penalty_sunday_7') }}</option>
								<option value="monday_07:00" {{ $season->getRelegationPenalty() === 'monday_07:00' ? 'selected' : '' }}>{{ __('tournaments.penalty_monday_7') }}</option>
							</select>
							<div class="f-13 cd">{{ __('tournaments.relegation_penalty_hint') }}</div>
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
				
				{{-- Список дивизионов --}}

				@forelse($season->leagues as $divLeague)
				<div class="ramka">
					<div class="d-flex between fvc mb-2">
						<h2 class="-mt-05 mb-0">{{ __('seasons.division_label') }} {{ $divLeague->name }}</h2>
						<div>
							<strong class="cd">{{ $divLeague->activeTeams->count() }}</strong> {{ __('seasons.teams_short') }} {!! $divLeague->max_teams ? ' / ' . __('seasons.max_short') . ' <strong class="cd">' . $divLeague->max_teams . '</strong>' : '' !!}
							@if($divLeague->reserveTeams->count() > 0)
							· {{ __('seasons.reserve_label') }} <strong class="cd">{{ $divLeague->reserveTeams->count() }}</strong>
							@endif
						</div>
					</div>

					{{-- Активные команды --}}
					@if($divLeague->activeTeams->isNotEmpty())
					@foreach($divLeague->activeTeams as $lt)
					<div class="card d-flex between fvc mb-1" style="padding:8px 12px">
						<div>
							@if($lt->team)
							<span class="b-600">{{ $lt->team->name }}</span>
							@if($lt->team->captain)
							<span class="f-16">({{ $lt->team->captain->name }})</span>
							@endif
							@elseif($lt->user)
							<span class="b-600">{{ trim(($lt->user->last_name ?? '') . ' ' . ($lt->user->first_name ?? '')) ?: '?' }}</span>
							@endif
						</div>
						<div class="d-flex fvc" style="gap:6px">
							<button type="button" class="btn btn-secondary f-13" style="padding:4px 10px"
								data-lt-id="{{ $lt->id }}"
								data-lt-name="{{ addslashes($lt->team?->name ?? trim(($lt->user->last_name ?? '') . ' ' . ($lt->user->first_name ?? ''))) }}"
								onclick="showRelegateModal(this.dataset.ltId, this.dataset.ltName)">
								{{ __('tournaments.relegate_team') }}
							</button>
							@if($season->leagues->count() > 1)
							<button type="button" class="btn btn-secondary f-13" style="padding:4px 10px"
								data-lt-id="{{ $lt->id }}"
								data-lt-name="{{ addslashes($lt->team?->name ?? trim(($lt->user->last_name ?? '') . ' ' . ($lt->user->first_name ?? ''))) }}"
								onclick="showTransferModal(this.dataset.ltId, this.dataset.ltName)">
								{{ __('tournaments.transfer_team') }}
							</button>
							@endif
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
					</div>
					@endforeach
					@else
					<div class="alert alert-info">{{ __('seasons.no_teams') }}</div>
					@endif

					{{-- Резерв --}}
					@if($divLeague->reserveTeams->isNotEmpty())
					<div class="mt-2 p-2" style="background:rgba(128,128,128,.06);border-radius:8px">
						<span class="f-13 b-600 mb-1">{{ __('seasons.reserve_label_short') }}</span>
						@foreach($divLeague->reserveTeams as $lt)
						<div class="d-flex between fvc mb-1" style="gap:6px">
							<span class="f-13" style="background:rgba(128,128,128,.12);border-radius:6px;padding:3px 8px">
								#{{ $lt->reserve_position }} {{ $lt->team?->name ?? trim(($lt->user->last_name ?? '') . ' ' . ($lt->user->first_name ?? '')) ?: '—' }}
								@if($lt->confirmation_expires_at)
								<span class="cd f-12"> · до {{ $lt->confirmation_expires_at->format('d.m H:i') }}</span>
								@endif
							</span>
							<form action="{{ route('seasons.teams.activate', [$season, $lt]) }}" method="POST">
								@csrf
								<button type="submit" class="btn f-13" style="padding:3px 10px;background:rgba(16,185,129,.15);color:#10b981">
									{{ __('tournaments.activate_team') }}
								</button>
							</form>
						</div>
						@endforeach
					</div>
					@endif

					{{-- Настройки дивизиона --}}
					<div class="mt-2 pt-2" style="border-top:1px solid rgba(128,128,128,.1)">
						<span class="f-13 b-600 cd">{{ __('tournaments.division_settings') }}</span>
						<form action="{{ route('seasons.update', $season) }}" method="POST" class="mt-1">
							@csrf @method('PUT')
							<input type="hidden" name="name" value="{{ $season->name }}">
							<input type="hidden" name="direction" value="{{ $season->direction }}">

							{{-- Строка 0: название --}}
							<div style="margin-bottom:8px">
								<label class="f-13 cd">{{ __('seasons.label_division_name') }}</label>
								<input type="text" name="divisions[{{ $divLeague->id }}][name]"
									value="{{ $divLeague->name }}" style="width:100%">
							</div>

							@php
								$lowerDiv = $season->leagues->where('level', $divLeague->level + 1)->first();
								$upperDiv = $season->leagues->where('level', $divLeague->level - 1)->first();
								$isLowest = $lowerDiv === null;
								$isHighest = $upperDiv === null;
							@endphp

							{{-- Строка 1: числовые поля --}}
							<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:8px">
								<div>
									<label class="f-13 cd">{{ __('tournaments.max_teams') }}</label>
									<input type="number" name="divisions[{{ $divLeague->id }}][max_teams]"
										value="{{ $divLeague->max_teams }}" min="2" max="32" style="width:100%">
								</div>
								<div>
									<label class="f-13 cd">{{ __('tournaments.division_level') }}</label>
									<input type="number" name="divisions[{{ $divLeague->id }}][level]"
										value="{{ $divLeague->level }}" min="1" max="10" style="width:100%">
									<div class="f-12 cd" style="line-height:1.2">1 = главный</div>
								</div>
								<div>
									<label class="f-13 cd">{{ __('tournaments.eliminate_count') }}</label>
									<input type="number" name="divisions[{{ $divLeague->id }}][config][eliminate_count]"
										value="{{ $divLeague->getEliminateCount() }}" min="0" max="10" style="width:100%">
								</div>
								<div>
									<label class="f-13 cd">{{ __('tournaments.promote_count') }}</label>
									<input type="number" name="divisions[{{ $divLeague->id }}][config][promote_count]"
										value="{{ $divLeague->getPromoteCount() }}" min="0" max="10" style="width:100%">
								</div>
							</div>

							{{-- Строка 2: селекты + кнопка --}}
							<div style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:flex-end">
								<div>
									<label class="f-13 cd">{{ __('tournaments.eliminate_to') }}</label>
									<select name="divisions[{{ $divLeague->id }}][config][eliminate_to]" style="width:100%">
										@if($isLowest)
											{{-- Нижний дивизион: вылет = в резерв --}}
											<option value="reserve" selected>{{ __('tournaments.to_reserve') }}</option>
										@else
											{{-- Не нижний: вылет в дивизион ниже --}}
											<option value="lower_division" {{ $divLeague->getEliminateTo() === 'lower_division' ? 'selected' : '' }}>
												{{ $lowerDiv ? '↓ ' . $lowerDiv->name : __('tournaments.to_lower_division') }}
											</option>
											@if($season->league && $season->league->hasFeeder())
											<option value="feeder" {{ $divLeague->getEliminateTo() === 'feeder' ? 'selected' : '' }}>{{ __('tournaments.to_feeder_league') }}</option>
											@endif
										@endif
									</select>
								</div>
								<div>
									<label class="f-13 cd">{{ __('tournaments.promote_to') }}</label>
									<select name="divisions[{{ $divLeague->id }}][config][promote_to]" style="width:100%">
										@if($isHighest)
											{{-- Верхний дивизион: повышаться некуда --}}
											<option value="">{{ __('tournaments.nowhere') }}</option>
											@if($season->league && $season->league->hasFeeder())
											<option value="parent_league" {{ $divLeague->getPromoteTo() === 'parent_league' ? 'selected' : '' }}>{{ __('tournaments.to_parent_league') }}</option>
											@endif
										@else
											{{-- Не верхний: повышение в дивизион выше --}}
											<option value="upper_division" {{ $divLeague->getPromoteTo() === 'upper_division' ? 'selected' : '' }}>
												{{ $upperDiv ? '↑ ' . $upperDiv->name : __('tournaments.to_upper_division') }}
											</option>
											<option value="" {{ $divLeague->getPromoteTo() === null ? 'selected' : '' }}>{{ __('tournaments.nowhere') }}</option>
										@endif
									</select>
								</div>
								<div>
									<button type="submit" class="btn f-13" style="padding:5px 14px">{{ __('seasons.btn_save') }}</button>
								</div>
							</div>
						</form>
					</div>

					<div class="d-flex" style="justify-content:flex-end;margin-top:8px">
						<form action="{{ route('divisions.destroy', $divLeague) }}" method="POST"
						onsubmit="return confirm({!! json_encode(__('seasons.confirm_delete_division', ['name' => '__N__'])) !!}.replace('__N__', @json($divLeague->name)))">
							@csrf @method('DELETE')
							<button class="btn btn-danger btn-alert f-13"
							data-title="{{ __('seasons.delete_division_title') }}"
							data-icon="warning"
							data-confirm-text="{{ __('seasons.btn_delete') }}"
							data-cancel-text="{{ __('seasons.btn_cancel') }}"
							style="padding:4px 12px">{{ __('seasons.btn_delete') }}</button>
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

				{{-- Кнопка "Выполнить промоушен" --}}
				@php
					$lastCompletedSE = $season->seasonEvents->where('status', 'completed')->sortByDesc('round_number')->first();
				@endphp
				@if($lastCompletedSE && !$season->isAutoPromotion() && $lastCompletedSE->occurrence_id)
				<div class="ramka" style="background:rgba(231,97,47,.04);border:1px solid rgba(231,97,47,.2)">
					<h2 class="-mt-05">{{ __('tournaments.promotion_ready') }}</h2>
					<p class="f-14 cd">{{ __('tournaments.promotion_ready_hint') }}</p>
					<form method="POST" action="{{ route('seasons.promote', $season) }}">
						@csrf
						<input type="hidden" name="occurrence_id" value="{{ $lastCompletedSE->occurrence_id }}">
						<input type="hidden" name="round_number" value="{{ $lastCompletedSE->round_number }}">
						<button type="submit" class="btn btn-alert"
							data-title="{{ __('tournaments.confirm_promotion') }}"
							data-icon="warning"
							data-confirm-text="{{ __('tournaments.execute_promotion') }}"
							data-cancel-text="{{ __('seasons.btn_cancel') }}">
							{{ __('tournaments.execute_promotion') }}
						</button>
					</form>
				</div>
				@endif
				
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

				{{-- История промоушенов --}}
				@if($promotionHistory->isNotEmpty())
				<div class="ramka">
					<h2 class="-mt-05">{{ __('tournaments.promotion_history') }}</h2>
					<div style="overflow-x:auto">
						<table style="width:100%;border-collapse:collapse;font-size:13px">
							<thead>
								<tr style="border-bottom:1px solid rgba(128,128,128,.2)">
									<th style="padding:6px 8px;text-align:left">{{ __('tournaments.date') }}</th>
									<th style="padding:6px 8px;text-align:left">{{ __('tournaments.round') }}</th>
									<th style="padding:6px 8px;text-align:left">{{ __('tournaments.player') }}</th>
									<th style="padding:6px 8px;text-align:left">{{ __('tournaments.action') }}</th>
									<th style="padding:6px 8px;text-align:left">{{ __('tournaments.from') }} → {{ __('tournaments.to') }}</th>
									<th style="padding:6px 8px;text-align:left">{{ __('tournaments.initiated_by') }}</th>
								</tr>
							</thead>
							<tbody>
							@foreach($promotionHistory as $ph)
							<tr style="border-bottom:1px solid rgba(128,128,128,.08)">
								<td style="padding:6px 8px;white-space:nowrap">{{ $ph->created_at->format('d.m.Y H:i') }}</td>
								<td style="padding:6px 8px">{{ $ph->round_number ?? '—' }}</td>
								<td style="padding:6px 8px">
									@if($ph->user)
									<a href="/user/{{ $ph->user_id }}" class="blink">{{ trim(($ph->user->last_name ?? '') . ' ' . ($ph->user->first_name ?? '')) ?: '?' }}</a>
									@else
									—
									@endif
								</td>
								<td style="padding:6px 8px">
									@php
									$actionBadges = [
										'promoted_to_upper'   => ['bg' => '#10b981', 'text' => '↑ ' . __('tournaments.promoted')],
										'promoted_to_parent'  => ['bg' => '#10b981', 'text' => '⬆ ' . __('tournaments.to_parent_league')],
										'relegated_to_feeder' => ['bg' => '#ef4444', 'text' => '↓ ' . __('tournaments.relegated')],
										'relegated_to_lower'  => ['bg' => '#f59e0b', 'text' => '↓ ' . __('tournaments.to_lower_division')],
										'relegated_to_reserve'=> ['bg' => '#6b7280', 'text' => '⏸ ' . __('tournaments.to_reserve')],
										'entered_from_queue'  => ['bg' => '#3b82f6', 'text' => '📋 ' . __('tournaments.from_queue')],
										'entered_from_feeder' => ['bg' => '#6366f1', 'text' => '⬆ ' . __('tournaments.from_feeder')],
										'manual_move'         => ['bg' => '#374151', 'text' => '✋ ' . __('tournaments.manual')],
										'declined_transfer'   => ['bg' => '#f59e0b', 'text' => '✕ ' . __('tournaments.declined')],
									];
									$badge = $actionBadges[$ph->action] ?? ['bg' => '#6b7280', 'text' => $ph->action];
									@endphp
									<span style="background:{{ $badge['bg'] }};color:#fff;padding:2px 8px;border-radius:12px;font-size:12px;white-space:nowrap">{{ $badge['text'] }}</span>
								</td>
								<td style="padding:6px 8px;white-space:nowrap">{{ $ph->fromDivision?->name ?? '—' }} → {{ $ph->toDivision?->name ?? __('tournaments.reserve') }}</td>
								<td style="padding:6px 8px">{{ $ph->initiated_by }}</td>
							</tr>
							@endforeach
							</tbody>
						</table>
					</div>
				</div>
				@endif

			</div>
		</div>

	</div>

	{{-- Модалка: вылет команды --}}
	<div id="relegate-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
		<div style="background:#fff;border-radius:16px;padding:2rem;width:90%;max-width:400px" class="body-dark-bg">
			<h4 class="mb-2">{{ __('tournaments.relegate_to') }}</h4>
			<p id="relegate-team-name" class="f-14 cd mb-2"></p>
			<form id="relegate-form" method="POST">
				@csrf
				<div class="d-flex" style="flex-direction:column;gap:8px">
					<button type="submit" name="target" value="reserve" class="btn btn-secondary">{{ __('tournaments.to_reserve') }}</button>
					@if($season->league && $season->league->hasFeeder())
					<button type="submit" name="target" value="feeder" class="btn btn-secondary">{{ __('tournaments.to_feeder_league') }} ({{ $season->league->feederLeague?->name }})</button>
					@endif
					@if($season->leagues->count() > 1)
					<button type="submit" name="target" value="lower_division" class="btn btn-secondary">{{ __('tournaments.to_lower_division') }}</button>
					@endif
				</div>
			</form>
			<button type="button" class="btn btn-secondary w-100 mt-2" onclick="document.getElementById('relegate-modal').style.display='none'">{{ __('seasons.btn_cancel') }}</button>
		</div>
	</div>

	{{-- Модалка: перевод в дивизион --}}
	<div id="transfer-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
		<div style="background:#fff;border-radius:16px;padding:2rem;width:90%;max-width:400px" class="body-dark-bg">
			<h4 class="mb-2">{{ __('tournaments.transfer_to_division') }}</h4>
			<p id="transfer-team-name" class="f-14 cd mb-2"></p>
			<form id="transfer-form" method="POST">
				@csrf
				<select name="to_division_id" class="mb-2" style="width:100%">
					@foreach($season->leagues as $divOpt)
					<option value="{{ $divOpt->id }}">{{ $divOpt->name }}</option>
					@endforeach
				</select>
				<button type="submit" class="btn w-100">{{ __('seasons.btn_save') }}</button>
			</form>
			<button type="button" class="btn btn-secondary w-100 mt-1" onclick="document.getElementById('transfer-modal').style.display='none'">{{ __('seasons.btn_cancel') }}</button>
		</div>
	</div>

	<x-slot name="script">
	<script>
	(function() {
		var relegateRoutes = @json($season->leagues->mapWithKeys(fn($l) => $l->activeTeams->mapWithKeys(fn($lt) => [$lt->id => route('seasons.teams.relegate', [$season, $lt])])->toArray())->toArray());
		var transferRoutes = @json($season->leagues->mapWithKeys(fn($l) => $l->activeTeams->mapWithKeys(fn($lt) => [$lt->id => route('seasons.teams.transfer', [$season, $lt])])->toArray())->toArray());

		window.showRelegateModal = function(ltId, name) {
			document.getElementById('relegate-team-name').textContent = name;
			document.getElementById('relegate-form').action = relegateRoutes[ltId] || '';
			var m = document.getElementById('relegate-modal');
			m.style.display = 'flex';
		};
		window.showTransferModal = function(ltId, name) {
			document.getElementById('transfer-team-name').textContent = name;
			document.getElementById('transfer-form').action = transferRoutes[ltId] || '';
			var m = document.getElementById('transfer-modal');
			m.style.display = 'flex';
		};

		// Закрытие по клику вне модалки
		['relegate-modal', 'transfer-modal'].forEach(function(id) {
			document.getElementById(id).addEventListener('click', function(e) {
				if (e.target === this) this.style.display = 'none';
			});
		});
	}());
	</script>
	</x-slot>
</x-voll-layout>
