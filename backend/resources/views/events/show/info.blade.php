<div class="ramka">

	<h2 class="-mt-05">{{ __('events.show_info_h2') }}</h2>


	<div class="mb-1 d-flex">
		<span class="emo">📅</span>
		<span>
			<strong>{{ __('events.show_info_date') }}</strong> {{ $dateHuman }}
		</span>
	</div>

	<div class="mb-1 d-flex">
		<span class="emo">⏰</span>
		<span>
			<strong>{{ __('events.show_info_time') }}</strong> {{ $timeLabel }}
		</span>
	</div>

	@if($durationLabel)
	<div class="mb-1 d-flex">
		<span class="emo">⏱️</span>
		<span>
			<strong>{{ __('events.show_info_duration') }}</strong> {{ $durationLabel }}
		</span>
	</div>
	@endif

	@if($address)
	<div class="mb-1 d-flex">
		<span class="emo">📍</span>
		<span>
			<strong>{{ __('events.show_info_place') }}</strong> {{ $address }}
		</span>
	</div>
	@endif

	<div class="event-share-actions mt-1 mb-1">
		<button type="button" class="btn btn-secondary btn-haptic" id="btn-share-event">{{ __('events.show_share_btn') }}</button>
		<button type="button" class="btn btn-secondary btn-haptic" id="btn-add-calendar">{{ __('events.show_info_calendar') }}</button>
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
			{{ __('events.show_info_gmaps') }}
		</a>
		@endif
		@if($osmUrl)
		<a href="{{ $osmUrl }}" target="_blank" class="mt-1 btn btn-secondary btn-small">
			{{ __('events.show_info_osm') }}
		</a>
		@endif
		@if($yandexLink)
		<a href="{{ $yandexLink }}" target="_blank" class="mt-1 btn btn-secondary btn-small">
			{{ __('events.show_info_yandex') }}
		</a>
		@endif
	</div>


</div>