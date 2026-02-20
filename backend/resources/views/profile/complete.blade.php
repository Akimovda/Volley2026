{{-- resources/views/profile/complete.blade.php --}}
<x-voll-layout body_class="edit-profile-page">
    {{-- ============================================================
	PROFILE COMPLETE (Modern UI)
	- ФИО: подсказки + автонормализация (JS), серверная валидация (контроллер)
	- Телефон: маска +7 (___) ___-__-__ (курсор НЕ прыгает), сохраняем E.164 в hidden "phone"
	- Уровни: select
	- Sticky Save: sidebar (desktop) + bottom bar (mobile)
	- Город: поиск/автокомплит (AJAX) + НЕ ломаем форму:
	* сохраняем city_id как hidden
	* оставляем fallback <select> (если JS выключен)
		============================================================ --}}
		
		@php
        /** @var \App\Models\User|null $user */
        $user = $user ?? auth()->user();
		
        $hasPendingOrganizerRequest = (bool)($hasPendingOrganizerRequest ?? ($hasPendingRequest ?? false));
        $canEditProtected = (bool)($canEditProtected ?? ($user && $user->can('edit-protected-profile-fields')));
		
        $filled = function ($value) {
		if (is_null($value)) return false;
		if (is_string($value)) return trim($value) !== '';
		return true;
        };
		
        $lockHint = 'Поле уже заполнено. Изменить может только администратор.';
		
        $posMap = [
		'setter'   => 'Связующий',
		'outside'  => 'Доигровщик',
		'opposite' => 'Диагональный',
		'middle'   => 'Центральный блокирующий',
		'libero'   => 'Либеро',
        ];
		
        $classicPrimary = optional($user?->classicPositions)->firstWhere('is_primary', true)?->position;
        $classicAll     = optional($user?->classicPositions)->pluck('position')->all() ?? [];
		
        $beachPrimaryZone  = optional($user?->beachZones)->firstWhere('is_primary', true)?->zone;
        $beachModeCurrent  = $user?->beach_universal ? 'universal' : (is_null($beachPrimaryZone) ? null : (string)$beachPrimaryZone);
		
        // уровни: UX — если уже есть birth_date и <18, показываем только [1,2,4]
        $age = $user?->birth_date ? \Carbon\Carbon::parse($user->birth_date)->age : null;
			$levels = ($age !== null && $age < 18) ? [1,2,4] : [1,2,3,4,5,6,7];
			
			// Prefill маски телефона на сервере
			$phoneMaskedPrefill = old('phone_masked');
			if ($phoneMaskedPrefill === null) {
            $p = old('phone', $user?->phone);
				if (is_string($p) && preg_match('/^\+7\d{10}$/', $p)) {
                $phoneMaskedPrefill = '+7 (' . substr($p, 2, 3) . ') ' . substr($p, 5, 3) . '-' . substr($p, 8, 2) . '-' . substr($p, 10, 2);
				} else {
                $phoneMaskedPrefill = '';
				}
				}
				
				
				
				
				
				// Конфигурация отображения городов
				$cityDisplayConfig = [
				'showCountry' => true,      // показывать страну в списке и метке
				'showRegion' => true,       // показывать регион в списке и метке
				'inputShowCountry' => true, // показывать страну в инпуте
				'inputShowRegion' => true,  // показывать регион в инпуте
				];
				
				// City prefill
				$selectedCityId = old('city_id', $user?->city_id);
				$selectedCityLabel = '';
				
				// Формируем метку для инпута: Город (Страна, Область)
				if (!empty($selectedCityId) && !empty($cities)) {
				$found = collect($cities)->firstWhere('id', (int)$selectedCityId);
				if ($found) {
				$cityName = $found->name ?? '';
				$details = [];
				
				if ($cityDisplayConfig['inputShowCountry'] && $found->country_code) {
				$details[] = $found->country_code;
				}
				
				if ($cityDisplayConfig['inputShowRegion'] && !empty($found->region)) {
				$details[] = $found->region;
				}
				
				if (!empty($details)) {
				$selectedCityLabel = $cityName . ' (' . implode(', ', $details) . ')';
				} else {
				$selectedCityLabel = $cityName;
				}
				}
				}
				@endphp
				
				
				
				
				
				
				<x-slot name="title">
					Редактирование данных вашего профиля
				</x-slot>
				
				<x-slot name="description">Редактирование данных вашего профиля: 
					@if(!empty($user->first_name) || !empty($user->last_name))
					{{ trim($user->first_name . ' ' . $user->last_name) }}
					@else
					Пользователь #{{ $user->id }}
					@endif
				</x-slot>
				
				<x-slot name="canonical">
					{{ url('/profile/complete') }}
				</x-slot> 
				
				<x-slot name="breadcrumbs">
					<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
						<a href="{{ route('profile.show') }}" itemprop="item">
							<span itemprop="name">Ваш профиль</span>
						</a>
						<meta itemprop="position" content="2">
					</li>
					<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
						<a href="{{ url('/profile/complete') }}" itemprop="item">
							<span itemprop="name">Редактирование данных</span>
						</a>
						<meta itemprop="position" content="3">
					</li>		
				</x-slot>
				
				<x-slot name="h1">Редактирование профиля</x-slot>
				
				<x-slot name="h2">
					@if(!empty($user->first_name) || !empty($user->last_name))
					{{ trim($user->first_name . ' ' . $user->last_name) }}
					@else
					Пользователь #{{ $user->id }}
					@endif
				</x-slot>
				
				<x-slot name="t_description">
					Заполните ключевые поля — после первого сохранения часть данных сможет менять только администратор.
				</x-slot>
				
				<x-slot name="style">
					<style>
/* ===== КАСТОМНЫЙ ПОИСК ГОРОДОВ ===== */
.city-autocomplete {
	position: relative;
	width: 100%;
	z-index:2;
}

.city-autocomplete input {
	width: 100%;
	padding: 1rem 4rem 1rem 2rem; /* правый отступ для иконки */
	border: 0.2rem solid rgba(0, 0, 0, 0.1);
	border-radius: 1rem;
	font-size: 1.8rem;
	color: #222333;
	background-color: rgba(0, 0, 0, 0.02);
	box-shadow: 0 0.2rem 0.4rem rgba(0, 0, 0, 0.03);
	transition: all 0.25s ease;
}

.city-autocomplete input:hover {
	border-color: rgba(0, 0, 0, 0.2);
}

.city-autocomplete input:focus {
	outline: none;
	border-color: #2967BA;
	background-color: rgba(0, 0, 0, 0.01);
	box-shadow: 0 0.4rem 1.2rem rgba(0, 0, 0, 0.08);
}

.city-autocomplete input::placeholder {
	color: #999;
	font-size: 1.6rem;
}

/* Иконка поиска */
.city-autocomplete::after {
	content: '';
	position: absolute;
	right: 1.5rem;
	top: 50%;
	transform: translateY(-50%);
	width: 2rem;
	height: 2rem;
	background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3C/svg%3E");
	background-repeat: no-repeat;
	background-position: center;
	background-size: contain;
	pointer-events: none;
	z-index: 1;
}

