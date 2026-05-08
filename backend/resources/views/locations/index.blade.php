{{-- resources/views/locations/index.blade.php --}}
<x-voll-layout body_class="locations-page">

    <x-slot name="title">{{ __('locations.index_title') }}</x-slot>
    <x-slot name="description">{{ __('locations.index_description') }}</x-slot>
    <x-slot name="canonical">{{ route('locations.index') }}</x-slot>
    <x-slot name="h1">{{ __('locations.index_h1') }}</x-slot>
    <x-slot name="t_description">{{ __('locations.index_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('locations.index') }}" itemprop="item"><span itemprop="name">{{ __('locations.breadcrumb_index') }}</span></a>
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
				<a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'cards'])) }}"
				class="btn {{ $mode === 'cards' ? '' : 'btn-secondary' }}">
					{{ __('locations.view_cards') }}
				</a>
			</div>
			<div class="mt-2" data-aos-delay="300" data-aos="fade-up">
				<a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'card', 'page' => 1])) }}"
				class="btn {{ $mode === 'card' ? '' : 'btn-secondary' }}">
					{{ __('locations.view_card') }}
				</a>
			</div>
			<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
				<a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'rows'])) }}"
				class="btn {{ $mode === 'rows' ? '' : 'btn-secondary' }}">
					{{ __('locations.view_rows') }}
				</a>
			</div>
			<div class="mt-2" data-aos-delay="450" data-aos="fade-up">
				<a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'map'])) }}"
				class="btn {{ $mode === 'map' ? '' : 'btn-secondary' }}">
					{{ __('locations.view_map') }}
				</a>
			</div>
		</div>
		<div class="d-flex m-center mt-1" data-aos="fade-up" data-aos-delay="550">
			<a href="{{ request()->fullUrlWithQuery(array_merge($base, ['active' => $active ? 0 : 1])) }}"
			class="btn btn-secondary">
                {{ $active ? __('locations.show_all') : __('locations.only_with_events') }}
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

        @if(!in_array($mode, ['map', 'card']) && (!$cities || $cities->isEmpty()))
		<div class="ramka">
			<div class="alert alert-info">{{ __('locations.empty_list') }}</div>
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
				<span><span class="cd b-600 pr-05">{{ $items->count() }}</span>{{ trans_choice(__('locations.count_plural'), $items->count()) }}</span>
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
				<span><span class="cd b-600 pr-05">{{ $items->count() }}</span>{{ trans_choice(__('locations.count_plural'), $items->count()) }}</span>
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
				@endforeach
			</ul>
		</div>
		@endforeach
		@endif

		{{-- ===== CARD (paginated) MODE ===== --}}
		@if($mode === 'card' && isset($locationsPaginated) && $locationsPaginated && $locationsPaginated->isNotEmpty())
		<div class="ramka">
			<div class="row">
				@foreach($locationsPaginated as $loc)
				<div class="col-sm-6 col-lg-4">
					<x-location-card :location="$loc" />
				</div>
				@endforeach
			</div>
			<div class="mt-3">
				{{ $locationsPaginated->links() }}
			</div>
		</div>
		@elseif($mode === 'card' && (!isset($locationsPaginated) || !$locationsPaginated || $locationsPaginated->isEmpty()))
		<div class="ramka">
			<div class="alert alert-info">{{ __('locations.empty_list') }}</div>
		</div>
		@endif

		{{-- ===== MAP MODE ===== --}}
		@if($mode === 'map')
		<div class="ramka">
			<h2 class="-mt-05">{{ __('locations.map_section') }}</h2>

			@auth
			<div id="ymap" style="height: 56rem; width: 100%; border-radius: 1rem; overflow: hidden; opacity: 0; transition: opacity 0.5s;"></div>

			<script>window.__LOC_POINTS__ = {!! $locationsJson ?? '[]' !!};</script>

			<script>
				(function () {
					var currentMap = null;
					var currentObjectManager = null;

					function getTheme() {
						return localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
					}

					function getMapType(theme) {
						return theme === 'dark' ? 'yandex#dark' : 'yandex#map';
					}

					function getIconPresets(theme) {
						return theme === 'dark'
							? { cluster: 'islands#orangeClusterIcons', object: 'islands#orangeIcon' }
							: { cluster: 'islands#blueClusterIcons',   object: 'islands#blueIcon'   };
					}

					function showMap() {
						var mapDiv = document.getElementById('ymap');
						if (mapDiv && mapDiv.style.opacity !== '1') mapDiv.style.opacity = '1';
					}

					window.updateLocationsMapTheme = function() {
						var theme = getTheme();
						if (currentMap) currentMap.setType(getMapType(theme));
						if (currentObjectManager) {
							var p = getIconPresets(theme);
							currentObjectManager.clusters.options.set({ preset: p.cluster });
							currentObjectManager.objects.options.set({ preset: p.object });
						}
					};

					function init() {
						var pts = Array.isArray(window.__LOC_POINTS__) ? window.__LOC_POINTS__ : [];
						var theme = getTheme();

						if (!pts.length) {
							currentMap = new ymaps.Map('ymap', {
								center: [55.751244, 37.618423],
								zoom: 4,
								type: getMapType(theme),
								controls: ['zoomControl', 'fullscreenControl']
							});
							showMap();
							return;
						}

						var lats = pts.map(function(p) { return p.lat; });
						var lngs = pts.map(function(p) { return p.lng; });
						var centerLat = (Math.min.apply(null, lats) + Math.max.apply(null, lats)) / 2;
						var centerLng = (Math.min.apply(null, lngs) + Math.max.apply(null, lngs)) / 2;

						var map = new ymaps.Map('ymap', {
							center: [centerLat, centerLng],
							zoom: 10,
							type: getMapType(theme),
							controls: ['zoomControl', 'fullscreenControl']
						});
						currentMap = map;

						map.setBounds(
							[[Math.min.apply(null, lats), Math.min.apply(null, lngs)],
							 [Math.max.apply(null, lats), Math.max.apply(null, lngs)]],
							{ checkZoomRange: true, zoomMargin: 50 }
						).then(showMap).catch(showMap);
						setTimeout(showMap, 500);

						var objectManager = new ymaps.ObjectManager({
							clusterize: true,
							gridSize: 64,
							clusterDisableClickZoom: false,
							geoObjectSeparatePanning: true
						});
						currentObjectManager = objectManager;

						var p = getIconPresets(theme);
						objectManager.objects.options.set({ preset: p.object });
						objectManager.clusters.options.set({ preset: p.cluster });

						var features = pts.map(function(loc, index) {
							function esc(s) {
								return String(s ?? '').replace(/[&<>"']/g, function(c) {
									return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
								});
							}
							var balloon = '<div style="max-width:25rem">'
								+ '<a href="' + esc(loc.url) + '" class="f-16 b-600 blink">' + esc(loc.name) + '</a>'
								+ (loc.city    ? '<div class="f-13 mt-1">'  + esc(loc.city)    + '</div>' : '')
								+ (loc.address ? '<div class="f-15 mt-05">' + esc(loc.address) + '</div>' : '')
								+ '</div>';
							return {
								type: 'Feature',
								id: index,
								geometry: { type: 'Point', coordinates: [loc.lat, loc.lng] },
								properties: {
									hintContent: (loc.city ? loc.city + ': ' : '') + loc.name,
									balloonContent: balloon,
									clusterCaption: loc.city || loc.name
								}
							};
						});

						objectManager.add({ type: 'FeatureCollection', features: features });
						map.geoObjects.add(objectManager);
					}

					// Загружаем API с нужной темой
					var theme = getTheme();
					var script = document.createElement('script');
					script.src = 'https://api-maps.yandex.ru/2.1/?apikey={{ config('services.yandex_maps.key') }}&lang={{ __('locations.yandex_lang') }}&theme=' + theme;
					script.onload = function() { ymaps.ready(init); };
					document.head.appendChild(script);

					document.addEventListener('click', function(e) {
						if (e.target.closest('.fix-header-btn-theme')) {
							setTimeout(function() {
								if (window.updateLocationsMapTheme) window.updateLocationsMapTheme();
							}, 150);
						}
					});
				})();
			</script>
			@else
			<div class="text-center pt-4 pb-4">
				<div class="f-18 b-500 mb-2">{{ __('locations.login_to_see_map') }}</div>
				<div class="d-flex gap-1 m-center">
					<a href="{{ route('login') }}" class="btn">{{ __('locations.btn_login') }}</a>
					<a href="{{ route('register') }}" class="btn btn-secondary">{{ __('locations.btn_register') }}</a>
				</div>
			</div>
			@endauth
		</div>
		@endif

	</div>

</x-voll-layout>
