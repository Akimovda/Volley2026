<div class="ramka">

	<h2 class="-mt-05">{{ __('events.show_info_h2') }}</h2>


	<div class="mb-1 d-flex">
		<span class="emo"><x-menu-icon name="calendar" class="cd" /></span>
		<span>
			<strong>{{ __('events.show_info_date') }}</strong> {{ $dateHuman }}
		</span>
	</div>

	<div class="mb-1 d-flex">
		<span class="emo"><x-menu-icon name="clock" class="cd" /></span>
		<span>
			<strong>{{ __('events.show_info_time') }}</strong> {{ $timeLabel }}
		</span>
	</div>

	@php $__w = $occurrence ? app(\App\Services\WeatherService::class)->forOccurrence($occurrence) : null; @endphp
	@if($__w)
	<div class="mb-1 d-flex">
		<span class="emo">{{ \App\Services\WeatherService::iconHtml($__w['icon'], true) }}</span>
		<span>
			<strong>{{ __('events.weather_title') }}:</strong> {{ $__w['temp'] }}@if($__w['pop'] >= 10), {{ mb_strtolower(__('events.weather_precip')) }} {{ $__w['pop'] }}%@endif
		</span>
	</div>
	@endif

	@if($durationLabel)
	<div class="mb-1 d-flex">
		<span class="emo"><x-menu-icon name="stopwatch" class="cd" /></span>
		<span>
			<strong>{{ __('events.show_info_duration') }}</strong> {{ $durationLabel }}
		</span>
	</div>
	@endif

	@if($address)
	<div class="mb-1 d-flex">
		<span class="emo"><x-menu-icon name="pin" class="cd" /></span>
		<span>
			<strong>{{ __('events.show_info_place') }}</strong> {{ $address }}
		</span>
	</div>
	@endif

	<div class="event-share-actions mt-1 mb-1">
		<button type="button" class="btn btn-secondary btn-haptic" id="btn-share-event"><x-menu-icon name="share" class="btn-icon" /> {{ __('events.show_share_btn') }}</button>
		<button type="button" class="btn btn-secondary btn-haptic" id="btn-add-calendar"><x-menu-icon name="calendar" class="btn-icon" /> {{ __('events.show_info_calendar') }}</button>
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