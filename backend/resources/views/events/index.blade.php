{{-- resources/views/events/index.blade.php --}}
@php
$nowUtc = \Illuminate\Support\Carbon::now('UTC');

$occList = ($occurrences ?? collect());
$evList  = ($events ?? collect());

$hasOcc    = ($occList instanceof \Illuminate\Support\Collection) && $occList->isNotEmpty();
$hasEvents = (!$hasOcc) && ($evList instanceof \Illuminate\Support\Collection) && $evList->isNotEmpty();

$fmtDate = function ($occ) {
$tz = $occ->timezone ?: ($occ->event?->timezone ?: 'UTC');
$s = $occ->starts_at ? \Illuminate\Support\Carbon::parse($occ->starts_at)->setTimezone($tz) : null;
$e = $occ->ends_at ? \Illuminate\Support\Carbon::parse($occ->ends_at)->setTimezone($tz) : null;
if (!$s) return ['date' => '—', 'time' => '—', 'tz' => $tz];
$date = $s->format('d.m.Y');
$time = $s->format('H:i') . ($e ? '–' . $e->format('H:i') : '');
return ['date' => $date, 'time' => $time, 'tz' => $tz];
};

$fmtEventDate = function ($event) {
$tz = $event?->timezone ?: 'UTC';
$s = $event?->starts_at ? \Illuminate\Support\Carbon::parse($event->starts_at)->setTimezone($tz) : null;
$e = $event?->ends_at ? \Illuminate\Support\Carbon::parse($event->ends_at)->setTimezone($tz) : null;
if (!$s) return ['date' => '—', 'time' => '—', 'tz' => $tz];
$date = $s->format('d.m.Y');
$time = $s->format('H:i') . ($e ? '–' . $e->format('H:i') : '');
return ['date' => $date, 'time' => $time, 'tz' => $tz];
};

$trainersById   = $trainersById ?? [];
$trainerColumn  = $trainerColumn ?? null;
$trainerIconUrl = asset('icons/trainer.png');

// ✅ Группировка по датам — ТОЛЬКО ДЛЯ OCCURRENCES (вариант 1)
$groupedByDate = [];
$today    = now()->startOfDay();
$todayKey = $today->format('Y-m-d');

