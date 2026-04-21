{{-- resources/views/components/location-card.blade.php --}}
@props(['location'])

@php
/** @var \App\Models\Location $location */
$href = $location->public_url
?? route('locations.show', [
'location' => $location->id,
'slug' => (\Illuminate\Support\Str::slug((string) $location->name, '-') ?: 'location'),
]);

$thumb    = $location->getFirstMediaUrl('photos', 'thumb');
if (empty($thumb)) $thumb = $location->getFirstMediaUrl('photos');

$cityName = $location->city?->name ?? '';
$address  = (string)($location->address ?? '');
$title    = (string)($location->name ?? 'Локация');
@endphp


<div class="card location-card">
<div>
		<a href="{{ $href }}" class="card-image">
			{{-- Фото --}}
			@if(!empty($thumb))
            <img src="{{ $thumb }}" alt="{{ $title }}" loading="lazy" class="location-thumb">
			@else
            <div class="icon-nophoto"></div>
			@endif
			
			{{-- Контент --}}
		</a>
		<a class="blink b-600" href="{{ $href }}">{{ $title }}</a>
		
		@if($cityName || $address)
		<div class="mt-1 f-16">
			@if($cityName)
			{{-- $cityName --}}
			@endif
			@if($address)
			<div>{{ $address }}</div>
			@endif
		</div>
		@endif
</div>		
		@if(!empty($location->short_text))
		<div class="location-card-footer">
			{{ $location->short_text }}
		</div>
		@endif
	</div>
