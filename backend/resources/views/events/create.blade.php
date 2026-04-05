{{-- resources/views/events/create.blade.php --}}
@php
$prefill = $prefill ?? [];
$volleyballConfig = config('volleyball');

$tournamentGameScheme = old('tournament_game_scheme', $prefill['tournament_game_scheme'] ?? '');
$tournamentTeamSizeMin = old('tournament_team_size_min', $prefill['tournament_team_size_min'] ?? '');
$tournamentReservePlayersMax = old('tournament_reserve_players_max', $prefill['tournament_reserve_players_max'] ?? '');
$tournamentTotalPlayersMax = old('tournament_total_players_max', $prefill['tournament_total_players_max'] ?? '');
$tournamentRequireLibero = old('tournament_require_libero', $prefill['tournament_require_libero'] ?? false);
$tournamentMaxRatingSum = old('tournament_max_rating_sum', $prefill['tournament_max_rating_sum'] ?? '');
$tournamentCaptainConfirmsMembers = old('tournament_captain_confirms_members', $prefill['tournament_captain_confirms_members'] ?? true);
$tournamentAutoSubmitWhenReady = old('tournament_auto_submit_when_ready', $prefill['tournament_auto_submit_when_ready'] ?? false);
$tournamentSeedingMode = old('tournament_seeding_mode', $prefill['tournament_seeding_mode'] ?? 'manual');
@endphp
@php
$registrationMode = old('registration_mode', $prefill['registration_mode'] ?? 'single');

$registrationModeLabels = [
'classic' => [
'single' => 'Одиночная запись игроков',
'team' => 'Командная запись',
],
'beach' => [
'single' => 'Одиночная запись игроков',
'mixed_group' => 'Групповая / смешанная запись',
],
];
@endphp
@php

$formats = [
'game' => 'Игра',
'training' => 'Тренировка',
'training_game' => 'Тренировка + Игра',
'coach_student' => 'Тренер + ученик (только пляж)',
'tournament' => 'Турнир',
'camp' => 'КЕМП',
];
// ✅ Timezones groups приходят из контроллера: $tzGroups + $tzDefault
$timezoneGroups  = $tzGroups ?? (array) config('event_timezones.groups', []);
$timezoneDefault = !empty($prefill['timezone'])
? (string) $prefill['timezone']
: (string) ($tzDefault ?? 'Europe/Moscow');

$currentTimezone = (string) old('timezone', $timezoneDefault);

$isAdmin = (auth()->user()?->role ?? null) === 'admin';

$step1Fields = [
'organizer_id',
'title','direction','format','registration_mode',
'tournament_game_scheme',
'tournament_team_size_min',
'tournament_reserve_players_max',
'tournament_total_players_max',
'tournament_require_libero',
'tournament_max_rating_sum',
'tournament_captain_confirms_members',
'tournament_auto_submit_when_ready',
'tournament_seeding_mode',
// ✅ trainer
'trainer_user_ids',      // новое
'trainer_user_id',       // legacy оставить
'trainer_user_label',    // чтобы ошибки лейбла тоже попадали в шаг 1 (если будут)

'game_subtype','game_min_players','game_max_players',
'game_libero_mode',
'game_gender_policy','game_gender_limited_side','game_gender_limited_max','game_gender_limited_positions',
'classic_level_min','classic_level_max',
'beach_level_min','beach_level_max',
'allow_registration',
'age_policy','is_snow',
];
// ✅ Step 2 fields (including registration timings)
$step2Fields = [
'timezone','starts_at_local','ends_at_local','location_id',
'is_recurring','recurrence_type','recurrence_interval','recurrence_months','recurrence_rule',
'reg_starts_days_before','reg_ends_minutes_before','cancel_lock_minutes_before',
];

$step3Fields = [
'is_private',
'is_paid',
'price_amount',
'price_currency',
'requires_personal_data',
'remind_registration_enabled',
'remind_registration_minutes_before',
'show_participants',
'cover_upload',
'cover_media_id',
'description_html',
];


// --- wizard initial step (server-side) ---
$initialStep = (int) request()->query('step', session('wizard_initial_step', 0));
// helper: ловим и обычные ошибки, и ошибки массива вида field.0
$hasErr = function (string $field) use ($errors): bool {
return $errors->has($field) || $errors->has($field . '.*');
};

