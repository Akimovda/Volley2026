
<div class="ramka">
	
	<h2 class="-mt-05">Описание</h2>
	
				
				{{-- ТЕКСТ ОПИСАНИЯ --}}
				@if(!empty($event->description_html))
				<div class="mb-2">
					{!! $event->description_html !!}
				</div>
				@endif
				
				
				{{-- КРАТКАЯ СВОДКА СОБЫТИЯ --}}
				@php
				
				$dirLabel = match($event->direction) {
				'classic' => '🏐 Классика',
				'beach' => '🏖 Пляжка',
				default => '🏐 Волейбол'
				};
				
				$levels = [
				1=>'⚪️',2=>'🟡',3=>'🟠',
				4=>'🔵',5=>'🟣',6=>'🔴',7=>'⚫️'
				];
				
				@endphp
				

		<div class="row row2">
			<div class="col-md-6 mb-2">
				
				@if($event->organizer)
				
				<div class="d-flex">
					<span class="emo">🧑‍💼</span>
					<div>
						<div class="b-600"> Организатор</div>
						<a class="blink" href="{{ route('users.show', $event->organizer->id) }}">
							{{ $event->organizer->name ?? '—' }}
						</a>
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
						<div class="b-600">Тренер</div>
						
						
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
							<span class="b-600">⚔️ Направление:</span>
							<span>{{ $dirLabel }}</span>
						</div>
						
						
						@if($event->gameSettings->subtype)
						<div class="event-row">
							<span class="b-600">🏐 Формат:</span>
							<span>{{ $event->gameSettings->subtype }}</span>
						</div>
						@endif
						
						
						@if($event->gameSettings->min_players && $event->gameSettings->max_players)
						<div class="event-row">
							<span class="b-600">👥 Игроков:</span>
							<span>
								{{ $event->gameSettings->min_players }}
								–
								{{ $event->gameSettings->max_players }}
							</span>
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
						
						@if($levelMin && $levelMax)
						<div class="event-row">
							<span class="b-600">📈 Уровень:</span>
							<span>
								{{ $levelMin }} {{ $levels[$levelMin] ?? '' }}
								–
								{{ $levelMax }} {{ $levels[$levelMax] ?? '' }}
							</span>
						</div>
						@endif
						
						@endif
						
						
						{{-- ОПЛАТА --}}
						@if(!is_null($event->price_minor))
						<div class="event-row">
							<span class="b-600">💵 Оплата:</span>
							<span>{{ money_human($event->price_minor, $event->price_currency) }}</span>
						</div>
						@elseif(!empty($event->price_text))
						<div class="event-row">
							<span class="b-600">💵 Оплата:</span>
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
					
					$genderLabels = [
					'mixed_open'    => 'Микс',
					'mixed_limited' => 'Микс (ограничение)',
					'mixed_5050'    => 'Микс 50/50',
					'only_male'     => 'Только мужчины',
					'only_female'   => 'Только девушки',
					];
					
					$positionLabels = [
					'setter'   => 'Связующий',
					'outside'  => 'Доигровщик',
					'opposite' => 'Диагональный',
					'middle'   => 'Центральный',
					'libero'   => 'Либеро',
					];
					
					$limitedPositions = $event->gameSettings?->gender_limited_positions;
					
					if (is_string($limitedPositions)) {
					$limitedPositions = json_decode($limitedPositions, true) ?: [];
					}
					
					$limitedPositions = collect($limitedPositions ?: [])
					->map(fn ($p) => $positionLabels[$p] ?? $p)
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
							<span class="b-600">🚧 Ограничения:</span>
							
							
							
							<ul class="list mt-1">
								
								{{-- ВОЗРАСТ --}}
								
								@if(($event->age_policy ?? '') === 'child')
								<div class="text-muted small mt-1">
									👶 Для детей
									@if(!is_null($event->child_age_min) && !is_null($event->child_age_max))
									от {{ (int)$event->child_age_min }} до {{ (int)$event->child_age_max }} лет
									@endif
								</div>
                                @endif
								@if($effectiveAgePolicy === 'adult')
								<li>Только взрослые (18+)</li>
								@elseif($effectiveAgePolicy === 'child')
								<li>Только дети</li>
								@else
								<li>Без ограничений (по возрасту)</li>
								@endif
								
								
								{{-- ЛИБЕРО --}}
								@if($event->gameSettings?->libero_mode)
								<li>
									Либеро:
									<strong>
										{{ $event->gameSettings->libero_mode === 'with_libero' ? 'да' : 'нет' }}
									</strong>
								</li>
								@endif
								
								
								{{-- ПОЛИТИКА ПОЛА --}}
								
								
								@if($event->gameSettings?->gender_policy)
								<li>
									Пол:
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
								? '🙎‍♀️ Девушки'
								: '🙎‍♂️ Мужчины';
								@endphp
								
								<li>
									{{ $label }}:
									<strong>до {{ $limit }}</strong>
								</li>
								
								@endif
								
								{{-- ПОЗИЦИИ ОГРАНИЧЕНИЙ --}}
								@if(
								$event->gameSettings?->gender_policy === 'mixed_limited' &&
								!empty($limitedPositions)
								)
								<li>
									Позиции ограничений:
									{{ implode(', ', $limitedPositions) }}
								</li>
								@endif
								
								
								@if($event->is_private)
								<li>Приватное мероприятие</li>
								@endif
								
								@if($event->requires_personal_data)
								<li>Требуются персональные данные</li>
								@endif
								
							</ul>
						</div>	
					</div>				
					
					
				</div>
			</div>		
		</div>
		
		

		
		
		
		
		@endif
		
		
	</div>		