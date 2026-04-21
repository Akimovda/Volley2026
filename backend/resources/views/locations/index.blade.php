{{-- resources/views/locations/index.blade.php --}}
<x-voll-layout body_class="locations-page">
	
    <x-slot name="title">Локации для волейбола</x-slot>
    <x-slot name="description">Спортивные площадки и залы для волейбола — фото, адреса, карта и расписание мероприятий</x-slot>
    <x-slot name="canonical">{{ route('locations.index') }}</x-slot>
    <x-slot name="h1">Локации</x-slot>
    <x-slot name="t_description">Площадки и залы для волейбола</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('locations.index') }}" itemprop="item"><span itemprop="name">Локации</span></a>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
	
    <x-slot name="d_description">
        @php
		$base   = request()->except('page');
		$mode   = $viewMode ?? 'cards';
		$active = (int)($activeOnly ?? 0);
        @endphp
		
		
		<div class="d-flex flex-wrap gap-1 m-center">
			<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
				<a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'cards', 'page' => 1])) }}"
				class="btn {{ $mode === 'cards' ? '' : 'btn-secondary' }}">
					Карточки
				</a>
			</div>
			<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
				<a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'rows', 'page' => 1])) }}"
				class="btn {{ $mode === 'rows' ? '' : 'btn-secondary' }}">
					Список
				</a>
			</div>	
			<div class="mt-2" data-aos-delay="450" data-aos="fade-up">
				<a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'map', 'page' => 1])) }}"
				class="btn {{ $mode === 'map' ? '' : 'btn-secondary' }}">
					Карта
				</a>
			</div>					
		</div>		
		<div class="d-flex m-center mt-1" data-aos="fade-up" data-aos-delay="550">
			<a href="{{ request()->fullUrlWithQuery(array_merge($base, ['active' => $active ? 0 : 1, 'page' => 1])) }}"
			class="btn {{ $active ? 'btn-secondary' : 'btn-secondary' }}">
                {{ $active ? 'Показать все' : 'Только с событиями' }}
			</a>		
		</div>		
		
	</x-slot>
	
    <x-slot name="style">
        <style>
            .location-thumb {
			width: 100%;
			aspect-ratio: 16/10;
			object-fit: cover;
			border-radius: 0.8rem 0.8rem 0 0;
			display: block;
            }
            .location-nophoto {
			width: 100%;
			aspect-ratio: 16/10;
			background: var(--bg2, #f3f4f6);
			border-radius: 0.8rem 0.8rem 0 0;
			display: flex;
			align-items: center;
			justify-content: center;
			color: var(--t3, #9ca3af);
			font-size: 1.4rem;
            }
            .location-card-link {
			display: block;
			text-decoration: none;
			color: inherit;
			transition: transform .15s;
            }
            .location-card-link:hover {
			transform: translateY(-2px);
            }
            .location-card-link .card {
			padding: 0;
			overflow: hidden;
            }
            .location-card-body {
			padding: 1.2rem 1.4rem 1.4rem;
            }
			.loclist {
			display: flex;
			flex-wrap: wrap;
			}
			.loclist  li {
			width: 50%;
			}	
@media screen and (max-width: 991px) {
			.loclist  li {
			width: 100%;
			}
}			
		</style>
	</x-slot>
	
    <div class="container">
		
        @php
		$cities = $cities ?? null;
		$points = [];
		if ($cities) {
		foreach ($cities as $c) {
		foreach (($c->locations ?? collect()) as $l) {
		if (!is_null($l->lat) && !is_null($l->lng)) {
		$points[] = [
		'id'      => (int)$l->id,
		'name'    => (string)$l->name,
		'address' => (string)($l->address ?? ''),
		'city'    => (string)($c->name ?? ''),
		'lat'     => (float)$l->lat,
		'lng'     => (float)$l->lng,
		'url'     => route('locations.show', ['location' => $l->id, 'slug' => \Illuminate\Support\Str::slug((string)$l->name, '-') ?: 'location']),
		];
		}
		}
		}
		}
        @endphp
		
        @if(!$cities || $cities->isEmpty())
		<div class="ramka">
			<div class="alert alert-info">Локации не найдены.</div>
		</div>
        @endif
		
        {{-- ===== CARDS MODE ===== --}}
        @if($mode === 'cards' && $cities && $cities->isNotEmpty())
		@foreach($cities as $city)
		@php $items = $city->locations ?? collect(); @endphp
		@if($items->isEmpty()) @continue @endif
		
		<div class="ramka">
			<div class="d-flex between">
				<h2 class="-mt-05">{{ $city->name }}
					@if(!empty($city->region))
					<span class="pl-05 f-16 b-500">({{ $city->region }})</span>
					@endif
				</h2>
					<span><span class="cd b-600">{{ $items->count() }}</span>{{ trans_choice('локация|локации|локаций', $items->count()) }}</span>
				</div>
				
				<div class="row row2">
					@foreach($items as $loc)
					<div class="col-sm-6 col-lg-4">
						<x-location-card :location="$loc" />
					</div>
					@endforeach
				</div>
			</div>
            @endforeach
			
            {{ $cities->links() }}
			@endif
			
			{{-- ===== ROWS MODE ===== --}}
			@if($mode === 'rows' && $cities && $cities->isNotEmpty())
            @foreach($cities as $city)
			@php $items = $city->locations ?? collect(); @endphp
			@if($items->isEmpty()) @continue @endif
			
			<div class="ramka">
				<div class="d-flex between">
					<h2 class="-mt-05">{{ $city->name }}
						@if(!empty($city->region))
						<span class="pl-05 f-16 b-500">({{ $city->region }})</span>
						@endif
					</h2>
					<span><span class="cd b-600">{{ $items->count() }}</span>{{ trans_choice('локация|локации|локаций', $items->count()) }}</span>
					</div>
					
					
					<ul class="list loclist">
						@foreach($items as $loc)
						<li>
							<a href="{{ route('locations.show', ['location' => $loc->id, 'slug' => \Illuminate\Support\Str::slug((string)$loc->name, '-') ?: 'location']) }}"
							class="b-600 blink">{{ $loc->name }}</a>
							@if($loc->address)
							<div class="f-16 mt-05 pb-05">{{ $loc->address }}</div>
							@endif
						</li>
					</tr>
					@endforeach
				</ul>
			</div>
            @endforeach
			
			{{ $cities->links() }}
			@endif
			
			{{-- ===== MAP MODE ===== --}}
			@if($mode === 'map')
            <div class="ramka">
                <h2 class="-mt-05">🗺 Карта локаций</h2>
                <div class="f-16 mb-2" style="opacity:.6">Отображаются локации текущей страницы.</div>
				
                <div id="ymap" style="height: 56rem; width: 100%; border-radius: 1rem; overflow: hidden;"></div>
				
                <script>window.__LOC_POINTS__ = @json($points);</script>
                <script src="https://api-maps.yandex.ru/2.1/?apikey={{ config('services.yandex_maps.key') }}&lang=ru_RU"></script>
                <script>
                    (function () {
                        function init() {
                            var pts = Array.isArray(window.__LOC_POINTS__) ? window.__LOC_POINTS__ : [];
                            var hasPts = pts.length > 0;
                            var center = hasPts ? [pts[0].lat, pts[0].lng] : [55.751244, 37.618423];
							
                            var map = new ymaps.Map("ymap", {
                                center: center,
                                zoom: hasPts ? 11 : 4,
                                controls: ['zoomControl', 'fullscreenControl']
							});
							
                            if (!hasPts) return;
							
                            var geoObjects = [];
                            pts.forEach(function(p) {
                                function esc(s) {
                                    return String(s ?? '').replace(/[&<>"']/g, function(c) {
                                        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
									});
								}
                                var balloon = '<div style="max-width:240px">'
								+ '<a href="' + esc(p.url) + '" style="font-weight:600;font-size:14px">' + esc(p.name) + '</a>'
								+ (p.city ? '<div style="color:#666;font-size:12px;margin-top:2px">' + esc(p.city) + '</div>' : '')
								+ (p.address ? '<div style="color:#666;font-size:12px;margin-top:4px">' + esc(p.address) + '</div>' : '')
								+ '</div>';
								
                                var pm = new ymaps.Placemark([p.lat, p.lng], {
                                    hintContent: (p.city ? p.city + ': ' : '') + p.name,
                                    balloonContent: balloon
								}, { preset: 'islands#redDotIcon' });
								
                                geoObjects.push(pm);
                                map.geoObjects.add(pm);
							});
							
                            var bounds = ymaps.geoQuery(geoObjects).getBounds();
                            if (bounds) map.setBounds(bounds, { checkZoomRange: true, zoomMargin: 40 });
						}
                        ymaps.ready(init);
					})();
				</script>
				
                @if($cities)
				{{ $cities->links() }}
                @endif
			</div>
			@endif
			
		</div>
		
	</x-voll-layout>	