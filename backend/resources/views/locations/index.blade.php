<x-app-layout>
    <x-slot name="header">
        @php
            $base   = request()->except('page');
            $mode   = $viewMode ?? 'cards';
            $active = (int)($activeOnly ?? 0);
        @endphp

        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Локации</h2>

            <div class="flex items-center gap-2">
                {{-- active filter --}}
                <a href="{{ request()->fullUrlWithQuery(array_merge($base, ['active' => $active ? 0 : 1])) }}"
                   class="px-3 py-1.5 rounded-xl text-sm border {{ $active ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200' }}">
                    {{ $active ? 'Только действующие: ON' : 'Только действующие: OFF' }}
                </a>

                {{-- view switch --}}
                <a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'rows'])) }}"
                   class="px-3 py-1.5 rounded-xl text-sm border {{ $mode==='rows' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200' }}">
                    Список
                </a>
                <a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'cards'])) }}"
                   class="px-3 py-1.5 rounded-xl text-sm border {{ $mode==='cards' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200' }}">
                    Карточки
                </a>
                <a href="{{ request()->fullUrlWithQuery(array_merge($base, ['view' => 'map'])) }}"
                   class="px-3 py-1.5 rounded-xl text-sm border {{ $mode==='map' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200' }}">
                    Карта
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 py-10">
        <div class="text-sm text-gray-600 mb-6">
            Доступные локации (фото, адрес, карта).
        </div>

        @php
            /** @var \Illuminate\Pagination\LengthAwarePaginator|\Illuminate\Contracts\Pagination\Paginator|null $cities */
            $cities = $cities ?? null;

            // Соберём точки для карты из текущей страницы городов (10 городов)
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
                            ];
                        }
                    }
                }
            }
        @endphp

        {{-- ===== rows mode ===== --}}
        @if($mode === 'rows')
            @if(!$cities || $cities->isEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 p-6 text-sm text-gray-500">
                    Пока нет локаций.
                </div>
            @else
                <div class="space-y-6">
                    @foreach($cities as $city)
                        @php $items = $city->locations ?? collect(); @endphp

                        <div class="bg-white rounded-2xl border border-gray-100 p-5">
                            <div class="flex items-center justify-between mb-3">
                                <div class="font-semibold text-gray-900">
                                    {{ $city->name }}
                                </div>
                                <div class="text-xs text-gray-400">{{ $items->count() }}</div>
                            </div>

                            <div class="divide-y divide-gray-100">
                                @foreach($items as $loc)
                                    <div class="py-3">
                                        <a class="text-indigo-600 hover:underline"
                                           href="{{ route('locations.show', ['location' => $loc->id, 'slug' => \Illuminate\Support\Str::slug($loc->name)]) }}">
                                            {{ $loc->name }}
                                        </a>
                                        @if($loc->address)
                                            <div class="text-sm text-gray-500">{{ $loc->address }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-8">{{ $cities->links() }}</div>
                </div>
            @endif
        @endif

        {{-- ===== cards mode ===== --}}
        @if($mode === 'cards')
            @if(!$cities || $cities->isEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 p-6 text-sm text-gray-500">
                    Пока нет локаций.
                </div>
            @else
                <div class="space-y-8">
                    @foreach($cities as $city)
                        @php $items = $city->locations ?? collect(); @endphp

                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div class="font-semibold text-gray-900">{{ $city->name }}</div>
                                <div class="text-xs text-gray-400">{{ $items->count() }}</div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                                @foreach($items as $loc)
                                    <x-location-card :location="$loc" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">{{ $cities->links() }}</div>
            @endif
        @endif

        {{-- ===== map mode (Яндекс) ===== --}}
        @if($mode === 'map')
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <div class="text-sm text-gray-600 mb-3">
                    Карта локаций (точки берём из текущей страницы городов).
                </div>

                <div id="ymap" style="height: 560px; width: 100%;" class="rounded-xl overflow-hidden"></div>

                <script>
                    window.__LOC_POINTS__ = @json($points);
                </script>

                <script src="https://api-maps.yandex.ru/2.1/?apikey={{ config('services.yandex_maps.key') }}&lang=ru_RU"></script>
                <script>
                    (function () {
                        function init() {
                            const pts = Array.isArray(window.__LOC_POINTS__) ? window.__LOC_POINTS__ : [];
                            const hasPts = pts.length > 0;
                            const center = hasPts ? [pts[0].lat, pts[0].lng] : [55.751244, 37.618423];

                            const map = new ymaps.Map("ymap", {
                                center,
                                zoom: hasPts ? 11 : 4,
                                controls: ['zoomControl', 'fullscreenControl']
                            });

                            if (!hasPts) return;

                            const geoObjects = [];
                            for (const p of pts) {
                                const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
                                      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
                                    }[c]));
                                    
                                    const hint = (p.city ? (p.city + ': ') : '') + p.name;
                                    
                                    const balloon = `
                                      <div style="max-width:260px">
                                        <div style="font-weight:600;margin-bottom:4px">${esc(p.name)}</div>
                                        ${p.city ? `<div style="color:#666;font-size:12px">${esc(p.city)}</div>` : ``}
                                        ${p.address ? `<div style="color:#666;font-size:12px;margin-top:4px">${esc(p.address)}</div>` : ``}
                                      </div>
                                    `;
                                const placemark = new ymaps.Placemark([p.lat, p.lng], {
                                    hintContent: hint,
                                    balloonContent: balloon
                                }, { preset: 'islands#redDotIcon' });

                                geoObjects.push(placemark);
                                map.geoObjects.add(placemark);
                            }

                            const bounds = ymaps.geoQuery(geoObjects).getBounds();
                            if (bounds) map.setBounds(bounds, { checkZoomRange: true, zoomMargin: 40 });
                        }
                        ymaps.ready(init);
                    })();
                </script>

                <div class="mt-6">
                    {{ $cities ? $cities->links() : '' }}
                </div>
            </div>
        @endif
    </div>
</x-app-layout>