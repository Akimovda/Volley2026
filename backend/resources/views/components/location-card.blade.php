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

<a href="{{ $href }}" class="location-card-link">
    <div class="card">
        {{-- Фото --}}
        @if(!empty($thumb))
            <img src="{{ $thumb }}" alt="{{ $title }}" loading="lazy" class="location-thumb">
        @else
            <div class="location-nophoto">📷</div>
        @endif

        {{-- Контент --}}
        <div class="location-card-body">
            <div class="b-600 f-18">{{ $title }}</div>

            @if($cityName || $address)
                <div class="f-16 mt-05" style="opacity:.7">
                    @if($cityName)
                        📍 {{ $cityName }}
                    @endif
                    @if($cityName && $address)
                        <span class="mx-1">·</span>
                    @endif
                    @if($address)
                        <span>{{ $address }}</span>
                    @endif
                </div>
            @endif

            @if(!empty($location->short_text))
                <div class="f-16 mt-1" style="opacity:.6; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                    {{ $location->short_text }}
                </div>
            @endif
        </div>
    </div>
</a>