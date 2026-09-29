{{-- Погода на время мероприятия (только кеш, см. WeatherService). Ожидает $occ. --}}
@php $__w = app(\App\Services\WeatherService::class)->forOccurrence($occ); @endphp
@if($__w)
<div class="event-weather" title="{{ __('events.weather_title') }}: {{ __('events.weather_precip') }} {{ $__w['pop'] }}%">
	<span class="event-weather-icon">{{ $__w['icon'] }}</span>
	<span>{{ $__w['temp'] }}</span>
	@if($__w['pop'] >= 10)<span class="event-weather-pop">💧{{ $__w['pop'] }}%</span>@endif
</div>
@endif
