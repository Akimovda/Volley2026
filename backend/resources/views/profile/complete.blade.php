<x-app-layout>
    <div class="v-container">
        <h1 class="text-2xl font-bold mb-4">Профиль игрока</h1>

        {{-- Ошибки валидации --}}
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

        {{-- Требуемые поля для записи на мероприятие --}}
        @if (!empty($requiredKeys))
            <div class="v-alert v-alert--warn mb-4">
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
        @endphp

        <form method="POST" action="{{ route('profile.extra.update') }}" class="space-y-6">
            @csrf

            {{-- Персональные данные --}}
            <div class="font-semibold text-lg">Персональные данные</div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @php $lockedLast = !$canEditProtected && $filled($user?->last_name); @endphp
                <div>
                    <label class="block mb-1 font-medium">Фамилия</label>
                    <input name="last_name" class="v-input w-full"
                           value="{{ old('last_name', $user?->last_name) }}"
                           @disabled($lockedLast)>
                    @if($lockedLast)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                </div>

                @php $lockedFirst = !$canEditProtected && $filled($user?->first_name); @endphp
                <div>
                    <label class="block mb-1 font-medium">Имя</label>
                    <input name="first_name" class="v-input w-full"
                           value="{{ old('first_name', $user?->first_name) }}"
                           @disabled($lockedFirst)>
                    @if($lockedFirst)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                </div>

                @php $lockedPat = !$canEditProtected && $filled($user?->patronymic); @endphp
                <div>
                    <label class="block mb-1 font-medium">
                        Отчество (видно вам, администратору и организаторам)
                    </label>
                    <input name="patronymic" class="v-input w-full"
                           value="{{ old('patronymic', $user?->patronymic) }}"
                           @disabled($lockedPat)>
                    @if($lockedPat)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                </div>

                @php $lockedPhone = !$canEditProtected && $filled($user?->phone); @endphp
                <div>
                    <label class="block mb-1 font-medium">
                        Телефон (видно вам, администратору и организаторам)
                    </label>
                    <input name="phone" class="v-input w-full"
                           value="{{ old('phone', $user?->phone) }}"
                           @disabled($lockedPhone)>
                    @if($lockedPhone)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                </div>

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
    class="v-input w-full"
    value="{{ $birthValue }}"
    @disabled($lockedBirth)
>
                    @if($lockedBirth)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                </div>

                @php $lockedCity = !$canEditProtected && $filled($user?->city_id); @endphp
                <div>
                    <label class="block mb-1 font-medium">Город</label>
                    <select name="city_id" class="v-input w-full" @disabled($lockedCity)>
                        <option value="">— выберите —</option>
                        @foreach(($cities ?? []) as $city)
                            <option value="{{ $city->id }}"
                                @selected((string)old('city_id', $user?->city_id) === (string)$city->id)>
                                {{ $city->name }}@if($city->region) ({{ $city->region }})@endif
                            </option>
                        @endforeach
                    </select>
                    @if($lockedCity)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                </div>

                {{-- Пол (НЕ фиксируемый) --}}
                <div>
                    <label class="block mb-1 font-medium">Пол</label>
                    <select name="gender" class="v-input w-full">
                        <option value="">— не указан —</option>
                        <option value="m" @selected(old('gender', $user?->gender) === 'm')>Мужчина</option>
                        <option value="f" @selected(old('gender', $user?->gender) === 'f')>Женщина</option>
                    </select>
                    <div class="v-hint mt-1">Пол виден всем.</div>
                </div>

                {{-- Рост (НЕ фиксируемый) --}}
                <div>
                    <label class="block mb-1 font-medium">Рост (см)</label>
                    <input type="number" name="height_cm" min="40" max="230" class="v-input w-full"
                           value="{{ old('height_cm', $user?->height_cm) }}">
                    <div class="v-hint mt-1">Допустимый диапазон: 40–230 см. Рост виден всем.</div>
                </div>
            </div>

            {{-- Навыки --}}
            <div class="font-semibold text-lg pt-2">Навыки в волейболе</div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Классика --}}
                <div class="v-card">
                    <div class="v-card__body space-y-3">
                        <div class="font-semibold">Классический волейбол</div>

                        @php $lockedClassic = !$canEditProtected && $filled($user?->classic_level); @endphp
                        <div>
                            <label class="block mb-1 font-medium">Уровень (классика)</label>
                            <input name="classic_level" class="v-input w-full"
                                   value="{{ old('classic_level', $user?->classic_level) }}"
                                   @disabled($lockedClassic)>
                            <div class="v-hint mt-1">Шкала: 1–7 (для &lt;18 только 1,2,4)</div>
                            @if($lockedClassic)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                        </div>

                        <div>
                            <label class="block mb-1 font-medium">Какое твое основное амплуа?</label>
                            <div class="space-y-1">
                                @foreach($posMap as $key => $label)
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="classic_primary_position" value="{{ $key }}"
                                               @checked(old('classic_primary_position', $classicPrimary) === $key)>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block mb-1 font-medium">
                                В каком амплуа ты можешь играть ещё?
                                <span class="text-sm text-gray-500">(можно пропустить)</span>
                            </label>

                            @php $primaryNow = old('classic_primary_position', $classicPrimary); @endphp
                            <div class="space-y-1">
                                @foreach($posMap as $key => $label)
                                    @php
                                        $checked = in_array($key, (array)old('classic_extra_positions', $classicAll), true);
                                        $disabled = ($primaryNow === $key);
                                    @endphp
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="classic_extra_positions[]" value="{{ $key }}"
                                               @checked($checked) @disabled($disabled)>
                                        <span>{{ $label }}</span>
                                        @if($disabled)<span class="text-xs text-gray-500">(основное)</span>@endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Пляж --}}
                <div class="v-card">
                    <div class="v-card__body space-y-3">
                        <div class="font-semibold">Пляжный волейбол</div>

                        @php $lockedBeach = !$canEditProtected && $filled($user?->beach_level); @endphp
                        <div>
                            <label class="block mb-1 font-medium">Уровень (пляж)</label>
                            <input name="beach_level" class="v-input w-full"
                                   value="{{ old('beach_level', $user?->beach_level) }}"
                                   @disabled($lockedBeach)>
                            <div class="v-hint mt-1">Шкала: 1–7 (для &lt;18 только 1,2,4)</div>
                            @if($lockedBeach)<div class="v-hint mt-1">{{ $lockHint }}</div>@endif
                        </div>

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

            <div class="v-actions">
                <button type="submit" class="v-btn v-btn--primary">Сохранить</button>
                <a class="v-btn v-btn--secondary" href="/events">К мероприятиям</a>
                <a class="v-btn v-btn--secondary" href="/user/profile">Аккаунт</a>
            </div>

            <div class="v-hint">
                После заполнения ключевых полей изменить их сможет только администратор.
            </div>
        </form>

        {{-- Заявка на организатора --}}
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
                                        class="w-full border rounded p-2"
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
</x-app-layout>
