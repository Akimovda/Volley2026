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
			.location-card {
			justify-content: space-between;
			display: flex;
			flex-flow: column;
			}
			
			.location-card-footer {
			padding: 1rem 1.6rem;
			margin: 1rem -2rem -1.5rem;
			border-radius: 0 0 1rem 1rem;
			overflow: hidden;
			font-size: 1.6rem;
			background: rgba(0,0,0,0.03);
			}			
			body.dark .location-card-footer {
			background: rgba(255,255,255,0.03);
			}				
			.ymaps-2-1-79-balloon__content {
			font: unset!important;
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
				<span><span class="cd b-600 pr-05">{{ $items->count() }}</span>{{ trans_choice('локация|локации|локаций', $items->count()) }}</span>
			</div>
			
			<div class="row">
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
				<span><span class="cd b-600 pr-05">{{ $items->count() }}</span>{{ trans_choice('локация|локации|локаций', $items->count()) }}</span>
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
		<h2 class="-mt-05">Карта локаций</h2>
		<div class="mb-2">Отображаются локации текущей страницы.</div>
		
		<div id="ymap" style="height: 56rem; width: 100%; border-radius: 1rem; overflow: hidden; opacity: 0; transition: opacity 0.5s;"></div>
		
		<script>window.__LOC_POINTS__ = @json($points);</script>
		<script src="https://api-maps.yandex.ru/2.1/?apikey={{ config('services.yandex_maps.key') }}&lang=ru_RU"></script>
		
		<script>
			(function () {
				let currentMap = null;
				let currentObjectManager = null;
				
				function applyThemeToMap(theme) {
					const mapContainer = document.getElementById('ymap');
					if (!mapContainer) return;
					
					if (theme === 'dark') {
						mapContainer.style.filter = 'invert(0.9) hue-rotate(180deg)';
						if (currentObjectManager) {
							currentObjectManager.clusters.options.set({
								preset: 'islands#orangeClusterIcons'
							});
							// Оранжевые маркеры для тёмной темы
							currentObjectManager.objects.options.set({
								preset: 'islands#orangeIcon'
							});
						}
						} else {
						mapContainer.style.filter = 'none';
						if (currentObjectManager) {
							currentObjectManager.clusters.options.set({
								preset: 'islands#darkBlueClusterIcons'
							});
							// Синие маркеры для светлой темы
							currentObjectManager.objects.options.set({
								preset: 'islands#blueIcon'
							});
						}
					}
				}
				
				window.updateLocationsMapTheme = function() {
					const theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
					applyThemeToMap(theme);
				};
				
				function showMap() {
					var mapDiv = document.getElementById('ymap');
					if (mapDiv && mapDiv.style.opacity !== '1') {
						mapDiv.style.opacity = '1';
					}
				}
				
				function init() {
					var pts = Array.isArray(window.__LOC_POINTS__) ? window.__LOC_POINTS__ : [];
					var hasPts = pts.length > 0;
					
					if (!hasPts) {
						var map = new ymaps.Map("ymap", {
							center: [55.751244, 37.618423],
							zoom: 4,
							controls: ['zoomControl', 'fullscreenControl']
						});
						currentMap = map;
						showMap();
						return;
					}
					
					// Вычисляем границы
					var lats = pts.map(function(p) { return p.lat; });
					var lngs = pts.map(function(p) { return p.lng; });
					
					var minLat = Math.min.apply(null, lats);
					var maxLat = Math.max.apply(null, lats);
					var minLng = Math.min.apply(null, lngs);
					var maxLng = Math.max.apply(null, lngs);
					
					var centerLat = (minLat + maxLat) / 2;
					var centerLng = (minLng + maxLng) / 2;
					
					// Создаём карту (невидимую)
					var map = new ymaps.Map("ymap", {
						center: [centerLat, centerLng],
						zoom: 10,
						controls: ['zoomControl', 'fullscreenControl']
					});
					
					currentMap = map;
					
					// Применяем границы и после этого показываем карту
					map.setBounds([[minLat, minLng], [maxLat, maxLng]], {
						checkZoomRange: true,
						zoomMargin: 50
					}).then(showMap).catch(showMap);
					
					// Запасной вариант
					setTimeout(showMap, 500);
					
					// Применяем тему
					var currentTheme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
					applyThemeToMap(currentTheme);
					
					// Создаём ObjectManager
					var objectManager = new ymaps.ObjectManager({
						clusterize: true,
						gridSize: 64,
						clusterDisableClickZoom: false,
						geoObjectSeparatePanning: true
					});
					
					currentObjectManager = objectManager;
					
					var features = pts.map(function(p, index) {
						function esc(s) {
							return String(s ?? '').replace(/[&<>"']/g, function(c) {
								return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
							});
						}
						
						var balloon = '<div style="max-width: 25rem">'
						+ '<a href="' + esc(p.url) + '" class="f-16 b-600 blink">' + esc(p.name) + '</a>'
						+ (p.city ? '<div class="f-13 mt-1">' + esc(p.city) + '</div>' : '')
						+ (p.address ? '<div class="f-15 mt-05">' + esc(p.address) + '</div>' : '')
						+ '</div>';
						
						return {
							type: "Feature",
							id: index,
							geometry: {
								type: "Point",
								coordinates: [p.lat, p.lng]
							},
							properties: {
								hintContent: (p.city ? p.city + ': ' : '') + p.name,
								balloonContent: balloon,
								clusterCaption: p.city || p.name
							},
							options: {
								preset: "islands#redDotIcon"
							}
						};
					});
					
					objectManager.add({
						type: "FeatureCollection",
						features: features
					});
					
					objectManager.objects.options.set('preset', 'islands#redDotIcon');
					
					if (currentTheme === 'dark') {
						objectManager.clusters.options.set({ preset: 'islands#grayClusterIcons' });
						} else {
						objectManager.clusters.options.set({ preset: 'islands#blueClusterIcons' });
					}
					
					map.geoObjects.add(objectManager);
				}
				
				ymaps.ready(init);
			})();
		</script>
		
		<script>
			(function() {
				function updateMapTheme() {
					if (window.updateLocationsMapTheme) {
						window.updateLocationsMapTheme();
					}
				}
				
				document.addEventListener('click', function(e) {
					const btn = e.target.closest('.fix-header-btn-theme');
					if (btn) {
						setTimeout(function() {
							updateMapTheme();
						}, 150);
					}
				});
			})();
		</script>
		
		
	</div>
	@if($cities)
	{{ $cities->links() }}
	@endif	
	@endif
	
</div>

</x-voll-layout>	