if ($hasOcc) {
foreach ($occList as $occ) {
$tz = $occ->timezone ?: ($occ->event?->timezone ?: 'UTC');
$date = $occ->starts_at
? \Illuminate\Support\Carbon::parse($occ->starts_at)->setTimezone($tz)->startOfDay()
: null;

if (!$date) continue;

$dateKey = $date->format('Y-m-d');

// ✅ архивные дни не показываем на /events
if ($dateKey < $todayKey) continue;

if (!isset($groupedByDate[$dateKey])) {
$groupedByDate[$dateKey] = [
'date' => $date,
	'occurrences' => [],
	];
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
	
    // для отображения дней
    $months = [
	1 => 'янв', 2 => 'фев', 3 => 'мар', 4 => 'апр', 5 => 'май', 6 => 'июн',
	7 => 'июл', 8 => 'авг', 9 => 'сен', 10 => 'окт', 11 => 'ноя', 12 => 'дек'
    ];
    $daysOfWeek = [
	1 => 'пн', 2 => 'вт', 3 => 'ср', 4 => 'чт', 5 => 'пт', 6 => 'сб', 7 => 'вс'
    ];
	
    // ====== Опции для фильтров (строим по occurrences, иначе пусто — не ломаем) ======
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
		<x-slot name="title">Мероприятия</x-slot>
		<x-slot name="description">Мероприятия</x-slot>
		<x-slot name="canonical">{{ route('events.index') }}</x-slot>
		
		<x-slot name="breadcrumbs">
			<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="{{ route('events.index') }}" itemprop="item">
					<span itemprop="name">Мероприятия</span>
				</a>
				<meta itemprop="position" content="2">
			</li>
		</x-slot>
		
		<x-slot name="h1">Мероприятия</x-slot>
		
		<x-slot name="h2">
			@if($hasOcc && !empty($groupedByDate))
            {{ array_key_first($groupedByDate) ? \Illuminate\Support\Carbon::parse(array_key_first($groupedByDate))->format('d.m.Y') : '' }}
            -
            {{ array_key_last($groupedByDate) ? \Illuminate\Support\Carbon::parse(array_key_last($groupedByDate))->format('d.m.Y') : '' }}
			@endif
		</x-slot>
		
		<x-slot name="t_description">
			Игры и тренеровки на нашей платформе
		</x-slot>
		
		<x-slot name="d_description">
			<div data-aos-delay="250" data-aos="fade-up">
				<button class="btn ufilter-btn mt-2">Фильтр</button>
			</div>
		</x-slot>
		
		<x-slot name="style">
			<style>
				
				.days-strip { overflow-x:auto; -webkit-overflow-scrolling: touch; scroll-snap-type: x mandatory;}
				.day-chip { padding: 1rem 1.8rem; min-width: 5rem; scroll-snap-align: start; text-align:center; cursor:pointer; user-select:none; line-height: 1.15; }
				.day-chip .dc-date { font-weight: 600; }
				.day-chip .dc-dow  { font-size: 12px; opacity: .85; margin-top: 2px; }
				
				
				.join-backdrop.hidden { display:none; }
				.join-backdrop { position:fixed; inset:0; z-index:1050; background:rgba(0,0,0,.55); }
				.join-modal { max-width: 720px; width: 100%; background:#fff; border-radius:16px; overflow:hidden; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
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
						<form method="GET" action="{{ route('events.index') }}">
							<div class="row">
								<div class="col-12 col-md-4">
									<label class="form-label mb-1">Направление</label>
									<select name="direction" class="form-select">
										<option value="" {{ $fDir==='' ? 'selected' : '' }}>Все</option>
										<option value="classic" {{ $fDir==='classic' ? 'selected' : '' }}>Классика</option>
										<option value="beach" {{ $fDir==='beach' ? 'selected' : '' }}>Пляжка</option>
									</select>
								</div>
								
								<div class="col-12 col-md-4">
									<label class="form-label mb-1">Тип мероприятия</label>
									<select name="format" class="form-select">
										<option value="" {{ $fFormat==='' ? 'selected' : '' }}>Все</option>
										@foreach(($formatOptions ?? []) as $k => $title)
										<option value="{{ e($k) }}" {{ $fFormat===$k ? 'selected' : '' }}>{{ e($title) }}</option>
										@endforeach
									</select>
								</div>
								
								<div class="col-12 col-md-4">
									<label class="form-label mb-1">Уровень</label>
									<select name="level" class="form-select">
										<option value="" {{ $fLevel==='' ? 'selected' : '' }}>Любой</option>
										@foreach(($levelOptions ?? []) as $lv)
										<option value="{{ (int)$lv }}" {{ (string)$fLevel===(string)$lv ? 'selected' : '' }}>{{ (int)$lv }}</option>
										@endforeach
									</select>
								</div>
								
								<div class="col-12 text-right m-center">
									<button type="submit" class="btn">Применить</button>
									
									<a href="{{ route('events.index') }}" class="btn btn-secondary">Сброс</a>
									
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
					<strong>Приватная ссылка 🔗:</strong>
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
			
			
			{{-- DEBUG (можешь потом удалить)
			<div style="padding:10px;background:#222;color:#0f0">
				occurrences: {{ isset($occurrences) ? $occurrences->count() : 'no var' }},
				events: {{ isset($events) ? $events->count() : 'no var' }}
			</div>
			--}}
			{{-- =========================
			ВАРИАНТ 1: OCCURRENCES
			========================= --}}
			@if ($hasOcc)
			{{-- Верхняя лента дней --}}
			<div id="eventsTabsRoot" data-today="{{ now()->format('Y-m-d') }}"></div>
			<div class="tabs-content">			
				
				<div class="row">
					<div class="col-lg-4 col-xl-3 order-1 order-lg-2">
						<div class="sticky">
							<div class="card-ramka">
								<div class="days-strip tabs mb-0" id="daysStrip">
									@foreach($groupedByDate as $dateKey => $dayData)
									@php
									$d = $dayData['date'];
									$day = (int)$d->format('j');
									$month = (int)$d->format('n');
									$weekday = (int)$d->format('N');
									$labelDate = $day . ' ' . ($months[$month] ?? '');
									$dow = $daysOfWeek[$weekday] ?? '';
									@endphp
									<div class="tab day-chip {{ $loop->first ? 'active' : '' }}"
									data-tab="day-{{ $loop->iteration }}"
									data-date="{{ $dateKey }}">
										<div class="dc-date">{{ $labelDate }}</div>
										<div class="dc-dow">{{ $dow }}</div>
									</div>
									@endforeach
									<div class="tab-highlight"></div>
								</div>	
								
									@if(count($groupedByDate) >= 10)
									<div class="text-end mt-2">
										<a href="{{ route('events.index', ['offset' => 10]) }}" class="w-100 btn btn-secondary">
											Следующие 10 дней 
										</a>
									</div>
									@endif								

							</div>
						</div> 	
					</div> 
					<div class="col-lg-8 col-xl-9 order-2 order-lg-1">					
							<div class="tab-panes">
								@foreach($groupedByDate as $dateKey => $dayData)
								<div class="tab-pane {{ $loop->first ? 'active' : '' }}" id="day-{{ $loop->iteration }}">
									
									<div class="row mb-0">
										@foreach ($dayData['occurrences'] as $occ)
										@php $event = $occ->event; @endphp
										
										@continue(!$event)
										
										@php
										$joinedOccurrenceIds     = $joinedOccurrenceIds ?? [];
										$restrictedOccurrenceIds = $restrictedOccurrenceIds ?? [];
										
										$isJoined     = in_array((int)$occ->id, $joinedOccurrenceIds, true);
										$joinDisabled = in_array((int)$occ->id, $restrictedOccurrenceIds, true);
										
										$dt = $fmtDate($occ);
										
										$addressParts = array_filter([
										$event?->location?->name,
										$event?->location?->city,
										$event?->location?->address,
										]);
										$address = $addressParts ? implode(', ', $addressParts) : '—';
										
										$coverUrl = $event ? $event->getFirstMediaUrl('cover') : '';
										
										$gs = $event?->gameSettings ?? null;
										
										// ✅ регистрация (объявляем ДО использования)
										$regEnabled = (bool) data_get($event, 'allow_registration', false);
										
										// ✅ max players — вытаскиваем откуда угодно (gameSettings, event, occurrence)
										$maxPlayersCard = (int) (data_get($gs, 'max_players') ?? 0);
										if ($maxPlayersCard <= 0) $maxPlayersCard = (int) (data_get($event, 'max_players') ?? 0);
										if ($maxPlayersCard <= 0) $maxPlayersCard = (int) (data_get($occ, 'max_players') ?? 0);
										
										
										// ✅ seatline показываем всегда, если лимит мест задан (независимо от allow_registration / окна регистрации)
										$showSeatLine = $maxPlayersCard > 0;
											
											
											$positions = $gs?->positions;
											if (is_string($positions)) $positions = json_decode($positions, true);
											$hasPositionRegistration = $gs && $maxPlayersCard > 0 && is_array($positions) && !empty($positions);
											
											$trainerLabel = null;
											if ($trainerColumn && $event) {
											$tid = (int)($event->{$trainerColumn} ?? 0);
											if ($tid > 0 && isset($trainersById[$tid])) {
											$tu = $trainersById[$tid];
											$trainerLabel = trim(($tu->name ?? '') ?: ($tu->email ?? '')) . ' (#' . (int)$tid . ')';
											}
											}
											
											$isTrainingFmt = in_array((string)($event?->format ?? ''), ['training','training_game'], true);
											
											// данные для фильтров
											$dir  = (string)($event?->direction ?? '');
											$fmt  = (string)($event?->format ?? '');
											$clMin = is_null($event?->classic_level_min) ? '' : (int)$event->classic_level_min;
											$clMax = is_null($event?->classic_level_max) ? '' : (int)$event->classic_level_max;
											$bMin  = is_null($event?->beach_level_min) ? '' : (int)$event->beach_level_min;
											$bMax  = is_null($event?->beach_level_max) ? '' : (int)$event->beach_level_max;
											
											// ===== Registration windows (UTC) =====
											$tzEvent = (string)($occ->timezone ?: ($event?->timezone ?: 'UTC'));
											
											$startsAtUtc = $occ->starts_at
											? \Illuminate\Support\Carbon::parse($occ->starts_at)->utc()
											: ($event?->starts_at ? \Illuminate\Support\Carbon::parse($event->starts_at)->utc() : null);
											
											$regStartsUtc = !empty($event?->registration_starts_at)
											? \Illuminate\Support\Carbon::parse($event->registration_starts_at)->utc()
											: null;
											
											$regEndsUtc = !empty($event?->registration_ends_at)
											? \Illuminate\Support\Carbon::parse($event->registration_ends_at)->utc()
											: null;
											
											$cancelUntilUtc = !empty($event?->cancel_self_until)
											? \Illuminate\Support\Carbon::parse($event->cancel_self_until)->utc()
											: null;
											
											$eventStarted  = $startsAtUtc ? $nowUtc->gte($startsAtUtc) : false;
											$regNotStarted = $regStartsUtc ? $nowUtc->lt($regStartsUtc) : false;
											$regClosed     = $regEndsUtc   ? $nowUtc->gte($regEndsUtc)   : false;
											
											$canRegister = $regEnabled && !$eventStarted && !$regNotStarted && !$regClosed;
											$canCancelSelf = $regEnabled && !$eventStarted && (!$cancelUntilUtc || $nowUtc->lt($cancelUntilUtc));
											
											// красивое время в таймзоне события
											$fmtLocalFromUtc = function($dtUtc) use ($tzEvent) {
											if (!$dtUtc) return null;
											$c = $dtUtc instanceof \Illuminate\Support\Carbon
											? $dtUtc->copy()
											: \Illuminate\Support\Carbon::parse($dtUtc, 'UTC');
											return $c->utc()->setTimezone($tzEvent)->format('d.m.Y H:i');
											};
											@endphp
											
											<div class="col-12 col-sm-6">
												<div class="card-ramka">  
												<div
												class="event-card"
												data-direction="{{ e($dir) }}"
												data-format="{{ e($fmt) }}"
												data-classic-min="{{ e($clMin) }}"
												data-classic-max="{{ e($clMax) }}"
												data-beach-min="{{ e($bMin) }}"
												data-beach-max="{{ e($bMax) }}"
												>
													@if(!empty($coverUrl))
													<img src="{{ $coverUrl }}" alt="" class="card-img-top" style="height:180px;object-fit:cover;">
													@endif
													
													<div class="card-body">
														<div class="d-flex gap-3 justify-content-between align-items-start">
															<div class="flex-grow-1" style="min-width:0;">

																<a href="{{ url('/events/' . (int)$event->id) . '?occurrence=' . (int)$occ->id }}" class="card-title mb-1">
																	{{ $event?->title ?? '—' }}
																	@if(!empty($event?->is_private))
																	<span class="ms-2 text-muted" title="Приватное мероприятие">🙈</span>
																	@endif
																</a>
																
																@php
																// direction label
																$dirLabel = ($dir === 'beach') ? 'Пляжка' : (($dir === 'classic') ? 'Классика' : '—');
																
																// start/end in tz (для красивого формата)
																$tzCard = $dt['tz'] ?? 'UTC';
																$sLocal = $occ->starts_at ? \Illuminate\Support\Carbon::parse($occ->starts_at)->setTimezone($tzCard) : null;
																$eLocal = $occ->ends_at ? \Illuminate\Support\Carbon::parse($occ->ends_at)->setTimezone($tzCard) : null;
																
																
																
																// "ДД ММММ" (по-русски)
																$dateLong = $sLocal ? $sLocal->locale('ru')->translatedFormat('d F') : '—';
																
																// time "HH:MM-HH:MM"
																$timeRange = $sLocal
																? $sLocal->format('H:i') . ($eLocal ? '-' . $eLocal->format('H:i') : '')
																: '—';
																
																// duration "⏳ 1:30"
																$durLabel = null;
																if ($sLocal && $eLocal) {
																$mins = max(0, $sLocal->diffInMinutes($eLocal, false));
																$h = intdiv($mins, 60);
																$m = $mins % 60;
																$durLabel = sprintf('%d:%02d', $h, $m);
																}
																
																// levels "2 - 6" (по направлению)
																if ($dir === 'beach') {
																$lvMin = is_null($event?->beach_level_min) ? null : (int)$event->beach_level_min;
																$lvMax = is_null($event?->beach_level_max) ? null : (int)$event->beach_level_max;
																} else {
																$lvMin = is_null($event?->classic_level_min) ? null : (int)$event->classic_level_min;
																$lvMax = is_null($event?->classic_level_max) ? null : (int)$event->classic_level_max;
																}
																$levelLabel = ($lvMin !== null || $lvMax !== null)
																? (($lvMin !== null ? $lvMin : '—') . ' - ' . ($lvMax !== null ? $lvMax : '—'))
																: null;
																
																// price
																$priceLabel = (!empty($event?->is_paid) && trim((string)($event?->price_text ?? '')) !== '')
																? trim((string)$event->price_text)
																: null;
																
																// trainer link (через trainersById + trainerColumn)
																$trainerUrl = null;
																if ($isTrainingFmt && $trainerColumn && $event) {
																$tid = (int)($event->{$trainerColumn} ?? 0);
																if ($tid > 0) {
																$trainerUrl = url('/user/' . $tid); // ✅ правильный роут профиля
																}
																}
																
																@endphp
																
																<div class="d-flex flex-wrap gap-2 mt-1">
																	<span class="badge text-bg-secondary">{{ $dirLabel }}</span>
																</div>
																
																<div class="mt-2 text-muted small">
																	🗓 <span class="fw-semibold text-body">{{ $dateLong }}</span>
																</div>
																
																<div class="text-muted small mt-1">
																	⏰ <span class="fw-semibold text-body">{{ $timeRange }}</span>
																	@if($durLabel)
																	<span class="ms-2">⏳ <span class="fw-semibold text-body">{{ $durLabel }}</span> час</span>
																	@endif
																</div>
																
																<div class="text-muted small mt-1">
																	📍 {{ $address }}
																</div>
																
																@if($isTrainingFmt && !empty($trainerLabel))
																<div class="text-muted small mt-1 d-flex align-items-center gap-2 flex-wrap">
																	<img src="{{ $trainerIconUrl }}" alt="trainer" style="width:18px;height:18px;opacity:.85;">
																	<span>Тренер:</span>
																	@if($trainerUrl)
																	<a class="fw-semibold text-decoration-underline" href="{{ $trainerUrl }}">{{ $trainerLabel }}</a>
																	@else
																	<span class="fw-semibold text-body">{{ $trainerLabel }}</span>
																	@endif
																</div>
																@endif
																@if(!empty($event?->organizer_id))
																@php
																// organizer label + link
																$orgId = (int)$event->organizer_id;
																$org = $event?->organizer_user ?? $event?->organizer ?? null;
																
																// если контроллер отдает организаторов/пользователей — используем их:
																// (на всякий случай: если связи нет, но есть массив)
																if (!$org && isset($usersById) && isset($usersById[$orgId])) $org = $usersById[$orgId];
																if (!$org && isset($trainersById) && isset($trainersById[$orgId])) $org = $trainersById[$orgId];
																
																$organizerLabel = null;
																if ($org) {
																$organizerLabel = trim((string)($org->name ?? ''));
																if ($organizerLabel === '') $organizerLabel = (string)($org->nickname ?? '');
																if ($organizerLabel === '') $organizerLabel = (string)($org->username ?? '');
																if ($organizerLabel === '') $organizerLabel = (string)($org->email ?? '');
																}
																if (!$organizerLabel && $orgId > 0) {
																$organizerLabel = 'Пользователь #' . $orgId;
																}
																
																$organizerUrl = $orgId > 0 ? url('/user/' . $orgId) : null;
																@endphp
																
																@if($organizerLabel)
																<div class="text-muted small mt-1 d-flex align-items-center gap-2 flex-wrap">
																	<span>Организатор:</span>
																	<a class="fw-semibold text-decoration-underline" href="{{ $organizerUrl }}">{{ $organizerLabel }}</a>
																</div>
																@endif
																@endif
																
																@if($levelLabel)
																<div class="text-muted small mt-1">
																	🎚 Уровень: <span class="fw-semibold text-body">{{ $levelLabel }}</span>
																</div>
																@endif
																
																@if($priceLabel)
																<div class="text-muted small mt-1">
																	💸 <span class="fw-semibold text-body">{{ $priceLabel }}</span>
																</div>
																@endif
																
																
																@if($showSeatLine)
																<div
																class="mt-2 small"
																data-seatline
																data-occurrence-id="{{ (int)$occ->id }}"
																data-registration-enabled="{{ $regEnabled ? '1' : '0' }}"
																data-reg-not-started="{{ $regNotStarted ? '1' : '0' }}"
																data-reg-closed="{{ $regClosed ? '1' : '0' }}"
																data-max-players="{{ (int)$maxPlayersCard }}"
																style="display:flex;align-items:center;gap:.4rem;"
																>
																	<span class="text-muted">🧑‍🧑‍🧒</span>
																	<span class="text-muted">Осталось мест:</span>
																	<span class="fw-semibold" data-left>—</span>
																	<span class="text-muted">из</span>
																	<span class="fw-semibold" data-total>{{ (int)$maxPlayersCard }}</span>
																	<span class="text-muted">!</span>
																</div>
																@elseif($regEnabled)
																<div class="mt-2 small text-muted">
																	🧑‍🧑‍🧒 Лимит мест не задан
																</div>
																@endif
															</div>
															
														
														</div>
														
														<div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
															@auth
															@if ($isJoined)
															
															@if ($canCancelSelf)
															<form method="POST" action="{{ route('occurrences.leave', ['occurrence' => $occ->id]) }}">
																@csrf
																@method('DELETE')
																<button type="submit" class="btn btn-outline-secondary">
																	Отменить запись
																</button>
															</form>
															@else
															<div class="small text-danger fw-semibold">
																Самостоятельная отмена невозможна!
															</div>
															@if(!empty($event?->cancel_self_until))
															<div class="w-100 small text-muted">
																Дедлайн был: {{ $fmtLocalFromUtc($event->cancel_self_until) }} ({{ $tzEvent }})
															</div>
															@endif
															@endif
															
															@else
															
															@if ($joinDisabled)
															<button type="button" class="btn btn-primary" disabled style="opacity:.55;cursor:not-allowed;">
																Записаться
															</button>
															<div class="w-100 small text-danger mt-1">
																У вашей учетной записи есть ограничения для этого мероприятия.
															</div>
															
															@elseif (!$regEnabled)
															<button type="button" class="btn btn-primary" disabled style="opacity:.55;cursor:not-allowed;">
																Регистрация выключена
															</button>
															
															@elseif ($eventStarted)
															<button type="button" class="btn btn-primary" disabled style="opacity:.55;cursor:not-allowed;">
																Запись недоступна
															</button>
															<div class="w-100 small text-muted mt-1">
																Мероприятие уже началось.
															</div>
															
															@elseif ($regNotStarted && $regStartsUtc)
															<div
															class="btn btn-primary"
															style="opacity:.85; pointer-events:none;"
															data-countdown
															data-target-utc="{{ $regStartsUtc->toIso8601String() }}"
															>
																До регистрации осталось:
																<span data-dd>—</span>д
																<span data-hhmm>—:—</span>
															</div>
															
															<div class="w-100 small text-muted mt-1">
																Регистрация начнётся: {{ $fmtLocalFromUtc($regStartsUtc) }} ({{ $tzEvent }})
															</div>
															
															@elseif ($regNotStarted)
															<div class="small text-muted">
																Регистрация ещё не началась.
															</div>
															
															@elseif ($regClosed)
															<button type="button" class="btn btn-primary" disabled style="opacity:.55;cursor:not-allowed;">
																Регистрация закрыта
															</button>
															<div class="w-100 small text-muted mt-1">
																Закрылась: {{ $fmtLocalFromUtc($regEndsUtc) }} ({{ $tzEvent }})
															</div>
															
															@else
															{{-- ✅ регистрация разрешена прямо сейчас --}}
															@if (!$hasPositionRegistration)
															<form method="POST" action="{{ route('occurrences.join', ['occurrence' => $occ->id]) }}">
																@csrf
																<button type="submit" class="btn btn-primary">
																	Записаться
																</button>
															</form>
															@else
															<button
															type="button"
															class="btn btn-primary js-open-join"
															data-occurrence-id="{{ (int)$occ->id }}"
															data-title="{{ e($event?->title ?? '') }}"
															data-date="{{ e($dt['date']) }}"
															data-time="{{ e($dt['time']) }}"
															data-tz="{{ e($dt['tz']) }}"
															data-address="{{ e($address) }}"
															>
																Записаться
															</button>
															@endif
															@endif
															
															@endif
															@else
															<a class="btn btn-primary" href="/login">Войти, чтобы записаться</a>
															@endauth
															
															
														</div>
													</div>
												</div>
											</div>
											</div> 		
											@endforeach
										</div>
									</div>
									@endforeach
								</div>
							</div> 
						</div> 

					@else
					<div class="alert alert-info">
						Пока мероприятий нет. Но скоро появятся 🙂
					</div>
					@endif
				</div>
				
			</div>
			{{-- JOIN MODAL --}}
			<div id="joinModalBackdrop" class="join-backdrop hidden">
				<div class="h-100 d-flex align-items-center justify-content-center p-3">
					<div class="join-modal">
						<div class="p-3 border-bottom d-flex align-items-start justify-content-between gap-3">
							<div>
								<div id="jmTitle" class="fw-semibold fs-5">Запись</div>
								<div id="jmMeta" class="text-muted small mt-1"></div>
								<div id="jmAddr" class="text-muted small mt-1"></div>
							</div>
							<button type="button" class="btn btn-outline-secondary btn-sm js-close-join">✕</button>
						</div>
						<div class="p-3">
							<div id="jmError" class="alert alert-danger d-none mb-2"></div>
							<div class="text-muted small mb-2">
								Выбери позицию (показаны только свободные):
							</div>
							<div id="jmLoading" class="text-muted small d-none mb-2">
								Загружаю доступные позиции…
							</div>
							<div id="jmPositions" class="row g-2"></div>
							<div class="mt-3 text-muted small">
								После выбора позиции вы сразу будете записаны.
							</div>
						</div>
					</div>
				</div>
			</div>
			
			<form id="joinForm" method="POST" action="" class="d-none">
				@csrf
				<input type="hidden" name="position" id="joinPosition" value="">
			</form>
			
			
			<x-slot name="script">
				<script>
					(function () {
						const backdrop  = document.getElementById('joinModalBackdrop');
						const titleEl   = document.getElementById('jmTitle');
						const metaEl    = document.getElementById('jmMeta');
						const addrEl    = document.getElementById('jmAddr');
						const posWrap   = document.getElementById('jmPositions');
						const errBox    = document.getElementById('jmError');
						const loadingEl = document.getElementById('jmLoading');
						const joinForm  = document.getElementById('joinForm');
						const joinPos   = document.getElementById('joinPosition');
						
						function showError(message) {
							if (!errBox) { alert(message); return; }
							errBox.textContent = message;
							errBox.classList.remove('d-none');
						}
						function clearError() {
							if (!errBox) return;
							errBox.textContent = '';
							errBox.classList.add('d-none');
						}
						function setLoading(isLoading) {
							if (!loadingEl) return;
							loadingEl.classList.toggle('d-none', !isLoading);
						}
						function openModalShell(payload) {
							clearError();
							setLoading(true);
							titleEl.textContent = payload.title || 'Запись';
							metaEl.textContent  = [payload.date, payload.time, payload.tz ? '('+payload.tz+')' : ''].filter(Boolean).join(' ');
							addrEl.textContent  = payload.address || '';
							posWrap.innerHTML = '';
							backdrop.classList.remove('hidden');
						}
						function closeModal() {
							backdrop.classList.add('hidden');
							posWrap.innerHTML = '';
							clearError();
							setLoading(false);
						}
						function renderPositions(occurrenceId, freePositions) {
							posWrap.innerHTML = '';
							if (!Array.isArray(freePositions) || freePositions.length === 0) {
								showError('Свободных мест больше нет (или нет доступных позиций по ограничениям).');
								return;
							}
							freePositions.forEach(p => {
								const col = document.createElement('div');
								col.className = 'col-12';
								const btn = document.createElement('button');
								btn.type = 'button';
								btn.className = 'btn btn-primary w-100';
							btn.innerHTML = `${p.label || p.key} <span class="ms-2 small opacity-75">(${p.free ?? 0})</span>`;
							btn.addEventListener('click', () => {
							joinForm.action = `/occurrences/${occurrenceId}/join`;
							joinPos.value = p.key;
							joinForm.submit();
							});
							col.appendChild(btn);
							posWrap.appendChild(col);
							});
							}
							async function fetchAvailability(occurrenceId) {
							const res = await fetch(`/occurrences/${occurrenceId}/availability`, {
							method: 'GET',
							headers: { 'Accept': 'application/json' },
							credentials: 'same-origin',
							});
							let data = null;
							try { data = await res.json(); } catch (e) {}
							if (data && data.redirect_url) {
							window.location = data.redirect_url;
							return null;
							}
							if (!res.ok || !data || data.ok === false) {
							const msg = (data && data.message) ? data.message : 'Не удалось получить доступность мероприятия.';
							showError(msg);
							return null;
							}
							return data;
							}
							
							document.querySelectorAll('.js-open-join').forEach(btn => {
							btn.addEventListener('click', async () => {
							const occurrenceId = btn.dataset.occurrenceId;
							openModalShell({
							title: btn.dataset.title,
							date: btn.dataset.date,
							time: btn.dataset.time,
							tz: btn.dataset.tz,
							address: btn.dataset.address,
							});
							const data = await fetchAvailability(occurrenceId);
							setLoading(false);
							if (!data) return;
							renderPositions(occurrenceId, data.free_positions || []);
							});
							});
							
							document.querySelectorAll('.js-close-join').forEach(btn => {
							btn.addEventListener('click', closeModal);
							});
							
							if (backdrop) {
							backdrop.addEventListener('click', (e) => {
							if (e.target === backdrop) closeModal();
							});
							}
							document.addEventListener('keydown', (e) => {
							if (e.key === 'Escape') closeModal();
							});
							
							// ===== Seats line =====
							const seatLines = Array.from(document.querySelectorAll('[data-seatline]'));
							
							async function loadSeatLine(el) {
							const occId = el.dataset.occurrenceId;
							const regEnabled = el.dataset.registrationEnabled === '1';
							const regNotStarted = el.dataset.regNotStarted === '1';
							const regClosed = el.dataset.regClosed === '1';
							
							const maxCard = Number(el.dataset.maxPlayers ?? 0) || 0;
							if (maxCard <= 0) return;
							
							// ✅ более надежные селекторы (и fallback на старые индексы)
							const leftEl  = el.querySelector('[data-left]')  || (el.querySelectorAll('span')[2] || null);
							const totalEl = el.querySelector('[data-total]') || (el.querySelectorAll('span')[4] || null);
							
							if (totalEl) totalEl.textContent = String(maxCard);
							
							// ✅ Если регистрация ещё не началась или закрыта — НЕ показываем "0".
							if (regNotStarted || regClosed || !regEnabled) {
							if (leftEl) leftEl.textContent = '—';
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
							if (!data || !data.meta) return;
							
							const apiMax = Number(data.meta.max_players ?? 0) || 0;
							const effectiveMax = apiMax > 0 ? apiMax : maxCard;
								
								let remainingTotal = Number(data.meta.remaining_total);
								
								if (!Number.isFinite(remainingTotal)) {
								const registeredTotal = Number(data.meta.registered_total ?? 0) || 0;
								remainingTotal = Math.max(0, effectiveMax - registeredTotal);
								}
								
								if (leftEl)  leftEl.textContent  = String(remainingTotal);
								if (totalEl) totalEl.textContent = String(effectiveMax);
								} catch (e) {}
								}
								
								if (seatLines.length) {
								const concurrency = 3;
								let i = 0;
								
								async function worker() {
								while (i < seatLines.length) {
								const idx = i++;
								await loadSeatLine(seatLines[idx]);
								}
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
									const today = new Date();
									const yyyy = today.getFullYear();
									const mm = String(today.getMonth()+1).padStart(2,'0');
									const dd = String(today.getDate()).padStart(2,'0');
									const todayKey = `${yyyy}-${mm}-${dd}`;
									const todayChip = chips.find(c => c.dataset.date === todayKey);
									if (todayChip) {
									activateTab(todayChip.dataset.tab);
									todayChip.scrollIntoView({behavior:'smooth', inline:'center', block:'nearest'});
									} else {
									activateTab(chips[0].dataset.tab);
									}
									})();
									
									
									
									// ===== Countdown blocks: "До регистрации осталось" =====
									function pad2(n){ n = Math.max(0, n|0); return (n<10?'0':'')+n; }
									
									function tickCountdown(el){
									var iso = el.getAttribute('data-target-utc');
									if (!iso) return;
									
									var target = Date.parse(iso);
									if (isNaN(target)) return;
									
									var now = Date.now();
									var diff = target - now;
									
									if (diff <= 0) {
									el.textContent = 'Регистрация доступна — обнови страницу';
									return;
									}
									
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
									
									function tickAllCountdowns(){
									document.querySelectorAll('[data-countdown]').forEach(tickCountdown);
									}
									
									tickAllCountdowns();
									setInterval(tickAllCountdowns, 30000);
									})();
									</script>
									</x-slot>	
									
									
								</x-voll-layout>
														