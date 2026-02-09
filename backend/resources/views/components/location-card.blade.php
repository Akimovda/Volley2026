@props([
    'location',
])

@php
    /** @var \App\Models\Location $location */
    $slug = \Illuminate\Support\Str::slug((string)$location->name, '-') ?: 'location';

    $thumb = $location->getFirstMediaUrl('photos', 'thumb');
    if (empty($thumb)) {
        // если конверсии 'thumb' нет — берём оригинал
        $thumb = $location->getFirstMediaUrl('photos');
    }

    $href = route('locations.show', ['location' => $location->id, 'slug' => $slug]);
@endphp

<a href="{{ $href }}" class="block bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
    <div class="aspect-[16/10] bg-gray-100">
        @if(!empty($thumb))
            <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover">
        @else
            <div class="w-full h-full flex items-center justify-center text-sm text-gray-400">
                Нет фото
            </div>
        @endif
    </div>

    <div class="p-4">
        <div class="font-semibold text-gray-900">
            {{ $location->name }}
        </div>

        <div class="mt-1 text-sm text-gray-600">
            @if(!empty($location->city))
                <span>{{ $location->city }}</span>
            @endif
            @if(!empty($location->address))
                <span class="text-gray-400">•</span>
                <span>{{ $location->address }}</span>
            @endif
        </div>

        @if(!empty($location->short_text))
            <div class="mt-2 text-sm text-gray-700 line-clamp-2">
                {{ $location->short_text }}
            </div>
        @endif
    </div>
</a>
