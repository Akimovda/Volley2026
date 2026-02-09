<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $location->name }}
            </h2>
            <a href="{{ route('locations.index') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                ← К локациям
            </a>
        </div>
    </x-slot>

    @php
        $hasCoords = $location->lat !== null && $location->lng !== null;
        $lat = $location->lat;
        $lng = $location->lng;

        // OSM embed (простая карта)
        $mapUrl = $hasCoords
            ? "https://www.openstreetmap.org/export/embed.html?layer=mapnik&marker={$lat},{$lng}&zoom=16"
            : null;

        $first = $location->getFirstMediaUrl('photos');
    @endphp

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 py-10 space-y-6">
        {{-- HERO --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="aspect-[21/9] bg-gray-100">
                @if(!empty($first))
                    <img src="{{ $first }}" alt="" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-sm text-gray-400">
                        Нет фото
                    </div>
                @endif
            </div>

            <div class="p-6">
                <div class="text-sm text-gray-600">
                    @if(!empty($location->city))
                        <span>{{ $location->city }}</span>
                    @endif
                    @if(!empty($location->address))
                        <span class="text-gray-400">•</span>
                        <span>{{ $location->address }}</span>
                    @endif
                </div>

                @if(!empty($location->short_text))
                    <div class="mt-3 text-gray-800">
                        {{ $location->short_text }}
                    </div>
                @endif

                @if(!empty($location->long_text))
                    <div class="mt-4 text-sm text-gray-700 whitespace-pre-line">
                        {{ $location->long_text }}
                    </div>
                @endif
            </div>
        </div>

        {{-- PHOTOS GRID --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="font-semibold text-gray-800 mb-3">Фото</div>

            @if($photos->isEmpty())
                <div class="text-sm text-gray-500">Фото пока нет.</div>
            @else
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($photos as $m)
                        @php
                            $u = $m->getUrl('thumb');
                            if (empty($u)) $u = $m->getUrl();
                        @endphp
                        <a href="{{ $m->getUrl() }}" target="_blank" class="block">
                            <img src="{{ $u }}" class="w-full h-32 object-cover rounded-xl border border-gray-100" alt="">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- MAP --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="font-semibold text-gray-800 mb-3">Карта</div>

            @if($hasCoords)
                <div class="rounded-2xl overflow-hidden border border-gray-100">
                    <iframe
                        src="{{ $mapUrl }}"
                        class="w-full"
                        style="height: 420px;"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>

                <div class="mt-3 text-xs text-gray-500">
                    Координаты: {{ $lat }}, {{ $lng }}
                </div>
            @else
                <div class="text-sm text-gray-500">
                    Для этой локации не указаны координаты (lat/lng).
                </div>
            @endif
            {{-- =========================
    SECTION: MAP (Yandex)
========================== --}}
@php
    $fullAddress = trim(implode(', ', array_filter([
        $location->city ?? null,
        $location->address ?? null,
    ])));

    $lat = $location->lat;
    $lng = $location->lng;
    $yKey = config('services.yandex_maps.key');
@endphp

<div class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center justify-between">
        <div class="font-semibold text-gray-800">Карта</div>
        @if(!$yKey)
            <div class="text-xs text-red-600">YANDEX_MAPS_API_KEY не задан в .env</div>
        @endif
    </div>

    <div class="mt-3 rounded-2xl overflow-hidden border border-gray-100">
        <div id="yandex_map" style="height: 320px; width: 100%;"></div>
    </div>

    @if($fullAddress)
        <div class="mt-2 text-xs text-gray-500">Адрес: {{ $fullAddress }}</div>
    @endif
</div>

{{-- MAP (Yandex widget, no API key needed) --}}
@php
    $lat = $location->lat;
    $lng = $location->lng;

    $hasCoords = is_numeric($lat) && is_numeric($lng);

    // yandex widget expects ll=lng,lat and pt=lng,lat
    $ll = $hasCoords ? ($lng . ',' . $lat) : '';
    $pt = $hasCoords ? ($lng . ',' . $lat . ',pm2rdm') : '';

    $query = trim(implode(', ', array_filter([
        $location->city ?? null,
        $location->address ?? null,
        $location->name ?? null,
    ])));

    $yandexLink = $hasCoords
        ? ('https://yandex.ru/maps/?pt=' . rawurlencode($lng . ',' . $lat) . '&z=16&l=map')
        : ('https://yandex.ru/maps/?text=' . rawurlencode($query));
@endphp

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-6 flex items-center justify-between gap-3">
        <div class="font-semibold text-gray-800">Карта</div>
        <a href="{{ $yandexLink }}" target="_blank" rel="noopener"
           class="text-sm font-semibold text-blue-600 hover:text-blue-700">
            Открыть в Яндекс.Картах →
        </a>
    </div>

    @if($hasCoords)
        <iframe
            src="https://yandex.ru/map-widget/v1/?ll={{ e($ll) }}&z=16&l=map&pt={{ e($pt) }}"
            class="w-full"
            style="height: 420px;"
            frameborder="0"
            allowfullscreen="true"
            loading="lazy"
        ></iframe>

        <div class="px-6 pb-6 text-xs text-gray-500">
            Координаты: {{ $lat }}, {{ $lng }}
        </div>
    @else
        <div class="px-6 pb-6 text-sm text-gray-600">
            Для карты нужны координаты <code>lat/lng</code>. Сейчас их нет.
            Добавь их в админке — и карта появится.
        </div>
    @endif
</div>

        </div>
    </div>
</x-app-layout>
