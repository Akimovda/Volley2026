{{-- resources/views/admin/locations/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Редактировать локацию (admin)
            </h2>

            <div class="flex gap-2">
                <a href="{{ route('admin.locations.index') }}" class="v-btn v-btn--secondary">← К списку</a>

                <form method="POST"
                      action="{{ route('admin.locations.destroy', $location) }}"
                      onsubmit="return confirm('Удалить локацию и все её фото?')">
                    @csrf
                    @method('DELETE')
                    <button class="v-btn v-btn--danger" type="submit">Удалить</button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 py-10">
        {{-- FLASH --}}
        @if (session('status'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">
                {{ session('error') }}
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

        {{-- =========================
            SECTION: MAIN FORM
        ========================== --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <form method="POST"
                  action="{{ route('admin.locations.update', $location) }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Name --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Название</label>
                        <input name="name"
                               class="w-full rounded-lg border-gray-200"
                               value="{{ old('name', $location->name) }}"
                               required>
                    </div>

                    {{-- Address --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Адрес</label>
                        <input name="address"
                               class="w-full rounded-lg border-gray-200"
                               value="{{ old('address', $location->address) }}">
                    </div>

                    {{-- City --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Город</label>
                        <input name="city"
                               class="w-full rounded-lg border-gray-200"
                               value="{{ old('city', $location->city) }}">
                    </div>

                    {{-- Timezone --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Timezone</label>
                        <input name="timezone"
                               class="w-full rounded-lg border-gray-200"
                               value="{{ old('timezone', $location->timezone ?? 'Europe/Berlin') }}"
                               required>
                    </div>

                    {{-- Short text --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Short text</label>
                        <input name="short_text"
                               class="w-full rounded-lg border-gray-200"
                               value="{{ old('short_text', $location->short_text) }}">
                    </div>

                    {{-- Long text --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Long text</label>
                        <textarea name="long_text"
                                  rows="4"
                                  class="w-full rounded-lg border-gray-200">{{ old('long_text', $location->long_text) }}</textarea>
                    </div>

                    {{-- Lat --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">lat</label>
                        <input name="lat"
                               type="number"
                               step="any"
                               class="w-full rounded-lg border-gray-200"
                               value="{{ old('lat', $location->lat) }}">
                    </div>

                    {{-- Lng --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">lng</label>
                        <input name="lng"
                               type="number"
                               step="any"
                               class="w-full rounded-lg border-gray-200"
                               value="{{ old('lng', $location->lng) }}">
                    </div>

                    {{-- Upload photos --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Добавить фото (до 5)</label>
                        <input name="photos[]"
                               type="file"
                               multiple
                               accept="image/*"
                               class="w-full rounded-lg border-gray-200">
                        <div class="text-xs text-gray-500 mt-1">
                            Если загрузить больше 5 — останутся последние 5.
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button class="v-btn v-btn--primary" type="submit">Сохранить</button>
                </div>
            </form>
        </div>

        {{-- =========================
            SECTION: PHOTOS (D&D SORT)
            - ВАЖНО: без вложенных form (HTML запрещает form внутри form)
            - Сохранение порядка: AJAX POST на admin.locations.photos.reorder
        ========================== --}}
        <div class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between gap-3">
                <div class="font-semibold text-gray-800">Фото (drag & drop)</div>

                @if(!$photos->isEmpty())
                    <button type="button" id="photos_save_btn" class="v-btn v-btn--secondary">
                        Сохранить порядок
                    </button>
                @endif
            </div>

            <div class="text-xs text-gray-500 mt-2" id="photos_hint">
                @if($photos->isEmpty())
                    Фото пока нет.
                @else
                    Перетащи карточки мышкой и нажми «Сохранить порядок».
                @endif
            </div>

            @if(!$photos->isEmpty())
                <div id="photos_grid" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($photos as $m)
                        <div class="border rounded-xl p-2 bg-white cursor-move"
                             data-photo-id="{{ $m->id }}">
                            {{-- Если у вас нет conversion "thumb", используй getUrl() --}}
                            <img src="{{ $m->getUrl() }}"
                                 class="w-full h-32 object-cover rounded-lg"
                                 alt="">

                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span class="text-xs text-gray-500">#{{ $m->id }}</span>

                                <form method="POST"
                                      action="{{ route('admin.locations.photos.destroy', [$location, $m]) }}"
                                      onsubmit="return confirm('Удалить фото?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">
                                        Удалить
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- =========================
            SECTION: SCRIPTS
            - SortableJS
            - AJAX reorder
        ========================== --}}
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
        <script>
            (function () {
                const grid = document.getElementById('photos_grid');
                if (!grid) return;

                const hint = document.getElementById('photos_hint');
                const saveBtn = document.getElementById('photos_save_btn');

                const reorderUrl = @json(route('admin.locations.photos.reorder', $location));
                const csrf = @json(csrf_token());

                function currentOrderIds() {
                    return Array.from(grid.querySelectorAll('[data-photo-id]'))
                        .map(el => Number(el.getAttribute('data-photo-id')))
                        .filter(n => Number.isFinite(n));
                }

                async function saveOrder() {
                    const photo_ids = currentOrderIds();
                    if (!photo_ids.length) return;

                    if (hint) hint.textContent = 'Сохраняю порядок...';

                    try {
                        const res = await fetch(reorderUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({ photo_ids }),
                        });

                        if (!res.ok) {
                            if (hint) hint.textContent = 'Не удалось сохранить порядок (HTTP ' + res.status + ').';
                            return;
                        }

                        if (hint) hint.textContent = 'Порядок фото сохранён ✅';
                    } catch (e) {
                        if (hint) hint.textContent = 'Ошибка сети при сохранении порядка.';
                    }
                }

                // D&D
                new Sortable(grid, {
                    animation: 150,
                    ghostClass: 'opacity-50',
                });

                // Save button
                saveBtn?.addEventListener('click', saveOrder);
            })();
        </script>
    </div>
</x-app-layout>
