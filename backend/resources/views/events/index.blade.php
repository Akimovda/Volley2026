@php

$nowUtc  = \Illuminate\Support\Carbon::now('UTC');
$occList = $occurrences ?? collect();
$evList  = $events ?? collect();

// Occurrence-режим определяется ТИПОМ переданной коллекции: occurrenceIndex()
// в EventIndexService всегда отдаёт Paginator (даже если в 10-дневном окне
// 0 записей), legacyIndex() отдаёт обычную Collection через $events. Если бы
// определяли режим по "hasOcc" (наличие данных), пустое окно ошибочно считалось
// бы legacy-режимом и чипы дней вообще не рисовались бы.
$isOccurrenceMode = $occList instanceof \Illuminate\Contracts\Pagination\Paginator;

$hasOcc = false;
if ($occList instanceof \Illuminate\Contracts\Pagination\Paginator) {
$hasOcc = $occList->count() > 0;
} elseif ($occList instanceof \Illuminate\Support\Collection) {
$hasOcc = $occList->isNotEmpty();
}

$hasEvents = false;
if (!$isOccurrenceMode) {
if ($evList instanceof \Illuminate\Contracts\Pagination\Paginator) {
$hasEvents = $evList->count() > 0;
} elseif ($evList instanceof \Illuminate\Support\Collection) {
$hasEvents = $evList->isNotEmpty();
}
}


// ✅ TZ пользователя (всегда строка — нужна для группировки по датам)
$userTz = \App\Support\DateTime::effectiveUserTz(auth()->user());
// true только если пользователь явно задал город — иначе карточки покажут timezone события
$userHasCityTz = !is_null(auth()->user()?->city?->timezone);

// ✅ Формат для карточек occurrences
$fmtDate = function ($occ) use ($userTz, $userHasCityTz) {
$eventTz = $occ->timezone ?: ($occ->event?->timezone ?: 'UTC');
$effectiveTz = $userHasCityTz ? $userTz : $eventTz;

$sUser = $occ->starts_at
? \Illuminate\Support\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($effectiveTz)
: null;

if (!$sUser) {
return ['date' => '—', 'time' => '—', 'tz' => $effectiveTz, 'tzLabel' => $effectiveTz, 'eventTz' => $eventTz];
}

$date = $sUser->format('d.m.Y');
$time = $sUser->format('H:i');
$tzLabel = $sUser->format('T') . ' (UTC' . $sUser->format('P') . ')';

return ['date' => $date, 'time' => $time, 'tz' => $effectiveTz, 'tzLabel' => $tzLabel, 'eventTz' => $eventTz];
};


$trainersById   = $trainersById ?? [];
$trainerColumn  = $trainerColumn ?? null;
$trainerIconUrl = asset('icons/trainer.png');

// ✅ Полное окно — 10 календарных дней подряд, по TZ пользователя. Каждый день
// попадает в чипы НЕЗАВИСИМО от наличия событий (иначе зелёная точка "есть
// события" бессмысленна — она была бы у всех показанных дней). Старт окна
// ($windowStartDate) вычислен в EventIndexService::occurrenceIndex() —
// явный ?date=, иначе legacy ?offset=, иначе ближайший день с событиями —
// здесь просто читаем готовое значение, чтобы не дублировать эту логику.
$today = \Illuminate\Support\Carbon::now($userTz)->startOfDay();
$groupedByDate = [];

if ($isOccurrenceMode) {
$windowStart = !empty($windowStartDate)
? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $windowStartDate, $userTz)->startOfDay()
: (clone $today);
for ($i = 0; $i < 10; $i++) {
$d = (clone $windowStart)->addDays($i);
$groupedByDate[$d->format('Y-m-d')] = ['date' => $d, 'occurrences' => []];
}

foreach ($occList as $occ) {
$date = $occ->starts_at
? \Illuminate\Support\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($userTz)->startOfDay()
: null;
if (!$date) continue;

$dateKey = $date->format('Y-m-d');
if (!isset($groupedByDate[$dateKey])) continue; // вне текущего 10-дневного окна

$groupedByDate[$dateKey]['occurrences'][] = $occ;
}
}

$months = __('events.month_short');
$daysOfWeek = __('events.dow_short');

