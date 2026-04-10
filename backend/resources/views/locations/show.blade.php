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
        {{ $location->name }} — локация
	</x-slot>
	
    <x-slot name="description">
        {{ $location->short_text ? strip_tags($location->short_text) : 'Информация о локации ' . $location->name }}
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
		{{ $cityName }}
	</x-slot>
    <x-slot name="t_description">
       тут будет область
	</x-slot>	


    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('locations.index') }}" itemprop="item"><span itemprop="name">Локации</span></a>
            <meta itemprop="position" content="1">
		</li>
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ $location->name }}</span>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
	
	
    <div class="container">
        {{-- Основная информация --}}
		<div class="row mb-0">
			<div class="col-md-8">
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
			<div class="col-md-4">
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
						title="Карта локации {{ $location->name }}"
						></iframe>
					</div>
					@else
					<div class="alert alert-info">
						Для отображения карты нужны координаты. Сейчас они не указаны.
					</div>
					@endif
				</div>
			</div>
		</div>
		
        {{-- Мероприятия в этой локации --}}
        <div class="ramka">
            <h2 class="-mt-05">Мероприятия в этой локации</h2>
        
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
                <div class="alert alert-info">Пока нет ближайших мероприятий для этой локации.</div>
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
                            <div id="jmTitle" class="fw-semibold fs-5">Запись</div>
                            <div id="jmMeta" class="text-muted small mt-1"></div>
                            <div id="jmAddr" class="text-muted small mt-1"></div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-close-join">✕</button>
                    </div>
                    <div class="p-3">
                        <div id="jmError" class="alert alert-danger d-none mb-2"></div>
                        <div class="text-muted small mb-2">Выбери позицию (показаны только свободные):</div>
                        <div id="jmLoading" class="text-muted small d-none mb-2">Загружаю доступные позиции…</div>
                        <div id="jmPositions" class="row g-2"></div>
                        <div class="mt-3 text-muted small">После выбора позиции вы сразу будете записаны.</div>
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
                titleEl.textContent = payload.title || 'Запись';
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
                    showError('Свободных мест больше нет.'); return;
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
                    showError((data?.message) || 'Не удалось получить данные.'); return null;
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
                    const apiMax    = Number(meta.max_players) || maxCard;
                    const remaining = Number.isFinite(Number(meta.remaining_total))
                        ? Number(meta.remaining_total)
                        : Math.max(0, apiMax - (Number(meta.registered_total) || 0));
                    if (leftEl)  leftEl.textContent  = String(remaining);
                    if (totalEl) totalEl.textContent = String(apiMax);
                } catch(e) {}
            });
        });
    </script>
	</x-slot>
</x-voll-layout>