
<div class="ramka">
	
	<h2 class="-mt-05">{{ __('events.show_desc_h2') }}</h2>


				{{-- ТЕКСТ ОПИСАНИЯ --}}
				@if(!empty($event->description_html))
				<div class="mb-2">
					{!! $event->description_html !!}
				</div>
				@endif


				{{-- КРАТКАЯ СВОДКА СОБЫТИЯ --}}
				@php

				$dirLabel = match($event->direction) {
				'classic' => __('events.show_desc_dir_classic'),
				'beach' => __('events.show_desc_dir_beach'),
				default => __('events.show_desc_dir_default')
				};
				
				$levels = [
				1=>'⚪️',2=>'🟡',3=>'🟠',
				4=>'🔵',5=>'🟣',6=>'🔴',7=>'⚫️'
				];
				
				@endphp
				

		<div class="row row2">
			<div class="col-md-6 mb-2">
				
				@if($event->organizer)
@php
    $org = $event->organizer;
    $orgAvatar = $org->profile_photo_url;
    $orgSchool = \App\Models\VolleyballSchool::where('organizer_id', $org->id)->first();
@endphp
<div class="d-flex fvc gap-1 mb-1">
    <a href="{{ route('users.show', $org->id) }}">
        <img src="{{ $orgAvatar }}" alt="{{ $org->name }}" style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0;">
    </a>
    <div>
        <div class="f-13" style="opacity:.6">{{ __('events.show_desc_organizer') }}</div>
        <a class="blink b-600" href="{{ route('users.show', $org->id) }}">
            {{ trim(($org->last_name ?? '') . ' ' . ($org->first_name ?? $org->name)) }}
        </a>
        @if($orgSchool)
        <div class="f-13 mt-05">
            <a class="blink" href="{{ route('volleyball_school.show', $orgSchool->slug) }}">🏐 {{ $orgSchool->name }}</a>
        </div>
        @endif
    </div>
