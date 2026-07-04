{{-- resources/views/locations/show.blade.php --}}
<x-voll-layout body_class="location-page">
	
	
	
    @php
	$hasCoords = is_numeric($location->lat) && is_numeric($location->lng);
	$lat = $location->lat;
	$lng = $location->lng;
	
	$firstMedia = $location->getFirstMedia('photos');
	$cityName = $location->city?->name;
	$regionName = $location->city?->region;
	$query = trim(implode(', ', array_filter([$cityName, $location->address, $location->name])));
	$yandexLink = $hasCoords
	? ('https://yandex.ru/maps/?pt=' . rawurlencode($lng . ',' . $lat) . '&z=16&l=map')
	: ('https://yandex.ru/maps/?text=' . rawurlencode($query));
	
	$user = auth()->user();
	$userTz = \App\Support\DateTime::effectiveUserTz(auth()->user());
	
	$tzLabel = function (? \Carbon\Carbon $c, string $fallbackTz) {
	if (!$c) return $fallbackTz;
	return $c->format('T') . ' (UTC' . $c->format('P') . ')';
	};
    @endphp
	
    <x-slot name="title">
        {{ $location->name }} {{ __('locations.show_title_suffix') }}
	</x-slot>
	
    <x-slot name="description">
        {{ $location->short_text ? strip_tags($location->short_text) : __('locations.show_description_default', ['name' => $location->name]) }}
	</x-slot>
	
    <x-slot name="canonical">
		@php
        $slug = Str::slug($location->name);
		@endphp
		{{ route('locations.show', ['location' => $location->id, 'slug' => $slug]) }}
	</x-slot>

	
	
    <x-slot name="style">
        <style>
