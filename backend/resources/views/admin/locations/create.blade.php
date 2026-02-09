<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Создать локацию (admin)</h2>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 py-10">
        @if (session('status'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100 text-sm">
                <div class="font-semibold mb-2">Ошибки:</div>
                <ul class="list-disc ml-5 space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <form method="POST" action="{{ route('admin.locations.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Название</label>
                        <input name="name" class="w-full rounded-lg border-gray-200" value="{{ old('name') }}" required>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Адрес</label>
                        <input name="address" class="w-full rounded-lg border-gray-200" value="{{ old('address') }}">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Город</label>

                        {{-- CITY autocomplete, сохраняем в locations.city (строка) --}}
                        <div class="relative" id="loc-city-autocomplete" data-search-url="{{ route('cities.search') }}">
                            <input
                                type="text"
                                id="loc_city"
                                name="city"
                                class="w-full rounded-lg border-gray-200 @error('city') ring-2 ring-red-500 border-red-500 @enderror"
                                value="{{ old('city', $location->city ?? '') }}"
                                placeholder="Начните вводить город…"
                                autocomplete="off"
                            >

                            <div
                                id="loc_city_dropdown"
                                class="absolute left-0 right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden hidden"
                                style="max-height: 28rem; overflow-y: auto; z-index: 60;"
                            >
                                <div class="px-3 py-2 text-xs text-gray-500 border-b border-gray-100">
                                    Введите минимум 2 символа — выберите город из списка.
                                </div>
                                <div id="loc_city_results"></div>
                            </div>
                        </div>

                        @error('city')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Timezone</label>
                        <input name="timezone" class="w-full rounded-lg border-gray-200"
                               value="{{ old('timezone', 'Europe/Berlin') }}" required>
                        <div class="text-xs text-gray-500 mt-1">IANA timezone, напр. Europe/Moscow</div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Short text</label>
                        <input name="short_text" class="w-full rounded-lg border-gray-200" value="{{ old('short_text') }}">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Long text</label>
                        <textarea name="long_text" rows="4" class="w-full rounded-lg border-gray-200">{{ old('long_text') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">lat</label>
                        <input name="lat" type="number" step="any" class="w-full rounded-lg border-gray-200" value="{{ old('lat') }}">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">lng</label>
                        <input name="lng" type="number" step="any" class="w-full rounded-lg border-gray-200" value="{{ old('lng') }}">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Фото локации (до 5)</label>
                        <input type="file" name="photos[]" multiple accept="image/*" class="w-full rounded-lg border-gray-200">
                        <div class="text-xs text-gray-500 mt-1">jpg/jpeg/png/webp, до 5MB каждое, максимум 5 файлов</div>
                    </div>
                </div>

                <div class="mt-6">
                    <button class="v-btn v-btn--primary" type="submit">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const wrap = document.getElementById('loc-city-autocomplete');
            const input = document.getElementById('loc_city');
            const dd = document.getElementById('loc_city_dropdown');
            const results = document.getElementById('loc_city_results');
            if (!wrap || !input || !dd || !results) return;

            function escapeHtml(s) {
                return String(s || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }
            function show() { dd.classList.remove('hidden'); }
            function hide() { dd.classList.add('hidden'); }
            function clear() { results.innerHTML = ''; }

            function groupByCountry(list) {
                const g = { RU: [], KZ: [], UZ: [], OTHER: [] };
                (list || []).forEach(x => {
                    const cc = (x.country_code || '').toUpperCase();
                    if (cc === 'RU') g.RU.push(x);
                    else if (cc === 'KZ') g.KZ.push(x);
                    else if (cc === 'UZ') g.UZ.push(x);
                    else g.OTHER.push(x);
                });
                return g;
            }

            function renderGroup(title, items) {
                let html = '';
                html += '<div class="px-3 py-2 text-xs font-semibold text-gray-700 bg-gray-50 border-b border-gray-100">' + escapeHtml(title) + '</div>';
                items.forEach(item => {
                    const label = item.name + (item.region ? ' (' + item.region + ')' : '');
                    html +=
                        '<button type="button" class="w-full text-left px-3 py-2 hover:bg-gray-50 border-b border-gray-100 loc-city-item" ' +
                        'data-label="' + escapeHtml(label) + '" data-name="' + escapeHtml(item.name) + '">' +
                            '<div class="text-sm text-gray-900">' + escapeHtml(item.name) + '</div>' +
                            '<div class="text-xs text-gray-500">' +
                                (item.country_code ? escapeHtml(item.country_code) : '') +
                                (item.region ? ' • ' + escapeHtml(item.region) : '') +
                            '</div>' +
                        '</button>';
                });
                return html;
            }

            let lastReqId = 0;
            function debounce(fn, ms) {
                let t = null;
                return function (...args) {
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(this, args), ms);
                };
            }

            async function fetchCities(q) {
                const url = wrap.getAttribute('data-search-url');
                if (!url) return null;

                const reqId = ++lastReqId;
                const u = new URL(url, window.location.origin);
                u.searchParams.set('q', q || '');
                u.searchParams.set('limit', '30');

                const r = await fetch(u.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });

                if (reqId !== lastReqId) return null;
                if (!r.ok) return null;

                return await r.json();
            }

            const run = debounce(async () => {
                const q = (input.value || '').trim();
                if (q.length === 0) { clear(); hide(); return; }

                if (q.length < 2) {
                    show();
                    results.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Введите ещё символы…</div>';
                    return;
                }

                show();
                results.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Поиск…</div>';

                const data = await fetchCities(q);
                const items = Array.isArray(data) ? data : (data && data.items ? data.items : []);

                if (!items.length) {
                    results.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Ничего не найдено.</div>';
                    return;
                }

                const g = groupByCountry(items);
                let html = '';
                if (g.RU.length) html += renderGroup('RU', g.RU);
                if (g.KZ.length) html += renderGroup('KZ', g.KZ);
                if (g.UZ.length) html += renderGroup('UZ', g.UZ);
                if (g.OTHER.length) html += renderGroup('Другие', g.OTHER);

                results.innerHTML = html;

                results.querySelectorAll('.loc-city-item').forEach(btn => {
                    btn.addEventListener('click', () => {
                        // В locations.city кладём только name (без региона)
                        input.value = btn.getAttribute('data-name') || '';
                        hide();
                    });
                });
            }, 220);

            input.addEventListener('input', run);
            input.addEventListener('focus', () => {
                const q = (input.value || '').trim();
                if (q.length >= 2) run();
            });

            document.addEventListener('click', (e) => {
                if (!wrap.contains(e.target)) hide();
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') hide();
            });
        })();
    </script>
</x-app-layout>