</div>	
				@if($event->organizer && $event->organizer->phone)
				<div class="d-flex mt-1">	
					<span class="emo">📞</span>
					<a class="blink" href="tel:{{ $event->organizer->phone }}">
						 {{ $event->organizer->formatted_phone }}
					</a>
				</div>	
				@endif
				
				@endif		
				
			</div>
			<div class="col-md-6 mb-2">
				
				{{-- ТРЕНЕР --}}
				@if($event->trainers && $event->trainers->count())
				
				<div class="d-flex">	
					<span class="emo">‍👨‍🏫‍</span>
					<div>
						<div class="b-600">{{ __('events.show_desc_coach') }}</div>
						
						
						@foreach($event->trainers as $trainer)
						<div>
							<a class="blink" href="{{ route('users.show', $trainer->id) }}">
								{{ $trainer->name ?? $trainer->nickname ?? '—' }}
							</a>
						</div>
						@endforeach
					</div>
				</div>
				@endif		
				
			</div>			
		</div>	
	
	
		
		
		<div class="row">
			<div class="col-md-6">
				<div class="card pt-1">
					
					@if($event->gameSettings)
					
					
					<div class="event-summary">
						
						<div class="event-row">
							<span class="b-600">{{ __('events.show_desc_direction_label') }}</span>
							<span>{{ $dirLabel }}</span>
						</div>
						
						
						@php
						// Для турниров схему берём из tournamentSetting (2x2, 3x3...),
						// для обычных мероприятий — из gameSettings.subtype (4x2, 5x1...)
						$formatLabel = ($event->format === 'tournament' && $event->tournamentSetting?->game_scheme)
							? $event->tournamentSetting->game_scheme
							: $event->gameSettings->subtype;
						@endphp
						@if($formatLabel)
						<div class="event-row">
							<span class="b-600">{{ __('events.show_desc_format_label') }}</span>
							<span>{{ $formatLabel }}</span>
						</div>
						@endif
						
						
						@if($event->gameSettings->min_players && $event->gameSettings->max_players)
						<div class="event-row">
							<span class="b-600">{{ __('events.show_desc_players_label') }}</span>
							<span>
								{{ $event->gameSettings->min_players }}
								–
								{{ $event->gameSettings->max_players }}
							</span>
						</div>
						@endif

						@if($event->format === 'tournament' && $event->tournament_teams_count)
						<div class="event-row">
							<span class="b-600">{{ __('events.show_desc_teams_label') }}</span>
							<span>{{ $event->tournament_teams_count }}</span>
						</div>
						@endif
						
						{{-- УРОВЕНЬ --}}
						@if(
						$event->classic_level_min || $event->classic_level_max ||
						$event->beach_level_min || $event->beach_level_max
						)
						
						@php
						
						$levelMin = $event->classic_level_min ?? $event->beach_level_min;
						$levelMax = $event->classic_level_max ?? $event->beach_level_max;
						
						@endphp
						
						@if($levelMin || $levelMax)
						<div class="event-row between" style="flex-wrap: wrap;gap:.5rem;">
							<span class="b-600">{{ __('events.show_desc_level_label') }}</span>
							<span>
								@if($levelMin)
								<span class="level-color-badge" style="color:{{ level_color((int)$levelMin) }};font-weight:700;">{{ level_name($levelMin) }}</span>
								@endif
								@if($levelMin && $levelMax && $levelMin != $levelMax)
								<span style="opacity:.5;"> – </span>
								<span class="level-color-badge" style="color:{{ level_color((int)$levelMax) }};font-weight:700;">{{ level_name($levelMax) }}</span>
								@endif
							</span>
						</div>
						@endif
						
						@endif
						
						
						{{-- ОПЛАТА --}}
						@if(!is_null($event->price_minor))
						<div class="event-row">
							<span class="b-600">{{ __('events.show_desc_payment_label') }}</span>
							<span>{{ money_human($event->price_minor, $event->price_currency) }}</span>
						</div>
						@elseif(!empty($event->price_text))
						<div class="event-row">
							<span class="b-600">{{ __('events.show_desc_payment_label') }}</span>
							<span>{{ $event->price_text }}</span>
						</div>
						@endif
						
					</div>
					
					@elseif($event->format === 'tournament' && $event->tournamentSetting)
					@php $tSetting = $event->tournamentSetting; @endphp

					<div class="event-summary">

					<div class="event-row">
					<span class="b-600">{{ __('events.show_desc_direction_label') }}</span>
					<span>{{ $dirLabel }}</span>
					</div>

					@if($tSetting->game_scheme)
					<div class="event-row">
					<span class="b-600">{{ __('events.show_desc_format_label') }}</span>
					<span>{{ $tSetting->game_scheme }}</span>
					</div>
					@endif

					@if($tSetting->team_size_min || $tSetting->team_size_max)
					<div class="event-row">
					<span class="b-600">{{ __('events.show_desc_lineup_label') }}</span>
					<span>{{ $tSetting->team_size_min ?? '?' }} – {{ $tSetting->team_size_max ?? '?' }}</span>
					</div>
					@endif

					@if($tSetting->teams_count)
					<div class="event-row">
					<span class="b-600">{{ __('events.show_desc_teams_label') }}</span>
					<span>{{ $tSetting->teams_count }}</span>
					</div>
					@endif

					@if($event->classic_level_min || $event->classic_level_max || $event->beach_level_min || $event->beach_level_max)
					@php
					$levelMin = $event->classic_level_min ?? $event->beach_level_min;
					$levelMax = $event->classic_level_max ?? $event->beach_level_max;
					@endphp
					@if($levelMin || $levelMax)
					<div class="event-row" style="flex-direction:column;gap:.5rem;">
					<span class="b-600">{{ __('events.show_desc_level_label') }}</span>
					<span>
					@if($levelMin)
					<span class="level-color-badge" style="color:{{ level_color((int)$levelMin) }};font-weight:700;">{{ level_name($levelMin) }}</span>
					@endif
					@if($levelMin && $levelMax && $levelMin != $levelMax)
					<span style="opacity:.5;"> – </span>
					<span class="level-color-badge" style="color:{{ level_color((int)$levelMax) }};font-weight:700;">{{ level_name($levelMax) }}</span>
					@endif
					</span>
					</div>
					@endif
					@endif

					@if(!is_null($event->price_minor))
					<div class="event-row">
					<span class="b-600">{{ __('events.show_desc_payment_label') }}</span>
					<span>{{ money_human($event->price_minor, $event->price_currency) }}</span>
					</div>
					@elseif(!empty($event->price_text))
					<div class="event-row">
					<span class="b-600">{{ __('events.show_desc_payment_label') }}</span>
					<span>{{ $event->price_text }}</span>
					</div>
					@endif

					</div>

					@endif

					
				</div>
			</div>
			<div class="col-md-6">
				<div class="card pb-05 pt-1">
					@php
					$effectiveAgePolicy = 'any';
					
					if (isset($occurrence) && !empty($occurrence->age_policy)) {
					$effectiveAgePolicy = (string) $occurrence->age_policy;
					} elseif (!empty($event->age_policy)) {
					$effectiveAgePolicy = (string) $event->age_policy;
					}
					
					$genderLabels = __('events.show_desc_genders');
					
					$positionLabels = [
					'setter'   => __('events.positions.setter'),
					'outside'  => __('events.positions.outside'),
					'opposite' => __('events.positions.opposite'),
					'middle'   => __('events.positions.middle_full'),
					'libero'   => __('events.positions.libero'),
					'reserve'  => __('events.positions.reserve'),
					];

					$limitedPositions = $event->gameSettings?->gender_limited_positions;

					if (is_string($limitedPositions)) {
					$limitedPositions = json_decode($limitedPositions, true) ?: [];
					}

					$limitedPositions = collect($limitedPositions ?: [])
					->map(fn ($p) => $positionLabels[$p] ?? position_name($p))
					->values()
					->all();
					@endphp
					
					{{-- БЛОК ОГРАНИЧЕНИЙ --}}
					@if(
					in_array($effectiveAgePolicy, ['adult', 'child'], true) ||
					$event->is_private ||
					$event->requires_personal_data ||
					($event->gameSettings && (
					$event->gameSettings->gender_policy ||
					$event->gameSettings->gender_limited_side ||
					$event->gameSettings->gender_limited_max
					))
					)
					
					
					
					
					<div class="event-summary">
						
						<div class="event-row d-block">
							<span class="b-600">{{ __('events.show_desc_restrictions_label') }}</span>
							
							
							
							<ul class="list mt-1">
								
								{{-- ВОЗРАСТ --}}
								
								@if(($event->age_policy ?? '') === 'child')
								<div class="text-muted small mt-1">
									{{ __('events.show_desc_for_kids') }}
									@if(!is_null($event->child_age_min) && !is_null($event->child_age_max))
									{{ __('events.show_desc_age_range', ['min' => (int)$event->child_age_min, 'max' => (int)$event->child_age_max]) }}
									@endif
								</div>
                                @endif
								@if($effectiveAgePolicy === 'adult')
								<li>{{ __('events.show_desc_only_adults') }}</li>
								@elseif($effectiveAgePolicy === 'child')
								<li>{{ __('events.show_desc_only_kids') }}</li>
								@else
								<li>{{ __('events.show_desc_no_age_limits') }}</li>
								@endif
								
								
								{{-- ЛИБЕРО --}}
								@if($event->gameSettings?->libero_mode)
								<li>
									{{ __('events.show_desc_libero') }}
									<strong>
										{{ $event->gameSettings->libero_mode === 'with_libero' ? __('events.show_desc_yes') : __('events.show_desc_no') }}
									</strong>
								</li>
								@endif
								
								
								{{-- ПОЛИТИКА ПОЛА --}}
								
								
								@if($event->gameSettings?->gender_policy)
								<li>
									{{ __('events.show_desc_gender_label') }}
									<strong>
										{{ $genderLabels[$event->gameSettings->gender_policy] ?? $event->gameSettings->gender_policy }}
									</strong>
								</li>
								@endif
								
								
								{{-- ЛИМИТ ПОЛА --}}
								@if(
								$event->gameSettings?->gender_policy === 'mixed_limited' &&
								$event->gameSettings?->gender_limited_side &&
								$event->gameSettings?->gender_limited_max
								)
								
								@php
								$side = $event->gameSettings->gender_limited_side;
								$limit = $event->gameSettings->gender_limited_max;
								
								$label = $side === 'female'
								? __('events.show_desc_girls')
								: __('events.show_desc_men');
								@endphp
								
								<li>
									{{ $label }}:
									<strong>{{ __('events.show_desc_gender_limit_to', ['limit' => $limit]) }}</strong>
								</li>

								@if($event->gameSettings?->gender_limited_reg_starts_days_before !== null)
									@php
										$regDays = (int) $event->gameSettings->gender_limited_reg_starts_days_before;
										$regLabel = $side === 'female' ? __('events.show_desc_girls') : __('events.show_desc_men');
									@endphp
									<li>
										{{ $regLabel }}: {{ __('events.show_desc_gender_reg_starts') }}
										<strong>{{ trans_choice('events.show_desc_days_before_start', $regDays, ['count' => $regDays]) }}</strong>
									</li>
								@endif
								
								@endif
								
								{{-- ПОЗИЦИИ ОГРАНИЧЕНИЙ --}}
								@if(
								$event->gameSettings?->gender_policy === 'mixed_limited' &&
								!empty($limitedPositions)
								)
								<li>
									{{ __('events.show_desc_positions_limited') }}
									{{ implode(', ', $limitedPositions) }}
								</li>
								@endif
								
								
								@if($event->is_private)
								<li>{{ __('events.show_desc_private') }}</li>
								@endif
								
								@if($event->requires_personal_data)
								<li>{{ __('events.show_desc_personal_data') }}</li>
								@endif
								
							</ul>
						</div>	
					</div>				
					
					
				</div>
			</div>		
		</div>
		
		

		
		
		
		
		@endif
		
		
	</div>		