$levelOptions = [1, 2, 3, 4, 5, 6, 7];
    $levelScope = level_terminology_scope_for_user(auth()->user());

    // ✅ Текущие значения фильтров — считаем один раз здесь, используем и в
    // компактной верхней панели, и в поп-апе фильтров, и в мини-формах.
    $fDir      = request('direction', '');
    $fFormat   = request('format', '');
    $fLevel    = request('level', '');
    $fLocation = request('location', '');
    $fCity     = request('city', '');

    $formatLabels = [
        'game'               => __('events.fmt_game'),
        'training'           => __('events.fmt_training'),
        'training_game'      => __('events.fmt_training_game'),
        'coach_student'      => __('events.fmt_coach_student'),
        'tournament'         => __('events.fmt_tournament'),
        'tournament_classic' => __('events.fmt_tournament_classic'),
        'tournament_beach'   => __('events.fmt_tournament_beach'),
        'camp'               => __('events.fmt_camp'),
    ];

    // Связь типа с направлением — тип без явной привязки (game/training/
    // training_game/tournament/camp) доступен для обоих направлений;
    // coach_student реально создаётся только для пляжа (см.
    // events-create.js allowBeach), tournament_classic/tournament_beach —
    // направление зашито в самом названии типа.
    $formatDirections = [
        'coach_student'      => ['beach'],
        'tournament_classic' => ['classic'],
        'tournament_beach'   => ['beach'],
    ];

    $formatLabelsFiltered = $fDir === ''
        ? $formatLabels
        : array_filter(
            $formatLabels,
            fn($k) => in_array($fDir, $formatDirections[$k] ?? ['classic', 'beach'], true),
            ARRAY_FILTER_USE_KEY
        );

    $hasActiveSecondaryFilters = $fFormat !== '' || $fLevel !== '' || $fLocation !== '';
    $userCityId = auth()->user()?->city_id;
	@endphp
	
	<x-voll-layout body_class="events-page">
		<x-slot name="title">{{ __('events.index_title') }}</x-slot>
		<x-slot name="description">{{ __('events.index_title') }}</x-slot>
		<x-slot name="canonical">{{ route('events.index') }}</x-slot>
		
		<x-slot name="breadcrumbs">
			<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="{{ route('events.index') }}" itemprop="item">
					<span itemprop="name">{{ __('events.index_title') }}</span>
				</a>
				<meta itemprop="position" content="2">
			</li>
		</x-slot>
		
		<x-slot name="h1">{{ __('events.index_h1') }}</x-slot>
		
		<x-slot name="h2">
            @if($isOccurrenceMode && !empty($groupedByDate))
			@php
			$firstKey = array_key_first($groupedByDate);
			$lastKey  = array_key_last($groupedByDate);
			$firstLbl = $firstKey ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $firstKey, $userTz)->format('d.m.Y') : '';
			$lastLbl  = $lastKey  ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $lastKey,  $userTz)->format('d.m.Y') : '';
			@endphp
			{{ $firstLbl }} - {{ $lastLbl }}
            @endif
		</x-slot>
		
		<x-slot name="t_description">
			{{ __('events.index_t_description') }}
		</x-slot>

		<x-slot name="image">
			<div class="top-section-img" data-aos="fade" data-aos-duration="1000">
				<div class="top-section-light-img">
					<img src="/img/events-light.webp" alt="img">
				</div>
				<div class="top-section-dark-img">
					<img src="/img/events-dark.webp" alt="img">
				</div>
			</div>
		</x-slot>

		<x-slot name="style">
            <style>
				
			</style>
		</x-slot>
		
		
		
		<div class="container">

			{{-- Поп-ап "Фильтры" (fancybox inline) — тип/уровень/локация, скрыто на странице --}}
			<div id="eventsFilterModal" style="display:none; max-width: 48rem">
				<h2 class="title-h -mt-05">{{ __('events.btn_filter') }}</h2>
				<div class="form" style="overflow: visible">
					<form method="GET" action="{{ route('events.index') }}">
						<input type="hidden" name="direction" value="{{ $fDir }}">
						<input type="hidden" name="city" value="{{ $fCity }}">
						<div class="row g-2">
							<div class="col-12">
								<label class="form-label mb-1">{{ __('events.filter_event_type') }}</label>
								<select name="format" id="format" class="form-select">
									<option value="" {{ $fFormat==='' ? 'selected' : '' }}>{{ __('events.filter_any') }}</option>
									@foreach($formatLabelsFiltered as $k => $lbl)
									<option value="{{ $k }}" {{ $fFormat===$k ? 'selected' : '' }}>{{ $lbl }}</option>
									@endforeach
								</select>
							</div>

							<div class="col-12">
								<label class="form-label mb-1">{{ __('events.filter_level') }}</label>
								<select name="level" class="form-select">
									<option value="" {{ $fLevel==='' ? 'selected' : '' }}>{{ __('events.filter_any_level') }}</option>
									@foreach(($levelOptions ?? []) as $lv)
									<option value="{{ (int)$lv }}" {{ (string)$fLevel===(string)$lv ? 'selected' : '' }}>{{ level_filter_label($lv, $levelScope) }}</option>
									@endforeach
								</select>
							</div>

                            <div class="col-12">
                                <label class="form-label mb-1">{{ __('events.filter_location') }}</label>
                                <select name="location" class="form-select">
                                    <option value="">{{ __('events.filter_any') }}</option>
                                    @foreach($activeLocationNames ?? [] as $locName)
                                    <option value="{{ e($locName) }}" {{ $fLocation === $locName ? 'selected' : '' }}>{{ e($locName) }}</option>
                                    @endforeach
                                </select>
							</div>

							<div class="col-12 d-flex flex-wrap gap-2 align-items-center mt-1">
								<button type="submit" class="btn">{{ __('events.filter_apply') }}</button>
								<a href="{{ route('events.index') }}" class="btn btn-secondary">{{ __('events.filter_reset') }}</a>
							</div>
						</div>
					</form>
				</div>
			</div>

			@if (session('status'))
			<div class="ramka">
				<div class="alert alert-success">
					{{ session('status') }}
					@if (session('private_link'))
					<strong>{{ __('events.private_link') }}</strong>
					<a class="text-decoration-underline" href="{{ session('private_link') }}" target="_blank" rel="noopener">
						{{ session('private_link') }}
					</a>
					@endif
				</div>
			</div>
			@endif
			
			@if (session('error'))
			<div class="ramka">
				<div class="alert alert-warning">
					{{ session('error') }}
				</div>
			</div>
			@endif
			
			{{-- =========================
			ВАРИАНТ 1: OCCURRENCES
			========================= --}}
			@if ($isOccurrenceMode)
			{{-- Верхняя лента дней --}}
			<div class="tabs-content">
				<div id="days"></div>	
				<div class="mob-sticky">
					<div class="card-ramka event-dates-ramka">

						<div class="events-topbar" data-aos-delay="250" data-aos="fade-up">
							<button type="button"
								id="btnOpenFilters"
								class="topbar-icon-btn{{ $hasActiveSecondaryFilters ? ' has-active' : '' }}"
								title="{{ __('events.btn_filter') }}"
								aria-label="{{ __('events.btn_filter') }}">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
									<line x1="4" y1="6" x2="20" y2="6"></line>
									<circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"></circle>
									<line x1="4" y1="12" x2="20" y2="12"></line>
									<circle cx="16" cy="12" r="2" fill="currentColor" stroke="none"></circle>
									<line x1="4" y1="18" x2="20" y2="18"></line>
									<circle cx="11" cy="18" r="2" fill="currentColor" stroke="none"></circle>
								</svg>
							</button>

							<form method="GET" action="{{ route('events.index') }}" class="form topbar-direction-form">
								<input type="hidden" name="format" value="{{ $fFormat }}">
								<input type="hidden" name="level" value="{{ $fLevel }}">
								<input type="hidden" name="location" value="{{ $fLocation }}">
								<input type="hidden" name="city" value="{{ $fCity }}">
								<select name="direction" id="direction" class="form-select" onchange="this.form.submit()">
									<option value="" {{ $fDir==='' ? 'selected' : '' }}>{{ __('events.filter_any') }}</option>
									<option value="classic" {{ $fDir==='classic' ? 'selected' : '' }}>{{ __('events.filter_classic') }}</option>
									<option value="beach" {{ $fDir==='beach' ? 'selected' : '' }}>{{ __('events.filter_beach') }}</option>
								</select>
							</form>

							<button type="button" id="btn-toggle-all-imgs" class="topbar-icon-btn" onclick="toggleAllImgs(this)" title="{{ __('events.btn_hide_photos') }}" aria-label="{{ __('events.btn_hide_photos') }}">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M4 8h3l2-2h6l2 2h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"></path>
									<circle cx="12" cy="14" r="3.2"></circle>
									<line x1="3" y1="4" x2="21" y2="20"></line>
								</svg>
							</button>

							@auth
							@if($userCityId)
							<form method="GET" action="{{ route('events.index') }}" id="cityToggleForm">
								<input type="hidden" name="direction" value="{{ $fDir }}">
								<input type="hidden" name="format" value="{{ $fFormat }}">
								<input type="hidden" name="level" value="{{ $fLevel }}">
								<input type="hidden" name="location" value="{{ $fLocation }}">
								<input type="hidden" name="city" value="{{ $fCity === 'all' ? '' : 'all' }}">
							</form>
							<button type="submit" form="cityToggleForm"
								class="topbar-icon-btn{{ $fCity === 'all' ? ' is-active' : '' }}"
								title="{{ $fCity === 'all' ? __('events.filter_city_all_title') : __('events.filter_city_my_title') }}"
								aria-label="{{ __('events.filter_all_cities') }}">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 22s7-7.58 7-12A7 7 0 1 0 5 10c0 4.42 7 12 7 12z"></path>
									<circle cx="12" cy="10" r="2.5"></circle>
								</svg>
							</button>
							@endif
							@endauth
						</div>

						{{-- Навигация prev/next — окно всегда ровно 10 календарных дней от
						$windowStart (который может быть не выровнен по границе "сегодня +
						N*10", если старт был вычислен как "ближайший день с событиями"),
						поэтому вперёд/назад считаем от него, не по offset-арифметике.
						"Предыдущие" — только если окно не упирается в сегодня. Рендерится
						ДВАЖДЫ: на узком экране — внутри скроллящейся ленты
						(day-nav-buttons--inline, едет вместе с чипами), на широком —
						отдельным блоком справа (day-nav-buttons--pinned, не скроллится).
						Показывается только одна копия через CSS media query. --}}
						@php
						$prevWindowStart = (clone $windowStart)->subDays(10);
						if ($prevWindowStart->lt($today)) $prevWindowStart = clone $today;
						$showPrevNav = $windowStart->gt($today);
						$nextDateParam = (clone $windowStart)->addDays(10)->format('Y-m-d');
						$prevDateParam = $prevWindowStart->format('Y-m-d');
						$baseParams    = array_filter([
						'direction' => request('direction'),
						'format'    => request('format'),
						'level'     => request('level'),
						'location'  => request('location'),
						'city'      => request('city'),
						], fn($v) => $v !== '' && $v !== null);
						@endphp
						@php
						$dayNavButtonsHtml = '';
						ob_start();
						@endphp
						@if($showPrevNav)
						<a href="{{ route('events.index', array_merge($baseParams, ['date' => $prevDateParam])) }}"
						class="no-highlight day-chip last-tab tab">
							<div class="dc-dow">{{ __('events.days_prev') }}</div>
							<div class="dc-date">{{ __('events.days_n_days') }}</div>
						</a>
						@endif
						<a href="{{ route('events.index', array_merge($baseParams, ['date' => $nextDateParam])) }}"
						class="no-highlight day-chip last-tab tab">
							<div class="dc-dow">{{ __('events.days_next') }}</div>
							<div class="dc-date">{{ __('events.days_n_days') }}</div>
						</a>
						@php
						$dayNavButtonsHtml = ob_get_clean();
						@endphp

						<div class="days-strip" id="daysStrip">
						<div class="tabs mb-0">
                            {{-- Чипы дат --}}
                            @foreach($groupedByDate as $dateKey => $dayData)
                            @php
							$d       = $dayData['date'];
							$day     = (int)$d->format('j');
							$month   = (int)$d->format('n');
							$weekday = (int)$d->format('N');
							$labelDate = $day . ' ' . ($months[$month] ?? '');
							$dow = $daysOfWeek[$weekday] ?? '';
							$isWeekend = $weekday >= 6;
							$dayHasEvents = !empty($dayData['occurrences']);
                            @endphp
                            <a href="javascript:void(0)" class="tab no-highlight day-chip {{ $isWeekend ? 'is-weekend' : '' }} {{ $loop->first ? 'active' : '' }}"
							data-date="{{ $dateKey }}"
							title="{{ $labelDate }}">
                                <div class="dc-dow">{{ $dow }}</div>
                                <div class="dc-date">{{ $day }}</div>
                                <span class="dc-dot {{ $dayHasEvents ? '' : 'dc-dot--empty' }}" aria-hidden="true"></span>
							</a>
                            @endforeach
                            <div class="tab-highlight"></div>
							{{-- Узкий экран: кнопки едут вместе с чипами --}}
							<div class="day-nav-buttons day-nav-buttons--inline">
								{!! $dayNavButtonsHtml !!}
							</div>
						</div>
						</div>

						{{-- Широкий экран: кнопки отдельным блоком справа, не скроллятся --}}
						<div class="day-nav-buttons day-nav-buttons--pinned">
							{!! $dayNavButtonsHtml !!}
						</div>
					</div>
				</div>


				<div class="days-feed" id="daysFeed">
                    @foreach($groupedByDate as $dateKey => $dayData)
                    @php
                    $d       = $dayData['date'];
                    $weekday = (int)$d->format('N');
                    $isWeekend = $weekday >= 6;
                    $dow = $daysOfWeek[$weekday] ?? '';
                    $dateLabel = $d->locale(app()->getLocale())->translatedFormat('j F');
                    $isToday = $d->isSameDay($today);
                    $isTomorrow = $d->isSameDay((clone $today)->addDay());
                    $dayPrefix = $isToday ? __('events.day_header_today') : ($isTomorrow ? __('events.day_header_tomorrow') : null);
                    $dayHeaderLabel = ($dayPrefix ? $dayPrefix . ' · ' : '') . $dow . ', ' . $dateLabel;
                    @endphp
                    <section class="day-section" data-date="{{ $dateKey }}">
                        <h3 class="day-section-title {{ $isWeekend ? 'is-weekend' : '' }}">{{ $dayHeaderLabel }}</h3>
                        @if(empty($dayData['occurrences']))
                        <div class="ramka">
                            <div class="alert alert-info day-empty-plaque">
                                <div>{{ __('events.empty_list_day') }}</div>
                                <div class="f-13 text-muted mt-05">{{ __('events.empty_list_day_notify') }}</div>
                            </div>
                        </div>
                        @else
                        <div class="row mb-0">
                            @foreach ($dayData['occurrences'] as $occ)
							@include('events._card')
                            @endforeach
						</div>
						@endif
					</section>
                    @endforeach
				</div>

			</div>{{-- .tabs-content --}}
			
			@else
			<div class="ramka">
				<div class="alert alert-info">
					{{ __('events.empty_list') }}
				</div>
			</div>
			@endif
			
			{{-- JOIN MODAL (Fancybox inline) --}}
			<div id="joinModalContent" style="display:none; max-width: 56rem">
				<h2 id="jmTitle" class="title-h -mt-05">{{ __('events.join_title') }}</h2>
				<div id="jmMeta" class="mb-05"></div>
				<div id="jmAddr" class="mb-2"></div>
				<div id="jmError" class="alert alert-danger" style="display:none"></div>
				<div id="jmLoading" class="mb-1" style="display:none;">{{ __('events.join_loading') }}</div>
				<div id="jmPositions"></div>
				<div class="f-16 mt-2">{{ __('events.join_after_choice') }}</div>
			</div>
			
			<form id="joinForm" method="POST" action="" style="display:none">
				@csrf
				<input type="hidden" name="position" id="joinPosition" value="">
			</form>

			@guest
			@include('auth._login_popup')
			@endguest

		</div>
		<x-slot name="script">
			<script src="/assets/fas.js"></script>
			<script>
				
				const positionNames = @json(__('events.positions'));
				
				const titleEl   = document.getElementById('jmTitle');
				const metaEl    = document.getElementById('jmMeta');
				const addrEl    = document.getElementById('jmAddr');
				const posWrap   = document.getElementById('jmPositions');
				const errBox    = document.getElementById('jmError');
				const loadingEl = document.getElementById('jmLoading');
				const joinForm  = document.getElementById('joinForm');
				const joinPos   = document.getElementById('joinPosition');
				
				function showError(message) {
					if (!errBox) return;
					errBox.textContent = message;
					errBox.style.display = '';
				}
				function clearError() {
					if (!errBox) return;
					errBox.textContent = '';
					errBox.style.display = 'none';
				}
				function setLoading(isLoading) {
					if (!loadingEl) return;
					loadingEl.style.display = isLoading ? '' : 'none';
				}
				
				function openJoinModal(payload) {
					clearError();
					setLoading(true);
					posWrap.innerHTML = '';
					titleEl.textContent = payload.title || @json(__('events.join_title'));
					metaEl.textContent  = [payload.date, payload.time, payload.tz ? '('+payload.tz+')' : ''].filter(Boolean).join(' ');
					addrEl.textContent  = payload.address || '';
                    
					jQuery.fancybox.open({
						src: '#joinModalContent',
						type: 'inline',
						opts: { hideScrollbar: false, touch: false, toolbar: false, smallBtn: true, animationEffect: 'zoom-in-out', transitionEffect: 'zoom-in-out', preventCaptionOverlap: false,}
					});
				}
				
				function renderPositions(occurrenceId, freePositions) {
					posWrap.innerHTML = '';
					setLoading(false);
					if (!Array.isArray(freePositions) || freePositions.length === 0) {
						showError(@json(__('events.join_no_free')));
						return;
					}
					freePositions.forEach(p => {
						const key   = p.key || p.role || '';
						const free  = p.free ?? 0;
						const label = positionNames[key] || key;
						const btn   = document.createElement('button');
						btn.type      = 'button';
						btn.className = 'd-flex between btn btn-primary w-100 mb-1';
						btn.innerHTML = label + '<span><span class="pl-1 pr-1 f-11">' + @json(__('events.join_free_label')) + '</span>' + free + '</span>';
						btn.addEventListener('click', () => {
							joinForm.action   = '/occurrences/' + occurrenceId + '/join';
							joinPos.value     = key;
							jQuery.fancybox.close();
							joinForm.submit();
						});
						posWrap.appendChild(btn);
					});
				}
				
				
				
				async function fetchAvailability(occurrenceId) {
					try {
						const res  = await fetch('/occurrences/' + occurrenceId + '/availability', {
							headers: { 'Accept': 'application/json' },
							credentials: 'same-origin',
						});
						const data = await res.json();
						if (data && data.redirect_url) { window.location = data.redirect_url; return null; }
						if (!res.ok || data.ok === false) {
							showError((data && data.message) ? data.message : @json(__('events.join_load_error')));
							return null;
						}
						return data;
                        } catch (e) {
						showError(@json(__('events.join_net_error')));
						return null;
					}
				}
				
				document.querySelectorAll('.js-open-join').forEach(btn => {
					btn.addEventListener('click', async () => {
						const occurrenceId = btn.dataset.occurrenceId;
						openJoinModal({
							title:   btn.dataset.title,
							date:    btn.dataset.date,
							time:    btn.dataset.time,
							tz:      btn.dataset.tz,
							address: btn.dataset.address,
						});
						const data = await fetchAvailability(occurrenceId);
						setLoading(false);
						if (!data) return;
						renderPositions(occurrenceId, data.free_positions || data.data?.free_positions || []);
					});
				});
				
				@include('events._partials.seatline_script')

				// ===== Отступ прилипания ленты дат от реальной высоты шапки =====
				// .mob-sticky имел top в rem (9.4rem/8.5rem на ≤480px), но в .is-app
				// высота шапки зависит от env(safe-area-inset-top) — разного на каждом
				// устройстве (notch/Dynamic Island/без выреза). Хардкод в rem был МЕНЬШЕ
				// реальной высоты шапки на телефонах с вырезом → прилипшая лента съезжала
				// ПОД шапку, верх чипов (день недели) обрезался. window.getFixedHeaderBottom()
				// (script.js) — единая утилита для этого расчёта, используется и здесь, и
				// десктопным поповером шапки (positionMenus в script.js) — раньше расчёт
				// был продублирован в двух местах.
				// КРИТИЧНО: этот блок должен выполняться РАНЬШЕ любого потенциально
				// рискованного кода ниже (лента дней/IntersectionObserver и т.п.) — весь
				// этот <script> выполняется последовательно как ОДИН блок, и необработанное
				// исключение в любом statement останавливает ВСЁ, что идёт после него в
				// этом же тэге (регресс 2026-08: IntersectionObserver без feature-detection
				// был объявлен раньше этого блока и в одном из билдов приложения ронял его
				// целиком — чипы дат уезжали под шапку, потому что этот код просто не
				// успевал выполниться). CSS top остаётся безопасным дефолтом, если что-то
				// в скрипте всё же упадёт.
				(function syncStickyOffset() {
					const mobSticky = document.querySelector('.events-page .mob-sticky');
					const headerEl = document.querySelector('.fix-header');
					if (!mobSticky || !headerEl) return;
					// В .is-app (Capacitor iOS/Android) top задаёт ЧИСТЫЙ CSS в style.css
					// (`.is-app .events-page .mob-sticky`, calc с env(safe-area-inset-top))
					// — env() браузер пересчитывает сам при каждом relayout, гонки в этой
					// части нет в принципе (в отличие от JS-снимка style.top, который не
					// обновляется сам, если WKWebView/Android WebView применит safe-area
					// асинхронно уже после первого apply()). Единственное, что здесь всё ещё
					// нужно от JS для .is-app — актуальная высота СОДЕРЖИМОГО шапки БЕЗ
					// safe-area (--fh-content-h: гармошка меню/шрифты/перенос текста) — это
					// уже настоящее изменение размера бокса, которое ResizeObserver ловит
					// корректно и без задержек. У .is-app своя модель шапки (top:0 +
					// padding-top:env(...), см. ".is-app .fix-header" в style.css) — safe-area
					// у неё «сидит» в padding-top, поэтому чтобы не учесть её дважды (один раз
					// в offsetHeight, второй раз явным env() в CSS-формуле чипов), вычитаем
					// текущий padding-top перед записью переменной.
					function apply() {
						mobSticky.style.top = window.getFixedHeaderBottom(12) + 'px';
						var safeTopPx = parseFloat(getComputedStyle(headerEl).paddingTop) || 0;
						document.documentElement.style.setProperty('--fh-content-h', (headerEl.offsetHeight - safeTopPx) + 'px');
					}
					apply();
					window.addEventListener('resize', apply);
					window.addEventListener('orientationchange', apply);
					window.addEventListener('load', apply);
					// Telegram WebApp SDK сообщает safe area АСИНХРОННО (viewportChanged/
					// safeAreaChanged), уже после первого рендера — обычный resize на это
					// не реагирует. voll-layout.blade.php диспатчит это кастомное событие
					// при каждом обновлении --tg-safe-area-inset-top.
					window.addEventListener('vp:header-resize', apply);
					// Догрузка шрифтов может незначительно сдвинуть высоту шапки уже
					// ПОСЛЕ 'load' (веб-шрифты часто ставятся в очередь, а не блокируют
					// load) — пересчитываем ещё раз, когда шрифты гарантированно готовы.
					if (document.fonts && document.fonts.ready) {
						document.fonts.ready.then(apply).catch(function(){});
					}
					// Возврат из фона (свёрнутое приложение/вкладка) — на некоторых
					// платформах safe-area/высота статус-бара пересчитывается заново
					// только в этот момент, не через resize.
					document.addEventListener('visibilitychange', function() {
						if (!document.hidden) apply();
					});
					// Самый надёжный триггер: ResizeObserver прямо на .fix-header —
					// ловит ЛЮБУЮ причину изменения её реальной высоты (safe-area,
					// сворачивание топбара, переносы текста и т.п.) одним общим
					// механизмом, без необходимости перечислять причины по отдельности.
					// apply() дешёвый (просто пишет style.top), дёргать его часто
					// безопасно. Поддержан WKWebView; на средах без ResizeObserver
					// (крайне маловероятно) остаются остальные обработчики выше.
					if ('ResizeObserver' in window) {
						new ResizeObserver(apply).observe(headerEl);
					}
				})();

				// ===== Сворачивание топбара при "прилипании" ленты дат =====
				// .mob-sticky прилипает к верху при скролле — в этот момент топбар
				// (фильтр/направление/фото/гео) внутри той же карточки сворачивается,
				// остаются видны только чипы дат. Проверяем через getBoundingClientRect
				// против реального top (теперь считается в syncStickyOffset выше, а не
				// хардкодится в rem) — так брейкпоинт не приходится дублировать в JS.
				(function initStickyCollapse() {
					const mobSticky = document.querySelector('.events-page .mob-sticky');
					const ramka = document.querySelector('.event-dates-ramka');
					if (!mobSticky || !ramka) return;

					let ticking = false;
					function checkStuck() {
						ticking = false;
						const stickyTop = parseFloat(getComputedStyle(mobSticky).top) || 0;
						const rectTop = mobSticky.getBoundingClientRect().top;
						ramka.classList.toggle('is-scrolled', rectTop <= stickyTop + 1);
					}
					window.addEventListener('scroll', function() {
						if (ticking) return;
						ticking = true;
						requestAnimationFrame(checkStuck);
					}, { passive: true });
					checkStuck();
				})();

				// ===== Лента дней: чипы ↔ скролл (двусторонняя связь) =====
				// Клик по чипу — плавный скролл к разделу дня, с учётом реальной
				// высоты липкой ленты дат (.mob-sticky), измеренной getBoundingClientRect
				// (тот же приём, что и в syncStickyOffset выше) — без этого раздел дня
				// уезжал бы ПОД шапку/ленту. Обратная связь (скролл → активный чип) —
				// через IntersectionObserver, с явной проверкой поддержки (как и
				// lazyMaps в script.js) — не все WebView-сборки приложения его
				// поддерживают одинаково надёжно, а необработанное исключение здесь
				// остановило бы остальной код в этом <script>-теге. Весь блок в
				// try/catch — тоже профилактика от каскадного отказа: критичный
				// layout-код (syncStickyOffset/initStickyCollapse) уже отработал ВЫШЕ,
				// но countdown/toggle-фото/поп-ап фильтров идут ПОСЛЕ этого блока и не
				// должны от него зависеть.
				try {
					// Единая точка правды для "где заканчивается прилипший верх страницы"
					// (шапка сайта + топбар фильтров + лента чипов дат, весь .mob-sticky,
					// НЕ только .fix-header — они разной высоты!). Используется и при
					// скролле к разделу дня (scrollToDaySection), и при определении
					// активного дня по скроллу (recomputeActiveDay) — нашли на реальном
					// баге (2026-08), что использование ДВУХ РАЗНЫХ границ (одна через
					// .mob-sticky.bottom, другая по ошибке через window.getFixedHeaderBottom()
					// — это высота ТОЛЬКО шапки сайта, без топбара/чипов) давало
					// систематическое смещение активного дня "на один" относительно
					// реальной прокрученной позиции.
					function stickyBottom() {
						const stickyBar = document.querySelector('.events-page .mob-sticky');
						return stickyBar ? stickyBar.getBoundingClientRect().bottom : 0;
					}

					// Явный клик по чипу — приоритетнее фоновой эвристики IntersectionObserver
					// на короткое окно после клика. Нашли на реальном баге (2026-08): IO
					// продолжает срабатывать ещё ~200мс ПОСЛЕ финальной scrollend-коррекции
					// (микро-сдвиг в несколько px из-за сворачивания/разворачивания топбара
					// у самого начала ленты — там порог is-scrolled особенно чувствителен),
					// и это позднее срабатывание иногда переопределяло только что
					// зафиксированный явным кликом активный день обратно на соседний.
					// Пользователь явно выбрал день тапом — эвристика скролла не должна его
					// перебивать, пока он ещё не начал скроллить сам.
					let suppressObserverUntil = 0;

					function scrollToDaySection(dateKey) {
						suppressObserverUntil = performance.now() + 1500;
						const target = document.querySelector('.day-section[data-date="' + dateKey + '"]');
						if (!target) return;

						function alignedTop() {
							return Math.max(0, target.getBoundingClientRect().top + window.pageYOffset - stickyBottom() - 12);
						}

						window.scrollTo({ top: alignedTop(), behavior: 'smooth' });

						// Топбар дат (фильтр/направление/фото/гео) сворачивается/разворачивается
						// при пересечении порога "прилипания" (is-scrolled) — если скролл
						// проходит через этот порог, высота .mob-sticky меняется ПРЯМО ВО ВРЕМЯ
						// анимации, и разовый расчёт до старта промахивается. РАНЬШЕ здесь была
						// доводка через фиксированный setTimeout(450) — не подошло: реальная
						// длительность smooth-скролла зависит от дистанции и может занимать
						// больше секунды на длинных прокрутках (замерено), поэтому таймаут либо
						// стрелял раньше окончания анимации (WebKit может игнорировать/откладывать
						// scrollTo, вызванный ПОКА идёт другая smooth-анимация — итоговая позиция
						// оставалась по старому, неверному расчёту), либо позже необходимого.
						// Теперь ждём РЕАЛЬНОГО завершения скролла: нативное событие 'scrollend'
						// (Chrome/Safari 16.4+), либо RAF-поллинг "scrollY не меняется N кадров
						// подряд" как фоллбэк — и только тогда одна корректирующая доводка по
						// уже осевшей (после сворачивания/разворачивания топбара) высоте.
						// После окончания скролла ЯВНО фиксируем активный чип на dateKey —
						// не полагаемся только на IntersectionObserver. Нашли на реальном
						// баге (2026-08): IO закономерно перехватывает ПРОМЕЖУТОЧНЫЙ день во
						// время самой анимации (пролетая мимо него), а после финальной
						// доводки в несколько px (недостаточно для нового пересечения) НЕ
						// перевычисляется заново — активным навсегда "застревал" день,
						// увиденный мельком в процессе скролла, а не тот, что реально выбрал
						// пользователь.
						function finalizeActiveChip() {
							setActiveChip(dateKey, { centerChip: true, updateUrl: true });
							// IntersectionObserver — асинхронный (колбэк всегда в отдельной
							// задаче/микротаске) и может сработать ЧУТЬ ПОЗЖЕ этого вызова,
							// перезаписав активный чип обратно на промежуточный — особенно
							// заметно на коротких скроллах между соседними днями (анимация
							// почти мгновенная, гонка более вероятна). Повторное подтверждение
							// на следующем кадре гарантированно побеждает такую гонку.
							requestAnimationFrame(() => setActiveChip(dateKey, { centerChip: true, updateUrl: true }));
						}

						if ('onscrollend' in window) {
							const onEnd = () => {
								window.removeEventListener('scrollend', onEnd);
								window.scrollTo({ top: alignedTop(), behavior: 'auto' });
								finalizeActiveChip();
							};
							window.addEventListener('scrollend', onEnd);
						} else {
							let lastY = window.scrollY;
							let stableFrames = 0;
							let ticks = 0;
							const maxTicks = 180; // ~3с на 60fps — страховка от бесконечного polling
							function poll() {
								ticks++;
								const y = window.scrollY;
								if (y === lastY) {
									stableFrames++;
								} else {
									stableFrames = 0;
									lastY = y;
								}
								if (stableFrames >= 3 || ticks >= maxTicks) {
									window.scrollTo({ top: alignedTop(), behavior: 'auto' });
									finalizeActiveChip();
									return;
								}
								requestAnimationFrame(poll);
							}
							requestAnimationFrame(poll);
						}
					}

					// Центрирует чип в горизонтальной ленте вручную через scrollLeft — НЕ
					// через chip.scrollIntoView(). Найдено на реальном баге (2026-08):
					// scrollIntoView({inline:'center'}) официально скроллит только СВОЙ
					// ближайший scroll-контейнер по нужной оси, но реализация в части
					// движков (в т.ч. воспроизведено в headless Chromium через
					// IntersectionObserver, вызывающий эту функцию ПРЯМО ВО ВРЕМЯ вертикальной
					// smooth-анимации скролла страницы) может задеть и вертикальный скролл
					// СТРАНИЦЫ как побочный эффект — из-за этого тап по чипу иногда уводил
					// страницу на сотни-тысячи px мимо цели (особенно при скролле вверх).
					// Прямая работа с scrollLeft полностью исключает любое влияние на
					// вертикальный скролл — эта функция гарантированно трогает только ленту.
					function centerChipInStrip(chip) {
						const strip = document.getElementById('daysStrip');
						if (!strip || !chip) return;
						const stripRect = strip.getBoundingClientRect();
						const chipRect = chip.getBoundingClientRect();
						const delta = (chipRect.left + chipRect.right) / 2 - (stripRect.left + stripRect.right) / 2;
						strip.scrollLeft += delta;
					}

					function setActiveChip(dateKey, options) {
						options = options || {};
						document.querySelectorAll('.day-chip[data-date]').forEach(c => {
							c.classList.toggle('active', c.dataset.date === dateKey);
						});
						if (options.centerChip) {
							const chip = document.querySelector('.day-chip[data-date="' + dateKey + '"]');
							if (chip) centerChipInStrip(chip);
						}
						if (options.updateUrl && window.history && window.history.replaceState) {
							const url = new URL(window.location.href);
							url.searchParams.set('date', dateKey);
							window.history.replaceState(null, '', url.toString());
						}
					}

					document.querySelectorAll('.day-chip[data-date]').forEach(chip => {
						chip.addEventListener('click', () => {
							setActiveChip(chip.dataset.date, { centerChip: true, updateUrl: true });
							scrollToDaySection(chip.dataset.date);
						});
					});

					if ('IntersectionObserver' in window) {
						const sections = Array.from(document.querySelectorAll('.day-section[data-date]'));
						if (sections.length) {
							// Нашли на реальном баге (2026-08): IntersectionObserver присылает в
							// entries ТОЛЬКО элементы, чьё пересечение ИЗМЕНИЛОСЬ с прошлого
							// вызова, а не полный снимок всех видимых секций — выбор "активной"
							// ТОЛЬКО среди entries этого конкретного batch (и даже среди всех
							// currently-intersecting, если секция физически высокая и её top
							// давно ушёл далеко за экран, а bottom ещё в зоне) регулярно давал
							// не тот день, особенно при скролле вверх. Надёжный паттерн —
							// IntersectionObserver ТОЛЬКО как триггер "что-то изменилось,
							// пересчитай", а сам выбор активного дня — прямой запрос
							// getBoundingClientRect() ПО ВСЕМ секциям заново при каждом
							// срабатывании: "последняя секция, чей заголовок уже пересёк
							// границу под шапкой" (максимальный top среди top ≤ границы).
							function recomputeActiveDay() {
								if (performance.now() < suppressObserverUntil) return;
								// stickyBottom()+12 — ТА ЖЕ граница и тот же запас, что и в
								// alignedTop() при скролле к разделу (иначе систематическое
								// смещение активного дня "на один", см. комментарий выше про
								// stickyBottom()).
								const boundary = stickyBottom() + 12;
								let bestDate = null;
								let bestTop = -Infinity;
								sections.forEach(s => {
									const top = s.getBoundingClientRect().top;
									if (top <= boundary + 1 && top > bestTop) {
										bestTop = top;
										bestDate = s.dataset.date;
									}
								});
								if (!bestDate) bestDate = sections[0].dataset.date;
								setActiveChip(bestDate, { centerChip: true, updateUrl: true });
							}

							const stickyBar = document.querySelector('.events-page .mob-sticky');
							const stickyH = stickyBar ? stickyBar.getBoundingClientRect().height : 100;
							const observer = new IntersectionObserver(recomputeActiveDay, {
								root: null,
								rootMargin: '-' + Math.ceil(stickyH + 20) + 'px 0px -70% 0px',
								threshold: 0,
							});

							sections.forEach(s => observer.observe(s));
						}
					}
				} catch (e) {
					console.error('events day-feed scroll sync error', e);
				}

				// ===== Countdown =====
				function pad2(n) { n = Math.max(0, n|0); return (n < 10 ? '0' : '') + n; }
				function tickCountdown(el) {
					var iso = el.getAttribute('data-target-utc');
					if (!iso) return;
					var target = Date.parse(iso);
					if (isNaN(target)) return;
					var diff = target - Date.now();
					if (diff <= 0) { el.textContent = @json(__('events.countdown_open')); return; }
					var totalMin = Math.floor(diff / 60000);
					var days = Math.floor(totalMin / (60*24));
					var minsLeft = totalMin - days*60*24;
					var hh = Math.floor(minsLeft / 60);
					var mm = minsLeft - hh*60;
					var ddEl = el.querySelector('[data-dd]');
					var hhmmEl = el.querySelector('[data-hhmm]');
					if (ddEl) ddEl.textContent = String(days);
					if (hhmmEl) hhmmEl.textContent = pad2(hh) + ':' + pad2(mm);
				}
				function tickAllCountdowns() {
					document.querySelectorAll('[data-countdown]').forEach(tickCountdown);
				}
				tickAllCountdowns();
				setInterval(tickAllCountdowns, 30000);
				
				// ===== Toggle ALL photos =====
				var _allHidden = JSON.parse(localStorage.getItem('eventImgHidden') || '{}').hidden || false;
				
				function applyImgState(hidden) {
					document.querySelectorAll('.card-img-top img').forEach(function(img) {
						if (hidden) {
							// Скрываем
							img.style.display = 'none';
							} else {
							// Показываем и подменяем data-src на src
							img.style.display = '';
							
							// Если есть атрибут data-src, переносим его значение в src
							if (img.hasAttribute('data-src')) {
								var dataSrcValue = img.getAttribute('data-src');
								img.setAttribute('src', dataSrcValue);
								// Опционально: удаляем data-src, чтобы не делать это повторно
								img.removeAttribute('data-src');
							}
						}
					});
					
					var btn = document.getElementById('btn-toggle-all-imgs');
					if (btn) {
						btn.classList.toggle('is-active', hidden);
						var label = hidden ? @json(__('events.btn_show_photos')) : @json(__('events.btn_hide_photos'));
						btn.setAttribute('title', label);
						btn.setAttribute('aria-label', label);
					}
					localStorage.setItem('eventImgHidden', JSON.stringify({ hidden: hidden }));
					_allHidden = hidden;
				}
				
				// применяем сохранённое состояние при загрузке
				if (_allHidden) {
					applyImgState(true);
					} else {
					// если не скрыты, заменяем data-src на src
					document.querySelectorAll('.card-img-top img').forEach(function(img) {
						if (img.hasAttribute('data-src')) {
							img.src = img.getAttribute('data-src');
						}
					});
				}
				
				window.toggleAllImgs = function(btn) {
					applyImgState(!_allHidden);
				};

				// ===== Поп-ап "Фильтры" (тип/уровень/локация) =====
				var btnOpenFilters = document.getElementById('btnOpenFilters');
				if (btnOpenFilters) {
					btnOpenFilters.addEventListener('click', function() {
						jQuery.fancybox.open({
							src: '#eventsFilterModal',
							type: 'inline',
							opts: { hideScrollbar: false, touch: false, toolbar: false, smallBtn: true, animationEffect: 'zoom-in-out', transitionEffect: 'zoom-in-out', baseClass: 'events-filter-fancybox' }
						});
					});
				}
			</script>
		</x-slot>
		
		
	</x-voll-layout>
