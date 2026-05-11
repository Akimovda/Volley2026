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
		/** @var \App\Models\User $actor */
		/** @var \App\Models\User $target */
		
		$actor  = $actor ?? auth()->user();
		$target = $target ?? ($user ?? auth()->user());
		
		/** @var \App\Models\User $user */
		$user = $target; // кого редактируем в blade
		
		$mode = $mode ?? 'self'; // self|admin_self|admin_other|organizer_other
		
		$isEditingOther = (int)($actor?->id ?? 0) !== (int)($user?->id ?? 0);
		$organizerLimitedView = ($mode === 'organizer_other');
		

		
		// ВАЖНО: права считаем по actor, а не по user (target)
		$canEditProtected = (bool)($canEditProtected ?? ($actor && $actor->can('edit-protected-profile-fields')));

		// Первичное заполнение: все поля открыты, даже если подтянулись из OAuth
		$isFirstCompletion = is_null($user?->profile_completed_at) && ($actor?->id === $user?->id);
		if ($isFirstCompletion) {
			$canEditProtected = true;
		}

		$filled = function ($value) {
		if (is_null($value)) return false;
		if (is_string($value)) return trim($value) !== '';
		return true;
		};

		$lockHint = __('profile.cp_lock_hint');
		
		$posMap = __('profile.pos_long');
		
		// Амплуа / позиции
		$classicPrimary = optional($user?->classicPositions)->firstWhere('is_primary', true)?->position;
		$classicAll     = optional($user?->classicPositions)->pluck('position')->all() ?? [];
		
		$beachPrimaryZone = optional($user?->beachZones)->firstWhere('is_primary', true)?->zone;
		$beachModeCurrent = $user?->beach_universal
		? 'universal'
		: (is_null($beachPrimaryZone) ? null : (string)$beachPrimaryZone);
		
		// Уровни: считаем возраст по old('birth_date'), чтобы после ошибок валидации UI не расходился
		$birthForAge = old('birth_date');
		if ($birthForAge === null) {
		$birthForAge = $user?->birth_date ? $user->birth_date->format('Y-m-d') : null;
		}
		
		$age = $birthForAge ? \Carbon\Carbon::parse($birthForAge)->age : null;
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
			'showCountry' => true,
			'showRegion' => true,
			'inputShowCountry' => true,
			'inputShowRegion' => true,
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
            
			$selectedCityLabel = !empty($details)
			? ($cityName . ' (' . implode(', ', $details) . ')')
			: $cityName;
			}
			}
            @endphp
			
			
			<x-slot name="title">
				@if(($mode ?? 'self') === 'admin_other')
				{{ __('profile.cp_title_admin_other', ['id' => $user->id]) }}
				@elseif(($mode ?? 'self') === 'organizer_other')
				{{ __('profile.cp_title_organizer', ['id' => $user->id]) }}
				@else
				{{ __('profile.cp_title_self') }}
				@endif
			</x-slot>
			
			<x-slot name="description">
				@php
				$labelName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
				@endphp
                
				@if(!empty($labelName))
				{{ $labelName }}
				@else
				{{ __('profile.cp_breadcrumb_user_n', ['id' => $user->id]) }}
				@endif
                
				@if(($mode ?? 'self') === 'admin_other')
				{{ __('profile.cp_desc_admin_suffix') }}
				@elseif(($mode ?? 'self') === 'organizer_other')
				{{ __('profile.cp_desc_organizer_suffix') }}
				@endif
			</x-slot>
			
			<x-slot name="canonical">
				{{ url('/profile/complete') }}
			</x-slot> 
			
			<x-slot name="breadcrumbs">
				<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
					@if(!$isEditingOther)
					<a href="{{ route('profile.show') }}" itemprop="item">
						<span itemprop="name">{{ __('profile.cp_breadcrumb_self') }}</span>
					</a>
					@else
					<a href="{{ route('users.show', ['user' => $user->id]) }}" itemprop="item">
						<span itemprop="name">{{ __('profile.cp_breadcrumb_user_n', ['id' => $user->id]) }}</span>
					</a>
					@endif
					<meta itemprop="position" content="2">
				</li>
                
				<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
					<a href="{{ $isEditingOther ? url('/profile/complete?user_id='.$user->id) : url('/profile/complete') }}" itemprop="item">
						<span itemprop="name">{{ __('profile.cp_breadcrumb_edit') }}</span>
					</a>
					<meta itemprop="position" content="3">
				</li>
			</x-slot>
			
			<x-slot name="h1">{{ __('profile.cp_h1') }}</x-slot>
			
			<x-slot name="h2">
				@if(!empty($user->first_name) || !empty($user->last_name))
				{{ trim($user->first_name . ' ' . $user->last_name) }}
				@else
				{{ __('profile.cp_breadcrumb_user_n', ['id' => $user->id]) }}
				@endif
			</x-slot>
			
			<x-slot name="t_description">
				{{ __('profile.cp_t_description') }}
			</x-slot>
			
			<x-slot name="style">
				<style>
					
					/* Общие стили для контейнеров */
					.levelmark-row .swiper-slide {
					height: auto;
					padding-top: 0.4rem;
					}
					
					.levelmark {
					padding: 0.6rem 1rem;
					display: flex;
					height: 100%;
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
					transform: translateY(-0.3rem);
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
				@if ($isFirstCompletion)
				<div class="ramka" style="border-left: 4px solid var(--c-primary, #2563eb);">
					<div class="text-center">
						<h3 class="mt-0">{{ __('profile.cp_welcome_first_title') }}</h3>
						<p>{!! __('profile.cp_welcome_first_lead') !!}</p>
						@if($user?->first_name || $user?->last_name)
						<p class="f-14" style="color: var(--c-muted, #6b7280);">{{ __('profile.cp_welcome_first_hint') }}</p>
						@endif
					</div>
				</div>
				@elseif (session('welcome'))
				<div class="ramka">
					<div class="text-center">
						<h3 class="mt-0">{{ __('profile.cp_welcome_session_title') }}</h3>
						<p>{!! __('profile.cp_welcome_session_lead') !!}</p>
						<p class="cd b-600 f-20">{{ __('profile.cp_welcome_session_motto') }}</p>
					</div>
				</div>
				@endif
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
						<div class="alert-title">{{ __('profile.cp_errors_title') }}</div>
						<ul class="list">
							@foreach ($errors->all() as $err)
							<li>{{ $err }}</li>
							@endforeach
						</ul>
					</div>
				</div>
				@endif
				
				{{-- ========================= MISSING FOR EVENT ========================= --}}
				@php $allMissingKeys = array_unique(array_merge($missingKeys ?? [], $requiredKeys ?? [])); @endphp
				@if (!empty($allMissingKeys))
				<div class="ramka">
					<div class="alert alert-error">
						<div class="alert-title">
							{{ !empty($missingKeys ?? []) ? __('profile.cp_missing_for_event') : __('profile.cp_required_title') }}
						</div>
						<ul class="list">
							@foreach ($allMissingKeys as $key)
							<li>
								@switch($key)
								@case('full_name') {{ __('profile.cp_required_full_name') }} @break
								@case('patronymic') {{ __('profile.cp_required_patronymic') }} @break
								@case('phone') {{ __('profile.cp_required_phone') }} @break
								@case('city') {{ __('profile.cp_required_city') }} @break
								@case('birth_date') {{ __('profile.cp_required_birth') }} @break
								@case('gender') {{ __('profile.cp_required_gender') }} @break
								@case('height_cm') {{ __('profile.cp_required_height') }} @break
								@case('classic_level') {{ __('profile.cp_required_classic') }} @break
								@case('beach_level') {{ __('profile.cp_required_beach') }} @break
								@default {{ $key }}
								@endswitch
							</li>
							@endforeach
						</ul>
					</div>
					@if (!empty($returnTo ?? ''))
					<div class="mb-1">
						<a href="{{ $returnTo }}" class="btn btn-small btn-secondary">← {{ __('profile.cp_return_to_event') }}</a>
					</div>
					@elseif (!empty($eventId))
					<div class="mb-1">
						{{ __('profile.cp_required_event_hint') }}
					</div>
					@endif
				</div>
				@endif
				
				
				
				<div class="row">
<div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
<div class="sticky">
<div class="card-ramka">
@include('profile._menu', [
    'menuUser'       => $user,
    'isEditingOther' => $isEditingOther ?? false,
    'activeMenu'     => 'profile_edit',
])
</div>
</div>
</div>
					<div class="col-lg-8 col-xl-9 order-1">
						<div class="form">
							<form id="profile-complete-form" method="POST" action="{{ route('profile.extra.update') }}">
        <input type="hidden" name="from_complete" value="1">
        @if (!empty($returnTo ?? ''))
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        @endif
								@csrf
								
									@if($organizerLimitedView)
									<div class="ramka">		
									<div class="alert alert-info">
										{{ __('profile.cp_organizer_view_info') }}
									</div>
									</div>
									@endif
								
								
								<div class="ramka" style="z-index:10">				
									
									{{-- ========================= MAIN ========================= --}}
									
									{{-- ========================= PERSONAL DATA ========================= --}}
									
									
									
									<input type="hidden" name="user_id" value="{{ $user->id }}">
									
									
									
									<h2 class="-mt-05">{{ __('profile.cp_sec_personal') }}</h2>
									<div class="row">
										{{-- -------- Фамилия -------- --}}
										@php $lockedLast = !$canEditProtected && $filled($user?->last_name); @endphp
										@if(!$organizerLimitedView)
										<div class="col-sm-6">
											<div class="card">
												<label>
													<div>{{ __('profile.cp_lbl_last') }}</div>
													<div class="f-16 b-500">{{ __('profile.cp_visible_all') }}</div>
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
													<li>{{ __('profile.cp_hint_cyr_name') }}</li>
													@if($lockedLast)<li>{{ $lockHint }}</li>@endif
												</ul>
											</div>
										</div>
										@endif
										{{-- -------- Имя -------- --}}
										@php $lockedFirst = !$canEditProtected && $filled($user?->first_name); @endphp
										@if(!$organizerLimitedView)
										<div class="col-sm-6">
											<div class="card">
												<label>
													<div>{{ __('profile.cp_lbl_first') }}</div>
													<div class="f-16 b-500">{{ __('profile.cp_visible_all') }}</div>
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
													<li>{{ __('profile.cp_hint_cyr_name') }}</li>
													@if($lockedFirst)<li>{{ $lockHint }}</li>@endif
												</ul>												
											</div>
										</div>
										@endif
										{{-- -------- Отчество -------- --}}
										@php $lockedPat = !$canEditProtected && $filled($user?->patronymic); @endphp
										@if(!$organizerLimitedView)
										<div class="col-sm-6">
											<div class="card">
												<label>
													<div>{{ __('profile.cp_lbl_patronym') }}</div>
													<div class="cd f-16 b-500">{{ __('profile.cp_visible_org') }}</div>
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
													<li>{{ __('profile.cp_hint_cyr_name') }}</li>
													@if($lockedPat)<li>{{ $lockHint }}</li>@endif
												</ul>													
											</div>
										</div>
										@endif
										{{-- -------- Телефон -------- --}}
										@php $lockedPhone = !$canEditProtected && $filled($user?->phone); @endphp
										@if(!$organizerLimitedView)
										<div class="col-sm-6">
											<div class="card">
												<label>
													<div>{{ __('profile.cp_lbl_phone') }}</div>
													<div class="cd f-16 b-500">{{ __('profile.cp_visible_org') }}</div>
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
										@endif
										{{-- -------- Дата рождения -------- --}}
										@php
										$lockedBirth = !$canEditProtected && !$organizerLimitedView && $filled($user?->birth_date);
										@endphp
										<div class="col-sm-6">
											<div class="card">
												<label>
													<div>{{ __('profile.cp_lbl_birth') }}</div>
													<div class="f-16 b-500">{{ __('profile.cp_visible_age_fmt') }}</div>
												</label>													
												
												@php
												$birthValue = old('birth_date');
												if ($birthValue === null) {
												$birthValue = $user?->birth_date ? $user->birth_date->format('Y-m-d') : '';
												}
												$birthDay   = $birthValue ? (int) date('j', strtotime($birthValue)) : 0;
												$birthMonth = $birthValue ? (int) date('n', strtotime($birthValue)) : 0;
												$birthYear  = $birthValue ? (int) date('Y', strtotime($birthValue)) : 0;
												@endphp

												<input type="hidden" name="birth_date" id="birth_date_hidden" value="{{ $birthValue }}">
												<div class="d-flex gap-2">
													<select id="birth_day" style="width:80px"
														class="{{ $errors->has('birth_date') ? 'input-error' : '' }}"
														@disabled($lockedBirth)>
														<option value="">{{ __('profile.cp_birth_day_ph') }}</option>
														@for($d = 1; $d <= 31; $d++)
														<option value="{{ $d }}" @selected($birthDay === $d)>{{ $d }}</option>
														@endfor
													</select>
													<select id="birth_month" style="flex:1"
														class="{{ $errors->has('birth_date') ? 'input-error' : '' }}"
														@disabled($lockedBirth)>
														<option value="">{{ __('profile.cp_birth_month_ph') }}</option>
														@foreach(__('profile.cp_months') as $mi1 => $mn) @php $mi = $mi1 - 1; @endphp
														<option value="{{ $mi + 1 }}" @selected($birthMonth === $mi + 1)>{{ $mn }}</option>
														@endforeach
													</select>
													<select id="birth_year" style="width:100px"
														class="{{ $errors->has('birth_date') ? 'input-error' : '' }}"
														@disabled($lockedBirth)>
														<option value="">{{ __('profile.cp_birth_year_ph') }}</option>
														@for($y = 2015; $y >= 1945; $y--)
														<option value="{{ $y }}" @selected($birthYear === $y)>{{ $y }}</option>
														@endfor
													</select>
												</div>
												<ul class="list f-16 mt-1">
													@error('birth_date')<li class="red b-600">{{ $message }}</li>@enderror
													@if($lockedBirth)<li>{{ $lockHint }}</li>@endif
												</ul>
													{{-- Скрыть возраст (только для женщин) --}}
                                        <div id="hide_age_wrap" class="{{ old('gender', $user?->gender) === 'f' ? '' : 'hidden' }}">
                                            <label class="checkbox-item mt-1">
                                                <input type="hidden" name="hide_age" value="0">
                                                <input type="checkbox" name="hide_age" value="1"
                                                    id="hide_age_checkbox"
                                                    @checked(old('hide_age', $user?->hide_age ?? false))>
                                                <div class="custom-checkbox"></div>
                                                <span>{{ __('profile.cp_hide_age') }}</span>
                                            </label>
                                        </div>
											</div>
										</div>
									
										{{-- -------- Город (AUTOCOMPLETE + fallback select) -------- --}}
										@php $lockedCity = !$canEditProtected && $filled($user?->city_id); @endphp
										@if(!$organizerLimitedView)
										<div class="col-sm-6">
											<div class="card">
												<label>
													<div>{{ __('profile.cp_lbl_city') }}</div>
													<div class="f-16 b-500">{{ __('profile.cp_visible_all') }}</div>
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
													placeholder="{{ __('profile.cp_city_search_ph') }}"
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
													@if($lockedCity)<li>{{ $lockHint }}</li>@else <li>{{ __('profile.cp_city_search_hint') }}</li> @endif
												</ul>
											</div>
										</div>
										@endif
										{{-- -------- Пол (НЕ фиксируемый) -------- --}}
										@if(!$organizerLimitedView)
										<div class="col-sm-6">
											<div class="card">
												<label>
													<div>{{ __('profile.cp_lbl_gender') }}</div>
													<div class="f-16 b-500">{{ __('profile.cp_visible_all') }}</div>
												</label>	
												<select name="gender" class="{{ $errors->has('gender') ? 'input-error' : '' }}">
													<option value="">{{ __('profile.cp_gender_none') }}</option>
													<option value="m" @selected(old('gender', $user?->gender) === 'm')>{{ __('profile.cp_gender_male') }}</option>
													<option value="f" @selected(old('gender', $user?->gender) === 'f')>{{ __('profile.cp_gender_female') }}</option>
												</select>
												
												<ul class="list f-16 mt-1">
													@error('gender')<li class="red b-600">{{ $message }}</li>@enderror
													{{--	@if($lockedGender)<li>{{ $lockHint }}</li>@endif --}}
												</ul>
											</div>
										</div>
										@endif
										{{-- -------- Рост (НЕ фиксируемый) -------- --}}
										@if(!$organizerLimitedView)
										<div class="col-sm-6">
											<div class="card">
												<label>
													<div>{{ __('profile.cp_lbl_height') }}</div>
													<div class="f-16 b-500">{{ __('profile.cp_visible_all') }}</div>
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
													<li>{{ __('profile.cp_hint_height_range') }}</li>
												</ul>													
											</div>
										</div>
										@endif
									</div>
								</div>
								
								{{-- ========================= SKILLS ========================= --}}
								
								
								
								@php
								$lockedClassic = !$canEditProtected && !$organizerLimitedView && $filled($user?->classic_level);
								$lockedBeach   = !$canEditProtected && !$organizerLimitedView && $filled($user?->beach_level);
								
								$currentClassicLevel = old('classic_level', $user?->classic_level);
								$currentBeachLevel = old('beach_level', $user?->beach_level);
								
								// Определяем доступные уровни по возрасту
								$birthDate = $birthValue ? \Carbon\Carbon::parse($birthValue) : null;
								$age = $birthDate ? $birthDate->age : null;
								
								$availableLevels = [1,2,3,4,5,6,7]; // все доступны по умолчанию
								$ageRestrictionMessage = '';
								
								if (!$birthDate) {
								$ageRestrictionMessage = __('profile.cp_age_msg_no_birth');
								$availableLevels = []; // ничего не доступно
								} elseif ($age < 18) {
								$availableLevels = [1,2,4]; // только эти для младше 18
								$ageRestrictionMessage = __('profile.cp_age_msg_under_18');
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
										
										<h2 class="-mt-05">{{ __('profile.cp_sec_classic') }}</h2>
										<div class="row">		
											<div class="col-12">		
												<div class="hideselect">									
													<label>
														<div>{{ __('profile.cp_lvl_label') }}</div>
														<div class="f-16 b-500">{{ __('profile.cp_visible_all') }}</div>
													</label>	
													
													{{-- Скрытый оригинальный select --}}
													<select
													name="classic_level"
													id="classic_level_select"
													style="display: none;"
													@disabled($lockedClassic)
													>
														<option value="">{{ __('profile.cp_select_pick') }}</option>
														@foreach($levels as $lvl)
														<option value="{{ $lvl }}" @selected((string)$currentClassicLevel === (string)$lvl)>{{ $lvl }}</option>
														@endforeach
													</select>
													<ul class="list f-16 mt-1">
														<li><a href="/level_players">{{ __('profile.cp_lvl_link') }}</a></li>
													</ul>
													{{-- Кнопки уровней --}}
													<div class="swiper levelmark-row" id="levelmark-wrap-classic" data-type="classic">
														<div class="swiper-wrapper">
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
															
															$currentted = (string)$currentClassicLevel === (string)$lvl;
															@endphp
															
															
															<div class="swiper-slide @if($currentted) current-slide @endif">
																
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
															</div>
															@endforeach
														</div>
														<div class="swiper-pagination"></div>
													</div>
													<ul class="list f-16 mt-1">
														@error('classic_level')
														<li class="red b-600">{{ $message }}</li>
														@else
														@if($lockedClassic)
														<li>{{ $lockHint }}</li>
														<li>{{ __('profile.cp_lvl_yours') }} <strong class="cd">{{ $user->classic_level }}</strong></li>
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
													<label>{{ __('profile.cp_role_primary') }}</label>
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
													<label>{{ __('profile.cp_role_extra') }}</label>
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
																@if($disabled)<span class="f-15">{{ __('profile.cp_role_primary_tag') }}</span>@endif
															</span>
														</label>
														@endforeach
													</div>
												</div>	
											</div>
										</div>
										
									</div>
									<div class="ramka">		
										
										<h2 class="-mt-05">{{ __('profile.cp_sec_beach') }}</h2>
										
										<label>
											<div>{{ __('profile.cp_lvl_label') }}</div>
											<div class="f-16 b-500">{{ __('profile.cp_visible_all') }}</div>
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
														<option value="">{{ __('profile.cp_select_pick') }}</option>
														@foreach($levels as $lvl)
														<option value="{{ $lvl }}" @selected((string)$currentBeachLevel === (string)$lvl)>{{ $lvl }}</option>
														@endforeach
													</select>
													
													<ul class="list f-16 mt-1">
														<li><a href="/level_players">{{ __('profile.cp_lvl_link') }}</a></li>
													</ul>
													
													{{-- Кнопки уровней --}}
													<div class="swiper levelmark-row" id="levelmark-wrap-beach" data-type="beach">
														<div class="swiper-wrapper">
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
															
															$currentted = (string)$currentBeachLevel === (string)$lvl;
															@endphp
															
															
															<div class="swiper-slide @if($currentted) current-slide @endif">
																
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
															</div>
															@endforeach
														</div>
														<div class="swiper-pagination"></div>
													</div>
													
													<ul class="list f-16 mt-1">
														@error('beach_level')
														<li class="red b-600">{{ $message }}</li>
														@else
														@if($lockedBeach)
														<li>{{ $lockHint }}</li> 
														<li>{{ __('profile.cp_lvl_yours') }} <strong class="cd">{{ $user->beach_level }}</strong></li>
														@elseif($ageRestrictionMessage)
														<li class="level-mes">{{ $ageRestrictionMessage }}</li> {{-- Если не заблокировано и есть возрастные ограничения --}}
														@else
														<li style="display: none" class="level-mes">{{ $ageRestrictionMessage }}</li>
														@endif
														@enderror
													</ul>
												</div>
											</div>
											
											<div class="col-sm-12">
												<div class="card">
													<label>{{ __('profile.cp_beach_mode_q') }}</label>
													
													<label class="radio-item">
														<input type="radio" name="beach_mode" value="2" @checked(old('beach_mode', $beachModeCurrent) === '2')>
														<div class="custom-radio"></div>
														<span>{{ __('profile.cp_beach_zone_2') }}</span>
													</label>
													<label class="radio-item">
														<input type="radio" name="beach_mode" value="4" @checked(old('beach_mode', $beachModeCurrent) === '4')>
														<div class="custom-radio"></div>
														<span>{{ __('profile.cp_beach_zone_4') }}</span>
													</label>
													<label class="radio-item">
														<input type="radio" name="beach_mode" value="universal" @checked(old('beach_mode', $beachModeCurrent) === 'universal')>
														<div class="custom-radio"></div>
														<span>{{ __('profile.cp_beach_universal') }}</span>
													</label>
													<div class="f-16 b-500">{{ __('profile.cp_beach_universal_hint') }}</div>
													
												</div>
											</div>	
										</div>											
									</div>
									
									<div class="card-ramka mb-2 text-center">	
										<button type="submit" class="btn">{{ __('profile.cp_btn_save') }}</button>
									</div>
								</form>
								
							</div>
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
				
				
				{{-- ============================================================
				JS:
				- ФИО: нормализация
				- Телефон: маска на blur + hidden E.164
				- Город: автокомплит (GET cities.search?q=...)
				============================================================ --}}
				
				
				
				<x-slot name="script">
					<script src="/assets/city.js"></script>
					<script src="/assets/fas.js"></script>
					@if(session('profile_prompt'))
					<script>
					document.addEventListener('DOMContentLoaded', function() {
						swal({
							title: @json(__('profile.cp_swal_prompt_title')),
							text: @json(__('profile.cp_swal_prompt_text')),
							icon: 'info',
							button: @json(__('profile.cp_swal_prompt_btn')),
						});
					});
					</script>
					@endif
					<script>
						$('.levelmark-row').each(function() {
							const initialIndex = $(this).find('.swiper-slide.current-slide').index();
							
							new Swiper(this, {
								slidesPerView: 2,
								centeredSlides: true,
								initialSlide: initialIndex >= 0 ? initialIndex : 0,
								spaceBetween: 6,
								loop: false,
								pagination: {
									el: '.swiper-pagination',
									clickable: true,
								},    
								// На мобилке показываем 1 по центру + части соседей
								breakpoints: {
									480: {
										slidesPerView: 3, 
										centeredSlides: false
									},
									640: {
										slidesPerView: 4, 
										centeredSlides: false 
									},
									768: {
										slidesPerView: 5,
										centeredSlides: false
									},
									992: {
										slidesPerView: 4,
										centeredSlides: false
									},
									1024: {
										slidesPerView: 5,
										centeredSlides: false
									},
									1280: {
										slidesPerView: 7,
										centeredSlides: false
									}
								}
							});	
						});
						
						
						
						
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
							// Показывать/скрывать "Скрыть возраст" в зависимости от пола
                            const genderSelect = document.querySelector('select[name="gender"]');
                            const hideAgeWrap  = document.getElementById('hide_age_wrap');
                            const hideAgeCheck = document.getElementById('hide_age_checkbox');
                            
                            function syncHideAge() {
                                if (!genderSelect || !hideAgeWrap) return;
                                const isFemale = genderSelect.value === 'f';
                                hideAgeWrap.classList.toggle('hidden', !isFemale);
                                if (!isFemale && hideAgeCheck) hideAgeCheck.checked = false;
                            }
                            
                            if (genderSelect) {
                                genderSelect.addEventListener('change', syncHideAge);
                                syncHideAge();
                            }
							
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
									message = @json(__('profile.cp_age_msg_no_birth'));
									} else {
									const age = calculateAge(birthValue);
									if (age < 18) {
										available = [1,2,4];
										message = @json(__('profile.cp_age_msg_under_18'));
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
							
							// Дата рождения: 3 select → hidden input
							(function() {
								var dayEl  = document.getElementById('birth_day');
								var monEl  = document.getElementById('birth_month');
								var yearEl = document.getElementById('birth_year');
								var hidden = document.getElementById('birth_date_hidden');
								if (!dayEl || !monEl || !yearEl || !hidden) return;

								var MONTHS_30 = [4, 6, 9, 11];

								function daysInMonth(m, y) {
									if (m === 2) return (y && y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0)) ? 29 : 28;
									return MONTHS_30.includes(m) ? 30 : 31;
								}

								function updateDays() {
									var m = parseInt(monEl.value) || 0;
									var y = parseInt(yearEl.value) || 0;
									var maxDay = m ? daysInMonth(m, y) : 31;
									var cur = parseInt(dayEl.value) || 0;
									var html = '<option value="">{{ __('profile.cp_birth_day_ph') }}</option>';
									for (var d = 1; d <= maxDay; d++) {
										html += '<option value="' + d + '"' + (cur === d ? ' selected' : '') + '>' + d + '</option>';
									}
									dayEl.innerHTML = html;
									if (cur > maxDay) dayEl.value = '';
								}

								function assembleBirthDate() {
									var d = parseInt(dayEl.value) || 0;
									var m = parseInt(monEl.value) || 0;
									var y = parseInt(yearEl.value) || 0;
									hidden.value = (d && m && y)
										? y + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0')
										: '';
									hidden.dispatchEvent(new Event('change', { bubbles: true }));
								}

								monEl.addEventListener('change', function() { updateDays(); assembleBirthDate(); });
								yearEl.addEventListener('change', function() { updateDays(); assembleBirthDate(); });
								dayEl.addEventListener('change', assembleBirthDate);

								updateDays();
							})();

							// Слушаем изменения даты рождения (через hidden input)
							if (birthInput) {
								birthInput.addEventListener('change', updateAvailableLevels);
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
								// Для select: синхронизировать кастомный wrapper (видим на десктопе)
								if (el.tagName === 'SELECT') {
									var prev = el.previousElementSibling;
									if (prev && prev.classList.contains('form-select-wrapper')) {
										if (isInvalid) prev.classList.add('input-error');
										else prev.classList.remove('input-error');
									}
								}
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
							
							
							
							// ---------- Submit: final check ----------
							const form = document.getElementById('profile-complete-form');
							if (!form) return;
							
							const phoneRe = /^\+7\d{10}$/;
							
							form.addEventListener('submit', (e) => {
								// Сбросить старые подсветки (в т.ч. от missingKeys при загрузке)
								form.querySelectorAll('.input-error').forEach(function(el) {
									el.classList.remove('input-error');
								});

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
								
								// Группа ФИО + телефон: всё или ничего
								{
									const grp = [
										document.querySelector('input[name="last_name"]'),
										document.querySelector('input[name="first_name"]'),
										phoneMasked,
									].filter(Boolean);
									if (grp.length > 0) {
										const vals = grp.map(el => (el.value || '').trim());
										const anyFilled = vals.some(v => v !== '');
										if (anyFilled) {
											grp.forEach((el, i) => {
												if (!vals[i]) { setInvalid(el, true); ok = false; }
											});
										}
									}
								}

								// City: если введено что-то, но city_id не выбран — подсветим
								if (cityInput && !cityInput.disabled && cityId) {
									const q = (cityInput.value || '').trim();
									const id = (cityId.value || '').trim();
									const bad = (q.length > 0 && id.length === 0);
									setInvalid(cityInput, bad);
									ok = ok && !bad;
								}

								// Birth date: все три select обязательны
								{
									const bDay  = document.getElementById('birth_day');
									const bMon  = document.getElementById('birth_month');
									const bYear = document.getElementById('birth_year');
									if (bDay && !bDay.disabled) {
										const bOk = !!(bDay.value && bMon.value && bYear.value);
										setInvalid(bDay, !bOk);
										setInvalid(bMon, !bOk);
										setInvalid(bYear, !bOk);
										ok = ok && bOk;
									}
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
					@if (!empty($missingKeys ?? []))
					<script>
					(function() {
						var missingKeys = @json($missingKeys);
						var fieldMap = {
							'full_name':     ['input[name="last_name"]', 'input[name="first_name"]'],
							'patronymic':    ['input[name="patronymic"]'],
							'phone':         ['#phone_masked'],
							'birth_date':    ['#birth_day', '#birth_month', '#birth_year'],
							'gender':        ['select[name="gender"]'],
							'classic_level': ['#classic_level_select'],
							'beach_level':   ['#beach_level_select'],
							'city':          ['#city_search'],
						};
						var firstEl = null;
						missingKeys.forEach(function(key) {
							var selectors = fieldMap[key] || [];
							selectors.forEach(function(sel) {
								var el = document.querySelector(sel);
								if (el && !el.disabled) {
									el.classList.add('input-error');
									// Для select: подсветить кастомный wrapper (видим на десктопе)
									var wrapper = (el.tagName === 'SELECT') ? el.previousElementSibling : null;
									if (wrapper && wrapper.classList.contains('form-select-wrapper')) {
										wrapper.classList.add('input-error');
										if (!firstEl) firstEl = wrapper; // скроллим к видимому wrapper
									} else {
										if (!firstEl) firstEl = el;
									}
									// Снимать подсветку когда пользователь заполняет поле
									el.addEventListener('change', function() {
										if (this.value) {
											this.classList.remove('input-error');
											var wr = this.previousElementSibling;
											if (wr && wr.classList.contains('form-select-wrapper')) {
												wr.classList.remove('input-error');
											}
										}
									});
									el.addEventListener('input', function() {
										if (this.value) {
											this.classList.remove('input-error');
											var wr = this.previousElementSibling;
											if (wr && wr.classList.contains('form-select-wrapper')) {
												wr.classList.remove('input-error');
											}
										}
									});
								}
							});
						});
						if (firstEl && typeof firstEl.scrollIntoView === 'function') {
							setTimeout(function() {
								firstEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
							}, 300);
						}
					})();
					</script>
					@endif
				</x-slot>
			</x-voll-layout>
				