/* Выпадающий список */
.city-dropdown {
	position: absolute;
	top: calc(100% + 0.5rem);
	left: 0;
	right: 0;
	background: #FFFFFF;
	border: 0.2rem solid rgba(0, 0, 0, 0.1);
	border-radius: 1rem;
	box-shadow: 0 0.4rem 1.2rem rgba(0, 0, 0, 0.1);
	z-index: 10;
	max-height: 32rem;
	overflow-y: auto;
	opacity: 0;
	visibility: hidden;
	transform: translateY(-1rem);
	transition: opacity 0.25s ease, transform 0.3s ease, visibility 0.25s;
}

.city-dropdown.active {
	opacity: 1;
	visibility: visible;
	transform: translateY(0);
}
.city-dropdown--active {
	opacity: 1;
	visibility: visible;
	transform: translateY(0);
}



/* Группы городов */
.city-group {
	padding: 0.8rem 2rem;
	font-size: 1.4rem;
	font-weight: 600;
	color: #666;
	background-color: #f5f5f5;
	border-bottom: 0.1rem solid rgba(0, 0, 0, 0.05);
	text-transform: uppercase;
	letter-spacing: 0.05em;
	position: sticky;
	top: 0;
	z-index: 2;
}

/* Элементы города */
.city-item {
	width: 100%;
	text-align: left;
	padding: 1rem 2rem;
	font-size: 1.6rem;
	cursor: pointer;
	transition: all 0.1s ease;
	border: none;
	background: none;
	border-bottom: 0.1rem solid rgba(0, 0, 0, 0.05);
	color: #222333;
}

.city-item:last-child {
	border-bottom: none;
}

.city-item:hover {
	background: #2967BA;
	color: #FFFFFF;
}

.city-item:hover .city-item-sub {
	color: rgba(255, 255, 255, 0.9);
}

.city-item-name {
	font-size: 1.6rem;
	margin-bottom: 0.2rem;
}

.city-item-sub {
	font-size: 1.2rem;
	color: #666;
}

/* Сообщения (загрузка, ничего не найдено) */
.city-message {
	padding: 1.5rem 2rem;
	font-size: 1.4rem;
	color: #666;
	text-align: center;
	background: #FFFFFF;
}

/* Состояние ошибки */
.city-autocomplete input.error {
	border-color: #ef4444 !important;
	background-color: rgba(239, 68, 68, 0.02);
}

.city-autocomplete input.error:focus {
	box-shadow: 0 0.4rem 1.2rem rgba(239, 68, 68, 0.1);
}

/* Состояние disabled */
.city-autocomplete input:disabled {
	opacity: 0.6;
	cursor: not-allowed;
	background-color: rgba(0, 0, 0, 0.01);
}

/* ===== ТЁМНАЯ ТЕМА ДЛЯ ПОИСКА ГОРОДОВ ===== */
body.dark .city-autocomplete input {
	background-color: #222333;
	border-color: rgba(255, 255, 255, 0.1);
	color: #e3e7eb;
	background-color: rgba(255, 255, 255, 0.02);
}

body.dark .city-autocomplete input:hover {
	border-color: rgba(255, 255, 255, 0.2);
}

body.dark .city-autocomplete input:focus {
	border-color: #E7612F;
	background-color: rgba(255, 255, 255, 0.01);
}

body.dark .city-autocomplete input::placeholder {
	color: #666;
}

body.dark .city-autocomplete::after {
	background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3C/svg%3E");
}

body.dark .city-dropdown {
	background: #222333;
	border-color: rgba(255, 255, 255, 0.1);
	box-shadow: 0 0.4rem 1.2rem rgba(0, 0, 0, 0.25);
}

body.dark .city-group {
	background-color: #1a1a2a;
	color: #999;
	border-bottom-color: rgba(255, 255, 255, 0.1);
}

body.dark .city-item {
	border-bottom-color: rgba(255, 255, 255, 0.1);
	color: #e3e7eb;
}

body.dark .city-item:hover {
	background: #E7612F;
	color: #fff;
}

body.dark .city-item-sub {
	color: #999;
}

body.dark .city-message {
	background: #222333;
	color: #999;
}
/* Общие стили для контейнеров */
.levelmark-row {
	display: flex;
	justify-content: center;
	flex-wrap: wrap;
	margin: 1rem -0.6rem;
	gap: 0;
}

.levelmark {
	flex: 1 1 calc(14.28% - 1rem);
	min-width: 11rem;
	margin: 0.5rem;
	padding: 0.6rem 1rem;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: space-between;
	text-align: center;
	border-radius: 1rem;
	cursor: pointer;
	transition: all 0.2s ease;
	font-size: 1.4rem;
	line-height: 1.4;
	box-shadow: 0 0.2rem 0.4rem rgba(0, 0, 0, 0.03);
}

/* 1. НЕВОЗМОЖНЫЕ варианты (нельзя выбрать), нельзя кликнуть */
.levelmark--disabled,
.levelmark--age-restricted {
	cursor: not-allowed;
	pointer-events: none;
}											
.levelmark--disabled {
	opacity: 0.4;
	filter: grayscale(0.6);
}

/* 2. Возможные варианты (можно выбрать) - по умолчанию полупрозрачные */
.levelmark:not(.levelmark--disabled):not(.levelmark--age-restricted) {
	opacity: 0.4;
	cursor: pointer;
	pointer-events: auto;
}

/* 3. Выбранный вариант - полностью непрозрачный */
.levelmark--selected {
	opacity: 1 !important;
	filter: grayscale(0) !important;
}

