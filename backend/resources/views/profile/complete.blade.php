<x-app-layout>
    @php
        $user = auth()->user();
        $canEditProtected = $user && $user->can('edit-protected-profile-fields');

        // helper: поле уже заполнено?
        $filled = function ($value) {
            if (is_null($value)) return false;
            if (is_string($value)) return trim($value) !== '';
            return true;
        };

        $lockHint = 'Поле уже заполнено. Изменить может только администратор или организатор.';
    @endphp

    <div class="v-container">
        <h1 class="text-2xl font-bold mb-4">Профиль игрока</h1>

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
                                    @case('classic_level') Уровень (классика) @break
                                    @case('beach_level') Уровень (пляж) @break
                                    @default {{ $key }}
                                @endswitch
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if (!empty($eventId))
                    <div class="v-hint mt-3">После сохранения профиля мы попробуем автоматически записать вас на мероприятие.</div>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('profile.extra.update') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @php $lockedLast = !$canEditProtected && $filled($user?->last_name); @endphp
                <div>
                    <label class="block mb-1 font-medium">Фамилия</label>
                    <input name="last_name" class="v-input w-full"
                           value="{{ old('last_name', $user?->last_name) }}"
                           @disabled($lockedLast)>
                    @if($lockedLast)
                        <div class="v-hint mt-1">{{ $lockHint }}</div>
                    @endif
                </div>

                @php $lockedFirst = !$canEditProtected && $filled($user?->first_name); @endphp
                <div>
                    <label class="block mb-1 font-medium">Имя</label>
                    <input name="first_name" class="v-input w-full"
                           value="{{ old('first_name', $user?->first_name) }}"
                           @disabled($lockedFirst)>
                    @if($lockedFirst)
                        <div class="v-hint mt-1">{{ $lockHint }}</div>
                    @endif
                </div>

                @php $lockedPat = !$canEditProtected && $filled($user?->patronymic); @endphp
                <div>
                    <label class="block mb-1 font-medium">Отчество (видно только вам и организаторам)</label>
                    <input name="patronymic" class="v-input w-full"
                           value="{{ old('patronymic', $user?->patronymic) }}"
                           @disabled($lockedPat)>
                    @if($lockedPat)
                        <div class="v-hint mt-1">{{ $lockHint }}</div>
                    @endif
                </div>

                @php $lockedPhone = !$canEditProtected && $filled($user?->phone); @endphp
                <div>
                    <label class="block mb-1 font-medium">Телефон (видно только вам и организаторам)</label>
                    <input name="phone" class="v-input w-full"
                           value="{{ old('phone', $user?->phone) }}"
                           @disabled($lockedPhone)>
                    @if($lockedPhone)
                        <div class="v-hint mt-1">{{ $lockHint }}</div>
                    @endif
                </div>

                @php $lockedBirth = !$canEditProtected && $filled($user?->birth_date); @endphp
                <div>
                    <label class="block mb-1 font-medium">Дата рождения</label>
                    <input type="date" name="birth_date" class="v-input w-full"
                           value="{{ old('birth_date', $user?->birth_date) }}"
                           @disabled($lockedBirth)>
                    @if($lockedBirth)
                        <div class="v-hint mt-1">{{ $lockHint }}</div>
                    @endif
                </div>

                @php $lockedCity = !$canEditProtected && $filled($user?->city_id); @endphp
                <div>
                    <label class="block mb-1 font-medium">Город</label>
                    <select name="city_id" class="v-input w-full" @disabled($lockedCity)>
                        <option value="">— выберите —</option>
                        @foreach(($cities ?? []) as $city)
                            <option value="{{ $city->id }}" @selected((string)old('city_id', $user?->city_id) === (string)$city->id)>
                                {{ $city->name }}@if($city->region) ({{ $city->region }})@endif
                            </option>
                        @endforeach
                    </select>
                    @if($lockedCity)
                        <div class="v-hint mt-1">{{ $lockHint }}</div>
                    @endif
                </div>

                @php $lockedClassic = !$canEditProtected && $filled($user?->classic_level); @endphp
                <div>
                    <label class="block mb-1 font-medium">Уровень (классика)</label>
                    <input name="classic_level" class="v-input w-full"
                           value="{{ old('classic_level', $user?->classic_level) }}"
                           @disabled($lockedClassic)>
                    <div class="v-hint mt-1">Шкала: 1..7 (для &lt;18 только 1,2,4)</div>
                    @if($lockedClassic)
                        <div class="v-hint mt-1">{{ $lockHint }}</div>
                    @endif
                </div>

                @php $lockedBeach = !$canEditProtected && $filled($user?->beach_level); @endphp
                <div>
                    <label class="block mb-1 font-medium">Уровень (пляж)</label>
                    <input name="beach_level" class="v-input w-full"
                           value="{{ old('beach_level', $user?->beach_level) }}"
                           @disabled($lockedBeach)>
                    <div class="v-hint mt-1">Шкала: 1..7 (для &lt;18 только 1,2,4)</div>
                    @if($lockedBeach)
                        <div class="v-hint mt-1">{{ $lockHint }}</div>
                    @endif
                </div>
            </div>

            <div class="v-actions">
                <button type="submit" class="v-btn v-btn--primary">Сохранить</button>
                <a class="v-btn v-btn--secondary" href="/events">К мероприятиям</a>
                <a class="v-btn v-btn--secondary" href="/user/profile">Аккаунт (Jetstream)</a>
            </div>

            <div class="v-hint">
                После заполнения ключевых полей (ФИО, отчество, телефон, дата рождения, уровни) изменить их сможет только администратор/организатор.
            </div>
        </form>
    </div>
</x-app-layout>
