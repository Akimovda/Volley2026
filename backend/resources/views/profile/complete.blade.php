<x-app-layout>
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
                <div>
                    <label class="block mb-1 font-medium">Фамилия</label>
                    <input name="last_name" class="v-input w-full" value="{{ old('last_name', auth()->user()->last_name) }}">
                </div>

                <div>
                    <label class="block mb-1 font-medium">Имя</label>
                    <input name="first_name" class="v-input w-full" value="{{ old('first_name', auth()->user()->first_name) }}">
                </div>

                <div>
                    <label class="block mb-1 font-medium">Отчество (видно только вам и организаторам)</label>
                    <input name="patronymic" class="v-input w-full" value="{{ old('patronymic', auth()->user()->patronymic) }}">
                </div>

                <div>
                    <label class="block mb-1 font-medium">Телефон (видно только вам и организаторам)</label>
                    <input name="phone" class="v-input w-full" value="{{ old('phone', auth()->user()->phone) }}">
                </div>

                <div>
                    <label class="block mb-1 font-medium">Дата рождения</label>
                    <input type="date" name="birth_date" class="v-input w-full" value="{{ old('birth_date', auth()->user()->birth_date) }}">
                </div>

                <div>
                    <label class="block mb-1 font-medium">Город</label>
                    <select name="city_id" class="v-input w-full">
                        <option value="">— выберите —</option>
                        @foreach(($cities ?? []) as $city)
                            <option value="{{ $city->id }}" @selected((string)old('city_id', auth()->user()->city_id) === (string)$city->id)>
                                {{ $city->name }}@if($city->region) ({{ $city->region }})@endif
                            </option>
                        @endforeach
                    </select>
                    <div class="v-hint mt-1">Если списка нет — мы добавим сидер городов РФ на следующем шаге.</div>
                </div>

                <div>
                    <label class="block mb-1 font-medium">Уровень (классика)</label>
                    <input name="classic_level" class="v-input w-full" value="{{ old('classic_level', auth()->user()->classic_level) }}">
                </div>

                <div>
                    <label class="block mb-1 font-medium">Уровень (пляж)</label>
                    <input name="beach_level" class="v-input w-full" value="{{ old('beach_level', auth()->user()->beach_level) }}">
                </div>
            </div>

            <div class="v-actions">
                <button type="submit" class="v-btn v-btn--primary">Сохранить</button>
                <a class="v-btn v-btn--secondary" href="/events">К мероприятиям</a>
                <a class="v-btn v-btn--secondary" href="/user/profile">Аккаунт (Jetstream)</a>
            </div>

            <div class="v-hint">
                Поля ФИО/отчество/телефон/дата рождения/уровни после заполнения смогут менять только администратор или организатор.
            </div>
        </form>
    </div>
</x-app-layout>
