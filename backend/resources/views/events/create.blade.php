{{-- resources/views/events/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Создать мероприятие
            </h2>
            <a href="{{ route('events.index') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                ← К мероприятиям
            </a>
        </div>
    </x-slot>

    {{-- FLASH --}}
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 mt-6">
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
    </div>

    @php
        $formats = [
            'free_play' => 'Свободные игры (организатор — зал/центр)',
            'game' => 'Игра',
            'training' => 'Тренировка',
            'training_game' => 'Тренировка + Игра',
            'coach_student' => 'Тренер + ученик (только пляж)',
            'tournament' => 'Турнир',
            'camp' => 'КЕМП',
        ];

        $timezones = [
            'Europe/Moscow', 'Europe/Berlin', 'Europe/Kyiv',
            'Asia/Dubai', 'Asia/Almaty',
            'UTC',
        ];
    @endphp

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <form method="POST" action="{{ route('events.store') }}">
                    @csrf

                    {{-- Admin organizer --}}
                    @if(!empty($canChooseOrganizer))
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Organizer (для admin)</label>
                            <select name="organizer_id" class="w-full rounded-lg border-gray-200">
                                <option value="">— выбрать organizer —</option>
                                @foreach($organizers as $org)
                                    <option value="{{ $org->id }}" @selected(old('organizer_id') == $org->id)>
                                        #{{ $org->id }} — {{ $org->name ?? $org->email }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="text-xs text-gray-500 mt-1">
                                Admin обязан выбрать organizer — событие будет привязано к нему.
                            </div>
                        </div>
                    @else
                        <div class="mb-5 p-3 rounded-lg bg-gray-50 border border-gray-100 text-sm text-gray-700">
                            <div class="font-semibold">Создание</div>
                            <div class="mt-1">
                                {{ $resolvedOrganizerLabel ?? '—' }}
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Название</label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                   class="w-full rounded-lg border-gray-200" placeholder="Напр. Вечерняя игра 6х6">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Направление</label>
                            <select name="direction" id="direction" class="w-full rounded-lg border-gray-200">
                                <option value="classic" @selected(old('direction','classic')==='classic')>Классический волейбол</option>
                                <option value="beach" @selected(old('direction')==='beach')>Пляжный волейбол</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Формат</label>
                            <select name="format" id="format" class="w-full rounded-lg border-gray-200">
                                @foreach($formats as $k => $label)
                                    <option value="{{ $k }}" @selected(old('format')===$k)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="text-xs text-gray-500 mt-1">
                                “Тренер + ученик” доступен только при “Пляжный волейбол”.
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Часовой пояс</label>
                            <select name="timezone" class="w-full rounded-lg border-gray-200">
                                @foreach($timezones as $tz)
                                    <option value="{{ $tz }}" @selected(old('timezone', 'Europe/Moscow')===$tz)>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Локация</label>
                            <select name="location_id" id="location_id" class="w-full rounded-lg border-gray-200">
                                <option value="">— выбрать —</option>
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}" @selected((int)old('location_id')===(int)$loc->id)>
                                        {{ $loc->name }}@if(!empty($loc->address)) — {{ $loc->address }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Начало (локальное)</label>
                            <input type="datetime-local" name="starts_at_local" value="{{ old('starts_at_local') }}"
                                   class="w-full rounded-lg border-gray-200">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Окончание (локальное)</label>
                            <input type="datetime-local" name="ends_at_local" value="{{ old('ends_at_local') }}"
                                   class="w-full rounded-lg border-gray-200">
                            <div class="text-xs text-gray-500 mt-1">Можно оставить пустым.</div>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl border border-gray-100 bg-white">
                            <label class="flex items-center gap-3">
                                <input type="hidden" name="is_private" value="0">
                                <input type="checkbox" name="is_private" value="1" @checked(old('is_private'))>
                                <span class="text-sm font-semibold">Приватное (доступно только по ссылке)</span>
                            </label>
                            <div class="text-xs text-gray-500 mt-2">
                                В БД будет сгенерирован токен ссылки (public_token) для приватного.
                            </div>
                        </div>

                        <div class="p-4 rounded-xl border border-gray-100 bg-white">
                            <label class="flex items-center gap-3">
                                <input type="hidden" name="allow_registration" value="0">
                                <input type="checkbox" name="allow_registration" value="1" @checked(old('allow_registration'))>
                                <span class="text-sm font-semibold">Разрешить запись игроков</span>
                            </label>
                            <div class="text-xs text-gray-500 mt-2">
                                Если выключено — это “рекламное/информативное” событие без регистрации.
                            </div>
                        </div>

                        <div class="p-4 rounded-xl border border-gray-100 bg-white">
                            <label class="flex items-center gap-3">
                                <input type="hidden" name="is_paid" value="0">
                                <input type="checkbox" name="is_paid" value="1" @checked(old('is_paid'))>
                                <span class="text-sm font-semibold">Платное</span>
                            </label>
                            <div class="mt-3">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Цена / условия</label>
                                <input type="text" name="price_text" value="{{ old('price_text') }}"
                                       class="w-full rounded-lg border-gray-200" placeholder="Напр. 1200₽/чел или по абонементу">
                            </div>
                        </div>

                        <div class="p-4 rounded-xl border border-gray-100 bg-white">
                            <label class="flex items-center gap-3">
                                <input type="hidden" name="is_recurring" value="0">
                                <input type="checkbox" name="is_recurring" value="1" @checked(old('is_recurring'))>
                                <span class="text-sm font-semibold">Повторяющееся</span>
                            </label>
                            <div class="mt-3">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">recurrence_rule</label>
                                <textarea name="recurrence_rule" rows="2"
                                          class="w-full rounded-lg border-gray-200"
                                          placeholder="Напр. RRULE:FREQ=WEEKLY;BYDAY=MO,WE;BYHOUR=19;BYMINUTE=0">{{ old('recurrence_rule') }}</textarea>
                                <div class="text-xs text-gray-500 mt-1">
                                    Можно хранить как iCal RRULE строку или ваш формат — это просто text.
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- старые поля (не обязательно, но оставим чтобы не терять функционал) --}}
                    <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-gray-50">
                        <div class="font-semibold text-sm text-gray-800">Доп. настройки (существующие поля)</div>

                        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="flex items-center gap-3">
                                <input type="hidden" name="requires_personal_data" value="0">
                                <input type="checkbox" name="requires_personal_data" value="1" @checked(old('requires_personal_data'))>
                                <span class="text-sm font-semibold">Требовать персональные данные</span>
                            </label>

                            <div class="flex gap-3">
                                <div class="w-1/2">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">classic_level_min</label>
                                    <input type="number" name="classic_level_min" value="{{ old('classic_level_min') }}"
                                           class="w-full rounded-lg border-gray-200" min="0" max="10">
                                </div>
                                <div class="w-1/2">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">beach_level_min</label>
                                    <input type="number" name="beach_level_min" value="{{ old('beach_level_min') }}"
                                           class="w-full rounded-lg border-gray-200" min="0" max="10">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Quick create location --}}
                    <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-white">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-semibold text-sm text-gray-800">Быстро добавить локацию</div>
                            <div class="text-xs text-gray-500">создастся и появится в списке</div>
                        </div>

                        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <input type="text" id="loc_name" class="rounded-lg border-gray-200" placeholder="Название (напр. Volley Arena)">
                            <input type="text" id="loc_address" class="rounded-lg border-gray-200" placeholder="Адрес (необязательно)">
                        </div>

                        <div class="mt-3 flex items-center gap-3">
                            <button type="button" id="loc_create_btn"
                                    class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                                Создать локацию
                            </button>
                            <div class="text-xs text-gray-500" id="loc_create_hint"></div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="submit" class="v-btn v-btn--primary">
                            Создать
                        </button>
                        <a href="{{ route('events.index') }}" class="v-btn v-btn--secondary">
                            Отмена
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const dirEl = document.getElementById('direction');
            const fmtEl = document.getElementById('format');

            function enforceCoachStudentRule() {
                const direction = dirEl.value;
                const format = fmtEl.value;
                // Если classic и выбран coach_student — переключим на training
                if (direction !== 'beach' && format === 'coach_student') {
                    fmtEl.value = 'training';
                }
            }

            dirEl?.addEventListener('change', enforceCoachStudentRule);
            fmtEl?.addEventListener('change', enforceCoachStudentRule);

            const btn = document.getElementById('loc_create_btn');
            const hint = document.getElementById('loc_create_hint');
            const nameEl = document.getElementById('loc_name');
            const addrEl = document.getElementById('loc_address');
            const selectEl = document.getElementById('location_id');

            btn?.addEventListener('click', async function () {
                const name = (nameEl.value || '').trim();
                const address = (addrEl.value || '').trim();

                if (!name) {
                    hint.textContent = 'Введите название локации.';
                    return;
                }

                hint.textContent = 'Создаю...';

                try {
                    const res = await fetch(@json(route('locations.quick_store')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                        },
                        body: JSON.stringify({ name, address }),
                    });

                    const json = await res.json();
                    if (!json.ok) {
                        hint.textContent = json.message || 'Ошибка создания локации';
                        return;
                    }

                    const id = json.data?.id;
                    const label = json.data?.name || name;

                    const opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = address ? `${label} — ${address}` : label;
                    opt.selected = true;

                    selectEl.appendChild(opt);

                    nameEl.value = '';
                    addrEl.value = '';
                    hint.textContent = 'Локация создана ✅';
                } catch (e) {
                    hint.textContent = 'Ошибка AJAX. Попробуйте ещё раз.';
                }
            });
        })();
    </script>
</x-app-layout>