if ($initialStep < 1 || $initialStep > 3) {
	$initialStep = 1;
	
	if ($errors->any()) {
	foreach ($step3Fields as $f) { if ($hasErr($f)) { $initialStep = 3; break; } }
	
	if ($initialStep === 1) {
	foreach ($step2Fields as $f) { if ($hasErr($f)) { $initialStep = 2; break; } }
	}
	} else {
	// fallback по old()
	if (
	old('timezone') || old('starts_at_local') || old('location_id') ||
	old('is_recurring') || old('recurrence_type') || old('recurrence_interval') || old('recurrence_months') ||
	old('reg_starts_days_before') || old('reg_ends_minutes_before') || old('cancel_lock_minutes_before')
	) {
	$initialStep = 2;
	} elseif (
	old('is_private') || old('is_paid') ||
	old('requires_personal_data') ||
	old('cover_media_id') ||
	old('remind_registration_enabled') ||
	old('remind_registration_minutes_before') ||
	old('show_participants') ||
	old('description_html') // ✅ чтобы оставаться на шаге 3
	) {
	$initialStep = 3;
	}
	}
    }
	
    // --- other precomputed helpers ---
    $monthsMap = [
	1=>'Янв',2=>'Фев',3=>'Мар',4=>'Апр',5=>'Май',6=>'Июн',
	7=>'Июл',8=>'Авг',9=>'Сен',10=>'Окт',11=>'Ноя',12=>'Дек'
    ];
	
    $oldMonths = old('recurrence_months', $prefill['recurrence_months'] ?? []);
    if (is_string($oldMonths)) $oldMonths = [$oldMonths];
    if (!is_array($oldMonths)) $oldMonths = [];
    $oldMonths = array_map('intval', $oldMonths);
	
    // ✅ Prefill trainers (multi + legacy fallback)
    $oldTrainerIds = old('trainer_user_ids', $prefill['trainer_user_ids'] ?? []);
    if (is_string($oldTrainerIds)) $oldTrainerIds = [$oldTrainerIds];
    if (!is_array($oldTrainerIds)) $oldTrainerIds = [];
    $oldTrainerIds = array_values(array_filter(array_unique(array_map('intval', $oldTrainerIds)), fn($id) => $id > 0));
    // ✅ Prefill tозрастные ограничения
    $oldAgePolicy = (string) old('age_policy', $prefill['age_policy'] ?? 'adult');
    if (!in_array($oldAgePolicy, ['adult','child','any'], true)) $oldAgePolicy = 'any';
	
    
    // legacy fallback: если пришёл один trainer_user_id
    $legacyOne = (int) old('trainer_user_id', $prefill['trainer_user_id'] ?? 0);
    if ($legacyOne > 0 && !in_array($legacyOne, $oldTrainerIds, true)) {
	$oldTrainerIds[] = $legacyOne;
    }
    
    // label для инпута (теперь контроллер отдаёт trainerPrefillLabel)
    $oldTrainerLabel = (string) old('trainer_user_label', $trainerPrefillLabel ?? ($prefill['trainer_user_label'] ?? ''));
	
	
    // ✅ registration offsets defaults
    $oldRegStartsDaysBefore = (int) old('reg_starts_days_before', 3);
    $oldRegEndsMinutesBefore = (int) old('reg_ends_minutes_before', 15);
    $oldCancelLockMinutesBefore = (int) old('cancel_lock_minutes_before', 60);
	
    if ($oldRegStartsDaysBefore < 0) $oldRegStartsDaysBefore = 3;
    if ($oldRegEndsMinutesBefore < 0) $oldRegEndsMinutesBefore = 15;
    if ($oldCancelLockMinutesBefore < 0) $oldCancelLockMinutesBefore = 60;
	@endphp
	
	
	
	
	<x-voll-layout body_class="create-blade"> 
		
		
		
		<x-slot name="title">
			Создание мероприятия
		</x-slot>
		
		<x-slot name="description">
			Создание мероприятия
		</x-slot>
		
		<x-slot name="canonical">
			{{-- Ссылка страницы в тег canonical, например --}} 
			{{ route('users.index') }}
		</x-slot>
		
		<x-slot name="h1">
			Создание мероприятия
		</x-slot>
		
		
		
		<x-slot name="t_description">
			<h2 class="-mt-1">Шаг <span id="wizard_step_num">1</span> из 3</h2>	
			<div class="mt-1">
				<div class="wizard-pill" id="pill_1">Настройка мероприятия</div>
				<div class="wizard-pill" id="pill_2">Выбор локации,времени и ограничений записи</div>
				<div class="wizard-pill" id="pill_3">Доступность, описание и др.</div>			
			</div>
		</x-slot>
		
		<x-slot name="breadcrumbs">
			<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
				<a href="{{ route('events.create') }}" itemprop="item"><span itemprop="name">Создание мероприятия</span></a>
				<meta itemprop="position" content="2">
			</li>
		</x-slot>	
		
		<x-slot name="d_description">
			<div class="wizard-wrap">
				<div class="wizard-line">
					<div id="wizard_bar" style="width: 33%;"></div>
				</div>
				<span id="wizard_percent" class="cd b-600">33%</span>
			</div>	
		</x-slot>
		
		<x-slot name="style">
			<link href="/assets/org.css" rel="stylesheet">
			<style>
				.wizard-wrap {
				padding: 2rem 0 0;
				position: relative;
				}
				#wizard_percent {
				display: flex;
				flex-flow: column;
				align-items: center;
				justify-content: center;
				height: 5rem;
				width: 5rem;
				top: 0;
				border-radius: 50%; 
				right: 0;
				background: rgba(255,255,255,0.8);
				box-shadow: 0 1rem 2.2rem rgba(0, 0, 0, 0.05), 0 0.5rem 1.2rem rgba(0, 0, 0, 0.03);
				position: absolute;
				}
				body.dark #wizard_percent {	
				background: #222333;
				}
				.progress-pill { background: rgba(17,24,39,0.06); border: 1px solid rgba(17,24,39,0.08); }
				.pill.is-active { border-color: rgba(59,130,246,0.55) !important; background: rgba(59,130,246,0.06); color: #111827; }
				.pill.is-done { border-color: rgba(16,185,129,0.45) !important; background: rgba(16,185,129,0.06); color: #065f46; }
				.ac-box { position: relative; }
				.ac-dd { position:absolute; left:0; right:0; top: calc(100% + 6px); z-index:50; background:#fff; border:1px solid rgba(17,24,39,0.12); border-radius:12px; overflow:hidden; box-shadow: 0 12px 28px rgba(15,23,42,0.10); display:none; }
				.ac-item { padding:10px 12px; cursor:pointer; display:flex; justify-content:space-between; gap:10px; }
				.ac-item:hover { background: rgba(59,130,246,0.06); }
				.ac-meta { font-size:12px; color:#6b7280; }
				
				#wizard_bar {
				transition: width .25s ease, background-color .25s ease;
				height: 1rem;
				border-radius: 1rem;
				}			
				.wizard-line {
				background: rgba(255,255,255,0.8);
				width: 100%;
				height: 1rem;
				border-radius: 1rem;
				}
				body.dark .wizard-line {
				background: #222333;
				}				
				.trainer-chip-remove {
				width: 2rem;
				height: 2rem;
				padding: 0;
				display: flex;
				flex-flow: column;
				align-items: center;
				justify-content: center;				
				}
				.location_preview {
				display: flex;
				flex-flow: column;
				align-items: center;
				justify-content: center;	
				aspect-ratio: 16 / 11;
				}
				.location_preview img {
				object-fit: cover;
				width: 100%;
				height: 100%;
				}
				.location_preview svg {
				fill: #2967BA;
				width: 6rem;
				height: 6rem;
				}	
				body.dark .location_preview svg {
				fill: #E7612F;
				}
				
				.wizard-pill.is-active {
				color: #2967BA;
				}
				body.dark .wizard-pill.is-active {
				color: #E7612F;
				}				
			</style>			
		</x-slot>			
		
		
		<div class="container">
			
			{{-- FLASH --}}
			@if (session('private_link'))
			
			<div class="ramka">	
				<div class="alert alert-info">
					<div class="b-600">Ссылка на приватное мероприятие:</div>
					<div class="mt-1">
						<a class="text-blue-700 underline break-all" href="{{ session('private_link') }}">
							{{ session('private_link') }}
						</a>
					</div>					
				</div>
			</div>
			@endif
			
			{{-- SUCCESS --}}
			@if (session('status'))
			<div class="ramka">	
				<div class="alert alert-success">
					{{ session('status') }}
				</div>
			</div>
			@endif
			
			{{-- ERROR --}}
			@if (session('error'))
			<div class="ramka">		
				<div class="alert alert-error">
					{{ session('error') }}
				</div>
			</div>
			@endif
			
			{{-- VALIDATION ERRORS --}}
			@if ($errors->any())
			<div class="ramka">		
				<div class="alert alert-error">
					<div class="alert-title">Проверьте поля</div>
					<ul class="list">
						@foreach ($errors->all() as $err)
						<li>{{ $err }}</li>
						@endforeach
					</ul>
				</div>
			</div>				
			@endif
			@if (session('wizard_errors'))
			<div class="ramka">
				<div class="alert alert-error">
					<div class="alert-title">Проверьте поля</div>
					<ul class="list">
						@foreach (session('wizard_errors') as $field => $messages)
						@foreach ((array) $messages as $msg)
						<li><strong>{{ $field }}:</strong> {{ $msg }}</li>
						@endforeach
						@endforeach
					</ul>
				</div>
			</div>
            @endif
			
			<div class="form">					
				<form 
				method="POST"
				action="{{ route('events.store') }}"
				data-initial-step="{{ $initialStep }}"
				data-admin-id="{{ auth()->id() }}"
				data-organizer-city-id="{{ auth()->user()->city_id ?? '' }}"
				data-users-search-url="{{ route('api.users.search') }}"
				data-has-errors="{{ $errors->any() ? '1' : '0' }}"
				enctype="multipart/form-data"
                >
					@csrf
					<input type="hidden" name="wizard_step" id="wizard_step" value="{{ $initialStep }}">
					{{-- ✅ city-first --}}
					<input type="hidden" name="city_id" id="event_city_id" value="{{ auth()->user()->city ? auth()->user()->city->id : '' }}">
					
					{{-- ✅ timezone пусть отправляется всегда (даже если select будет disabled) --}}
					<input type="hidden" name="timezone" id="event_timezone_hidden" value="{{ auth()->user()->city->timezone ?? '' }}">
					{{-- STEP 1 --}}
					<div data-step="1" class="wizard-step step-shell">
						
						{{-- Admin organizer --}}
						@if(!empty($canChooseOrganizer))
						<div class="ramka" style="z-index:10">	
							<h2 class="-mt-05">Назначение организатора</h2>	
							<div class="row">	
								<div class="col-md-6">
									<div class="card">
										<select name="organizer_id">
											<option value="" selected>— выбрать организатора —</option>
											@foreach($organizers as $org)
											<option value="{{ $org->id }}"
											@selected(old('organizer_id', $prefill['organizer_id'] ?? '') == $org->id)>
												#{{ $org->id }} — {{ $org->name ?? $org->email }}
											</option>
											@endforeach
										</select>
										<ul class="list f-16 mt-1">
											<li>Можно не выбирать — тогда организатором станет текущий admin.</li>
										</ul>	
									</div>
								</div>
							</div>
						</div>
						@else
						{{--
						{{ $resolvedOrganizerLabel ?? '—' }}
						--}}
						@endif					
						
						
						
						<div class="row row2">	
							<div class="col-md-12">
								<div class="ramka" style="z-index: 9">
									<h2 class="-mt-05">Настройка мероприятия</h2>	
									<div class="row">
										
										<div class="col-md-6">
											<div class="card pb-2">
												<label>Направление</label>
												<select name="direction" id="direction">
													<option value="classic" @selected(old('direction', $prefill['direction'] ?? 'classic')==='classic')>Классический волейбол</option>
													<option value="beach" @selected(old('direction', $prefill['direction'] ?? '')==='beach')>Пляжный волейбол</option>
												</select>
												@error('direction')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
												@enderror
												
												
												<div class="mt-2">
													<label>Название мероприятия</label>
													<input type="text"
													name="title"
													value="{{ old('title', $prefill['title'] ?? '') }}"
													class="w-full rounded-lg border-gray-200"
													placeholder="Напр. Вечерняя игра 6х6">
													@error('title')
													<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
													@enderror
													@php
													$agePolicy = (string) old('age_policy', $prefill['age_policy'] ?? 'adult');
													@endphp													
												</div>
											</div>
										</div>							
										
										<div class="col-md-6">
											<div class="card">
												<label>Тип мероприятия</label>
												<select name="format" id="format" class="w-full rounded-lg border-gray-200">
													@foreach($formats as $k => $label)
													<option value="{{ $k }}" @selected(old('format', $prefill['format'] ?? 'game')===$k)>{{ $label }}</option>
													@endforeach
												</select>
												@error('format')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
												@enderror
												<div class="pb-05"></div>
												{{--
												<div class="text-xs text-gray-500 mt-1">
													“Тренер + ученик” доступен только при “Пляжный волейбол”.
												</div>
												--}}
												
												
												{{-- ✅ Климатические условия только для пляжа + "Игра" --}}
												<div id="climate_block" class="mt-1" data-show-if="direction=beach,format=game">
													<label>Климатические условия</label>
													
													<label class="checkbox-item" id="is_snow_wrap">
														<input type="hidden" name="is_snow" value="0">
														<input type="checkbox" name="is_snow" value="1" id="is_snow"
														@checked(old('is_snow', $prefill['is_snow'] ?? false))>
														<div class="custom-checkbox"></div>
														<span>Снег / зима</span>
													</label>
												</div>											
												
												
												{{-- ✅ TRAINER (только training/training_game) --}}
												@php
												$fmt0 = (string)old('format', $prefill['format'] ?? 'game');
												$showTrainer0 = in_array($fmt0, ['training','training_game','training_pro_am','camp','coach_student'], true);
												@endphp
												<div class="mt-1" id="trainer_block" data-show-if="format=training|training_game|camp|coach_student">
													
													<label>Тренеры</label>
													
													<div class="ac-box">
														{{-- chips --}}
														<div id="trainer_chips">
															@foreach($oldTrainerIds as $tid)
															<div class="d-flex fvc mb-1 between f-16 pl-1 pr-1">
																<span>#{{ (int)$tid }}</span>
																<button type="button" class="btn btn-small btn-secondary trainer-chip-remove" data-id="{{ (int)$tid }}">×</button>
															</div>
															<input type="hidden" name="trainer_user_ids[]" value="{{ (int)$tid }}" data-trainer-hidden="{{ (int)$tid }}">
															@endforeach
														</div>
														
														<input type="text"
														id="trainer_search"
														placeholder="Начни вводить имя или фамилию"
														value=""
														autocomplete="off">
														
														{{-- legacy hidden (первый тренер, чтобы старые места не ломались) --}}
														<input type="hidden" name="trainer_user_id" id="trainer_user_id_legacy" value="{{ $oldTrainerIds[0] ?? '' }}">
														<input type="hidden" name="trainer_user_label" id="trainer_user_label" value="{{ e($oldTrainerLabel) }}">
														
														<div id="trainer_dd" class="form-select-dropdown trainer_dd"></div>
													</div>
													
													<ul class="list f-16 mt-1">
														<li>Можно выбрать несколько тренеров.</li>
														<li><a onclick="return false;" href="#" type="button" id="trainer_clear" class="f-16 blink">Сбросить</a></li>
													</ul>										
													
													{{--
													<div class="text-xs text-gray-500 mt-1">
														Поле показывается только для “Тренировка”, “Тренировка + Игра”, “Тренер + ученик”, “Кемп”.
													</div>
													--}}
												</div>											
											</div>
										</div>											
										
										
										
										<div class="col-md-6" id="game_settings_block" data-hide-if="format=tournament">
											<div class="card">
												<div class="row">
													<div class="col-4">
														
														<label>Подтип</label>
														<select name="game_subtype" id="game_subtype" class="w-full rounded-lg border-gray-200">
															<!-- <option value="">— выбрать —</option> -->
															<option value="4x4" @selected(old('game_subtype', $prefill['game_subtype'] ?? '')==='4x4')>4×4</option>
															<option value="4x2" @selected(old('game_subtype', $prefill['game_subtype'] ?? '4x2')==='4x2')>4×2</option>
															<option value="5x1" @selected(old('game_subtype', $prefill['game_subtype'] ?? '')==='5x1')>5×1</option>
														</select>										
														@error('game_subtype')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
														@enderror
													</div>
													<div class="col-4">	
														<label>Команды</label>
														
														<input
														type="number"
														name="teams_count"
														id="teams_count"
														class="form-control"
														value="{{ old('teams_count', 2) }}"
														min="2"
														max="200"
														>		
														
													</div>
													
													
													<div class="col-4">
														<label>Минимум</label>
														<input type="number"
														name="game_min_players"
														id="game_min_players"
														min="0" max="99"
														value="{{ old('game_min_players', $prefill['game_min_players'] ?? 8) }}"
														class="w-full rounded-lg border-gray-200"
														placeholder="">
														@error('game_min_players')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
														@enderror
														<div id="game_min_hint" class="text-xs text-gray-500 mt-1" style="display:none;"></div>
													</div>
													
													
													<div class="hidden">
														<label class="block text-xs font-semibold text-gray-600 mb-1">До (max)</label>
														<input type="number"
														name="game_max_players"
														id="game_max_players"
														min="1" max="99"
														value="{{ old('game_max_players', $prefill['game_max_players'] ?? '') }}"
														class="w-full rounded-lg border-gray-200"
														placeholder="например 12">
														<div id="game_max_hint" class="text-xs text-gray-500 mt-1" style="display:none;"></div>
													</div>
												</div>
												
												{{-- libero_mode --}}
												<div id="libero_mode_block" class="mt-1" data-show-if="direction=classic,game_subtype=5x1">
													<label class="pt-05">Режим либеро</label>
													<select name="game_libero_mode" id="game_libero_mode" class="w-full rounded-lg border-gray-200">
														<option value="with_libero" @selected(old('game_libero_mode', $prefill['game_libero_mode'] ?? 'with_libero')==='with_libero')>С либеро (отдельная позиция)</option>
														<option value="without_libero" @selected(old('game_libero_mode', $prefill['game_libero_mode'] ?? '')==='without_libero')>Без либеро</option>
													</select>
													
												</div>											
												<ul class="list f-16 mt-1">
													<li>Максимум <strong class="cd" id="players_preview">0</strong></li>
													<li>Позиции для записи будут расчитано автоматически.</li>
													<li>Если на мероприятие запишется меньше минимума игроков, оно будет автоматически отменено</li>
												</ul>						
											</div>
										</div>										
										
										
										
										<div class="col-md-6" id="registration_mode_block" data-hide-if="format=tournament">
											<div class="card">
												<label>Режим регистрации</label>
												
												<select name="registration_mode" id="registration_mode" class="w-full rounded-lg border-gray-300">
													<option value="single"
													@selected($registrationMode === 'single')
													data-direction="classic beach">
														Одиночная запись игроков
													</option>
													
													<option value="team"
													@selected($registrationMode === 'team')
													data-direction="classic">
														Командная запись
													</option>
													
													<option value="mixed_group"
													@selected($registrationMode === 'mixed_group')
													data-direction="beach">
														Групповая / смешанная запись
													</option>
												</select>
												@error('registration_mode')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
												@enderror
												<ul class="list f-16 mt-1">
													<li id="registration_mode_hint_classic" data-show-if="direction=classic">
														Для классики: либо одиночная запись игроков, либо полноценная командная запись.
													</li>
													<li id="registration_mode_hint_beach" data-show-if="direction=beach">
														Для пляжа: можно либо записываться по одному, либо объединять игроков в группы по подтипу игры.
													</li>
												</ul>
											</div>
										</div>
									</div>
								</div>
								
								<div class="ramka" id="tournament_settings_block" data-show-if="format=tournament">
									<h2 class="-mt-05">Настройки турнира</h2>	
									<div class="row">
										<div class="col-md-4">
                                            <div class="card">
												<label>Схема игры</label>
												<select
												name="tournament_game_scheme"
												id="tournament_game_scheme"
												class="w-full rounded-lg border-gray-200"
												>
													<option value="">— выбрать —</option>
													<option value="2x2" @selected((string)$tournamentGameScheme === '2x2')>2x2</option>
													<option value="3x3" @selected((string)$tournamentGameScheme === '3x3')>3x3</option>
													<option value="4x4" @selected((string)$tournamentGameScheme === '4x4')>4x4</option>
													<option value="4x2" @selected((string)$tournamentGameScheme === '4x2')>4x2</option>
													<option value="5x1" @selected((string)$tournamentGameScheme === '5x1')>5x1</option>
													<option value="5x1_libero" @selected((string)$tournamentGameScheme === '5x1_libero')>5x1 с либеро</option>
												</select>
												@error('tournament_game_scheme')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
												@enderror
												
                                                <div class="mt-2">
                                                    <label for="tournament_teams_count">Кол-во команд в турнире</label>
                                                    <input
													type="number"
													id="tournament_teams_count"
													name="tournament_teams_count"
													min="3"
													max="100"
													step="1"
													value="{{ old('tournament_teams_count', $tournamentTeamsCount ?? 4) }}"
													class="w-full rounded-lg border-gray-200"
                                                    >
													
													<ul class="list f-16 mt-1">
														<li>От 3 до 100, по умолчанию 4.</li>
													</ul>													
													
                                                    @error('tournament_teams_count')
													<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                    @enderror
												</div>										
											</div>
										</div>	
										
										
										
										<div class="col-md-8">
                                            <div class="card">												
												
												<label for="tournament_team_size_min">Настройка состава команды</label>
												<hr class="mb-1">
												<div class="row">
													
													<div class="col-sm-4">
														<label class="b-500">Основной состав</label>
                                                        <input
														type="number"
														name="tournament_team_size_min"
														id="tournament_team_size_min"
														min="1"
														max="50"
														value="{{ $tournamentTeamSizeMin }}"
														class="w-full rounded-lg border-gray-200"
														readonly
                                                        >
														<ul class="list f-16 mt-1">
															<li>Определяется автоматически по выбранной схеме игры.</li>
														</ul>	
                                                        @error('tournament_team_size_min')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                        @enderror
													</div>
													
                                                    <div class="col-sm-4">
														<label class="b-500">Макс. запасных игроков</label>
                                                        <input
														type="number"
														name="tournament_reserve_players_max"
														id="tournament_reserve_players_max"
														min="0"
														max="20"
														value="{{ $tournamentReservePlayersMax }}"
														class="w-full rounded-lg border-gray-200"
                                                        >
														<ul class="list f-16 mt-1">
															<li>Сколько запасных сверх основного состава можно заявить.</li>
														</ul>														
                                                        @error('tournament_reserve_players_max')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                        @enderror
													</div>
													
													<div class="col-sm-4">
                                                        <label class="b-500">Макс. всего игроков</label>
                                                        <input
														type="number"
														name="tournament_total_players_max"
														id="tournament_total_players_max"
														min="1"
														max="50"
														value="{{ $tournamentTotalPlayersMax }}"
														class="w-full rounded-lg border-gray-200"
														readonly
                                                        >
														<ul class="list f-16 mt-1">
															<li>Основной состав + максимум запасных.</li>
														</ul>														
                                                        @error('tournament_total_players_max')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                        @enderror
													</div>
												</div>
												
                                                <div
												class="mt-3"
												id="tournament_rating_sum_wrap"
												data-show-if="direction=beach,format=tournament"
                                                >
                                                    <label>Лимит суммы рейтинга</label>
                                                    <input
													type="number"
													name="tournament_max_rating_sum"
													id="tournament_max_rating_sum"
													min="0"
													max="100000"
													value="{{ $tournamentMaxRatingSum }}"
													class="w-full rounded-lg border-gray-200"
													placeholder="Например 12"
                                                    >
                                                    @error('tournament_max_rating_sum')
													<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                    @enderror
												</div>
											</div>
										</div>
										<div class="col-md-4">
											<div class="card">
												<label>Уведомления</label>
												<label class="checkbox-item">
													<input type="hidden" name="tournament_captain_confirms_members" value="0">
													<input
													type="checkbox"
													name="tournament_captain_confirms_members"
													value="1"
													@checked($tournamentCaptainConfirmsMembers)
													>
													<div class="custom-checkbox"></div>
													<span>Капитан подтверждает участников</span>
												</label>
												
												<label class="checkbox-item">
													<input type="hidden" name="tournament_auto_submit_when_ready" value="0">
													<input
													type="checkbox"
													name="tournament_auto_submit_when_ready"
													value="1"
													@checked($tournamentAutoSubmitWhenReady)
													>
													<div class="custom-checkbox"></div>
													<span>Автоматически подавать заявку, когда состав готов</span>
												</label>
											</div>
										</div>
										<div class="col-md-8">
											<div class="card">
												
												<label>Посев</label>
												
												<div class="row">
													<div class="col-md-4">
														
														<select
														name="tournament_seeding_mode"
														id="tournament_seeding_mode"
														class="w-full rounded-lg border-gray-200"
														>
															<option value="manual" @selected((string)$tournamentSeedingMode === 'manual')>Ручной</option>
															<option value="random" @selected((string)$tournamentSeedingMode === 'random')>Случайный</option>
															<option value="rating" @selected((string)$tournamentSeedingMode === 'rating')>По рейтингу</option>
														</select>
														@error('tournament_seeding_mode')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
														@enderror
														
													</div>
													<div class="col-md-8">
														
														<ul class="list f-16">
															<li>Состав команды определяется выбранной схемой игры.</li>
															<li>Макс. всего игроков считается автоматически: основной состав + запасные.</li>
															<li>Для пляжных турниров можно дополнительно ограничить сумму рейтинга.</li>
														</ul>
													</div>
												</div>
											</div>
										</div>
									</div>	
								</div>
							</div>
							<div class="col-md-12">
								<div class="ramka" style="z-index: 8">
									<h2 class="-mt-05">Ограничения</h2>	
									<div class="row">
										
										<div class="col-md-6" id="age_policy_block">
											<div class="card">
												<label>Возрастные ограничения</label>
												
												<label class="radio-item">
													<input checked type="radio" name="age_policy" value="adult">
													<div class="custom-radio"></div>
													<span>Для взрослых</span>
												</label>
												
												<label class="radio-item">
													<input type="radio" name="age_policy" value="child">
													<div class="custom-radio"></div>
													<span>Для детей</span>
												</label>
												
												<div id="child_age_wrap" class="{{ old('age_policy', $prefill['age_policy'] ?? 'adult') === 'child' ? '' : 'hidden' }}">
													<div class="row mt-1">
														<div class="col-md-6">
															<label class="form-label">Возраст от</label>
															<input
															type="number"
															name="child_age_min"
															class="form-input"
															min="6"
															max="17"
															step="1"
															value="{{ old('child_age_min', $prefill['child_age_min'] ?? 6) }}"
															placeholder="Например: 8"
															>
															@error('child_age_min')
															<div class="text-danger small mt-1">{{ $message }}</div>
															@enderror
														</div>
														
														<div class="col-md-6">
															<label class="form-label">Возраст до</label>
															<input
															type="number"
															name="child_age_max"
															class="form-input"
															min="6"
															max="17"
															step="1"
															value="{{ old('child_age_max', $prefill['child_age_max'] ?? 17) }}"
															placeholder="Например: 14"
															>
															@error('child_age_max')
															<div class="text-danger small mt-1">{{ $message }}</div>
															@enderror
														</div>
													</div>
													
													<ul class="list f-16 mt-1 mb-2">
														<li>Допустимый возраст участников: от 6 до 17 лет.</li>
													</ul>											
													
												</div>												
												
												
												
												<label class="radio-item">
													<input type="radio" name="age_policy" value="any">
													<div class="custom-radio"></div>
													<span>Без ограничений</span>
												</label>
												
											</div>	
										</div>
										
										
										
										{{-- Game config --}}
										<div class="col-md-6">
											<div class="card">
												{{--
												<div class="text-sm font-semibold text-gray-800">Игровые настройки</div>
												<div class="text-xs text-gray-500 mt-1" id="game_defaults_hint">
													Количество игроков рассчитывается автоматически на основе выбранного формата команды.
												</div>
												
												<div class="text-xs text-gray-500 mt-1" id="game_players_hint"></div>
												<div class="text-xs text-gray-500 mt-1">
													Доступно для классического волейбола при подтипе 5×1.
												</div>
												--}}	
												
												
												
												{{-- Gender policy --}}
												{{--
												<div class="text-sm font-semibold text-gray-800">Гендерные ограничения</div>
												
												<div class="text-xs text-gray-500 mt-1">
													Лимит по мероприятию главный: <span class="font-semibold">max_players</span>. Гендерные лимиты — дополнительные.
												</div>
												--}}
												
												
												<label>Гендерные ограничения</label>
												<select name="game_gender_policy" id="game_gender_policy" class="w-full rounded-lg border-gray-200">
													<option value="mixed_open" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? 'mixed_open')==='mixed_open')>
														М/Ж (без ограничений)
													</option>
													{{-- ✅ 50/50 (ТОЛЬКО ДЛЯ BEACH, но можно показывать всегда и скрывать JS-ом) --}}
													<option value="mixed_5050" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='mixed_5050')>
														Микс 50/50
													</option>
													
													<option value="only_male" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='only_male')>Только М</option>
													<option value="only_female" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='only_female')>Только Ж</option>
													{{-- ✅ limited имеет смысл ТОЛЬКО для classic --}}
													<option value="mixed_limited" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='mixed_limited')>
														М/Ж (с ограничениями)
													</option>
												</select>
												<div class="pb-05"></div>
												<div id="gender_5050_hint" class="text-sm text-gray-500 mt-2 hidden"></div>
												
												
												<div id="gender_limited_side_wrap" class="mt-1 hidden">
													<label>Кого ограничиваем</label>
													@php
													$sideVal = old('game_gender_limited_side', $prefill['game_gender_limited_side'] ?? 'female');
													@endphp
													<div class="d-flex mt-1">
														<label class="radio-item">
															<input type="radio" name="game_gender_limited_side" value="female" @checked($sideVal==='female')>
															<div class="custom-radio"></div>
															<span class="text-sm font-semibold">Женщин</span>
														</label>
														<label class="radio-item ml-2">
															<input type="radio" name="game_gender_limited_side" value="male" @checked($sideVal==='male')>
															<div class="custom-radio"></div>
															<span class="text-sm font-semibold">Мужчин</span>
														</label>
													</div>
													{{--
													<div class="mt-1">
														Ограничиваемый пол получает лимит мест (ниже).
													</div>
													--}}
												</div>
												
												<div id="gender_limited_max_wrap" class="mt-1 hidden">
													<label>Макс. мест для ограничиваемых</label>
													<div class="row">
														<div class="col-sm-6">
															
															<input type="number"
															name="game_gender_limited_max"
															id="game_gender_limited_max"
															value="{{ old('game_gender_limited_max', $prefill['game_gender_limited_max'] ?? '') }}"
															class="w-full rounded-lg border-gray-200"
															min="0" max="99"
															placeholder="например, 2">
														</div>
													</div>
												</div>
												
												
												<div id="gender_limited_positions_wrap" class="mt-1 hidden">
													<label>Позиции, доступные ограничиваемому полу</label>
													
													
													
													<div id="gender_positions_box" class=""></div>
													
													@php
													$oldLimitedPositions = old('game_gender_limited_positions', $prefill['game_gender_limited_positions'] ?? []);
													if (is_string($oldLimitedPositions)) $oldLimitedPositions = [$oldLimitedPositions];
													if (!is_array($oldLimitedPositions)) $oldLimitedPositions = [];
													@endphp
													<input type="hidden" id="gender_positions_old_json" value="{{ e(json_encode(array_values($oldLimitedPositions))) }}">
													
													<ul class="list f-16 mt-1">
														<li><a onclick="return false;" href="#" type="button" id="gender_positions_clear" class="f-16 blink">Сбросить</a></li>
													</ul>													
													
													
												</div>
												
												{{-- legacy hidden (compat) --}}
												<input type="hidden" name="game_allow_girls" id="game_allow_girls_legacy" value="{{ old('game_allow_girls', $prefill['game_allow_girls'] ?? 1) ? 1 : 0 }}">
												<input type="hidden" name="game_girls_max" id="game_girls_max_legacy" value="{{ old('game_girls_max', $prefill['game_girls_max'] ?? '') }}">
												
												
												
												
												
											</div>
										</div>										
										
										{{-- Levels --}}
										@php
										$classicMin = old('classic_level_min', $prefill['classic_level_min'] ?? null);
										$classicMax = old('classic_level_max', $prefill['classic_level_max'] ?? null);
										$beachMin   = old('beach_level_min',   $prefill['beach_level_min'] ?? null);
										$beachMax   = old('beach_level_max',   $prefill['beach_level_max'] ?? null);
										@endphp
										
										<div class="col-md-6">
											<div class="card">
												
												<div id="levels_classic" data-show-if="direction=classic">
													<label>Уровень допуска (Классический волейбол)</label>
													<hr class="mb-1">
													<div class="row">
														<div class="col-6">
															<label>От </label>
															<select name="classic_level_min" class="w-full rounded-lg border-gray-200">
																<option value="">—</option>
																@for ($i = 1; $i <= 7; $i++)
																<option value="{{ $i }}" @selected((string)$classicMin === (string)$i)>{{ $i }} - {{ level_name($i) }}</option>
																@endfor
															</select>
														</div>
														
														<div class="col-6">
															<label>До </label>
															<select name="classic_level_max" class="w-full rounded-lg border-gray-200">
																<option value="">—</option>
																@for ($i = 1; $i <= 7; $i++)
																<option value="{{ $i }}" @selected((string)$classicMax === (string)$i)>{{ $i }} - {{ level_name($i) }}</option>
																@endfor
															</select>
														</div>
													</div>
												</div>
												
												
												<div id="levels_beach" data-show-if="direction=beach">
													<label>Уровень допуска (Пляжный волейбол)</label>
													<hr class="mb-1">
													<div class="row">
														<div class="col-6">
															<label>От </label>
															<select name="beach_level_min" class="w-full rounded-lg border-gray-200">
																<option value="">-</option>
																@for ($i = 1; $i <= 7; $i++)
																<option value="{{ $i }}" @selected((string)$beachMin === (string)$i)>{{ $i }} - {{ level_name($i) }}</option>
																@endfor
															</select>
															@error('beach_level_min')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
														</div>
														
														<div class="col-6">
															<label>До </label>
															<select name="beach_level_max" class="w-full rounded-lg border-gray-200">
																<option value="">-</option>
																@for ($i = 1; $i <= 7; $i++)
																<option value="{{ $i }}" @selected((string)$beachMax === (string)$i)>{{ $i }} - {{ level_name($i) }}</option>
																@endfor
															</select>
															@error('beach_level_max')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
														</div>
													</div>
												</div>
												@error('direction')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
												@enderror
												
												<ul class="list f-16 mt-1">
													<li>Если выбраны оба — диапазона “от и до”. Если заполнено одно — ограничение будет по нему.</li>
												</ul>											
												
											</div>
										</div>								
										
										{{-- allow_registration --}}
										<div class="col-md-6">
											<div class="card">
												<label>Регистрация игроков через сервис?</label>
												@php
												$allowRegVal = old('allow_registration', $prefill['allow_registration'] ?? 1);
												@endphp
												
												<label class="radio-item">
													<input type="radio" name="allow_registration" value="1" @checked((string)$allowRegVal==='1')>
													<div class="custom-radio"></div>
													<span>Да (Доступно создание повторяющихся мероприятий)</span>
												</label>
												<label class="radio-item">
													<input type="radio" name="allow_registration" value="0" @checked((string)$allowRegVal==='0')>
													<div class="custom-radio"></div>
													<span>Нет (Только одноразовое + заглушка оплаты)</span>
												</label>
												
												<div id="no_registration_stub" class="m">
													<div class="font-semibold">Платное размещение (заглушка)</div>
													<div class="mt-1 text-xs text-gray-500">
														Здесь позже появится “Оплатить” и логика платного размещения, если регистрация выключена.
													</div>
												</div>
											</div>
										</div>										
										
										
										
										
									</div>									
								</div>								
							</div>
						</div>
						<div class="ramka text-center">
							<button type="button" class="btn" data-next>
								Дальше
							</button>
						</div>							
						
						
					</div>
					{{-- STEP 2 --}}
					<div data-step="2" class="wizard-step hidden step-shell">
						<div class="ramka">
							<h2 class="-mt-05">Выбор локации,времени и ограничений записи</h2>		
							<div class="row">
								
								<div class="col-lg-4">
									<div class="card">
										<label>Начало (локальное)</label>
										@php
										$minDate = now()->format('Y-m-d\TH:i');
										$maxDate = now()->addYear()->format('Y-m-d\TH:i');
										
										// Устанавливаем завтра в 19:00
										$defaultDate = now()->addDay()->setTime(19, 0)->format('Y-m-d\TH:i');
										@endphp
										
										<input type="datetime-local"
										name="starts_at_local"
										value="{{ old('starts_at_local', $defaultDate) }}"
										min="{{ $minDate }}"
										max="{{ $maxDate }}">
										<div class="pb-05"></div>
										@error('starts_at_local')
										<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
										@enderror
									</div>
								</div>							
								
								{{-- ✅ CITY (autocomplete -> hidden city_id) --}}
								<div class="col-lg-8">
									<div class="card">
										<label>Город</label>
										
										<div
										style="max-width: 40rem"
										class="city-autocomplete"
										id="event-city-autocomplete"
										data-search-url="{{ route('cities.search') }}"
										data-city-search-url="{{ route('cities.search') }}"
										data-locations-url="{{ route('ajax.locations.byCity') }}"
										data-city-meta-url="{{ route('ajax.cities.meta') }}"
										>
											
											{{-- Поле для отображения --}}
											<input
											type="text"
											name="city_label"
											id="event_city_q"
											class="w-full rounded-lg border-gray-200 @error('city_id') ring-2 ring-red-500 border-red-500 @enderror"
											value="{{ auth()->user()->city 
											? auth()->user()->city->name.' ('.auth()->user()->city->country_code.', '.auth()->user()->city->region.')' 
											: '' }}"
											placeholder="Начните вводить город…"
											autocomplete="off"
											>
											
											<div
											id="event_city_dropdown"
											class="city-dropdown"
											style="max-height: 28rem; overflow-y: auto; z-index: 60;"
											>
												<div id="event_city_results"></div>
											</div>
										</div>
										
										
										@error('city_id')
										<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                        @enderror
										
										<div class="d-flex between mt-2">
											<label>Локация</label>
											@if($isAdmin)
											<a href="{{ route('admin.locations.create') }}"
											class="text-sm font-semibold text-blue-600 hover:text-blue-700">
												+ Создать локацию
											</a>
											@endif
										</div>
										
										<select name="location_id" id="location_id" class="w-full rounded-lg border-gray-200">
											<option value="">— выбрать локацию —</option>
										</select>
										@error('location_id')
										<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                        @enderror
										{{-- preview --}}
										
										
										<div id="location_preview" class="mt-2 hidden">
											<div class="row fvc">
												
												<div class="col-3 location_preview">
													<img id="location_preview_img" src="" alt="" class="border hidden">
													<div id="location_preview_noimg" class="icon-nophoto"></div>
												</div>
												
												<div class="col-9">
													<p class="cd b-600" id="location_preview_name"></p>
													<p class="mt-1" id="location_preview_meta"></p>
												</div>
												<div class="col-12">
													<div class="border" id="location_preview_map_wrap" style="display:none;">
														<iframe
														id="location_preview_map"
														src=""
														class="w-100"
														style="height: 220px;"
														loading="lazy"
														referrerpolicy="no-referrer-when-downgrade"
														></iframe>
													</div>
												</div>												
												
											</div>
										</div>
										
										@if(!$isAdmin)
										<ul class="list f-16 mt-1">
											<li>Локации создаёт администратор. Если нужной локации нет — напишите админу.</li>
										</ul>											
										@endif
									</div>									
								</div>
								
								
								<div class="col-lg-4">
									<div class="card">
										<label>Длительность мероприятия</label>
										<hr class="mb-1">
										<div class="row">
											<div class="col-4">
												<label>Дни:</label>										
												<input type="number"
												min="0"
												name="duration_days"
												value="{{ old('duration_days', 0) }}"
												class="w-full rounded-lg border-gray-200"
												placeholder="Дни (0‑30)">
											</div>
											
											<div class="col-4">
												<label>Часы:</label>					
												<input type="number"
												min="0"
												max="23"
												name="duration_hours"
												value="{{ old('duration_hours', 0) }}"
												class="w-full rounded-lg border-gray-200"
												placeholder="Часы (0‑23)">
											</div>
											
											<div class="col-4">
												<label>Минуты:</label>			
												<input type="number"
												min="0"
												max="59"
												name="duration_minutes"
												value="{{ old('duration_minutes', 0) }}"
												class="w-full rounded-lg border-gray-200"
												placeholder="Минуты (0‑59)">
											</div>
											<input type="hidden" name="duration_sec" id="duration_sec" value="0">
											@error('duration_sec')
											<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
											@enderror
										</div>
										
										<ul class="list f-16 mt-1">
											<li>Дни — для кемпов и турниров</li>
											<li>Часы — для тренировок и игр  </li>
											<li>Минуты — точная настройка длительности</li>
										</ul>										
									</div>
								</div>
								<script>
									document.addEventListener('DOMContentLoaded', () => {
										const form = document.querySelector('form[action="{{ route('events.store') }}"]');
										if (!form) return;
										
										function updateDurationSec() {
											const days    = parseInt(form.querySelector('[name="duration_days"]')?.value || 0, 10);
											const hours   = parseInt(form.querySelector('[name="duration_hours"]')?.value || 0, 10);
											const minutes = parseInt(form.querySelector('[name="duration_minutes"]')?.value || 0, 10);
											
											let durationSec = 0;
											durationSec += days * 86400;
											durationSec += hours * 3600;
											durationSec += minutes * 60;
											
											// Без защиты — оставляем как есть, даже если 0
											document.getElementById('duration_sec').value = durationSec;
										}
										
										// Обновляем при изменении полей
										form.addEventListener('change', (e) => {
											if (e.target.name === 'duration_days' || e.target.name === 'duration_hours' || e.target.name === 'duration_minutes') {
												updateDurationSec();
											}
										});
										
										// При загрузке тоже обновляем
										updateDurationSec();
									});
								</script>
								
								
								
								
								{{-- ✅ Registration timings (Step 2) --}}
								<div class="col-lg-8" id="reg_timing_box">
									<div class="card">
										<label>Окно регистрации</label>
										<hr class="mb-1">
										<div class="row">
											<div class="col-sm-4">
												<label>Начало регистрации</label>
												<input type="number"
												name="reg_starts_days_before"
												id="reg_starts_days_before"
												min="0" max="365"
												value="{{ $oldRegStartsDaysBefore }}">
												
												<ul class="list f-16 mt-1">
													<li class="b-600">Дней до</li>
													<li>По умолчанию: 3 дня.</li>
												</ul>													
												<div class="text-xs text-gray-500 mt-1"></div>
											</div>
											
											<div class="col-sm-4">
												<label>Окончание регистрации</label>
												<input type="number"
												name="reg_ends_minutes_before"
												id="reg_ends_minutes_before"
												min="0" max="10080"
												value="{{ $oldRegEndsMinutesBefore }}">
												
												<ul class="list f-16 mt-1">
													<li class="b-600">Минут до</li>
													<li>По умолчанию: 15 минут.</li>
												</ul>													
												
											</div>
											
											<div class="col-sm-4">
												<label>Запрет отмены записи</label>
												<input type="number"
												name="cancel_lock_minutes_before"
												id="cancel_lock_minutes_before"
												min="0" max="10080"
												value="{{ $oldCancelLockMinutesBefore }}">
												<ul class="list f-16 mt-1">
													<li class="b-600">Минут до</li>
													<li>По умолчанию: 60 минут.</li>
												</ul>													
											</div>
										</div>
										
										<ul class="list f-16 mt-1">
											{{--
											<li>Эти настройки применяются только если в шаге 1 выбрано “Регистрация игроков через сервис: Да”.</li>
											--}}	
											<li>Время считается от <span class="f-600">начала мероприятия</span>.</li>
											<li>Пример: “Запрет отмены 60 минут” → за час до начала кнопка отмены станет недоступной.</li>
										</ul>									
									</div>
								</div>
								
							</div>
						</div>
						<div class="ramka">
							<h2 class="-mt-05">Повторение мероприятия</h2>		
							
							{{-- ✅ Повторение перенесено сюда (Step 2) --}}
							<div id="recurrence_box">
								<div class="mb-1">
									{{-- toggle --}}
									<label class="checkbox-item">
										<input type="hidden" name="is_recurring" value="0">
										<input type="checkbox" name="is_recurring" value="1" id="is_recurring">
										<div class="custom-checkbox"></div>
										<span>Повторяющееся мероприятие</span>
									</label>	
									
									<ul class="list f-16 mt-1" id="recurrence_disabled_hint">
										<li>Повторы доступны только при включённой регистрации игроков.</li>
									</ul>										
									
								</div>
								{{-- fields --}}
								<div class="row mt-2" id="recurrence_fields" style="display:none;">
									
									{{-- type --}}
									<div class="col-md-4">
										<div class="card">
											<label>Тип повторения</label>
											<select name="recurrence_type" id="recurrence_type">
												<option value="">— выбрать —</option>
												<option value="daily">Ежедневно</option>
												<option value="weekly">Еженедельно</option>
												<option value="monthly">Ежемесячно</option>
											</select>
											
											{{-- WEEKDAYS --}}
											<div class="mt-2" id="weekdays_wrap" style="display:none;">
												
												<label>
													Дни недели
												</label>
												
												<div class="row row2">
													@foreach([
													1 => 'Понедельник',
													2 => 'Вторник', 
													3 => 'Среда',
													4 => 'Четверг',
													5 => 'Пятница',
													6 => 'Суббота',
													7 => 'Воскресенье'
													] as $num => $label)
													<div class="col-6">
														<label class="checkbox-item">
															<input type="checkbox"
															name="recurrence_weekdays[]"
															value="{{ $num }}">
															<div class="custom-checkbox"></div>
															<span>{{ $label }}</span>
														</label>
													</div>
													@endforeach
												</div>
												
											</div>
										</div>		
									</div>
									
									{{-- END TYPE --}}
									<div class="col-md-4">
										<div class="card">
											<label>
												Окончание повторов
											</label>
											
											<div class="flex flex-col gap-2">
												<label class="radio-item">
													<input checked type="radio" name="recurrence_end_type" value="none">
													<div class="custom-radio"></div>
													<span>Без окончания</span>
												</label>
												
												<label class="radio-item">
													<input type="radio" name="recurrence_end_type" value="until">
													<div class="custom-radio"></div>
													<span>До даты</span>
												</label>
												<div class="mb-1">
													<input type="date" name="recurrence_end_until">
												</div>
												<label class="radio-item">
													<input type="radio" name="recurrence_end_type" value="count">
													<div class="custom-radio"></div>
													<span>По количеству</span>
												</label>
												
												<input type="number"
												min="1"
												name="recurrence_end_count"
												placeholder="например 10">
												<div class="pb-05"></div>												
											</div>
										</div>									
									</div>
									
									
									{{-- interval --}}
									<div class="col-md-4">
										<div class="card">
											<label>Интервал</label>
											<input type="number"
											min="1" max="365"
											id="recurrence_interval"
											name="recurrence_interval"
											value="1">
											
											<ul class="list f-16 mt-1">
												<li>1 = каждый раз</li>
												<li>2 = через раз</li>
											</ul>										
										</div>
									</div>
									
									
									
									{{-- legacy --}}
									<input type="hidden" name="recurrence_rule"
									value="{{ old('recurrence_rule', $prefill['recurrence_rule'] ?? '') }}">
								</div>
								
								<script>
									document.addEventListener('DOMContentLoaded', () => {
										const isRecurring   = document.getElementById('is_recurring');
										const fields        = document.getElementById('recurrence_fields');
										const typeSelect    = document.getElementById('recurrence_type');
										const weekdaysWrap  = document.getElementById('weekdays_wrap');
										const allowRegRadios = document.querySelectorAll('input[name="allow_registration"]');
										const disabledHint  = document.getElementById('recurrence_disabled_hint');
										
										function allowRegistrationEnabled() {
											return [...allowRegRadios].some(r => r.checked && r.value === '1');
										}
										
										function syncRecurrenceUI() {
											const allowed = allowRegistrationEnabled();
											
											if (!allowed) {
												isRecurring.checked = false;
												isRecurring.disabled = true;
												fields.style.display = 'none';
												disabledHint.classList.remove('hidden');
												return;
											}
											
											isRecurring.disabled = false;
											disabledHint.classList.add('hidden');
											
											fields.style.display = isRecurring.checked ? '' : 'none';
											
											const type = typeSelect.value;
											weekdaysWrap.style.display = (type === 'weekly') ? '' : 'none';
										}
										
										isRecurring.addEventListener('change', syncRecurrenceUI);
										typeSelect.addEventListener('change', syncRecurrenceUI);
										allowRegRadios.forEach(r => r.addEventListener('change', syncRecurrenceUI));
										
										syncRecurrenceUI();
									});
								</script>	
								
								
							</div>
							
							
						</div>	
						<div class="ramka text-center">
							<button type="button" class="btn btn-secondary" data-back>
								Назад
							</button>
							<button type="button" class="btn" data-next>
								Дальше
							</button>
						</div>							
					</div>
					
					{{-- STEP 3 --}}
					<div data-step="3" class="wizard-step hidden step-shell">
						<div class="ramka" style="z-index: 5">
							<h2 class="-mt-05">Доступность</h2>		
							<div class="row">
								<div class="col-md-4">
									<div class="card">
										<label class="checkbox-item">
											<input type="hidden" name="is_private" value="0">
											<input type="checkbox" name="is_private" value="1" id="is_private">
											<div class="custom-checkbox"></div>
											<span>Приватное (доступно только по ссылке)</span>
										</label>
										<ul class="list f-16 mt-1">
											<li>Будет сгенерирован токен ссылки (public_token) для приватного.</li>
										</ul>											
									</div>
								</div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <label class="checkbox-item">
                                            <input type="hidden" name="is_paid" value="0">
                                            <input
											type="checkbox"
											name="is_paid"
											value="1"
											id="is_paid"
											@checked((bool) old('is_paid', $prefill['is_paid'] ?? false))
                                            >
                                            <div class="custom-checkbox"></div>
                                            <span>Платное</span>
										</label>
										
                                        <div class="row mt-2" id="price_wrap">
                                            <div class="col-md-6">
                                                <label class="form-label">Стоимость</label>
                                                <input
												type="number"
												name="price_amount"
												class="form-input"
												value="{{ old('price_amount', $prefill['price_amount'] ?? '') }}"
												placeholder="Например: 134"
												min="10"
												max="500000"
												step="0.01"
												inputmode="decimal"
                                                >
                                                @error('price_amount')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                @enderror
											</div>
											
                                            <div class="col-md-6">
                                                <label class="form-label">Валюта</label>
                                                <select name="price_currency" class="form-select">
                                                    @php
													$currencyOptions = [
													'RUB' => 'RUB — Российский рубль (₽)',
													'USD' => 'USD — Доллар США ($)',
													'EUR' => 'EUR — Евро (€)',
													'KZT' => 'KZT — Тенге (₸)',
													'KGS' => 'KGS — Киргизский сом',
													'BYN' => 'BYN — Белорусский рубль',
													'UZS' => 'UZS — Узбекский сум',
													'AMD' => 'AMD — Армянский драм (֏)',
													'AZN' => 'AZN — Азербайджанский манат (₼)',
													'TJS' => 'TJS — Сомони',
													'TMT' => 'TMT — Туркменский манат',
													'GEL' => 'GEL — Лари (₾)',
													'MDL' => 'MDL — Молдавский лей',
													];
													
													$selectedCurrency = old('price_currency', $prefill['price_currency'] ?? 'RUB');
                                                    @endphp
													
                                                    @foreach($currencyOptions as $code => $label)
													<option value="{{ $code }}" @selected($selectedCurrency === $code)>
														{{ $label }}
													</option>
                                                    @endforeach
												</select>
                                                @error('price_currency')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                @enderror
											</div>
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="card">
										<label class="checkbox-item">
											<input type="hidden" name="requires_personal_data" value="0">
											<input type="checkbox" name="requires_personal_data" value="1">
											<div class="custom-checkbox"></div>
											<span class="text-sm font-semibold">Требовать персональные данные</span>
										</label>
										<ul class="list f-16 mt-1">
											<li>Если включено — при записи будем просить дополнительные данные.</li>
										</ul>										
									</div>
								</div>
							</div>
						</div>
						{{-- ===== Помощник записи 🤖 =====--}}
						
                        <div class="ramka" data-show-if="allow_registration=1" id="bot_assistant_block">
                            <h2 class="-mt-05">Помощник записи 🤖</h2>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card">
                                        <label class="checkbox-item">
                                            <input type="hidden" name="bot_assistant_enabled" value="0">
                                            <input
											type="checkbox"
											name="bot_assistant_enabled"
											value="1"
											id="bot_assistant_enabled"
											@checked((bool) old('bot_assistant_enabled', $prefill['bot_assistant_enabled'] ?? false))
                                            >
                                            <div class="custom-checkbox"></div>
                                            <span>Включить помощника записи</span>
										</label>
										
                                        <ul class="list f-16 mt-1">
                                            <li>Если за первые сутки после открытия записи зарегистрировалось меньше <strong>порога</strong> — боты начнут постепенно занимать места.</li>
                                            <li>По мере прихода живых игроков боты уходят и освобождают места.</li>
                                            <li>Видно только организатору и администратору.</li>
                                            <li>Боты не занимают последнее свободное место.</li>
                                            <li>Активность ботов замораживается за 3 часа до начала.</li>
										</ul>
									</div>
								</div>
								
                                <div class="col-md-6" id="bot_assistant_settings" @if(!old('bot_assistant_enabled', $prefill['bot_assistant_enabled'] ?? false)) style="display:none" @endif>
                                    <div class="card">
                                        <label>Порог запуска (%)</label>
                                        <div class="d-flex fvc gap-2 mt-1">
                                            <input
											type="range"
											name="bot_assistant_threshold"
											id="bot_assistant_threshold"
											min="5"
											max="30"
											step="5"
											value="{{ old('bot_assistant_threshold', $prefill['bot_assistant_threshold'] ?? 10) }}"
											style="flex:1"
											oninput="document.getElementById('bot_threshold_val').textContent = this.value + '%'"
                                            >
                                            <strong id="bot_threshold_val" class="cd" style="min-width:3rem; text-align:right">
                                                {{ old('bot_assistant_threshold', $prefill['bot_assistant_threshold'] ?? 10) }}%
											</strong>
										</div>
                                        <ul class="list f-16 mt-1">
                                            <li>Если через сутки записалось меньше <strong id="bot_threshold_hint">{{ old('bot_assistant_threshold', $prefill['bot_assistant_threshold'] ?? 10) }}%</strong> от максимума — боты включаются.</li>
                                            <li>Диапазон: 5–30%.</li>
										</ul>
									</div>
								</div>
								
                                <div class="col-md-6" id="bot_assistant_fill" @if(!old('bot_assistant_enabled', $prefill['bot_assistant_enabled'] ?? false)) style="display:none" @endif>
                                    <div class="card">
                                        <label>Макс. заполнение ботами (%)</label>
                                        <div class="d-flex fvc gap-2 mt-1">
                                            <input
											type="range"
											name="bot_assistant_max_fill_pct"
											id="bot_assistant_max_fill_pct"
											min="10"
											max="60"
											step="10"
											value="{{ old('bot_assistant_max_fill_pct', $prefill['bot_assistant_max_fill_pct'] ?? 40) }}"
											style="flex:1"
											oninput="document.getElementById('bot_fill_val').textContent = this.value + '%'"
                                            >
                                            <strong id="bot_fill_val" class="cd" style="min-width:3rem; text-align:right">
                                                {{ old('bot_assistant_max_fill_pct', $prefill['bot_assistant_max_fill_pct'] ?? 40) }}%
											</strong>
										</div>
                                        <ul class="list f-16 mt-1">
                                            <li>Боты не займут больше <strong id="bot_fill_hint">{{ old('bot_assistant_max_fill_pct', $prefill['bot_assistant_max_fill_pct'] ?? 40) }}%</strong> мест одновременно.</li>
                                            <li>Минимум 2 места всегда остаются свободными для живых игроков.</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
						<div class="ramka">
							<h2 class="-mt-05">Уведомления и видимость</h2>		
							<div class="row">
								
								{{-- ✅ Notifications + participants visibility --}}
								
								
								@php
								$remMin = (int) old('remind_registration_minutes_before', $prefill['remind_registration_minutes_before'] ?? 600);
								if ($remMin < 0) $remMin = 600;
								$showParts = (bool) old('show_participants', $prefill['show_participants'] ?? true);
								@endphp
								
								<div class="col-md-4">
									<div class="card">
										<label>Напоминание игроку о записи</label>
										
										<label class="checkbox-item">
											<input type="hidden" name="remind_registration_enabled" value="0">
											<input checked type="checkbox" name="remind_registration_enabled" value="1" id="remind_registration_enabled">
											<div class="custom-checkbox"></div>
											<span>Включено</span>
										</label>
										
										<div class="mt-2">
											<label>За сколько минут до начала</label>
											<input
											type="number"
											step="0.1"
											min="0"
											value="{{ $remMin / 60 }}"
											id="remind_registration_hours"
											class="w-full rounded-lg border-gray-200"
											>
											
											<input
											type="hidden"
											name="remind_registration_minutes_before"
											id="remind_registration_minutes_before"
											value="{{ $remMin }}"
											>
											
											<ul class="list f-16 mt-1">
												<li>Формат: 1 = 1 час, 0.4 = 40 минут, 0.1 = 10 минут</li>
											</ul>
											
											
										</div>
									</div>
								</div>
                                {{-- CHANNEL NOTIFICATIONS --}}
                                @php
								$selectedChannels = old('channels', []);
								if (!is_array($selectedChannels)) {
								$selectedChannels = [];
								}
                                
								$channelSilent = (bool) old('channel_silent', false);
								$channelUpdateMessage = (bool) old('channel_update_message', true);
								$channelIncludeImage = (bool) old('channel_include_image', true);
								$channelIncludeRegistered = (bool) old('channel_include_registered', true);
                                
								$selectedOrganizerId = (int) old('organizer_id', $prefill['organizer_id'] ?? auth()->id());
                                
								$userChannels = \App\Models\UserNotificationChannel::query()
								->verified()
								->where('user_id', $selectedOrganizerId)
								->orderBy('platform')
								->orderBy('title')
								->get();
                                @endphp
                                
                                <div class="col-md-4">
                                    <div class="card">
                                        <label>Анонс в каналы</label>
										
                                        <ul class="list f-16 mb-2">
                                            <li>При открытии регистрации сообщение отправится в выбранные каналы</li>
                                            <li>Для повторяющихся мероприятий анонс будет отправляться для каждой новой даты</li>
										</ul>
										
                                        @if($userChannels->isEmpty())
										<div class="f-16">
											Нет подключенных каналов —
											<a href="{{ route('profile.notification_channels') }}" class="link">
												подключить
											</a>
										</div>
                                        @else
										<div class="mt-2">
											@foreach($userChannels as $channel)
											<label class="checkbox-item">
												<input type="checkbox"
												name="channels[]"
												value="{{ $channel->id }}"
												@checked(in_array((string) $channel->id, array_map('strval', $selectedChannels), true))>
												<div class="custom-checkbox"></div>
												<span>
													{{ strtoupper($channel->platform) }} — {{ $channel->title ?: 'Без названия' }}
													<span class="text-muted">({{ $channel->chat_id }})</span>
												</span>
											</label>
											@endforeach
										</div>
										
										<div class="mt-2">
											<label class="checkbox-item">
												<input type="hidden" name="channel_silent" value="0">
												<input type="checkbox" name="channel_silent" value="1" @checked($channelSilent)>
												<div class="custom-checkbox"></div>
												<span>Тихое обновление</span>
											</label>
											
											<label class="checkbox-item">
												<input type="hidden" name="channel_update_message" value="0">
												<input type="checkbox" name="channel_update_message" value="1" @checked($channelUpdateMessage)>
												<div class="custom-checkbox"></div>
												<span>Обновлять сообщение</span>
											</label>
											
											<label class="checkbox-item">
												<input type="hidden" name="channel_include_image" value="0">
												<input type="checkbox" name="channel_include_image" value="1" @checked($channelIncludeImage)>
												<div class="custom-checkbox"></div>
												<span>Добавлять картинку</span>
											</label>
											
											<label class="checkbox-item">
												<input type="hidden" name="channel_include_registered" value="0">
												<input type="checkbox" name="channel_include_registered" value="1" @checked($channelIncludeRegistered)>
												<div class="custom-checkbox"></div>
												<span>Показывать список игроков</span>
											</label>
										</div>
                                        @endif
									</div>
								</div>
								<div class="col-md-4">
									<div class="card">
										<label>Показывать список записавшихся</label>
										<label class="radio-item">
											<input type="radio" name="show_participants" value="1" @checked($showParts)>
											<div class="custom-radio"></div>
											<span>Да</span>
										</label>
										<label class="radio-item">
											<input type="radio" name="show_participants" value="0" @checked(!$showParts)>
											<div class="custom-radio"></div>
											<span>Нет</span>
										</label>
										
										<ul class="list f-16 mt-1">
											<li>Если “Нет” — на странице события список участников не показываем.</li>
										</ul>											
										
									</div>
								</div>
							</div>
						</div>
						<div class="ramka" style="z-index:6">
							<h2 class="-mt-05">Фото и описание</h2>		
							
							<div class="row">
								
								

								
								{{-- ✅ COVER --}}
								
								<div class="col-md-4">
									<div class="card">
										
										{{--
										
										<p>
											Можно загрузить файл или выбрать из вашей галереи. Если загружен файл — он важнее выбора из галереи.
										</p>
										
										
										<label>Загрузить с компьютера</label>
										<input type="file" name="cover_upload" accept="image/*" class="w-full rounded-lg border-gray-200">
										
										<ul class="list f-16 mt-1">
											<li>JPG / PNG / WebP, до 5MB.</li>
										</ul>												
										--}}	
										
										
										
										@php
										$userEventPhotos = auth()->user()->getMedia('event_photos')->sortByDesc('created_at');
										@endphp
										
										@if($userEventPhotos->count() > 0)
										<div>
											<label>Фотографии для мероприятия</label>
											
											
											
											
											<div class="event-photos-selector" 
											data-selected='{{ json_encode(old('event_photos', $eventPhotos ?? [])) }}'>
											
											<div class="swiper eventPhotosSwiper">
											<div class="swiper-wrapper">
											@foreach($userEventPhotos as $photo)
											<div class="swiper-slide">
											<div class="hover-image mb-1">
											<img src="{{ $photo->getUrl('event_thumb') }}" alt="event photo" loading="lazy"/>
										</div>
										<div class="mt-1 d-flex between fvc">
											<label class="checkbox-item mb-0">
												<input type="checkbox" class="photo-select" value="{{ $photo->id }}">
												<div class="custom-checkbox"></div>
												<span>Выбрать</span>
											</label>    
											<div class="photo-order-badge f-16 b-600 cd"></div>
										</div>
									</div>
									@endforeach
								</div>
								<div class="swiper-pagination"></div>
							</div>
							
							<ul class="list f-16 mt-1">
								<li>Выберите фото для галереи. Первое отмеченное фото будет главным.</li>
								<li>Фотографии можно добавить (с галочкой "Для мероприятий") в разделе <a href="{{ route('user.photos') }}">Ваши фотографии</a></li>
							</ul>								
							
							
							<input type="hidden" name="event_photos" id="event_photos_input" value="">
						</div>
					</div>
					<script>
						document.addEventListener('DOMContentLoaded', function() {
							// Инициализация Swiper
							new Swiper('.eventPhotosSwiper', {
								slidesPerView: 1,
								spaceBetween: 15,
								pagination: { el: '.swiper-pagination', clickable: true },
								breakpoints: { 640: { slidesPerView: 1 }, 768: { slidesPerView: 1 }, 1024: { slidesPerView: 1 } }
							});
							
							const container = document.querySelector('.event-photos-selector');
							const savedPhotos = JSON.parse(container.dataset.selected || '[]');
							let selectedPhotos = [...savedPhotos]; // копируем массив
							
							function updateUI() {
								document.querySelectorAll('.photo-select').forEach(checkbox => {
									const id = parseInt(checkbox.value);
									const isSelected = selectedPhotos.includes(id);
									checkbox.checked = isSelected;
									
									const badge = checkbox.closest('.swiper-slide').querySelector('.photo-order-badge');
									if (isSelected) {
										const order = selectedPhotos.indexOf(id) + 1;
										badge.textContent = order === 1 ? '★ Главное' : `Фото: ${order}`;
										} else {
										badge.textContent = '';
									}
								});
								
								document.getElementById('event_photos_input').value = JSON.stringify(selectedPhotos);
							}
							
							document.querySelectorAll('.photo-select').forEach(checkbox => {
								checkbox.addEventListener('change', function() {
									const id = parseInt(this.value);
									
									if (this.checked) {
										selectedPhotos.push(id);
										} else {
										const index = selectedPhotos.indexOf(id);
										if (index !== -1) selectedPhotos.splice(index, 1);
									}
									
									updateUI();
								});
							});
							
							updateUI();
						});
					</script>
					@else
					<div class="mt-2">
						<div class="alert alert-info">
							<p>У вас нет фотографий для мероприятий.</p> 
							<p><a href="{{ route('user.photos') }}">Загрузите фото</a> с галочкой "Для мероприятий".</p>
						</div>
					</div>
					@endif
					
					
					
				</div>
			</div>
			
								{{-- STEP 3: Описание мероприятия --}}
								<div class="col-md-8">
									<div class="card">
										<label>Описание мероприятия</label>
										
										{{-- Важно: hidden input + trix-editor --}}
										<input id="description_html" type="hidden" name="description_html">
										
										<trix-editor input="description_html" class="trix-content"></trix-editor>
										
										@error('description_html')
										<div class="text-red-600 text-sm mt-2">{{ $message }}</div>
										@enderror
										
									</div>
								</div>							
											
			
			
			
			
		</div>
	</div>
	<div class="ramka text-center">
		<button type="button" class="btn btn-secondary" data-back>
			Назад
		</button>
		<button type="submit" class="btn">Создать</button>
	</div>							
