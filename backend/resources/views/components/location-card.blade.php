{{-- resources/views/components/location-card.blade.php --}}
@props(['location'])

@php
    /** @var \App\Models\Location $location */

    // ✅ не дублируем slug в blade, если добавишь аксессор в модели:
    // public_url / public_slug (как обсуждали)
    $href = $location->public_url
        ?? route('locations.show', [
            'location' => $location->id,
            'slug' => (\Illuminate\Support\Str::slug((string) $location->name, '-') ?: 'location'),
        ]);

    $thumb = $location->getFirstMediaUrl('photos', 'thumb');
    if (empty($thumb)) $thumb = $location->getFirstMediaUrl('photos');

    $cityName = $location->city?->name;
    $address  = (string) ($location->address ?? '');
    $title    = (string) ($location->name ?? 'Локация');
@endphp

<a href="{{ $href }}"
   class="group block bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md hover:border-gray-200 transition">
    <div class="aspect-[16/10] bg-gray-100">
        @if(!empty($thumb))
            <img src="{{ $thumb }}"
                 alt="{{ $title }}"
                 loading="lazy"
                 class="w-full h-full object-cover transition duration-200 group-hover:scale-[1.01]">
        @else
            <div class="w-full h-full flex items-center justify-center text-sm text-gray-400">
                Нет фото
            </div>
        @endif
    </div>

    <div class="p-4">
        <div class="font-semibold text-gray-900 leading-snug">
            {{ $title }}
        </div>

        @if(!empty($cityName) || $address !== '')
            <div class="mt-1 text-sm text-gray-600">
                @if(!empty($cityName))
                    <span class="inline-flex items-center gap-1">
                        <span class="text-gray-400">📍</span>
                        <span>{{ $cityName }}</span>
                    </span>
                @endif

                @if($address !== '')
                    @if(!empty($cityName)) <span class="mx-2 text-gray-300">•</span> @endif
                    <span class="line-clamp-1">{{ $address }}</span>
                @endif
            </div>
        @endif

        @if(!empty($location->short_text))
            <div class="mt-2 text-sm text-gray-700 line-clamp-2">
                {{ $location->short_text }}
            </div>
        @endif
    </div>
</a>