{{-- resources/views/locations/index.blade.php --}}
<x-voll-layout body_class="locations-page">

    <x-slot name="title">Локации для волейбола</x-slot>
    <x-slot name="description">Спортивные площадки и залы для волейбола — фото, адреса, карта и расписание мероприятий</x-slot>
    <x-slot name="canonical">{{ route('locations.index') }}</x-slot>
    <x-slot name="h1">Локации</x-slot>
    <x-slot name="t_description">Площадки и залы для волейбола</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('locations.index') }}" itemprop="item"><span itemprop="name">Локации</span></a>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>

    <x-slot name="d_description">
        @php
            $base   = request()->except('page');
            $mode   = $viewMode ?? 'cards';
            $active = (int)($activeOnly ?? 0);
        @endphp
        <div class="d-flex flex-wrap gap-2 mt-2" data-aos="fade-up" data-aos-delay="200">
            {{-- Фильтр активных --}}
            <a href="{{ request()->fullUrlWithQuery(array_merge($base, ['active' => $active ? 0 : 1, 'page' => 1])) }}"
               class="btn {{ $active ? 'btn' : 'btn-secondary' }}">
                {{ $active ? '✅ Только с событиями' : 'Только с событиями' }}
            </a>

            {{-- Режимы отображения --}}
            <a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'cards', 'page' => 1])) }}"
               class="btn {{ $mode === 'cards' ? '' : 'btn-secondary' }}">
                ▦ Карточки
            </a>
            <a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'rows', 'page' => 1])) }}"
               class="btn {{ $mode === 'rows' ? '' : 'btn-secondary' }}">
                ☰ Список
            </a>
            <a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'map', 'page' => 1])) }}"
               class="btn {{ $mode === 'map' ? '' : 'btn-secondary' }}">
                🗺 Карта
            </a>
        </div>
    </x-slot>

    <x-slot name="style">
        <style>
            .location-thumb {
                width: 100%;
                aspect-ratio: 16/10;
                object-fit: cover;
                border-radius: 0.8rem 0.8rem 0 0;
                display: block;
            }
            .location-nophoto {
                width: 100%;
                aspect-ratio: 16/10;
                background: var(--bg2, #f3f4f6);
                border-radius: 0.8rem 0.8rem 0 0;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--t3, #9ca3af);
                font-size: 1.4rem;
            }
            .location-card-link {
                display: block;
                text-decoration: none;
                color: inherit;
                transition: transform .15s;
            }
            .location-card-link:hover {
                transform: translateY(-2px);
            }
            .location-card-link .card {
                padding: 0;
                overflow: hidden;
            }
            .location-card-body {
                padding: 1.2rem 1.4rem 1.4rem;
            }
        </style>
    </x-slot>

    <div class="container">

        @php
            $cities = $cities ?? null;
            $points = [];
            if ($cities) {
                foreach ($cities as $c) {
                    foreach (($c->locations ?? collect()) as $l) {
                        if (!is_null($l->lat) && !is_null($l->lng)) {
                            $points[] = [
                                'id'      => (int)$l->id,
                                'name'    => (string)$l->name,
                                'address' => (string)($l->address ?? ''),
                                'city'    => (string)($c->name ?? ''),
                                'lat'     => (float)$l->lat,
                                'lng'     => (float)$l->lng,
                                'url'     => route('locations.show', ['location' => $l->id, 'slug' => \Illuminate\Support\Str::slug((string)$l->name, '-') ?: 'location']),
                            ];
                        }
                    }
                }
            }
        @endphp

        @if(!$cities || $cities->isEmpty())
            <div class="ramka">
                <div class="alert alert-info">Локации не найдены.</div>
            </div>
        @endif

        {{-- ===== CARDS MODE ===== --}}
        @if($mode === 'cards' && $cities && $cities->isNotEmpty())
            @foreach($cities as $city)
                @php $items = $city->locations ?? collect(); @endphp
                @if($items->isEmpty()) @continue @endif

                <div class="ramka">
                    <div class="d-flex between fvc mb-2">
                        <h2 class="-mt-05">📍 {{ $city->name }}
                            @if(!empty($city->region))
                                <span class="f-16 b-400" style="opacity:.6">({{ $city->region }})</span>
                            @endif
                        </h2>
                        <span class="f-16" style="opacity:.5">{{ $items->count() }} {{ trans_choice('локация|локации|локаций', $items->count()) }}</span>
                    </div>

                    <div class="row row2">
                        @foreach($items as $loc)
                            <div class="col-sm-6 col-lg-4">
                                <x-location-card :location="$loc" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="ramka">{{ $cities->links() }}</div>
        @endif

        {{-- ===== ROWS MODE ===== --}}
        @if($mode === 'rows' && $cities && $cities->isNotEmpty())
            @foreach($cities as $city)
                @php $items = $city->locations ?? collect(); @endphp
                @if($items->isEmpty()) @continue @endif

                <div class="ramka">
                    <div class="d-flex between fvc mb-2">
                        <h2 class="-mt-05">📍 {{ $city->name }}</h2>
                        <span class="f-16" style="opacity:.5">{{ $items->count() }}</span>
                    </div>

                    <div class="form">
                        <table class="table">
                            <tbody>
                                @foreach($items as $loc)
                                    <tr>
                                        <td style="width:6rem; padding: 0.6rem 0.8rem">
                                            @php
                                                $thumb = $loc->getFirstMediaUrl('photos', 'thumb') ?: $loc->getFirstMediaUrl('photos');
                                            @endphp
                                            @if($thumb)
                                                <img src="{{ $thumb }}" alt="{{ $loc->name }}"
                                                     style="width:5.6rem;height:3.6rem;object-fit:cover;border-radius:0.6rem;display:block">
                                            @else
                                                <div style="width:5.6rem;height:3.6rem;border-radius:0.6rem;background:var(--bg2);display:flex;align-items:center;justify-content:center;font-size:1.2rem;opacity:.4">📷</div>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('locations.show', ['location' => $loc->id, 'slug' => \Illuminate\Support\Str::slug((string)$loc->name, '-') ?: 'location']) }}"
                                               class="b-600 cd">{{ $loc->name }}</a>
                                            @if($loc->address)
                                                <div class="f-16 mt-05" style="opacity:.6">{{ $loc->address }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="ramka">{{ $cities->links() }}</div>
        @endif

        {{-- ===== MAP MODE ===== --}}
        @if($mode === 'map')
            <div class="ramka">
                <h2 class="-mt-05">🗺 Карта локаций</h2>
                <div class="f-16 mb-2" style="opacity:.6">Отображаются локации текущей страницы.</div>

                <div id="ymap" style="height: 56rem; width: 100%; border-radius: 1rem; overflow: hidden;"></div>

                <script>window.__LOC_POINTS__ = @json($points);</script>
                <script src="https://api-maps.yandex.ru/2.1/?apikey={{ config('services.yandex_maps.key') }}&lang=ru_RU"></script>
                <script>
                    (function () {
                        function init() {
                            var pts = Array.isArray(window.__LOC_POINTS__) ? window.__LOC_POINTS__ : [];
                            var hasPts = pts.length > 0;
                            var center = hasPts ? [pts[0].lat, pts[0].lng] : [55.751244, 37.618423];

                            var map = new ymaps.Map("ymap", {
                                center: center,
                                zoom: hasPts ? 11 : 4,
                                controls: ['zoomControl', 'fullscreenControl']
                            });

                            if (!hasPts) return;

                            var geoObjects = [];
                            pts.forEach(function(p) {
                                function esc(s) {
                                    return String(s ?? '').replace(/[&<>"']/g, function(c) {
                                        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
                                    });
                                }
                                var balloon = '<div style="max-width:240px">'
                                    + '<a href="' + esc(p.url) + '" style="font-weight:600;font-size:14px">' + esc(p.name) + '</a>'
                                    + (p.city ? '<div style="color:#666;font-size:12px;margin-top:2px">' + esc(p.city) + '</div>' : '')
                                    + (p.address ? '<div style="color:#666;font-size:12px;margin-top:4px">' + esc(p.address) + '</div>' : '')
                                    + '</div>';

                                var pm = new ymaps.Placemark([p.lat, p.lng], {
                                    hintContent: (p.city ? p.city + ': ' : '') + p.name,
                                    balloonContent: balloon
                                }, { preset: 'islands#redDotIcon' });

                                geoObjects.push(pm);
                                map.geoObjects.add(pm);
                            });

                            var bounds = ymaps.geoQuery(geoObjects).getBounds();
                            if (bounds) map.setBounds(bounds, { checkZoomRange: true, zoomMargin: 40 });
                        }
                        ymaps.ready(init);
                    })();
                </script>

                @if($cities)
                    <div class="mt-2">{{ $cities->links() }}</div>
                @endif
            </div>
        @endif

    </div>

</x-voll-layout>