.location-swiper {
	border-radius: 1rem;
	overflow: hidden;
}		
.swiper-slide img {
	aspect-ratio: 16/9 ;
	object-fit: cover;
	cursor: pointer;
}
.event-card {
	height: 100%;
}		
.location-adress {
	display: flex;
	align-items: center;
}			
.location-adress span {
	flex: 0 0 2rem;
	margin-right: 1rem;
	width: 2rem;
	height: 2rem;	
}			
.location-adress span svg {
	fill: #E7612F;
	width: 2rem;
	height: 2rem;	
}
		</style>
	</x-slot>
	
    <x-slot name="h1">
        {{ $location->name }}
	</x-slot>
	
    <x-slot name="h2">
		{{ __('locations.show_h2_city_prefix') }} {{ $cityName }}@if($regionName), {{ $regionName }}@endif
	</x-slot>
    <x-slot name="t_description">@if($location->address && $location->address !== $cityName){{ $location->address }}@endif</x-slot>	


    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('locations.index') }}" itemprop="item"><span itemprop="name">{{ __('locations.breadcrumb_index') }}</span></a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ $location->name }}</span>
            <meta itemprop="position" content="3">
		</li>
	</x-slot>
	
	
    <div class="container">
        {{-- Основная информация --}}
		<div class="row mb-0">
			<div class="col-md-7">
				<div class="ramka mb-0">
                    <div class="mb-2">
                        @if(!empty($location->address))
						<div class="location-adress">
							<span class="icon-map"></span>
							<strong class="cd f-20">{{ $location->address }}</strong>
						</div>
                        @endif
					</div>
					

					
                    @if(!empty($location->long_text_full))
					<div>
						{!! $location->long_text_full !!}
					</div>
                    @endif
					{{--
                    @if(!empty($location->note))
					<div class="mb-2">
						<strong>Примечание:</strong> {{ $location->note }}
					</div>
                    @endif					
					--}}
				</div>
			</div>
			<div class="col-md-5">
				<div class="sticky mb-0">
					<div class="ramka mb-0">	
						{{-- Галерея со Swiper + Fancybox --}}
						@if($photos->isNotEmpty())
						
						<div class="location-gallery">
							<div class="swiper location-swiper">
								<div class="swiper-wrapper">
									@foreach($photos as $media)
									<div class="swiper-slide">
										<div class="hover-image">
											<a href="{{ $media->getUrl() }}" class="fancybox" data-fancybox="gallery">
												<img src="{{ $media->getUrl('thumb') ?: $media->getUrl() }}" alt="" loading="lazy">
												<span></span>
												<div class="hover-image-circle"></div>
											</a>
										</div>								
									</div>
									@endforeach
								</div>
							</div>
							<div class="swiper-pagination"></div>
						</div>
						@endif	
					</div>
				</div>
			</div>
		</div>
		
		
        {{-- Яндекс.Карта с ленивой загрузкой --}}
        <div class="ramka no-highlight">
            <div class="row">
                <div class="col-12">
					
					@if($hasCoords)
					@php
					$theme = request()->cookie('theme') == 'dark' ? 'dark' : 'light';
					$ll = $lng . ',' . $lat;
					$pt = $lng . ',' . $lat . ',pm2rdm';
					$mapSrc = "https://yandex.ru/map-widget/v1/?ll={$ll}&z=16&l=map&pt={$pt}&scroll=false";
					@endphp
					
					<div class="map-container f-0">
						<iframe
						data-src="{{ $mapSrc }}"
						class="w-100 lazy-map iframe-map"
						style="height: 42rem; border: 0; border-radius: 1rem;"
						frameborder="0"
						allowfullscreen="true"
						loading="lazy"
						title="{{ __('locations.map_iframe_title', ['name' => $location->name]) }}"
						></iframe>
					</div>
					@else
					<div class="alert alert-info">
						{{ __('locations.map_no_coords') }}
					</div>
					@endif
				</div>
			</div>
		</div>
		
        {{-- ТАЙМЛАЙН ЗАНЯТОСТИ КОРТОВ (только владелец локации / админ) --}}
        @php
        $canManageTimeline = $user && ((method_exists($user, 'isAdmin') && $user->isAdmin()) || (int) ($location->owner_id ?? 0) === (int) $user->id);
        @endphp
        @if($canManageTimeline)
        <div class="ramka" id="timelineSection">
            <div class="d-flex between fvc mb-2" style="flex-wrap:wrap;gap:10px">
                <h2 class="-mt-05" style="margin:0">{{ __('club.timeline') }}</h2>
                <div class="d-flex gap-1" role="tablist">
                    <button type="button" class="btn btn-small btn-primary" id="tlSwitchList">{{ __('club.list_view') }}</button>
                    <button type="button" class="btn btn-small btn-secondary" id="tlSwitchTimeline">{{ __('club.timeline') }}</button>
                </div>
            </div>

            <div id="timelinePanel" style="display:none">
                <div class="d-flex between fvc mb-2" style="flex-wrap:wrap;gap:10px">
                    <div class="d-flex gap-1 fvc" style="flex-wrap:wrap">
                        <button type="button" class="btn btn-small btn-secondary" id="tlPrev">{{ __('club.yesterday') }}</button>
                        <span class="b-600" id="tlCurrentLabel">{{ __('club.today') }}</span>
                        <input type="date" id="tlDatePicker" class="btn-small">
                        <button type="button" class="btn btn-small btn-secondary" id="tlNext">{{ __('club.tomorrow') }}</button>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-small btn-primary" id="tlModeDay">{{ __('club.day_view') }}</button>
                        <button type="button" class="btn btn-small btn-secondary" id="tlModeWeek">{{ __('club.week_view') }}</button>
                    </div>
                </div>

                <div id="tlLoading" class="alert alert-info" style="display:none">…</div>
                <div id="tlDayGrid" class="timeline-day"></div>
                <div id="tlWeekGrid" class="timeline-week" style="display:none"></div>
            </div>
        </div>
        @endif

        {{-- Мероприятия в этой локации --}}

        {{-- ТУРНИРЫ В ЛОКАЦИИ --}}
        @php
            $locationTournaments = \App\Models\Event::where('location_id', $location->id)
                ->where('format', 'tournament')
                ->whereHas('tournamentStages')
                ->with(['tournamentStages' => fn($q) => $q->withCount('matches')])
                ->orderByDesc('starts_at')
                ->limit(5)
                ->get();
        @endphp

        @if($locationTournaments->isNotEmpty())
        <div class="ramka">
            <h2 class="-mt-05">{{ __('locations.tournaments_section') }}</h2>
            @foreach($locationTournaments as $tourn)
                @php
                    $matchesCount = $tourn->tournamentStages->sum('matches_count');
                    $isActive = $tourn->tournamentStages->where('status', 'in_progress')->isNotEmpty();
                @endphp
                <div class="d-flex f-14" style="padding:8px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:10px;align-items:center;flex-wrap:wrap">
                    <div style="flex:1;min-width:150px">
                        <a href="{{ route('tournament.public.show', $tourn->id) }}" class="blink b-600">
                            {{ $tourn->title }}
                        </a>
                        <div class="f-12" style="opacity:.5">
                            {{ $tourn->starts_at ? $tourn->starts_at->format('d.m.Y') : '' }}
                            · {{ $tourn->direction === 'beach' ? __('locations.tournaments_dir_beach') : __('locations.tournaments_dir_classic') }}
                        </div>
                    </div>
                    <span class="f-12 p-1 px-2 b-600" style="background:rgba(41,103,186,.15);border-radius:6px">
                        {{ $matchesCount }} {{ __('locations.tournaments_matches_short') }}
                    </span>
                    @if($isActive)
                        <span class="f-12 p-1 px-2 b-600" style="background:rgba(16,185,129,.15);border-radius:6px;color:#10b981">{{ __('locations.tournaments_live') }}</span>
                    @endif
                </div>
            @endforeach
        </div>
        @endif