</div>	
</form>

</div>
</div>


<x-slot name="script"> 
	<script src="/assets/fas.js"></script>    
	<script src="/assets/org.js"></script>
	<script>
		window.volleyballConfig = @json($volleyballConfig);
	</script>
	{{-- Page JS --}}
	<script src="/js/config/volleyball-config.js"></script>
	<script src="/js/events-create.js?v={{ time() }}"></script>
	
	<script>
		let rerenderTimer = null;
		
		// Универсальная функция перерисовки одного селекта
		function safeRerenderEl(selector) {
			const $select = $(selector);
			if (!$select.length) {
				//console.log('Селект не найден:', selector);
				return;
			}
			
			const $wrapper = $select.prev('.form-select-wrapper');
			
			if ($wrapper.length) {
				// Сохраняем значение
				const currentValue = $select.val();
				
				// Удаляем старую обертку
				$wrapper.remove();
				$select.removeData('custom-initialized');
				
				// Создаем новую
				if (typeof createCustomSelect === 'function') {
					createCustomSelect($select);
					$select.val(currentValue);
					
					// Обновляем отображение
					setTimeout(function() {
						const $newWrapper = $select.prev('.form-select-wrapper');
						if ($newWrapper.length && typeof updateCustomSelect === 'function') {
							updateCustomSelect($select, $newWrapper);
						}
					}, 20);
				}
				} else {
				//console.log('Нет кастомной обертки для:', selector);
			}
		}
		
		// Существующая функция для всех селектов
		function safeRerenderAll() {
			if (rerenderTimer) clearTimeout(rerenderTimer);
			rerenderTimer = setTimeout(function() {
				$('select').each(function() {
					safeRerenderEl(this);
				});
			}, 150);
		}
		
		// Делаем функции глобальными (чтоб из консоли можно было вызывать)
		window.safeRerenderEl = safeRerenderEl;
		window.safeRerenderAll = safeRerenderAll;
		
		// Вешаем на все значимые события
		$('form').on('change', '#direction, #format, #game_subtype, #game_libero_mode, #game_gender_policy', safeRerenderAll);
		
		// На клик по городу
		$('body').on('click', '.city-item', function() {
			setTimeout(safeRerenderAll, 100);
		});
		
		// Первичная отрисовка
		safeRerenderEl('#format');
		safeRerenderEl('#game_gender_policy');
		
		// На AJAX
		$(document).ajaxComplete(safeRerenderAll);
		
	</script>			
	
</x-slot>			

</x-voll-layout>
