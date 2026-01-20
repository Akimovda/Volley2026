{{-- resources/views/profile/complete.blade.php --}}
<x-app-layout>
    {{-- ============================================================
        PROFILE COMPLETE
        - Улучшенная стилистика
        - Валидация/нормализация ФИО (кириллица + автотранслит + формат)
        - Маска телефона +7 (___) ___-__-__ (сохраняем E.164: +7XXXXXXXXXX)
        - Уровни (классика/пляж) = выпадающий список
        - Sticky Save: sidebar (desktop) + bottom bar (mobile)
    ============================================================ --}}

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            {{-- =========================
                HEADER
            ========================== --}}
            <div class="flex items-start justify-between gap-4 mb-6">
                <div class="min-w-0">
                    <h1 class="text-2xl font-extrabold text-gray-900 leading-tight">
                        Профиль игрока
                    </h1>
                    <div class="text-sm text-gray-600 mt-1">
                        Заполни ключевые поля — после первого сохранения часть данных сможет менять только администратор.
                    </div>
                </div>
                <div class="shrink-0 hidden md:flex gap-2">
                    <a class="v-btn v-btn--secondary" href="/events">К мероприятиям</a>
                    <a class="v-btn v-btn--secondary" href="/user/profile">Аккаунт</a>
                </div>
            </div>

            {{-- =========================
                FLASH / STATUS / ERRORS
            ========================== --}}
            @if (session('status'))
                <div class="v-alert v-alert--success mb-4">
                    <div class="v-alert__text">{{ session('status') }}</div>
                </div>
            @endif

            @if (session('error'))
                <div class="v-alert v-alert--warn mb-4">
                    <div class="v-alert__title">Ошибка</div>
                    <div class="v-alert__text">{{ session('error') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="v-alert v-alert--warn mb-4">
                    <div class="v-alert__title">Проверьте поля</div>
                    <div class="v-alert__text">
                        <ul class="list-disc pl-6 mt-2">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- =========================
                REQUIRED KEYS (from requirements)
            ========================== --}}
            @if (!empty($requiredKeys))
                <div class="v-alert v-alert--warn mb-6">
                    <div class="v-alert__title">Перед записью заполните:</div>
                    <div class="v-alert__text">
                        <ul class="list-disc pl-6 mt-2">
                            @foreach ($requiredKeys as $key)
                                <li>
                                    @switch($key)
                                        @case('full_name') Фамилия и имя @break
                                        @case('patronymic') Отчество @break
                                        @case('phone') Телефон @break
                                        @case('city') Город @break
                                        @case('birth_date') Дата рождения @break
                                        @case('gender') Пол @break
                                        @case('height_cm') Рост @break
                                        @case('classic_level') Уровень (классика) @break
                                        @case('beach_level') Уровень (пляж) @break
                                        @default {{ $key }}
                                    @endswitch
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if (!empty($eventId))
                        <div class="v-hint mt-3">
                            После сохранения профиля мы попробуем автоматически записать вас на мероприятие.
                        </div>
                    @endif
                </div>
            @endif

            @php
                /** @var \App\Models\User|null $user */
                $user = $user ?? auth()->user();

                // совместимость (в контроллере могло называться по-разному)
                $hasPendingOrganizerRequest = (bool)($hasPendingOrganizerRequest ?? ($hasPendingRequest ?? false));

                // только admin может редактировать "зафиксированные" поля после заполнения
                $canEditProtected = (bool)($canEditProtected ?? ($user && $user->can('edit-protected-profile-fields')));

                $filled = function ($value) {
                    if (is_null($value)) return false;
                    if (is_string($value)) return trim($value) !== '';
                    return true;
                };

                $lockHint = 'Поле уже заполнено. Изменить может только администратор.';

                // Амплуа/зоны (для дефолтов)
                $posMap = [
                    'setter'   => 'Связующий',
                    'outside'  => 'Доигровщик',
                    'opposite' => 'Диагональный',
                    'middle'   => 'Центральный блокирующий',
                    'libero'   => 'Либеро',
                ];

                // важно: отношения classicPositions/beachZones должны существовать в User модели
                $classicPrimary = optional($user?->classicPositions)->firstWhere('is_primary', true)?->position;
                $classicAll     = optional($user?->classicPositions)->pluck('position')->all() ?? [];

                $beachPrimaryZone = optional($user?->beachZones)->firstWhere('is_primary', true)?->zone;
                $beachModeCurrent = $user?->beach_universal ? 'universal' : (is_null($beachPrimaryZone) ? null : (string)$beachPrimaryZone);

                // уровни (1..7)
                $levels = [1,2,3,4,5,6,7];
            @endphp

            {{-- =========================
                LAYOUT: main + sidebar
            ========================== --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- ============================================================
                    MAIN COLUMN
                ============================================================ --}}
                <div class="lg:col-span-2">

                    <form id="profile-complete-form" method="POST" action="{{ route('profile.extra.update') }}" class="space-y-6">
                        @csrf

                        {{-- =========================
                            PERSONAL DATA
                        ========================== --}}
                        <div class="v-card">
                            <div class="v-card__body space-y-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-lg font-semibold text-gray-900">Персональные данные</div>
                                        <div class="text-sm text-gray-600 mt-1">
                                            Эти поля видны вам, администратору и (где указано) организаторам.
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                    {{-- ---------- Фамилия ---------- --}}
                                    @php $lockedLast = !$canEditProtected && $filled($user?->last_name); @endphp
                                    <div>
                                        <label class="block mb-1 font-medium">Фамилия</label>
                                        <input
                                            name="last_name"
                                            class="v-input w-full {{ $errors->has('last_name') ? 'ring-2 ring-red-500 border-red-500' : '' }}"
                                            value="{{ old('last_name', $user?->last_name) }}"
                                            data-ru-name
                                            autocomplete="family-name"
                                            @disabled($lockedLast)
                                        >
                                        <div class="text-xs text-gray-500 mt-1">
                                            Формат: кириллица, минимум 2 символа, первая буква заглавная (пример: Иванов-Петров).
                                        </div>
                                        @if($lockedLast)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                                        @error('last_name')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                    </div>

                                    {{-- ---------- Имя ---------- --}}
                                    @php $lockedFirst = !$canEditProtected && $filled($user?->first_name); @endphp
                                    <div>
                                        <label class="block mb-1 font-medium">Имя</label>
                                        <input
                                            name="first_name"
                                            class="v-input w-full {{ $errors->has('first_name') ? 'ring-2 ring-red-500 border-red-500' : '' }}"
                                            value="{{ old('first_name', $user?->first_name) }}"
                                            data-ru-name
                                            autocomplete="given-name"
                                            @disabled($lockedFirst)
                                        >
                                        <div class="text-xs text-gray-500 mt-1">
                                            Формат: кириллица, минимум 2 символа, первая буква заглавная (пример: Дмитрий).
                                        </div>
                                        @if($lockedFirst)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                                        @error('first_name')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                    </div>

                                    {{-- ---------- Отчество ---------- --}}
                                    @php $lockedPat = !$canEditProtected && $filled($user?->patronymic); @endphp
                                    <div>
                                        <label class="block mb-1 font-medium">
                                            Отчество <span class="text-xs text-gray-500">(видно вам, админу и организаторам)</span>
                                        </label>
                                        <input
                                            name="patronymic"
                                            class="v-input w-full {{ $errors->has('patronymic') ? 'ring-2 ring-red-500 border-red-500' : '' }}"
                                            value="{{ old('patronymic', $user?->patronymic) }}"
                                            data-ru-name
                                            autocomplete="additional-name"
                                            @disabled($lockedPat)
                                        >
                                        <div class="text-xs text-gray-500 mt-1">
                                            Формат: кириллица, минимум 2 символа, первая буква заглавная (пример: Сергеевич).
                                        </div>
                                        @if($lockedPat)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                                        @error('patronymic')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                    </div>

                                    {{-- ---------- Телефон ---------- --}}
                                    @php $lockedPhone = !$canEditProtected && $filled($user?->phone); @endphp
                                    <div>
                                        <label class="block mb-1 font-medium">
                                            Телефон <span class="text-xs text-gray-500">(видно вам, админу и организаторам)</span>
                                        </label>

                                        {{-- ВИДИМЫЙ: маска --}}
                                        <input
                                            name="phone_masked"
                                            id="phone_masked"
                                            value="{{ old('phone_masked') ?: '' }}"
                                            class="v-input w-full {{ $errors->has('phone') ? 'ring-2 ring-red-500 border-red-500' : '' }}"
                                            placeholder="+7 (___) ___-__-__"
                                            inputmode="tel"
                                            autocomplete="tel"
                                            data-ru-phone-masked
                                            @disabled($lockedPhone)
                                        >

                                        {{-- СКРЫТЫЙ: E.164 --}}
                                        <input
                                            type="hidden"
                                            name="phone"
                                            id="phone_e164"
                                            value="{{ old('phone', $user?->phone) }}"
                                        >

                                        <div class="text-xs text-gray-500 mt-1">
                                            Мы сохраним номер в формате <b>+7XXXXXXXXXX</b> (E.164 для РФ).
                                        </div>

                                        @if($lockedPhone)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                                        @error('phone')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                    </div>

                                    {{-- ---------- Дата рождения ---------- --}}
                                    @php $lockedBirth = !$canEditProtected && $filled($user?->birth_date); @endphp
                                    <div>
                                        <label class="block mb-1 font-medium">Дата рождения</label>
                                        @php
                                            $birthValue = old('birth_date');
                                            if ($birthValue === null) {
                                                $birthValue = $user?->birth_date ? $user->birth_date->format('Y-m-d') : '';
                                            }
                                        @endphp
                                        <input
                                            type="date"
                                            name="birth_date"
                                            class="v-input w-full {{ $errors->has('birth_date') ? 'ring-2 ring-red-500 border-red-500' : '' }}"
                                            value="{{ $birthValue }}"
                                            @disabled($lockedBirth)
                                        >
                                        @if($lockedBirth)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                                        @error('birth_date')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                    </div>

                                    {{-- ---------- Город ---------- --}}
                                    @php $lockedCity = !$canEditProtected && $filled($user?->city_id); @endphp
                                    <div>
                                        <label class="block mb-1 font-medium">Город</label>
                                        <select
                                            name="city_id"
                                            class="v-input w-full {{ $errors->has('city_id') ? 'ring-2 ring-red-500 border-red-500' : '' }}"
                                            @disabled($lockedCity)
                                        >
                                            <option value="">— выберите —</option>
                                            @foreach(($cities ?? []) as $city)
                                                <option value="{{ $city->id }}"
                                                    @selected((string)old('city_id', $user?->city_id) === (string)$city->id)>
                                                    {{ $city->name }}@if($city->region) ({{ $city->region }})@endif
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($lockedCity)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                                        @error('city_id')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                    </div>

                                    {{-- ---------- Пол (НЕ фиксируемый) ---------- --}}
                                    <div>
                                        <label class="block mb-1 font-medium">Пол</label>
                                        <select name="gender" class="v-input w-full {{ $errors->has('gender') ? 'ring-2 ring-red-500 border-red-500' : '' }}">
                                            <option value="">— не указан —</option>
                                            <option value="m" @selected(old('gender', $user?->gender) === 'm')>Мужчина</option>
                                            <option value="f" @selected(old('gender', $user?->gender) === 'f')>Женщина</option>
                                        </select>
                                        <div class="v-hint mt-1">Пол виден всем.</div>
                                        @error('gender')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                    </div>

                                    {{-- ---------- Рост (НЕ фиксируемый) ---------- --}}
                                    <div>
                                        <label class="block mb-1 font-medium">Рост (см)</label>
                                        <input
                                            type="number"
                                            name="height_cm"
                                            min="40"
                                            max="230"
                                            class="v-input w-full {{ $errors->has('height_cm') ? 'ring-2 ring-red-500 border-red-500' : '' }}"
                                            value="{{ old('height_cm', $user?->height_cm) }}"
                                        >
                                        <div class="v-hint mt-1">Допустимый диапазон: 40–230 см. Рост виден всем.</div>
                                        @error('height_cm')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                    </div>

                                </div>
                            </div>
                        </div>

                        {{-- =========================
                            SKILLS
                        ========================== --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            {{-- ---------- Классика ---------- --}}
                            <div class="v-card">
                                <div class="v-card__body space-y-4">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="font-semibold text-gray-900">Классический волейбол</div>
                                            <div class="text-sm text-gray-600">Уровень и амплуа.</div>
                                        </div>
                                    </div>

                                    {{-- Уровень (классика) -> SELECT --}}
                                    @php $lockedClassic = !$canEditProtected && $filled($user?->classic_level); @endphp
                                    <div>
                                        <label class="block mb-1 font-medium">Уровень (классика)</label>
                                        <select
                                            name="classic_level"
                                            class="v-input w-full {{ $errors->has('classic_level') ? 'ring-2 ring-red-500 border-red-500' : '' }}"
                                            @disabled($lockedClassic)
                                        >
                                            <option value="">— выберите —</option>
                                            @foreach($levels as $lvl)
                                                <option value="{{ $lvl }}" @selected((string)old('classic_level', $user?->classic_level) === (string)$lvl)>
                                                    {{ $lvl }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="v-hint mt-1">Шкала: 1–7 (для &lt;18 только 1,2,4).</div>
                                        @if($lockedClassic)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                                        @error('classic_level')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                    </div>

                                    {{-- Амплуа primary --}}
                                    <div>
                                        <label class="block mb-1 font-medium">Какое твое основное амплуа?</label>
                                        <div class="space-y-1">
                                            @foreach($posMap as $key => $label)
                                                <label class="flex items-center gap-2">
                                                    <input
                                                        type="radio"
                                                        name="classic_primary_position"
                                                        value="{{ $key }}"
                                                        @checked(old('classic_primary_position', $classicPrimary) === $key)
                                                    >
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    {{-- Амплуа extras --}}
                                    <div>
                                        <label class="block mb-1 font-medium">
                                            В каком амплуа ты можешь играть ещё?
                                            <span class="text-sm text-gray-500">(можно пропустить)</span>
                                        </label>

                                        @php $primaryNow = old('classic_primary_position', $classicPrimary); @endphp
                                        <div class="space-y-1">
                                            @foreach($posMap as $key => $label)
                                                @php
                                                    $checked  = in_array($key, (array)old('classic_extra_positions', $classicAll), true);
                                                    $disabled = ($primaryNow === $key);
                                                @endphp
                                                <label class="flex items-center gap-2">
                                                    <input
                                                        type="checkbox"
                                                        name="classic_extra_positions[]"
                                                        value="{{ $key }}"
                                                        @checked($checked)
                                                        @disabled($disabled)
                                                    >
                                                    <span>{{ $label }}</span>
                                                    @if($disabled)<span class="text-xs text-gray-500">(основное)</span>@endif
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ---------- Пляж ---------- --}}
                            <div class="v-card">
                                <div class="v-card__body space-y-4">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="font-semibold text-gray-900">Пляжный волейбол</div>
                                            <div class="text-sm text-gray-600">Уровень и зона.</div>
                                        </div>
                                    </div>

                                    {{-- Уровень (пляж) -> SELECT --}}
                                    @php $lockedBeach = !$canEditProtected && $filled($user?->beach_level); @endphp
                                    <div>
                                        <label class="block mb-1 font-medium">Уровень (пляж)</label>
                                        <select
                                            name="beach_level"
                                            class="v-input w-full {{ $errors->has('beach_level') ? 'ring-2 ring-red-500 border-red-500' : '' }}"
                                            @disabled($lockedBeach)
                                        >
                                            <option value="">— выберите —</option>
                                            @foreach($levels as $lvl)
                                                <option value="{{ $lvl }}" @selected((string)old('beach_level', $user?->beach_level) === (string)$lvl)>
                                                    {{ $lvl }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="v-hint mt-1">Шкала: 1–7 (для &lt;18 только 1,2,4).</div>
                                        @if($lockedBeach)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                                        @error('beach_level')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                    </div>

                                    {{-- Beach mode --}}
                                    <div>
                                        <label class="block mb-1 font-medium">В какой зоне вы играете: 2, 4 или вы универсал?</label>

                                        <div class="space-y-1">
                                            <label class="flex items-center gap-2">
                                                <input type="radio" name="beach_mode" value="2"
                                                    @checked(old('beach_mode', $beachModeCurrent) === '2')>
                                                <span>Зона 2</span>
                                            </label>

                                            <label class="flex items-center gap-2">
                                                <input type="radio" name="beach_mode" value="4"
                                                    @checked(old('beach_mode', $beachModeCurrent) === '4')>
                                                <span>Зона 4</span>
                                            </label>

                                            <label class="flex items-center gap-2">
                                                <input type="radio" name="beach_mode" value="universal"
                                                    @checked(old('beach_mode', $beachModeCurrent) === 'universal')>
                                                <span>Универсал</span>
                                            </label>

                                            <div class="v-hint mt-2">
                                                Если выбран “Универсал”, в профиле будут отмечены зоны 2 и 4 и будет пометка “универсальный игрок”.
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        {{-- =========================
                            DESKTOP ACTIONS (inside main)
                        ========================== --}}
                        <div class="hidden lg:flex items-center gap-2">
                            <button type="submit" class="v-btn v-btn--primary">Сохранить</button>
                            <a class="v-btn v-btn--secondary" href="/events">К мероприятиям</a>
                            <a class="v-btn v-btn--secondary" href="/user/profile">Аккаунт</a>
                        </div>

                        <div class="v-hint">
                            Подсказка: если вы введёте ФИО латиницей — мы автоматически переведём в кириллицу и приведём к формату.
                        </div>

                        {{-- =========================
                            MOBILE: sticky bottom save bar
                        ========================== --}}
                        <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50">
                            <div class="bg-white/95 backdrop-blur border-t border-gray-200">
                                <div class="max-w-6xl mx-auto px-4 py-3">
                                    <button type="submit" class="v-btn v-btn--primary w-full" id="save-profile-btn-mobile">
                                        Сохранить
                                    </button>
                                    <div class="text-xs text-gray-500 mt-1 text-center">
                                        Если есть ошибки — подсветим поля красным.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="lg:hidden" style="height:90px;"></div>
                    </form>

                    {{-- =========================
                        ORGANIZER REQUEST
                    ========================== --}}
                    @auth
                        @if (($user->role ?? 'user') === 'user')
                            <div class="v-card mt-8">
                                <div class="v-card__body">
                                    <div class="font-semibold text-lg mb-2">
                                        Хочу стать организатором мероприятий
                                    </div>

                                    <div class="text-sm text-gray-600 mb-4">
                                        Организатор может создавать мероприятия, управлять участниками
                                        и назначать помощников. Заявка рассматривается администратором.
                                    </div>

                                    @if (!empty($hasPendingOrganizerRequest))
                                        <div class="v-alert v-alert--info">
                                            <div class="v-alert__text">
                                                Ваша заявка уже отправлена и ожидает рассмотрения.
                                            </div>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('organizer.request') }}">
                                            @csrf
                                            <div class="mb-3">
                                                <label class="block text-sm font-medium mb-1">
                                                    Комментарий (необязательно)
                                                </label>
                                                <textarea
                                                    name="message"
                                                    rows="3"
                                                    class="v-input w-full"
                                                    placeholder="Например: регулярно организую игры и хочу делать это через Volley"
                                                ></textarea>
                                            </div>

                                            <button type="submit" class="v-btn v-btn--primary">
                                                Отправить заявку
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endauth

                </div>

                {{-- ============================================================
                    SIDEBAR (desktop)
                ============================================================ --}}
                <div class="hidden lg:block">
                    <div class="space-y-4 lg:sticky lg:top-24">
                        <div class="v-card">
                            <div class="v-card__body space-y-3">
                                <div class="text-lg font-semibold text-gray-900">Быстрые действия</div>
                                <button type="submit" form="profile-complete-form" class="v-btn v-btn--primary w-full">
                                    Сохранить
                                </button>
                                <a class="v-btn v-btn--secondary w-full text-center" href="/events">К мероприятиям</a>
                                <a class="v-btn v-btn--secondary w-full text-center" href="/user/profile">Аккаунт</a>
                                <div class="text-xs text-gray-500">
                                    На десктопе блок “Сохранить” закреплён для удобства.
                                </div>
                            </div>
                        </div>

                        <div class="v-card">
                            <div class="v-card__body">
                                <div class="text-sm text-gray-700">
                                    <div class="font-semibold mb-2">Правила ввода</div>
                                    <ul class="list-disc pl-5 space-y-1 text-sm text-gray-600">
                                        <li>Фамилия/Имя/Отчество — кириллица, минимум 2 символа, “С Заглавной”.</li>
                                        <li>Если введёте латиницей — автоматически переведём в кириллицу.</li>
                                        <li>Телефон — формат <b>+7XXXXXXXXXX</b>, в поле показываем маску.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ============================================================
        JS: ФИО (кириллица + автотранслит + формат) + телефон маска/E.164
    ============================================================ --}}
    <script>
        (function () {
            // ------------------------------------------------------------
            // Utilities
            // ------------------------------------------------------------
            function setInvalid(el, isInvalid) {
                if (!el) return;
                if (isInvalid) {
                    el.classList.add('ring-2', 'ring-red-500', 'border-red-500');
                } else {
                    el.classList.remove('ring-2', 'ring-red-500', 'border-red-500');
                }
            }

            // ------------------------------------------------------------
            // Name helpers: translit LAT->CYR + normalize "С Заглавной"
            // ------------------------------------------------------------
            const translitMap = {
                'yo':'ё','zh':'ж','kh':'х','ts':'ц','ch':'ч','sh':'ш','sch':'щ','yu':'ю','ya':'я',
                'a':'а','b':'б','v':'в','g':'г','d':'д','e':'е','z':'з','i':'и','j':'й','k':'к',
                'l':'л','m':'м','n':'н','o':'о','p':'п','r':'р','s':'с','t':'т','u':'у','f':'ф',
                'h':'х','c':'к','y':'ы','w':'в','q':'к','x':'кс'
            };

            function translitLatinToCyr(input) {
                let s = String(input || '').trim();
                if (!s) return s;

                // если нет латиницы — оставляем
                if (!/[A-Za-z]/.test(s)) return s;

                // приводим к нижнему для обработки, но сохраняем разделители
                let out = '';
                let i = 0;
                const lower = s.toLowerCase();

                while (i < lower.length) {
                    // разрешаем дефисы/пробелы/апостроф
                    const ch = lower[i];

                    // пропускаем не-буквенные, оставляем как есть
                    if (!/[a-z]/.test(ch)) {
                        out += s[i]; // оригинальный символ (например '-', пробел)
                        i++;
                        continue;
                    }

                    // сначала пытаемся найти 3,2-символьные сочетания
                    const tri = lower.slice(i, i+3);
                    const bi  = lower.slice(i, i+2);

                    if (translitMap[tri]) { out += translitMap[tri]; i += 3; continue; }
                    if (translitMap[bi])  { out += translitMap[bi];  i += 2; continue; }

                    // одиночная буква
                    if (translitMap[ch]) { out += translitMap[ch]; i += 1; continue; }

                    out += ch;
                    i++;
                }

                return out;
            }

            function normalizeCyrName(input) {
                let s = String(input || '').trim();

                // заменим множественные пробелы
                s = s.replace(/\s+/g, ' ');

                // автотранслит, если латиница
                s = translitLatinToCyr(s);

                // оставляем только кириллицу/пробел/дефис/апостроф
                s = s.replace(/[^А-Яа-яЁё \-']/g, '');

                // "С Заглавной" по частям (через пробел/дефис/апостроф)
                const parts = s.split(/(\s+|-|')/);
                const norm = parts.map(part => {
                    // разделители оставляем
                    if (part === ' ' || part === '-' || part === "'" || /^\s+$/.test(part)) return part;

                    const p = part.toLowerCase();
                    if (!p) return p;
                    return p.charAt(0).toUpperCase() + p.slice(1);
                }).join('');

                return norm;
            }

            function isValidCyrName(value) {
                const v = String(value || '').trim();
                if (!v) return true; // nullable
                if (v.length < 2) return false;
                // только кириллица + пробел/дефис/апостроф
                if (!/^[А-Яа-яЁё \-']+$/.test(v)) return false;
                return true;
            }

            // ------------------------------------------------------------
            // Phone helpers: mask + E.164 RU
            // ------------------------------------------------------------
            function digitsOnly(s) {
                return String(s || '').replace(/\D/g, '');
            }

            function toE164Ru(raw) {
                let d = digitsOnly(raw);

                // 8XXXXXXXXXX -> +7XXXXXXXXXX
                if (d.length === 11 && d.startsWith('8')) d = '7' + d.slice(1);

                // 7XXXXXXXXXX -> +7XXXXXXXXXX
                if (d.length === 11 && d.startsWith('7')) return '+7' + d.slice(1);

                // XXXXXXXXXX -> +7XXXXXXXXXX
                if (d.length === 10) return '+7' + d;

                if (d.length === 0) return '';
                return '+' + d;
            }

            function formatMaskFromDigits(raw) {
                let d = digitsOnly(raw);

                // хотим 10 цифр после +7
                if (d.startsWith('7')) d = d.slice(1);
                if (d.startsWith('8')) d = d.slice(1);
                d = d.slice(0, 10);

                const a = d.slice(0,3);
                const b = d.slice(3,6);
                const c = d.slice(6,8);
                const e = d.slice(8,10);

                let out = '+7';
                if (a.length) out += ' (' + a;
                if (a.length < 3) return out;
                out += ')';

                if (b.length) out += ' ' + b;
                if (b.length < 3) return out;

                if (c.length) out += '-' + c;
                if (c.length < 2) return out;

                if (e.length) out += '-' + e;
                return out;
            }

            // ------------------------------------------------------------
            // Init: Names
            // ------------------------------------------------------------
            const nameInputs = document.querySelectorAll('[data-ru-name]');
            nameInputs.forEach((inp) => {
                // не трогаем disabled
                if (inp.disabled) return;

                // мягкая нормализация при blur
                inp.addEventListener('blur', () => {
                    if (inp.disabled) return;
                    inp.value = normalizeCyrName(inp.value);
                    setInvalid(inp, !isValidCyrName(inp.value));
                });

                // в процессе ввода: не ломаем курсор, но можем чистить “жёстко” на blur
                inp.addEventListener('input', () => {
                    if (inp.disabled) return;
                    // если пользователь вводит латиницу — покажем более “чистое” сразу
                    if (/[A-Za-z]/.test(inp.value)) {
                        inp.value = normalizeCyrName(inp.value);
                    }
                });
            });

            // ------------------------------------------------------------
            // Init: Phone
            // ------------------------------------------------------------
            const phoneMasked = document.getElementById('phone_masked');
            const phoneE164 = document.getElementById('phone_e164');

            if (phoneMasked && phoneE164) {
                // префилл: если в hidden уже e164 — покажем маску
                if (!phoneMasked.value && phoneE164.value) {
                    phoneMasked.value = formatMaskFromDigits(phoneE164.value);
                }

                phoneMasked.addEventListener('input', () => {
                    if (phoneMasked.disabled) return;

                    const e164 = toE164Ru(phoneMasked.value);
                    phoneE164.value = e164;

                    // перерисуем маску
                    const masked = formatMaskFromDigits(phoneMasked.value);
                    phoneMasked.value = masked;
                });

                phoneMasked.addEventListener('blur', () => {
                    if (phoneMasked.disabled) return;
                    phoneE164.value = toE164Ru(phoneMasked.value);
                    phoneMasked.value = formatMaskFromDigits(phoneMasked.value);
                });
            }

            // ------------------------------------------------------------
            // Submit: validate names + phone
            // ------------------------------------------------------------
            const form = document.getElementById('profile-complete-form');
            if (!form) return;

            const phoneRe = /^\+7\d{10}$/;

            form.addEventListener('submit', (e) => {
                let ok = true;

                // Names
                nameInputs.forEach((inp) => {
                    if (inp.disabled) return;
                    inp.value = normalizeCyrName(inp.value);

                    const bad = !isValidCyrName(inp.value);
                    setInvalid(inp, bad);
                    ok = ok && !bad;
                });

                // Phone
                if (phoneMasked && !phoneMasked.disabled && phoneE164) {
                    phoneE164.value = toE164Ru(phoneMasked.value);

                    const bad = !!phoneE164.value && !phoneRe.test((phoneE164.value || '').trim());
                    setInvalid(phoneMasked, bad);
                    ok = ok && !bad;
                }

                if (!ok) {
                    e.preventDefault();
                    // можно добавить лёгкий скролл к первому красному
                    const firstBad = document.querySelector('.ring-red-500');
                    if (firstBad && typeof firstBad.scrollIntoView === 'function') {
                        firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
        })();
    </script>
</x-app-layout>