<div class="ramka">
            <h2 class="-mt-05">{{ __('locations.events_section') }}</h2>
        
        @php
        $nowUtc         = \Illuminate\Support\Carbon::now('UTC');
        $occList        = $occurrences ?? collect();
        $trainerIconUrl = asset('icons/trainer.png');
        $trainersById   = [];
        $trainerColumn  = null;
        $fmtDate = function ($occ) use ($userTz) {
            $eventTz = $occ->timezone ?: ($occ->event?->timezone ?: 'UTC');
            $sUser = $occ->starts_at
                ? \Illuminate\Support\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($userTz)
                : null;
            if (!$sUser) return ['date'=>'—','time'=>'—','tz'=>$userTz,'tzLabel'=>$userTz,'eventTz'=>$eventTz];
            return [
                'date'    => $sUser->format('d.m.Y'),
                'time'    => $sUser->format('H:i'),
                'tz'      => $userTz,
                'tzLabel' => $sUser->format('T').' (UTC'.$sUser->format('P').')',
                'eventTz' => $eventTz,
            ];
        };
        
        // Фильтруем прошедшие
        $futureOcc = $occList->filter(function($occ) use ($nowUtc) {
            return $occ->starts_at && \Illuminate\Support\Carbon::parse($occ->starts_at, 'UTC')->gt($nowUtc);
        })->sortBy('starts_at');
        
        // ✅ Обогащаем join/cancel через Guard
        $guard = app(\App\Services\EventRegistrationGuard::class);
        foreach ($futureOcc as $occ) {
            if (!isset($occ->join)) {
                $occ->join   = $guard->quickCheck(auth()->user(), $occ);
                $occ->cancel = null;
            }
        }
        
        $joinedOccurrenceIds     = [];
        $restrictedOccurrenceIds = [];
        if (auth()->check()) {
            $joinedOccurrenceIds = \App\Models\EventRegistration::where('user_id', auth()->id())
                ->whereIn('occurrence_id', $futureOcc->pluck('id'))
                ->where('status', 'active')
                ->pluck('occurrence_id')
                ->map(fn($id) => (int)$id)
                ->toArray();
        }
        @endphp
        
            @if($futureOcc->isEmpty())
                <div class="alert alert-info">{{ __('locations.events_empty') }}</div>
            @else
                <div class="row mb-0">
                    @foreach($futureOcc as $occ)
                        @include('events._card')
                    @endforeach
                </div>
            @endif
        </div>
        {{-- JOIN MODAL --}}
        <div id="joinModalBackdrop" class="join-backdrop hidden" style="position:fixed;inset:0;z-index:1050;background:rgba(0,0,0,.55);">
            <div class="h-100 d-flex align-items-center justify-content-center p-3">
                <div class="join-modal" style="max-width:720px;width:100%;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);">
                    <div class="p-3 border-bottom d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div id="jmTitle" class="fw-semibold fs-5">{{ __('locations.join_modal_title') }}</div>
                            <div id="jmMeta" class="text-muted small mt-1"></div>
                            <div id="jmAddr" class="text-muted small mt-1"></div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-close-join">✕</button>
                    </div>
                    <div class="p-3">
                        <div id="jmError" class="alert alert-danger d-none mb-2"></div>
                        <div class="text-muted small mb-2">{{ __('locations.join_choose_position') }}</div>
                        <div id="jmLoading" class="text-muted small d-none mb-2">{{ __('locations.join_loading_positions') }}</div>
                        <div id="jmPositions" class="row g-2"></div>
                        <div class="mt-3 text-muted small">{{ __('locations.join_after_choice') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <form id="joinForm" method="POST" action="" class="d-none">
            @csrf
            <input type="hidden" name="position" id="joinPosition" value="">
        </form>
        <style>.join-backdrop.hidden{display:none!important;}.hidden{display:none!important;}</style>
	</div>
	
    <x-slot name="script">
		<script src="/assets/fas.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            @if($photos->isNotEmpty())
            const swiper = new Swiper('.location-swiper', {
                slidesPerView: 1,
                spaceBetween: 8,
                pagination: { el: '.swiper-pagination', clickable: true },
                breakpoints: { 
				520: { slidesPerView: 2, spaceBetween: 12 },
				768: { slidesPerView: 1, spaceBetween: 12 }
				}
            });
            @endif

            // ===== Join Modal =====
            const backdrop  = document.getElementById('joinModalBackdrop');
            const titleEl   = document.getElementById('jmTitle');
            const metaEl    = document.getElementById('jmMeta');
            const addrEl    = document.getElementById('jmAddr');
            const posWrap   = document.getElementById('jmPositions');
            const errBox    = document.getElementById('jmError');
            const loadingEl = document.getElementById('jmLoading');
            const joinForm  = document.getElementById('joinForm');
            const joinPos   = document.getElementById('joinPosition');

            function showError(msg) { errBox.textContent = msg; errBox.classList.remove('d-none'); }
            function clearError()   { errBox.textContent = ''; errBox.classList.add('d-none'); }
            function setLoading(v)  { loadingEl.classList.toggle('d-none', !v); }

            function openModal(payload) {
                clearError(); setLoading(true);
                titleEl.textContent = payload.title || @json(__('locations.join_modal_title'));
                metaEl.textContent  = [payload.date, payload.time, payload.tz ? '('+payload.tz+')' : ''].filter(Boolean).join(' ');
                addrEl.textContent  = payload.address || '';
                posWrap.innerHTML   = '';
                backdrop.classList.remove('hidden');
            }
            function closeModal() {
                backdrop.classList.add('hidden');
                posWrap.innerHTML = '';
                clearError(); setLoading(false);
            }
            function renderPositions(occurrenceId, freePositions) {
                posWrap.innerHTML = '';
                if (!Array.isArray(freePositions) || !freePositions.length) {
                    showError(@json(__('locations.join_no_free'))); return;
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
                    headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
                });
                let data = null;
                try { data = await res.json(); } catch(e) {}
                if (!res.ok || !data || data.ok === false) {
                    showError((data?.message) || @json(__('locations.join_data_error'))); return null;
                }
                return data;
            }

            document.querySelectorAll('.js-open-join').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const occurrenceId = btn.dataset.occurrenceId;
                    openModal({ title: btn.dataset.title, date: btn.dataset.date, time: btn.dataset.time, tz: btn.dataset.tz, address: btn.dataset.address });
                    const data = await fetchAvailability(occurrenceId);
                    setLoading(false);
                    if (!data) return;
                    renderPositions(occurrenceId, data.free_positions || data.data?.free_positions || []);
                });
            });

            document.querySelectorAll('.js-close-join').forEach(btn => btn.addEventListener('click', closeModal));
            backdrop?.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

            // ===== Seatline =====
            Array.from(document.querySelectorAll('[data-seatline]')).forEach(async el => {
                const occId      = el.dataset.occurrenceId;
                const regEnabled = el.dataset.registrationEnabled === '1';
                const maxCard    = Number(el.dataset.maxPlayers) || 0;
                const leftEl     = el.querySelector('[data-left]');
                const totalEl    = el.querySelector('[data-total]');
                if (!maxCard || !regEnabled) return;
                try {
                    const res  = await fetch(`/occurrences/${occId}/availability`, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    const data = await res.json();
                    const meta = data?.meta || data?.data?.meta;
                    if (!meta) return;
                    const isTournament = el.dataset.isTournament === '1';
                    if (isTournament && meta.tournament_teams_max > 0) {
                        const tMax = Number(meta.tournament_teams_max);
                        const tReg = Number(meta.tournament_teams_registered ?? 0) || 0;
                        if (leftEl)  leftEl.textContent  = String(tReg);
                        if (totalEl) totalEl.textContent = String(tMax);
                    } else {
                        const apiMax = Number(meta.total_capacity ?? meta.max_players) || maxCard;
                        const reg    = Number(meta.registered_total ?? 0) || 0;
                        if (leftEl)  leftEl.textContent  = String(reg);
                        if (totalEl) totalEl.textContent = String(apiMax);
                    }
                } catch(e) {}
            });

            // ===== Таймлайн занятости кортов =====
            (function () {
                const section = document.getElementById('timelineSection');
                if (!section) return;

                const timelineUrl = @json($canManageTimeline ? route('locations.timeline', $location) : null);
                const listBtn = document.getElementById('tlSwitchList');
                const tlBtn = document.getElementById('tlSwitchTimeline');
                const panel = document.getElementById('timelinePanel');
                const prevBtn = document.getElementById('tlPrev');
                const nextBtn = document.getElementById('tlNext');
                const datePicker = document.getElementById('tlDatePicker');
                const currentLabel = document.getElementById('tlCurrentLabel');
                const modeDayBtn = document.getElementById('tlModeDay');
                const modeWeekBtn = document.getElementById('tlModeWeek');
                const loadingEl = document.getElementById('tlLoading');
                const dayGrid = document.getElementById('tlDayGrid');
                const weekGrid = document.getElementById('tlWeekGrid');

                const directionLabels = {
                    classic: @json(__('club.direction_classic')),
                    beach: @json(__('club.direction_beach')),
                };
                const closedLabel = @json(__('club.closed_day'));
                const eventsCountTpl = @json(__('club.events_count', ['count' => '__N__']));
                const todayLabel = @json(__('club.today'));

                const state = { mode: 'day', date: new Date() };
                const PX_PER_MIN = 1.5;

                function fmtDate(d) { return d.toISOString().slice(0, 10); }

                function setActive(btnActive, btnInactive) {
                    btnActive.classList.remove('btn-secondary'); btnActive.classList.add('btn-primary');
                    btnInactive.classList.remove('btn-primary'); btnInactive.classList.add('btn-secondary');
                }

                function showList() { panel.style.display = 'none'; setActive(listBtn, tlBtn); }
                function showTimeline() { panel.style.display = ''; setActive(tlBtn, listBtn); load(); }
                listBtn.addEventListener('click', showList);
                tlBtn.addEventListener('click', showTimeline);

                function setMode(mode) {
                    state.mode = mode;
                    if (mode === 'day') {
                        setActive(modeDayBtn, modeWeekBtn);
                        dayGrid.style.display = ''; weekGrid.style.display = 'none';
                    } else {
                        setActive(modeWeekBtn, modeDayBtn);
                        dayGrid.style.display = 'none'; weekGrid.style.display = '';
                    }
                    load();
                }
                modeDayBtn.addEventListener('click', () => setMode('day'));
                modeWeekBtn.addEventListener('click', () => setMode('week'));

                prevBtn.addEventListener('click', function () {
                    state.date.setDate(state.date.getDate() - (state.mode === 'week' ? 7 : 1));
                    load();
                });
                nextBtn.addEventListener('click', function () {
                    state.date.setDate(state.date.getDate() + (state.mode === 'week' ? 7 : 1));
                    load();
                });
                datePicker.addEventListener('change', function () {
                    if (datePicker.value) { state.date = new Date(datePicker.value + 'T00:00:00'); load(); }
                });

                function updateLabel() {
                    const isToday = fmtDate(state.date) === fmtDate(new Date());
                    currentLabel.textContent = isToday
                        ? todayLabel
                        : state.date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' });
                    datePicker.value = fmtDate(state.date);
                }

                function timeToMin(t) {
                    const parts = t.split(':').map(Number);
                    return parts[0] * 60 + parts[1];
                }

                function renderDay(directions) {
                    dayGrid.innerHTML = '';
                    const openDirs = directions.filter(d => !d.is_closed);
                    if (!openDirs.length) {
                        dayGrid.innerHTML = '<div class="alert alert-info">' + closedLabel + '</div>';
                        return;
                    }

                    const dayStart = Math.min.apply(null, openDirs.map(d => timeToMin(d.opens_at)));
                    const dayEnd = Math.max.apply(null, openDirs.map(d => timeToMin(d.closes_at)));
                    const totalMin = dayEnd - dayStart;

                    const wrap = document.createElement('div');
                    wrap.className = 'timeline-scroll';

                    const axis = document.createElement('div');
                    axis.className = 'timeline-axis';
                    axis.style.height = (totalMin * PX_PER_MIN) + 'px';
                    for (let m = dayStart; m <= dayEnd; m += 30) {
                        const label = document.createElement('div');
                        label.className = 'timeline-axis-label';
                        label.style.top = ((m - dayStart) * PX_PER_MIN) + 'px';
                        label.textContent = String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
                        axis.appendChild(label);
                    }

                    const courtsWrap = document.createElement('div');
                    courtsWrap.className = 'timeline-courts';

                    directions.forEach(function (dir) {
                        const group = document.createElement('div');
                        group.className = 'timeline-direction-group';

                        const label = document.createElement('div');
                        label.className = 'timeline-direction-label';
                        label.textContent = directionLabels[dir.direction] || dir.direction;
                        group.appendChild(label);

                        const row = document.createElement('div');
                        row.className = 'timeline-courts-row';

                        if (dir.is_closed) {
                            const closed = document.createElement('div');
                            closed.className = 'timeline-closed';
                            closed.textContent = closedLabel;
                            row.appendChild(closed);
                        } else {
                            dir.courts.forEach(function (court) {
                                const col = document.createElement('div');
                                col.className = 'timeline-court-col';

                                const header = document.createElement('div');
                                header.className = 'timeline-court-header';
                                header.textContent = court.name;
                                col.appendChild(header);

                                const body = document.createElement('div');
                                body.className = 'timeline-court-body';
                                body.style.height = (totalMin * PX_PER_MIN) + 'px';

                                court.slots.forEach(function (slot) {
                                    const startMin = timeToMin(slot.starts_at);
                                    const endMin = timeToMin(slot.ends_at);
                                    const block = document.createElement('a');
                                    block.className = 'timeline-event-block';
                                    block.href = '/events/' + slot.event_id;
                                    block.style.top = ((startMin - dayStart) * PX_PER_MIN) + 'px';
                                    block.style.height = Math.max(18, (endMin - startMin) * PX_PER_MIN) + 'px';
                                    block.style.background = slot.color || '#4A9EFF';
                                    block.innerHTML = '<div class="timeline-event-title">' + (slot.title || '') + '</div>' +
                                        '<div class="timeline-event-meta">' + slot.starts_at + '–' + slot.ends_at + (slot.organizer ? ' · ' + slot.organizer : '') + '</div>';
                                    body.appendChild(block);
                                });

                                col.appendChild(body);
                                row.appendChild(col);
                            });
                        }

                        group.appendChild(row);
                        courtsWrap.appendChild(group);
                    });

                    wrap.appendChild(axis);
                    wrap.appendChild(courtsWrap);
                    dayGrid.appendChild(wrap);
                }

                function renderWeek(days) {
                    weekGrid.innerHTML = '';
                    const directionsSet = new Set();
                    days.forEach(d => d.directions.forEach(dd => directionsSet.add(dd.direction)));
                    const directionsList = Array.from(directionsSet);
                    const counts = days.flatMap(d => d.directions.map(dd => dd.events_count));
                    const maxCount = Math.max(1, ...counts);

                    const table = document.createElement('table');
                    table.className = 'timeline-week-table';

                    const thead = document.createElement('thead');
                    const headRow = document.createElement('tr');
                    headRow.appendChild(document.createElement('th'));
                    days.forEach(function (day) {
                        const th = document.createElement('th');
                        th.textContent = day.day_label;
                        th.className = 'timeline-week-day-header';
                        th.addEventListener('click', function () {
                            state.date = new Date(day.date + 'T00:00:00');
                            setMode('day');
                        });
                        headRow.appendChild(th);
                    });
                    thead.appendChild(headRow);
                    table.appendChild(thead);

                    const tbody = document.createElement('tbody');
                    directionsList.forEach(function (directionKey) {
                        const row = document.createElement('tr');
                        const th = document.createElement('th');
                        th.textContent = directionLabels[directionKey] || directionKey;
                        row.appendChild(th);

                        days.forEach(function (day) {
                            const dd = day.directions.find(x => x.direction === directionKey);
                            const td = document.createElement('td');
                            if (!dd || dd.is_closed) {
                                td.textContent = closedLabel;
                                td.className = 'timeline-week-closed';
                            } else {
                                const intensity = dd.events_count / maxCount;
                                td.style.background = 'rgba(74,158,255,' + (0.08 + intensity * 0.5).toFixed(2) + ')';
                                td.textContent = eventsCountTpl.replace('__N__', dd.events_count);
                            }
                            row.appendChild(td);
                        });

                        tbody.appendChild(row);
                    });
                    table.appendChild(tbody);
                    weekGrid.appendChild(table);
                }

                async function load() {
                    updateLabel();
                    loadingEl.style.display = '';
                    try {
                        const url = timelineUrl + '?date=' + fmtDate(state.date) + '&mode=' + state.mode;
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                        const data = await res.json();
                        if (state.mode === 'day') { renderDay(data); } else { renderWeek(data); }
                    } catch (e) {
                        dayGrid.innerHTML = ''; weekGrid.innerHTML = '';
                    } finally {
                        loadingEl.style.display = 'none';
                    }
                }
            })();
        });
    </script>
	</x-slot>
</x-voll-layout>