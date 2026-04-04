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
	aspect-ratio: 4/3 ;
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
					<div class="ramka p-1 mb-0 no-highlight">	
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
        <div class="ramka p-1 no-highlight">
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
			$occList = $occurrences ?? collect();
			$evList  = $events ?? collect();
            @endphp
			
            @if($occList->isEmpty() && $evList->isEmpty())
			<div class="alert alert-info">Пока нет ближайших мероприятий для этой локации.</div>
            @else
			<div class="row">
				{{-- Occurrences --}}
				@foreach($occList as $occ)
				@php $event = $occ->event; @endphp
				@continue(!$event)
				
				<div class="col-md-6">
					<a href="{{ url('/events/' . (int)$event->id) }}" class="card event-card text-decoration-none">
						<div class="card-body">
							<h3 class="card-title h5">{{ $event->title }}</h3>
							<div class="card-subtitle mb-2 text-muted small">
								{{ $event->location?->name }}
								@if($city = $event->location?->city?->name)
								<span class="mx-1">•</span> {{ $city }}
								@endif
								@if($event->location?->address)
								<span class="mx-1">•</span> {{ $event->location->address }}
								@endif
							</div>
							
							@php
							$eventTz = $occ->timezone ?: ($event->timezone ?: 'UTC');
							$sUser = $occ->starts_at ? \Carbon\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($userTz) : null;
							$eUser = $occ->ends_at   ? \Carbon\Carbon::parse($occ->ends_at,   'UTC')->setTimezone($userTz) : null;
							$userTzLabel = $tzLabel($sUser, $userTz);
							@endphp
							
							<div class="card-text mt-2">
								@if($sUser)
								{{ $sUser->format('d.m.Y · H:i') }}@if($eUser)–{{ $eUser->format('H:i') }}@endif
								<span class="text-muted small d-block">({{ $userTzLabel }})</span>
								<span class="text-muted small d-block">Таймзона события: {{ $eventTz }}</span>
								@else
								—
								@endif
							</div>
						</div>
					</a>
				</div>
				@endforeach
				
				{{-- Fallback events --}}
				@foreach($evList as $event)
				<div class="col-md-6">
					<a href="{{ url('/events/' . (int)$event->id) }}" class="card event-card text-decoration-none">
						<div class="card-body">
							<h3 class="card-title h5">{{ $event->title }}</h3>
							<div class="card-subtitle mb-2 text-muted small">
								{{ $event->location?->name }}
								@if($city = $event->location?->city?->name)
								<span class="mx-1">•</span> {{ $city }}
								@endif
								@if($event->location?->address)
								<span class="mx-1">•</span> {{ $event->location->address }}
								@endif
							</div>
							
							@php
							$eventTz = $event->timezone ?: 'UTC';
							$sUser = $event->starts_at ? \Carbon\Carbon::parse($event->starts_at, 'UTC')->setTimezone($userTz) : null;
							$eUser = $event->ends_at   ? \Carbon\Carbon::parse($event->ends_at,   'UTC')->setTimezone($userTz) : null;
							$userTzLabel = $tzLabel($sUser, $userTz);
							@endphp
							
							<div class="card-text mt-2">
								@if($sUser)
								{{ $sUser->format('d.m.Y · H:i') }}@if($eUser)–{{ $eUser->format('H:i') }}@endif
								<span class="text-muted small d-block">({{ $userTzLabel }})</span>
								<span class="text-muted small d-block">Таймзона события: {{ $eventTz }}</span>
								@else
								—
								@endif
							</div>
						</div>
					</a>
				</div>
				@endforeach
			</div>
            @endif
		</div>
	</div>
	
    <x-slot name="script">
		<script src="/assets/fas.js"></script> 		
		<script>
			document.addEventListener('DOMContentLoaded', function() {
						
				// === Инициализация Swiper ===
				@if($photos->isNotEmpty())
				const swiper = new Swiper('.location-swiper', {
					slidesPerView: 2,
					spaceBetween: 8,
					pagination: {
						el: '.swiper-pagination',
						clickable: true,
					},
						breakpoints: {
							768: {
								slidesPerView: 1, 
								spaceBetween: 12
							}					
						}
				});	
				@endif
			});		
		</script>				
	</x-slot>
</x-voll-layout>