/* 4. Ховер для возможных вариантов */
.levelmark:not(.levelmark--disabled):not(.levelmark--age-restricted):hover {
	opacity: 1; 
	box-shadow: 0 0.4rem 1.2rem rgba(0, 0, 0, 0.08);
	transform: translateY(-0.4rem);
}
.levelmark--age-restricted {
	position: relative;
}
.levelmark--age-restricted::after {
	content: "";
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(255,255,255,0.3);
	backdrop-filter: grayscale(0.5);	
}
body.dark .levelmark--age-restricted::after {
	background: rgba(0,0,0,0.3);
}
.levelmark--age-restricted .level-number::after {
	content: "🔒";
	position: absolute;
	top: 0.4rem;
	right: 0.4rem;
	font-size: 1.6rem;
	filter: grayscale(0);
	z-index: 2;
}
.level-number {
	font-weight: 700;
	font-size: 2rem;
	margin-bottom: 0.2rem;
}
.level-name {
	font-size: 12px;
	line-height: 1.1;
	min-height: 20px;
	text-transform: uppercase;
}
@media screen and (max-width: 991px) {
	.level-name {
		font-size: 12px;
	}
}
@media screen and (max-width:767px) {
	.level-name {
		font-size: 11px;
	}
}
/* Ошибки */
.level-error {
	color: #ef4444;
	font-weight: 600;
	font-size: 1.4rem;
	margin-top: 0.5rem;
}
.hideselect .form-select-wrapper {
	display: none;
}						
						
					</style>
				</x-slot>
				
				<div class="container">
					
					
					{{-- ========================= FLASH / ERRORS ========================= --}}
					@if (session('status'))
					<div class="ramka">	
						<div class="alert alert-success">
							{{ session('status') }}
						</div>
					</div>
					@endif
					@if (session('error'))
					<div class="ramka">		
						<div class="alert alert-error">
							{{ session('error') }}
						</div>
					</div>
					@endif
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
					
					{{-- ========================= REQUIRED KEYS ========================= --}}
					@if (!empty($requiredKeys))
					<div class="ramka">			
						<div class="alert alert-error">
							<div class="alert-title">Перед записью заполните:</div>
							<ul class="list">
								@foreach ($requiredKeys as $key)
								<li>
									@switch($key)
									@case('full_name') Фамилия и имя @break
									@case('patronymic') Отчество @break
									@case('phone') Телефон @break
									@case('city') Город @break
									@case('birth_date') Дата рождения @break
									@case('gender') Пол @break
									@case('height_cm') Рост @break
									@case('classic_level') Уровень (классика) @break
									@case('beach_level') Уровень (пляж) @break
									@default {{ $key }}
									@endswitch
								</li>
								@endforeach
							</ul>
						</div>
						@if (!empty($eventId))
						<div class="mb-1">
							После сохранения профиля мы попробуем автоматически записать вас на мероприятие.
						</div>
						@endif
					</div>
					@endif				
					
					
					
					<div class="row">
						<div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
							<div class="sticky">
								<div class="card-ramka mb-2">
									<div class="row">
										<div class="col-3 col-lg-12">
											<div class="profile-avatar">
												<img
												src="{{ $user->profile_photo_url }}"
												alt="avatar"
												class="avatar"
												/>			
											</div>
										</div>
										<div class="col-9 col-lg-12">
											<nav class="menu-nav">
												<a href="{{ route('profile.show') }}" class="menu-item">
													<span class="menu-text">Ваш профиль</span>
												</a>
												<a href="{{ url('/profile/complete') }}" class="menu-item active">
													<strong class="cd menu-text">Редактировать профиль</strong>
												</a>
												<a href="{{ route('user.photos') }}" class="menu-item">
													<span class="menu-text">Ваши фотографии</span>
												</a>
												<form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
													@csrf
													<button type="submit" class="menu-item">Выйти</button>
												</form>							
											</nav>	
										</div>	
									</div>
								</div>
								<div class="card-ramka mb-2">
									<p>Для сохранения результатов радактирования нажмите:</p>
									<div class="text-center">
										<button type="submit" form="profile-complete-form" class="btn">Сохранить</button>
									</div>
								</div>
								
							</div> 	
						</div> 
						<div class="col-lg-8 col-xl-9 order-1">
							<div class="form">
								<div class="ramka" style="z-index:10">				
									
									{{-- ========================= MAIN ========================= --}}
									
									<form id="profile-complete-form" method="POST" action="{{ route('profile.extra.update') }}">
										@csrf
										
										{{-- ========================= PERSONAL DATA ========================= --}}
										
										
										
										<h2 class="-mt-05">Персональные данные</h2>
										
										<div class="row">
											{{-- -------- Фамилия -------- --}}
											@php $lockedLast = !$canEditProtected && $filled($user?->last_name); @endphp
											<div class="col-sm-6">
												<div class="card">
													<label>
														<div>Фамилия</div>
														<div class="f-16 b-500">Видно всем пользователям</div>
													</label>
													<input
													name="last_name"
													class="{{ $errors->has('last_name') ? 'input-error' : '' }}"
													value="{{ old('last_name', $user?->last_name) }}"
													data-ru-name
													autocomplete="family-name"
													@disabled($lockedLast)
													>
													<ul class="list f-16 mt-1">
														@error('last_name')<li class="red b-600">{{ $message }}</li>@enderror
														<li>Кириллица, ≥2 символов, с заглавной</li>
														@if($lockedLast)<li>{{ $lockHint }}</li>@endif
													</ul>
												</div>
											</div>
											{{-- -------- Имя -------- --}}
											@php $lockedFirst = !$canEditProtected && $filled($user?->first_name); @endphp
											<div class="col-sm-6">
												<div class="card">
													<label>
														<div>Имя</div>
														<div class="f-16 b-500">Видно всем пользователям</div>
													</label>											
													<input
													name="first_name"
													class="{{ $errors->has('first_name') ? 'input-error' : '' }}"
													value="{{ old('first_name', $user?->first_name) }}"
													data-ru-name
													autocomplete="given-name"
													@disabled($lockedFirst)
													>
													<ul class="list f-16 mt-1">
														@error('first_name')<li class="red b-600">{{ $message }}</li>@enderror
														<li>Кириллица, ≥2 символов, с заглавной</li>
														@if($lockedFirst)<li>{{ $lockHint }}</li>@endif
													</ul>												
												</div>
											</div>
											{{-- -------- Отчество -------- --}}
											@php $lockedPat = !$canEditProtected && $filled($user?->patronymic); @endphp
											<div class="col-sm-6">
												<div class="card">
													<label>
														<div>Отчество</div>
														<div class="cd f-16 b-500">Видно только организаторам</div>
													</label>
													
													<input
													name="patronymic"
													class="{{ $errors->has('patronymic') ? 'input-error' : '' }}"
													value="{{ old('patronymic', $user?->patronymic) }}"
													data-ru-name
													autocomplete="additional-name"
													@disabled($lockedPat)
													>
													
													<ul class="list f-16 mt-1">
														@error('patronymic')<li class="red b-600">{{ $message }}</li>@enderror
														<li>Кириллица, ≥2 символов, с заглавной</li>
														@if($lockedPat)<li>{{ $lockHint }}</li>@endif
													</ul>													
												</div>
											</div>
											
											{{-- -------- Телефон -------- --}}
											@php $lockedPhone = !$canEditProtected && $filled($user?->phone); @endphp
											<div class="col-sm-6">
												<div class="card">
													<label>
														<div>Телефон</div>
														<div class="cd f-16 b-500">Видно только организаторам</div>
													</label>
													
													
													<input
													name="phone_masked"
													id="phone_masked"
													value="{{ $phoneMaskedPrefill }}"
													class="{{ $errors->has('phone') ? 'input-error' : '' }}"
													placeholder="+7 (___) ___-__-__"
													inputmode="tel"
													autocomplete="tel"
													@disabled($lockedPhone)
													>
													
													<input
													type="hidden"
													name="phone"
													id="phone_e164"
													value="{{ old('phone', $user?->phone) }}"
													>
													
													<ul class="list f-16 mt-1">
														@error('phone')<li class="red b-600">{{ $message }}</li>@enderror
														@if($lockedPhone)<li>{{ $lockHint }}</li>@endif
													</ul>													
												</div>
											</div>
											{{-- -------- Дата рождения -------- --}}
											@php $lockedBirth = !$canEditProtected && $filled($user?->birth_date); @endphp
											<div class="col-sm-6">
												<div class="card">
													<label>
														<div>Дата рождения</div>
														<div class="f-16 b-500">Видно всем пользователям</div>
													</label>													
													
													@php
													$birthValue = old('birth_date');
													if ($birthValue === null) {
													$birthValue = $user?->birth_date ? $user->birth_date->format('Y-m-d') : '';
													}
													@endphp
													<input
													type="date"
													name="birth_date"
													class="{{ $errors->has('birth_date') ? 'input-error' : '' }}"
													value="{{ $birthValue }}"
													@disabled($lockedBirth)
													>
													<ul class="list f-16 mt-1">
														@error('birth_date')<li class="red b-600">{{ $message }}</li>@enderror
														@if($lockedBirth)<li>{{ $lockHint }}</li>@endif
													</ul>														
												</div>
											</div>
											{{-- -------- Город (AUTOCOMPLETE + fallback select) -------- --}}
											@php $lockedCity = !$canEditProtected && $filled($user?->city_id); @endphp
											<div class="col-sm-6">
												<div class="card">
													<label>
														<div>Город</div>
														<div class="f-16 b-500">Видно всем пользователям</div>
													</label>												
													
													{{-- То, что реально сохраняем --}}
													<input
													type="hidden"
													name="city_id"
													id="city_id"
													value="{{ old('city_id', $user?->city_id) }}"
													>
													
													{{-- UI input (поиск) --}}
													<div class="city-autocomplete" id="city-autocomplete" data-search-url="{{ route('cities.search') }}">
														<input
														type="text"
														id="city_search"
														placeholder="Начните вводить город…"
														value="{{ old('city_search', $selectedCityLabel) }}"
														autocomplete="off"
														@disabled($lockedCity)
														>
														
														{{-- dropdown --}}
														<div id="city_dropdown" class="city-dropdown">
															<div id="city_results"></div>
														</div>
													</div>
													
													<ul class="list f-16 mt-1">
														@error('city_id')<li class="red b-600">{{ $message }}</li>@enderror
														@if($lockedCity)<li>{{ $lockHint }}</li>@else <li>Введите минимум 2 символа для поиска.</li> @endif
													</ul>
													
													
												</div>
											</div>
											{{-- -------- Пол (НЕ фиксируемый) -------- --}}
											<div class="col-sm-6">
												<div class="card">
													<label>
														<div>Пол</div>
														<div class="f-16 b-500">Видно всем пользователям</div>
													</label>	
													<select name="gender" class="{{ $errors->has('gender') ? 'input-error' : '' }}">
														<option value="">— не указан —</option>
														<option value="m" @selected(old('gender', $user?->gender) === 'm')>Мужчина</option>
														<option value="f" @selected(old('gender', $user?->gender) === 'f')>Женщина</option>
													</select>
													
													<ul class="list f-16 mt-1">
														@error('gender')<li class="red b-600">{{ $message }}</li>@enderror
														{{--	@if($lockedGender)<li>{{ $lockHint }}</li>@endif --}}
													</ul>
												</div>
											</div>	
											{{-- -------- Рост (НЕ фиксируемый) -------- --}}
											<div class="col-sm-6">
												<div class="card">
													<label>
														<div>Рост (см)</div>
														<div class="f-16 b-500">Видно всем пользователям</div>
													</label>	
													<input
													type="number"
													name="height_cm"
													min="40"
													max="230"
													class="{{ $errors->has('height_cm') ? 'input-error' : '' }}"
													value="{{ old('height_cm', $user?->height_cm) }}"
													>
													<ul class="list f-16 mt-1">
														@error('height_cm')<li class="red b-600">{{ $message }}</li>@enderror
														<li>Допустимый диапазон: 40–230 см.</li>
													</ul>													
												</div>
											</div>
										</div>
									</div>
									
									{{-- ========================= SKILLS ========================= --}}
									
									
									
									@php
									$lockedClassic = !$canEditProtected && $filled($user?->classic_level);
									$lockedBeach = !$canEditProtected && $filled($user?->beach_level);
									
									$currentClassicLevel = old('classic_level', $user?->classic_level);
									$currentBeachLevel = old('beach_level', $user?->beach_level);
									
									// Определяем доступные уровни по возрасту
									$birthDate = $birthValue ? \Carbon\Carbon::parse($birthValue) : null;
									$age = $birthDate ? $birthDate->age : null;
									
									$availableLevels = [1,2,3,4,5,6,7]; // все доступны по умолчанию
									$ageRestrictionMessage = '';
									
									if (!$birthDate) {
									$ageRestrictionMessage = 'Сначала укажите дату рождения';
									$availableLevels = []; // ничего не доступно
									} elseif ($age < 18) {
									$availableLevels = [1,2,4]; // только эти для младше 18
									$ageRestrictionMessage = 'Доступны только уровни 1, 2, 4';
									}
									

										
										// Конфигурация уровней
										$levelConfigs = [
										'classic' => [
										'name' => 'Уровень',
										'locked' => $lockedClassic,
										'current' => $currentClassicLevel,
										'select_id' => 'classic_level_select',
										'wrap_id' => 'levelmark-wrap-classic',
										],
										'beach' => [
										'name' => 'Уровень (пляж)',
										'locked' => $lockedBeach,
										'current' => $currentBeachLevel,
										'select_id' => 'beach_level_select',
										'wrap_id' => 'levelmark-wrap-beach',
										],
										];
										@endphp
										
										
										
										
										<div class="ramka">	
											
											{{-- -------- Classic -------- --}}
											
											<h2 class="-mt-05">Классический волейбол</h2>
											<div class="row">		
												<div class="col-12">		
													<div class="hideselect">									
														<label>
															<div>Уровень</div>
															<div class="f-16 b-500">Видно всем пользователям</div>
														</label>	
														
														{{-- Скрытый оригинальный select --}}
														<select
														name="classic_level"
														id="classic_level_select"
														style="display: none;"
														@disabled($lockedClassic)
														>
															<option value="">— выберите —</option>
															@foreach($levels as $lvl)
															<option value="{{ $lvl }}" @selected((string)$currentClassicLevel === (string)$lvl)>{{ $lvl }}</option>
															@endforeach
														</select>
														<ul class="list f-16 mt-1">
															<li><a href="/level_players">Подробная информация об уровнях игроков</a></li>
														</ul>
														{{-- Кнопки уровней --}}
														<div class="levelmark-row" id="levelmark-wrap-classic" data-type="classic">
															@foreach(range(1, 7) as $lvl)
															@php
															$isDisabled = $lockedClassic || !in_array($lvl, $availableLevels);
															$isAgeRestricted = !$lockedClassic && !in_array($lvl, $availableLevels) && $birthDate && $age < 18;
															$isSelected = !$isDisabled && (string)$currentClassicLevel === (string)$lvl;
															
															$classes = ['levelmark'];
															$classes[] = 'level-' . $lvl;
															if ($isSelected) $classes[] = 'levelmark--selected';
															if ($isDisabled) $classes[] = 'levelmark--disabled';
															if ($isAgeRestricted) $classes[] = 'levelmark--age-restricted';
															@endphp
															
															<div 
															class="{{ implode(' ', $classes) }}"
															data-level="{{ $lvl }}"
															data-available="{{ in_array($lvl, $availableLevels) ? 'true' : 'false' }}"
															data-selected="{{ $isSelected ? 'true' : 'false' }}"
															data-type="classic"
															@if($isDisabled) aria-disabled="true" @endif
															>
																<div class="level-number">{{ $lvl }}</div>
																<div class="level-name">{{ level_name($lvl) }}</div>
															</div>
															@endforeach
														</div>
														
														<ul class="list f-16 mt-1">
															@error('classic_level')
															<li class="red b-600">{{ $message }}</li>
															@else
															@if($lockedClassic)
															<li>{{ $lockHint }}</li>
															<li>Ваш уровень: <strong class="cd">{{ $user->classic_level }}</strong></li>
															@elseif($ageRestrictionMessage)
															<li class="level-mes">{{ $ageRestrictionMessage }}</li> {{-- Если не заблокировано и есть возрастные ограничения --}}
															@else
															<li style="display: none" class="level-mes">{{ $ageRestrictionMessage }}</li>
															@endif
															@enderror
														</ul>  
													</div>							
												</div>								
												
												<div class="col-sm-6">
													<div class="card">
														<label>Основное амплуа</label>
														<div class="space-y-1">
															@foreach($posMap as $key => $label)
															<label class="radio-item">
																<input type="radio" name="classic_primary_position" value="{{ $key }}"
																@checked(old('classic_primary_position', $classicPrimary) === $key)>
																<div class="custom-radio"></div>
																<span>{{ $label }}</span>
															</label>
															@endforeach
														</div>
													</div>	
												</div>
												
												
												<div class="col-sm-6">
													<div class="card">
														<label>Дополнительное амплуа</label>
														@php $primaryNow = old('classic_primary_position', $classicPrimary); @endphp
														<div class="space-y-1">
															@foreach($posMap as $key => $label)
															@php
															$checked  = in_array($key, (array)old('classic_extra_positions', $classicAll), true);
															$disabled = ($primaryNow === $key);
															@endphp
															<label class="checkbox-item">
																<input type="checkbox" name="classic_extra_positions[]" value="{{ $key }}"
																@checked($checked) @disabled($disabled)>
																<div class="custom-checkbox"></div>
																<span>{{ $label }}
																	@if($disabled)<span class="f-15">(основное)</span>@endif
																</span>
															</label>
															@endforeach
														</div>
													</div>	
												</div>
											</div>
											
										</div>
										<div class="ramka">		
											
											<h2 class="-mt-05">Пляжный волейбол</h2>
											
											<label>
												<div>Уровень</div>
												<div class="f-16 b-500">Видно всем пользователям</div>
											</label>
											
											{{-- -------- Beach -------- --}}
											<div class="row">
												<div class="col-12">
													<div class="hideselect">				
														{{-- Скрытый оригинальный select --}}
														<select
														name="beach_level"
														id="beach_level_select"
														style="display: none;"
														@disabled($lockedBeach)
														>
															<option value="">— выберите —</option>
															@foreach($levels as $lvl)
															<option value="{{ $lvl }}" @selected((string)$currentBeachLevel === (string)$lvl)>{{ $lvl }}</option>
															@endforeach
														</select>
														
														<ul class="list f-16 mt-1">
															<li><a href="/level_players">Подробная информация об уровнях игроков</a></li>
														</ul>
														
														{{-- Кнопки уровней --}}
														<div class="levelmark-row" id="levelmark-wrap-beach" data-type="beach">
															@foreach(range(1, 7) as $lvl)
															@php
															$isDisabled = $lockedBeach || !in_array($lvl, $availableLevels);
															$isAgeRestricted = !$lockedBeach && !in_array($lvl, $availableLevels) && $birthDate && $age < 18;
															$isSelected = !$isDisabled && (string)$currentBeachLevel === (string)$lvl;
															
															$classes = ['levelmark'];
															$classes[] = 'level-' . $lvl;
															if ($isSelected) $classes[] = 'levelmark--selected';
															if ($isDisabled) $classes[] = 'levelmark--disabled';
															if ($isAgeRestricted) $classes[] = 'levelmark--age-restricted';
															@endphp
															
															<div 
															class="{{ implode(' ', $classes) }}"
															data-level="{{ $lvl }}"
															data-available="{{ in_array($lvl, $availableLevels) ? 'true' : 'false' }}"
															data-selected="{{ $isSelected ? 'true' : 'false' }}"
															data-type="beach"
															@if($isDisabled) aria-disabled="true" @endif
															>
																<div class="level-number">{{ $lvl }}</div>
																<div class="level-name">{{ level_name($lvl) }}</div>
															</div>
															@endforeach
														</div>
														
														<ul class="list f-16 mt-1">
															@error('beach_level')
															<li class="red b-600">{{ $message }}</li>
															@else
															@if($lockedBeach)
															<li>{{ $lockHint }}</li> 
															<li>Ваш уровень: <strong class="cd">{{ $user->beach_level }}</strong></li>
															@elseif($ageRestrictionMessage)
															<li class="level-mes">{{ $ageRestrictionMessage }}</li> {{-- Если не заблокировано и есть возрастные ограничения --}}
															@else
															<li style="display: none" class="level-mes">{{ $ageRestrictionMessage }}</li>
															@endif
															@enderror
														</ul>
													</div>
												</div>
												<div class="col-sm-6">
													<label class="block mb-1 font-medium">В какой зоне вы играете: 2, 4 или вы универсал?</label>
													<div class="space-y-1">
														<label class="flex items-center gap-2">
															<input type="radio" name="beach_mode" value="2" @checked(old('beach_mode', $beachModeCurrent) === '2')>
															<span>Зона 2</span>
														</label>
														<label class="flex items-center gap-2">
															<input type="radio" name="beach_mode" value="4" @checked(old('beach_mode', $beachModeCurrent) === '4')>
															<span>Зона 4</span>
														</label>
														<label class="flex items-center gap-2">
															<input type="radio" name="beach_mode" value="universal" @checked(old('beach_mode', $beachModeCurrent) === 'universal')>
															<span>Универсал</span>
														</label>
														<div class="v-hint mt-2">Если выбран “Универсал”, отметим зоны 2 и 4 и поставим пометку “универсальный игрок”.</div>
													</div>
												</div>
											</div>
											
										</div>
										
										
										<div class="card-ramka mb-2 text-center">	
											<button type="submit" class="btn">Сохранить</button>
										</div>
									</form>
									
									{{-- ========================= ORGANIZER REQUEST ========================= --}}
									@auth
									@if (($user->role ?? 'user') === 'user')
									<div class="v-card mt-8">
										<div class="v-card__body">
											<div class="font-semibold text-lg mb-2">Хочу стать организатором мероприятий</div>
											<div class="text-sm text-gray-600 mb-4">
												Организатор может создавать мероприятия, управлять участниками и назначать помощников.
											</div>
											
											@if (!empty($hasPendingOrganizerRequest))
											<div class="v-alert v-alert--info">
												<div class="v-alert__text">Ваша заявка уже отправлена и ожидает рассмотрения.</div>
											</div>
											@else
											<form method="POST" action="{{ route('organizer.request') }}">
												@csrf
												<div class="mb-3">
													<label class="block text-sm font-medium mb-1">Комментарий (необязательно)</label>
													<textarea name="message" rows="3" class="v-input w-full"
													placeholder="Например: регулярно организую игры и хочу делать это через Volley"></textarea>
												</div>
												<button type="submit" class="v-btn v-btn--primary">Отправить заявку</button>
											</form>
											@endif
										</div>
									</div>
									@endif
									@endauth
								</div>
								
								{{-- ========================= SIDEBAR (desktop) ========================= --}}
								
								
								{{--
								ФИО — кириллица, минимум 2 символа, “С Заглавной”.
								Если введёте латиницей — автоматически переведём в кириллицу.
								Телефон сохраняем как <b>+7XXXXXXXXXX</b>.
								Город выбирайте из подсказок (так гарантируем корректный <b>city_id</b>).
								--}}
								
							</div> 	
						</div> 	
					</div> 	
				</div> 							
				
				{{-- ============================================================
				JS:
				- ФИО: нормализация
				- Телефон: маска на blur + hidden E.164
				- Город: автокомплит (GET cities.search?q=...)
				============================================================ --}}
				
				
				
				<x-slot name="script">	
					<script>
						(function () {
							
							// Конфигурация для обоих типов уровней
							const levelTypes = ['classic', 'beach'];
							
							// Получаем все элементы
							const selects = {};
							const wraps = {};
							
							levelTypes.forEach(type => {
								selects[type] = document.getElementById(type + '_level_select');
								wraps[type] = document.getElementById('levelmark-wrap-' + type);
							});
							
							const birthInput = document.querySelector('input[name="birth_date"]');
							const isClassicLocked = {{ $lockedClassic ? 'true' : 'false' }};
							const isBeachLocked = {{ $lockedBeach ? 'true' : 'false' }};
							
							// Функция расчета возраста
							function calculateAge(birthDate) {
								const today = new Date();
								const birth = new Date(birthDate);
								let age = today.getFullYear() - birth.getFullYear();
								const m = today.getMonth() - birth.getMonth();
								if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
									age--;
								}
								return age;
							}
							
							// Функция обновления сообщения в конкретном ul
							function updateLevelMessage(type, message) {
								const wrap = wraps[type];
								if (!wrap) return;
								
								// Ищем следующий ul после wrap
								const ul = wrap.nextElementSibling;
								if (!ul || !ul.classList.contains('list')) return;
								
								// Ищем li с классом level-mes
								const mesLi = ul.querySelector('li.level-mes');
								
								if (message) {
									// Если есть сообщение - показываем и пишем
									if (mesLi) {
										mesLi.textContent = message;
										mesLi.style.display = ''; // показываем
									}
									} else {
									// Если сообщения нет - прячем, но не удаляем
									if (mesLi) {
										mesLi.style.display = 'none'; // просто скрываем
									}
								}
							}
							
							// Функция обновления доступных уровней по возрасту
							function updateAvailableLevels() {
								const birthValue = birthInput ? birthInput.value : null;
								let available = [1,2,3,4,5,6,7];
								let message = '';
								
								if (!birthValue) {
									available = [];
									message = 'Сначала укажите дату рождения';
									} else {
									const age = calculateAge(birthValue);
									if (age < 18) {
										available = [1,2,4];
										message = 'Доступны только уровни 1, 2, 4';
									}
								}
								
								// Обновляем кнопки для обоих типов
								levelTypes.forEach(type => {
									const wrap = wraps[type];
									if (!wrap) return;
									
									const isLocked = type === 'classic' ? isClassicLocked : isBeachLocked;
									
									// Обновляем сообщение
									updateLevelMessage(type, message);
									
									// Если поле заблокировано - не трогаем кнопки
									if (isLocked) return;
									
									wrap.querySelectorAll('.levelmark').forEach(btn => {
										const level = parseInt(btn.dataset.level);
										const isAvailable = available.includes(level);
										
										// Убираем все классы блокировки
										btn.classList.remove('levelmark--disabled', 'levelmark--age-restricted');
										
										if (!isAvailable) {
											// Если недоступно - добавляем age-restricted
											btn.classList.add('levelmark--age-restricted');
											
											// Если кнопка была выбрана - снимаем выделение
											if (btn.classList.contains('levelmark--selected')) {
												btn.classList.remove('levelmark--selected');
												btn.dataset.selected = 'false';
												selects[type].value = '';
											}
										}
										
										// Обновляем data-атрибут
										btn.dataset.available = isAvailable ? 'true' : 'false';
									});
								});
							}
							
							// Обработчик клика по кнопкам
							levelTypes.forEach(type => {
								const wrap = wraps[type];
								if (!wrap) return;
								
								wrap.addEventListener('click', (e) => {
									const btn = e.target.closest('.levelmark');
									if (!btn) return;
									
									const isLocked = type === 'classic' ? isClassicLocked : isBeachLocked;
									if (isLocked) return;
									
									const level = btn.dataset.level;
									const isAvailable = btn.dataset.available === 'true';
									
									if (!isAvailable) return;
									
									// Убираем выделение у всех
									wrap.querySelectorAll('.levelmark').forEach(b => {
										b.classList.remove('levelmark--selected');
										b.dataset.selected = 'false';
									});
									
									// Выделяем текущую
									btn.classList.add('levelmark--selected');
									btn.dataset.selected = 'true';
									
									// Устанавливаем значение в select
									selects[type].value = level;
									selects[type].dispatchEvent(new Event('change', { bubbles: true }));
									
									// Если выбрали уровень - убираем сообщение (опционально)
									// updateLevelMessage(type, '');
								});
							});
							
							// Слушаем изменения даты рождения
							if (birthInput) {
								birthInput.addEventListener('change', updateAvailableLevels);
								birthInput.addEventListener('blur', updateAvailableLevels);
								birthInput.addEventListener('input', function() {
									if (!this.value) {
										updateAvailableLevels();
									}
								});
							}
							
							// Инициализация при загрузке
							updateAvailableLevels();
							
							// Если уже выбран уровень, проверяем его доступность
							levelTypes.forEach(type => {
								const currentValue = selects[type]?.value;
								if (currentValue) {
									const selectedBtn = wraps[type]?.querySelector(`.levelmark[data-level="${currentValue}"]`);
									if (selectedBtn && selectedBtn.dataset.available === 'true') {
										selectedBtn.classList.add('levelmark--selected');
										selectedBtn.dataset.selected = 'true';
										} else if (selectedBtn) {
										// Если кнопка есть но недоступна - сбрасываем select
										selects[type].value = '';
									}
								}
							});				
							
							
							
							
							
							// ---------- UI helper ----------
							function setInvalid(el, isInvalid) {
								if (!el) return;
								if (isInvalid) el.classList.add('input-error');
								else el.classList.remove('input-error');
							}
							
							// ---------- Translit + name normalize ----------
							const translitMap = {
								'sch':'щ','yo':'ё','zh':'ж','kh':'х','ts':'ц','ch':'ч','sh':'ш','yu':'ю','ya':'я',
								'a':'а','b':'б','v':'в','g':'г','d':'д','e':'е','z':'з','i':'и','j':'й','k':'к',
								'l':'л','m':'м','n':'н','o':'о','p':'п','r':'р','s':'с','t':'т','u':'у','f':'ф',
								'h':'х','c':'к','y':'ы','w':'в','q':'к','x':'кс'
							};
							
							function translitLatinToCyr(input) {
								let s = String(input || '').trim();
								if (!s) return s;
								if (!/[A-Za-z]/.test(s)) return s;
								
								let out = '';
								let i = 0;
								const lower = s.toLowerCase();
								
								while (i < lower.length) {
									const ch = lower[i];
									if (!/[a-z]/.test(ch)) { out += s[i]; i++; continue; }
									
									const tri = lower.slice(i, i+3);
									const bi  = lower.slice(i, i+2);
									
									if (translitMap[tri]) { out += translitMap[tri]; i += 3; continue; }
									if (translitMap[bi])  { out += translitMap[bi];  i += 2; continue; }
									
									out += translitMap[ch] || ch;
									i++;
								}
								return out;
							}
							
							function normalizeCyrName(input) {
								let s = String(input || '').trim();
								if (!s) return s;
								
								if (/[A-Za-z]/.test(s)) s = translitLatinToCyr(s);
								
								s = s.replace(/\s+/g, ' ');
								s = s.replace(/[’]/g, "'");
								s = s.replace(/-{2,}/g, '-');
								s = s.replace(/[^А-Яа-яЁё \-']/g, '');
								
								const parts = s.split(/(\s+|-|')/);
								return parts.map(part => {
									if (part === ' ' || part === '-' || part === "'" || /^\s+$/.test(part)) return part;
									const p = part.toLowerCase();
									if (!p) return p;
									return p.charAt(0).toUpperCase() + p.slice(1);
								}).join('');
							}
							
							function isValidCyrName(value) {
								const v = String(value || '').trim();
								if (!v) return true;
								if (v.length < 2) return false;
								return /^[А-Яа-яЁё \-']+$/.test(v);
							}
							
							// ---------- Phone helpers ----------
							function digitsOnly(s) { return String(s || '').replace(/\D/g, ''); }
							
							function toE164Ru(raw) {
								let d = digitsOnly(raw);
								if (d.length === 11 && d.startsWith('8')) d = '7' + d.slice(1);
								if (d.length === 11 && d.startsWith('7')) return '+7' + d.slice(1);
								if (d.length === 10) return '+7' + d;
								if (d.length === 0) return '';
								return '+' + d;
							}
							
							function formatMaskFromDigits(raw) {
								let d = digitsOnly(raw);
								if (d.startsWith('7') || d.startsWith('8')) d = d.slice(1);
								d = d.slice(0, 10);
								
								const a = d.slice(0,3), b = d.slice(3,6), c = d.slice(6,8), e = d.slice(8,10);
								let out = '+7';
								if (a.length) out += ' (' + a;
								if (a.length < 3) return out;
								out += ')';
								if (b.length) out += ' ' + b;
								if (b.length < 3) return out;
								if (c.length) out += '-' + c;
								if (c.length < 2) return out;
								if (e.length) out += '-' + e;
								return out;
							}
							
							// ---------- Names init ----------
							const nameInputs = document.querySelectorAll('[data-ru-name]');
							nameInputs.forEach((inp) => {
								if (inp.disabled) return;
								
								inp.addEventListener('blur', () => {
									if (inp.disabled) return;
									inp.value = normalizeCyrName(inp.value);
									setInvalid(inp, !isValidCyrName(inp.value));
								});
								
								inp.addEventListener('input', () => {
									if (inp.disabled) return;
									if (/[A-Za-z]/.test(inp.value)) {
										inp.value = normalizeCyrName(inp.value);
									}
								});
							});
							
							// ---------- Phone init ----------
							const phoneMasked = document.getElementById('phone_masked');
							const phoneE164 = document.getElementById('phone_e164');
							
							if (phoneMasked && phoneE164) {
								phoneMasked.addEventListener('input', () => {
									if (phoneMasked.disabled) return;
									phoneE164.value = toE164Ru(phoneMasked.value);
								});
								
								phoneMasked.addEventListener('blur', () => {
									if (phoneMasked.disabled) return;
									phoneE164.value = toE164Ru(phoneMasked.value);
									phoneMasked.value = formatMaskFromDigits(phoneMasked.value);
								});
							}
							
							
							// ---------- City autocomplete (стилизованный под селект) ----------
							const cityWrap = document.getElementById('city-autocomplete');
							const cityInput = document.getElementById('city_search');
							const cityId = document.getElementById('city_id');
							const dd = document.getElementById('city_dropdown');
							const results = document.getElementById('city_results');
							
							const CITY_CONFIG = {
								showCountry: {{ $cityDisplayConfig['showCountry'] ? 'true' : 'false' }},
								showRegion: {{ $cityDisplayConfig['showRegion'] ? 'true' : 'false' }},
								inputShowCountry: {{ $cityDisplayConfig['inputShowCountry'] ? 'true' : 'false' }},
								inputShowRegion: {{ $cityDisplayConfig['inputShowRegion'] ? 'true' : 'false' }}
							};
							
							
							function extractCityNameForSearch(fullLabel) {
								if (!fullLabel) return '';
								
								// Просто берем всё до первой скобки
								const matches = fullLabel.match(/^([^(]+)/);
								return matches ? matches[1].trim() : fullLabel.trim();
							}						
							
							
							
							
							
							function escapeHtml(s) {
								return String(s || '')
								.replace(/&/g, '&amp;')
								.replace(/</g, '&lt;')
								.replace(/>/g, '&gt;')
								.replace(/"/g, '&quot;')
								.replace(/'/g, '&#039;');
							}
							
							function showDropdown() {
								if (!dd) return;
								dd.classList.add('active');
							}
							
							function hideDropdown() {
								if (!dd) return;
								dd.classList.remove('active');
							}
							
							function clearResults() {
								if (results) results.innerHTML = '';
							}
							
							function renderGroup(title, items) {
								const html = [];
								html.push('<div class="city-group">' + escapeHtml(title) + '</div>');
								
								items.forEach(item => {
									// Формируем подпись под городом (страна и регион)
									const subParts = [];
									if (CITY_CONFIG.showCountry && item.country_code) {
										subParts.push(escapeHtml(item.country_code));
									}
									if (CITY_CONFIG.showRegion && item.region) {
										subParts.push(escapeHtml(item.region));
									}
									const subText = subParts.length ? ' • ' + subParts.join(' • ') : '';
									
									html.push(
									'<button type="button" class="city-item" ' +
									'data-id="' + escapeHtml(item.id) + '" ' +
									'data-name="' + escapeHtml(item.name) + '" ' +
									'data-country="' + escapeHtml(item.country_code || '') + '" ' +
									'data-region="' + escapeHtml(item.region || '') + '">' +
									'<div class="city-item-name">' + escapeHtml(item.name) + '</div>' +
									(subText ? '<div class="city-item-sub">' + subText + '</div>' : '') +
									'</button>'
									);
								});
								
								return html.join('');
							}
							
							let lastReqId = 0;
							
							function debounce(fn, ms) {
								let t = null;
								return function (...args) {
									clearTimeout(t);
									t = setTimeout(() => fn.apply(this, args), ms);
								};
							}
							
							async function fetchCities(q) {
								if (!cityWrap) return null;
								const url = cityWrap.getAttribute('data-search-url');
								if (!url) return null;
								
								const reqId = ++lastReqId;
								
								const u = new URL(url, window.location.origin);
								u.searchParams.set('q', q || '');
								u.searchParams.set('limit', '30');
								
								const r = await fetch(u.toString(), {
									headers: { 'Accept': 'application/json' },
									credentials: 'same-origin'
								});
								
								if (reqId !== lastReqId) return null;
								if (!r.ok) return null;
								return await r.json();
							}
							
							function applySelected(id, name, countryCode, region) {
								if (cityId) cityId.value = id ? String(id) : '';
								
								if (cityInput) {
									let displayName = name;
									
									const parts = [];
									if (CITY_CONFIG.inputShowCountry && countryCode) {
										parts.push(countryCode);
									}
									if (CITY_CONFIG.inputShowRegion && region) {
										parts.push(region);
									}
									
									if (parts.length) {
										displayName = name + ' (' + parts.join(', ') + ')';
									}
									
									cityInput.value = displayName || '';
								}
								
								hideDropdown();
							}
							
							// Функция для обновления отображения при загрузке
							function updateDisplayFromSelected() {
								if (!cityInput || !cityId) return;
								
								const selectedId = cityId.value;
								if (!selectedId) return;
								
								// Если уже есть значение в city_search, не меняем его
								if (cityInput.value.trim()) return;
								
								// Пробуем найти выбранный город в уже загруженных данных
								// или делаем запрос для получения информации о городе по ID
								// Пока просто оставляем как есть
							}
							
							function groupByCountry(list) {
								const groups = { RU: [], KZ: [], UZ: [], OTHER: [] };
								(list || []).forEach(x => {
									const cc = (x.country_code || '').toUpperCase();
									if (cc === 'RU') groups.RU.push(x);
									else if (cc === 'KZ') groups.KZ.push(x);
									else if (cc === 'UZ') groups.UZ.push(x);
									else groups.OTHER.push(x);
								});
								return groups;
							}
							
							if (cityWrap && cityInput && cityId && dd && results) {
								if (cityInput.disabled) {
									cityInput.classList.add('city-search-input--disabled');
									} else {
									const runSearch = debounce(async (searchTerm) => {
										const q = searchTerm || extractCityNameForSearch(cityInput.value);
										
										if (q.length < 2) {
											clearResults();
											if (q.length === 0) {
												hideDropdown();
												} else {
												showDropdown();
												results.innerHTML = '<div class="city-message">Введите ещё символы…</div>';
											}
											return;
										}
										
										clearResults();
										showDropdown();
										results.innerHTML = '<div class="city-message">Поиск…</div>';
										
										const data = await fetchCities(q);
										if (!data) {
											results.innerHTML = '<div class="city-message">Не удалось загрузить список.</div>';
											return;
										}
										
										const items = Array.isArray(data) ? data : (data.items || []);
										if (!items.length) {
											results.innerHTML = '<div class="city-message">Ничего не найдено.</div>';
											return;
										}
										
										const g = groupByCountry(items);
										
										let html = '';
										if (g.RU.length) html += renderGroup('Россия', g.RU);
										if (g.KZ.length) html += renderGroup('Казахстан', g.KZ);
										if (g.UZ.length) html += renderGroup('Узбекистан', g.UZ);
										if (g.OTHER.length) html += renderGroup('Другие страны', g.OTHER);
										
										results.innerHTML = html;
										
										results.querySelectorAll('.city-item').forEach(btn => {
											btn.addEventListener('click', () => {
												const id = btn.getAttribute('data-id');
												const name = btn.getAttribute('data-name') || btn.querySelector('.city-item-name')?.textContent || '';
												const countryCode = btn.getAttribute('data-country');
												const region = btn.getAttribute('data-region');
												applySelected(id, name, countryCode, region);
											});
										});
									}, 220);
									
									cityInput.addEventListener('input', () => {
										// Пользователь меняет текст - сбрасываем city_id
										cityId.value = '';
										runSearch();
									});
									
									// При фокусе - НЕ меняем визуальное отображение
									cityInput.addEventListener('focus', () => {
										// Просто запускаем поиск по чистому названию
										const searchTerm = extractCityNameForSearch(cityInput.value);
										if (searchTerm.length >= 2) {
											// Запоминаем текущий city_id перед поиском
											const currentCityId = cityId.value;
											
											
											
											// Если ничего не нашли и был выбран город - восстановим?
											// Но это уже внутри runSearch
										}
									});
									
									document.addEventListener('click', (e) => {
										if (!cityWrap.contains(e.target)) {
											hideDropdown();
										}
									});
									
									cityInput.addEventListener('keydown', (e) => {
										if (e.key === 'Escape') hideDropdown();
									});
									
									// При загрузке проверяем, если есть выбранный город но пустой инпут
									updateDisplayFromSelected();
								}
							}
							
							// ---------- Submit: final check ----------
							const form = document.getElementById('profile-complete-form');
							if (!form) return;
							
							const phoneRe = /^\+7\d{10}$/;
							
							form.addEventListener('submit', (e) => {
								let ok = true;
								
								// Names
								nameInputs.forEach((inp) => {
									if (inp.disabled) return;
									inp.value = normalizeCyrName(inp.value);
									const bad = !isValidCyrName(inp.value);
									setInvalid(inp, bad);
									ok = ok && !bad;
								});
								
								// Phone
								if (phoneMasked && !phoneMasked.disabled && phoneE164) {
									phoneE164.value = toE164Ru(phoneMasked.value);
									const bad = !!phoneE164.value && !phoneRe.test((phoneE164.value || '').trim());
									setInvalid(phoneMasked, bad);
									ok = ok && !bad;
								}
								
								// City: если введено что-то, но city_id не выбран — подсветим
								if (cityInput && !cityInput.disabled && cityId) {
									const q = (cityInput.value || '').trim();
									const id = (cityId.value || '').trim();
									const bad = (q.length > 0 && id.length === 0);
									setInvalid(cityInput, bad);
									ok = ok && !bad;
								}
								
								if (!ok) {
									e.preventDefault();
									const firstBad = document.querySelector('.input-error');
									if (firstBad && typeof firstBad.scrollIntoView === 'function') {
										firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
									}
								}
							});
						})();
					</script>
				</x-slot>	
			</x-voll-layout>
				