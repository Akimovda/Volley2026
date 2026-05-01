<div class="ramka">
	
	<h2 class="-mt-05">ДАТА /  ВРЕМЯ / ЛОКАЦИЯ</h2>
	
	
	<div class="mb-1 d-flex">
		<span class="emo">📅</span>
		<span>
			<strong>Дата:</strong> {{ $dateHuman }}
		</span>
	</div>
	
	<div class="mb-1 d-flex">
		<span class="emo">⏰</span> 
		<span>
			<strong>Время:</strong> {{ $timeLabel }}
		</span>
	</div>
	
	@if($durationLabel)
	<div class="mb-1 d-flex">
		<span class="emo">⏱️</span>
		<span>
			<strong>Длительность:</strong> {{ $durationLabel }}
		</span>
	</div>
	@endif
	
	@if($address)
	<div class="mb-1 d-flex">
		<span class="emo">📍</span>
		<span>
			<strong>Место:</strong> {{ $address }}
		</span>
	</div>
	@endif	
	
	<div class="event-share-actions mt-1 mb-1">
		<button type="button" class="btn btn-secondary btn-haptic" id="btn-share-event">🤝 Поделиться</button>
		<button type="button" class="btn btn-secondary btn-haptic" id="btn-add-calendar">📆 В календарь</button>
	</div>

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
		style="height: 32rem; border: 0; border-radius: 1rem;"
		frameborder="0"
		allowfullscreen="true"
		loading="lazy"
		></iframe>
	</div>	
	@endif
	<div class="m-center">
		@if($gMapsUrl)
		<a href="{{ $gMapsUrl }}" target="_blank" class="mt-1 btn btn-secondary btn-small">
			Google Карты
		</a>
		@endif
		@if($osmUrl)
		<a href="{{ $osmUrl }}" target="_blank" class="mt-1 btn btn-secondary btn-small">
			OpenStreetMap
		</a>
		@endif
		@if($yandexLink)
		<a href="{{ $yandexLink }}" target="_blank" class="mt-1 btn btn-secondary btn-small">
			Яндекс Карты
		</a>
		@endif
	</div>


</div>	