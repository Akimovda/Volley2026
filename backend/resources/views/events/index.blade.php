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

// ✅ Полное окно — 10 календарных дней подряд начиная с "сегодня" (+ offset*10),
// по TZ пользователя. Каждый день попадает в чипы НЕЗАВИСИМО от наличия событий
// (иначе зелёная точка "есть события" бессмысленна — она была бы у всех
// показанных дней). offset — та же пагинация, что читает EventIndexService.
$dayOffset = max(0, (int) request('offset', 0));
$groupedByDate = [];

if ($isOccurrenceMode) {
$windowStart = \Illuminate\Support\Carbon::now($userTz)->startOfDay()->addDays($dayOffset * 10);
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

		<x-slot name="d_description">
			<div class="events-topbar mt-2" data-aos-delay="250" data-aos="fade-up">
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
			<div id="eventsTabsRoot" data-today="{{ \Illuminate\Support\Carbon::now($userTz)->format('Y-m-d') }}"></div>
			<div class="tabs-content">	
				<div id="days"></div>	
				<div class="mob-sticky">
					<div class="card-ramka event-dates-ramka">
						
						<div class="days-strip tabs mb-0" id="daysStrip">
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
                            <a href="#days" class="tab day-chip {{ $isWeekend ? 'is-weekend' : '' }} {{ $loop->first ? 'active' : '' }}"
							data-tab="day-{{ $loop->iteration }}"
							data-date="{{ $dateKey }}"
							title="{{ $labelDate }}">
                                <div class="dc-dow">{{ $dow }}</div>
                                <div class="dc-date">{{ $day }}</div>
                                <span class="dc-dot {{ $dayHasEvents ? '' : 'dc-dot--empty' }}" aria-hidden="true"></span>
							</a>
                            @endforeach
							
                            {{-- Навигация prev/next — окно всегда ровно 10 календарных дней,
                            поэтому "следующие 10 дней" показываем всегда, а "предыдущие" —
                            только если мы не на первом окне (offset>0). --}}
                            @php
							$currentOffset = (int) request('offset', 0);
							$nextOffset    = $currentOffset + 10;
							$prevOffset    = max(0, $currentOffset - 10);
							$baseParams    = array_filter([
							'direction' => request('direction'),
							'format'    => request('format'),
							'level'     => request('level'),
							'location'  => request('location'),
							'city'      => request('city'),
							], fn($v) => $v !== '' && $v !== null);
                            @endphp

                            @if($currentOffset > 0)
                            <a href="{{ route('events.index', array_merge($baseParams, ['offset' => $prevOffset])) }}"
							class="no-highlight day-chip last-tab tab">
                                <div class="dc-dow">{{ __('events.days_prev') }}</div>
                                <div class="dc-date">{{ __('events.days_n_days') }}</div>
							</a>
                            @endif

                            <a href="{{ route('events.index', array_merge($baseParams, ['offset' => $nextOffset])) }}"
							class="no-highlight day-chip last-tab tab">
                                <div class="dc-dow">{{ __('events.days_next') }}</div>
                                <div class="dc-date">{{ __('events.days_n_days') }}</div>
						</a>
                            <div class="tab-highlight"></div>
						</div>
					</div>
				</div>
				
				
				<div class="tab-panes">
                    @foreach($groupedByDate as $dateKey => $dayData)
                    <div class="tab-pane {{ $loop->first ? 'active' : '' }}" id="day-{{ $loop->iteration }}">
                        @if(empty($dayData['occurrences']))
                        <div class="ramka">
                            <div class="alert alert-info">{{ __('events.empty_list_day') }}</div>
                        </div>
                        @else
                        <div class="row mb-0">
                            @foreach ($dayData['occurrences'] as $occ)
							@include('events._card')
                            @endforeach
						</div>
						@endif
					</div>
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
				
				// ===== Days strip =====
				function activateTab(tabId) {
					document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
					const pane = document.getElementById(tabId);
					if (pane) pane.classList.remove('hidden');
					document.querySelectorAll('.day-chip').forEach(c => c.classList.remove('active'));
					const chip = document.querySelector(`.day-chip[data-tab="${tabId}"]`);
					if (chip) chip.classList.add('active');
				}
				
				document.querySelectorAll('.day-chip').forEach(chip => {
					chip.addEventListener('click', (e) => {
						if (!chip.dataset.tab) return; // кнопка смены диапазона — пропускаем href
						e.preventDefault();
						activateTab(chip.dataset.tab);
					});
				});
				
				(function initToday() {
					const chips = Array.from(document.querySelectorAll('.day-chip'));
					if (!chips.length) return;
					const root     = document.getElementById('eventsTabsRoot');
					const todayKey = root?.dataset?.today ?? null;
					const todayChip = todayKey ? chips.find(c => c.dataset.date === todayKey) : null;
					if (todayChip) {
						activateTab(todayChip.dataset.tab);
						// горизонтальный скролл полосы дней — только inline, без вертикального сдвига страницы
						todayChip.scrollIntoView({ behavior: 'instant', inline: 'center', block: 'nearest' });
					} else {
						activateTab(chips[0].dataset.tab);
					}
				})();
				
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
