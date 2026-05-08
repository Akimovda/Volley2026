@php

$nowUtc  = \Illuminate\Support\Carbon::now('UTC');
$occList = $occurrences ?? collect();
$evList  = $events ?? collect();

$hasOcc = false;
if ($occList instanceof \Illuminate\Contracts\Pagination\Paginator) {
$hasOcc = $occList->count() > 0;
} elseif ($occList instanceof \Illuminate\Support\Collection) {
$hasOcc = $occList->isNotEmpty();
}

$hasEvents = false;
if (!$hasOcc) {
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

// ✅ Группировка по датам — по TZ пользователя
$groupedByDate = [];
$today    = \Illuminate\Support\Carbon::now($userTz)->startOfDay();
$todayKey = $today->format('Y-m-d');

if ($hasOcc) {
foreach ($occList as $occ) {
$date = $occ->starts_at
? \Illuminate\Support\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($userTz)->startOfDay()
: null;
if (!$date) continue;

$dateKey = $date->format('Y-m-d');
if ($dateKey < $todayKey) continue;

if (!isset($groupedByDate[$dateKey])) {
$groupedByDate[$dateKey] = ['date' => $date, 'occurrences' => []];
	}
	$groupedByDate[$dateKey]['occurrences'][] = $occ;
	}
	
	if (!empty($groupedByDate) && is_array($groupedByDate)) {
	ksort($groupedByDate);
	$groupedByDate = array_slice($groupedByDate, 0, 10, true);
	} else {
	$groupedByDate = [];
	}
    }
	
    $months = __('events.month_short');
    $daysOfWeek = __('events.dow_short');
	
    $formatOptions = [];
    $levelOptions  = [];
    if ($hasOcc) {
	foreach ($occList as $occ) {
	$e = $occ->event;
	if (!$e) continue;
	
	$fmt = (string)($e->format ?? '');
	if ($fmt !== '') $formatOptions[$fmt] = $fmt;
	
	foreach (['classic_level_min','classic_level_max','beach_level_min','beach_level_max'] as $col) {
	$v = $e->{$col} ?? null;
	if (!is_null($v) && is_numeric($v)) $levelOptions[(int)$v] = (int)$v;
	}
	}
    }
    ksort($formatOptions);
    ksort($levelOptions);
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
            @if($hasOcc && !empty($groupedByDate))
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
		
		<x-slot name="d_description">
			<div class="d-flex flex-wrap gap-1 m-center">
				<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
					<button class="btn ufilter-btn">{{ __('events.btn_filter') }}</button>
				</div>
				<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
					<button type="button" id="btn-toggle-all-imgs" class="btn btn-secondary" onclick="toggleAllImgs(this)">{{ __('events.btn_hide_photos') }}</button>
				</div>						
			</div>
		</x-slot>
		
		<x-slot name="style">
            <style>
				
			</style>
		</x-slot>
		
		
		
		<div class="container">
			@php
			$fDir    = request('direction', '');
			$fFormat = request('format', '');
			$fLevel  = request('level', '');
			@endphp
			
			
			
			<div class="users-filter">
				<div class="ramka">
					<div class="form">
						@php
						$fDir      = request('direction', '');
						$fFormat   = request('format', '');
						$fLevel    = request('level', '');
						$fLocation = request('location', '');
$fCity     = request('city', '');
						
						$formatLabels = [
						'game'               => __('events.fmt_game'),
						'training'           => __('events.fmt_training'),
						'training_game'      => __('events.fmt_training_game'),
						'training_pro_am'    => __('events.fmt_training_pro_am'),
						'coach_student'      => __('events.fmt_coach_student'),
						'tournament'         => __('events.fmt_tournament'),
						'tournament_classic' => __('events.fmt_tournament_classic'),
						'tournament_beach'   => __('events.fmt_tournament_beach'),
						'camp'               => __('events.fmt_camp'),
						];
						@endphp
						<form method="GET" action="{{ route('events.index') }}">
							<div class="row g-2">
								<div class="col-12 col-md-3">
									<label class="form-label mb-1">{{ __('events.filter_direction') }}</label>
									<select name="direction" class="form-select">
										<option value="" {{ $fDir==='' ? 'selected' : '' }}>{{ __('events.filter_any') }}</option>
										<option value="classic" {{ $fDir==='classic' ? 'selected' : '' }}>{{ __('events.filter_classic') }}</option>
										<option value="beach" {{ $fDir==='beach' ? 'selected' : '' }}>{{ __('events.filter_beach') }}</option>
									</select>
								</div>
								
								<div class="col-12 col-md-3">
									<label class="form-label mb-1">{{ __('events.filter_event_type') }}</label>
									<select name="format" class="form-select">
										<option value="" {{ $fFormat==='' ? 'selected' : '' }}>{{ __('events.filter_any') }}</option>
										@foreach($formatLabels as $k => $lbl)
										<option value="{{ $k }}" {{ $fFormat===$k ? 'selected' : '' }}>{{ $lbl }}</option>
										@endforeach
									</select>
								</div>
								
								<div class="col-12 col-md-2">
									<label class="form-label mb-1">{{ __('events.filter_level') }}</label>
									<select name="level" class="form-select">
										<option value="" {{ $fLevel==='' ? 'selected' : '' }}>{{ __('events.filter_any_level') }}</option>
										@foreach(($levelOptions ?? []) as $lv)
										<option value="{{ (int)$lv }}" {{ (string)$fLevel===(string)$lv ? 'selected' : '' }}>{{ (int)$lv }}</option>
										@endforeach
									</select>
								</div>
								
                                <div class="col-12 col-md-4">
                                    <label class="form-label mb-1">{{ __('events.filter_location') }}</label>
                                    <input type="text"
									name="location"
									class="form-control"
									placeholder="{{ __('events.filter_location_ph') }}"
									value="{{ e($fLocation) }}"
									id="filter-location-input"
									autocomplete="off"
									list="location-datalist"
                                    >
                                    <datalist id="location-datalist"></datalist>
								</div>
								
								<div class="col-12 d-flex flex-wrap gap-2 align-items-center">
@auth
@if(auth()->user()->city_id)
<label class="checkbox-item">
<input type="checkbox" name="city" value="all" {{ $fCity === 'all' ? 'checked' : '' }}>
<div class="custom-checkbox"></div>
<span>{{ __('events.filter_all_cities') }}</span>
</label>
@endif
@endauth
									<button type="submit" class="btn">{{ __('events.filter_apply') }}</button>
									<a href="{{ route('events.index') }}" class="btn btn-secondary">{{ __('events.filter_reset') }}</a>
								</div>
							</div>
						</form>
					</div>
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
			@if ($hasOcc)
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
                            @endphp
                            <a href="#days" class="tab day-chip {{ $loop->first ? 'active' : '' }}"
							data-tab="day-{{ $loop->iteration }}"
							data-date="{{ $dateKey }}">
                                <div class="dc-date">{{ $labelDate }}</div>
                                <div class="dc-dow">{{ $dow }}</div>
							</a>
                            @endforeach
							
                            {{-- Навигация prev/next --}}
                            @if(count($groupedByDate) >= 10 || request('offset', 0) > 0)
                            @php
							$currentOffset = (int) request('offset', 0);
							$nextOffset    = $currentOffset + 10;
							$prevOffset    = max(0, $currentOffset - 10);
							$baseParams    = array_filter([
							'direction' => request('direction'),
							'format'    => request('format'),
							'level'     => request('level'),
							'location'  => request('location'),
							], fn($v) => $v !== '' && $v !== null);
                            @endphp
							
                            @if($currentOffset > 0)
                            <a href="{{ route('events.index', array_merge($baseParams, ['offset' => $prevOffset])) }}"
							class="no-highlight day-chip last-tab tab">
                                <div class="dc-dow">{{ __('events.days_prev') }}</div>
                                <div class="dc-date">{{ __('events.days_n_days') }}</div>
							</a>
                            @endif
							
                            @if(count($groupedByDate) >= 10)
                            <a href="{{ route('events.index', array_merge($baseParams, ['offset' => $nextOffset])) }}"
							class="no-highlight day-chip last-tab tab">
                                <div class="dc-dow">{{ __('events.days_next') }}</div>
                                <div class="dc-date">{{ __('events.days_n_days') }}</div>
							</a>
                            @endif
                            @endif
                            <div class="tab-highlight"></div>
						</div>
					</div>
				</div>
				
				
				<div class="tab-panes">
                    @foreach($groupedByDate as $dateKey => $dayData)
                    <div class="tab-pane {{ $loop->first ? 'active' : '' }}" id="day-{{ $loop->iteration }}">
                        <div class="row mb-0">
                            @foreach ($dayData['occurrences'] as $occ)
							@include('events._card')
                            @endforeach
						</div>
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
				
				// ===== Seats line =====
				const seatLines = Array.from(document.querySelectorAll('[data-seatline]'));
				
				async function loadSeatLine(el) {
					const occId        = el.dataset.occurrenceId;
					const regEnabled   = el.dataset.registrationEnabled === '1';
					const maxCard      = Number(el.dataset.maxPlayers ?? 0) || 0;
					if (maxCard <= 0) return;

					const leftEl  = el.querySelector('[data-left]');
					const totalEl = el.querySelector('[data-total]');
					if (totalEl) totalEl.textContent = String(maxCard);

					if (!regEnabled) {
						if (leftEl) leftEl.textContent = '0';
						return;
					}

					try {
						const res = await fetch(`/occurrences/${occId}/availability`, {
							method: 'GET',
							headers: { 'Accept': 'application/json' },
							credentials: 'same-origin',
						});
						let data = null;
						try { data = await res.json(); } catch (e) {}

						const meta = data?.meta || data?.data?.meta || null;
						if (!data || !meta) {
							if (leftEl) leftEl.textContent = '0';
							return;
						}

						const isTournament = el.dataset.isTournament === '1';
						if (isTournament && meta.tournament_teams_max > 0) {
							const tMax = Number(meta.tournament_teams_max);
							const tReg = Number(meta.tournament_teams_registered ?? 0) || 0;
							if (leftEl)  leftEl.textContent  = String(tReg);
							if (totalEl) totalEl.textContent = String(tMax);
						} else {
							const apiMax       = Number(meta.total_capacity ?? meta.max_players ?? 0) || 0;
							const effectiveMax = apiMax > 0 ? apiMax : maxCard;
							const registeredTotal = Number(meta.registered_total ?? 0) || 0;
							if (leftEl)  leftEl.textContent  = String(registeredTotal);
							if (totalEl) totalEl.textContent = String(effectiveMax);
						}
					} catch (e) {}
				}
				
				if (seatLines.length) {
					const concurrency = 3;
					let i = 0;
					async function worker() {
						while (i < seatLines.length) { const idx = i++; await loadSeatLine(seatLines[idx]); }
					}
					for (let k = 0; k < concurrency; k++) worker();
				}
				
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
					chip.addEventListener('click', () => activateTab(chip.dataset.tab));
				});
				
				(function initToday() {
					const chips = Array.from(document.querySelectorAll('.day-chip'));
					if (!chips.length) return;
					const root     = document.getElementById('eventsTabsRoot');
					const todayKey = root?.dataset?.today ?? null;
					const todayChip = todayKey ? chips.find(c => c.dataset.date === todayKey) : null;
					if (todayChip) {
						activateTab(todayChip.dataset.tab);
						todayChip.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
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
					if (btn) btn.textContent = hidden ? @json(__('events.btn_show_photos')) : @json(__('events.btn_hide_photos'));
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
				// ===== Location autocomplete =====
				(function () {
					var datalist = document.getElementById('location-datalist');
					var input    = document.getElementById('filter-location-input');
					if (!datalist || !input) return;
					
					fetch('/ajax/locations/with-events?active=1', {
						headers: { 'Accept': 'application/json' },
						credentials: 'same-origin',
					})
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (!data.ok || !Array.isArray(data.items)) return;
						data.items.forEach(function(item) {
							var opt = document.createElement('option');
							opt.value = item.name;
							datalist.appendChild(opt);
						});
					})
					.catch(function() {});
				})();
			</script>
		</x-slot>	
		
		
	</x-voll-layout>
