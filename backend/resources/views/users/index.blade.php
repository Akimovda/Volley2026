<x-voll-layout>
    <div class="v-container">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h1 class="text-2xl font-bold">Игроки -999-</h1>
        </div>

        <form method="GET" action="{{ route('users.index') }}" class="v-card mb-6">
            <div class="v-card__body grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block mb-1 font-medium">Поиск</label>
                    <input name="q" class="v-input w-full" value="{{ $filters['q'] ?? '' }}" placeholder="Имя / фамилия / @telegram" />
                </div>

                <div>
                    <label class="block mb-1 font-medium">Город</label>
                    <select name="city_id" class="v-input w-full">
                        <option value="">— любой —</option>
                        @foreach($cities as $c)
                            <option value="{{ $c->id }}" @selected((string)($filters['city_id'] ?? '') === (string)$c->id)>
                                {{ $c->name }}@if($c->region) ({{ $c->region }})@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-1 font-medium">Пол</label>
                    <select name="gender" class="v-input w-full">
                        <option value="">— любой —</option>
                        <option value="m" @selected(($filters['gender'] ?? '') === 'm')>Мужчина</option>
                        <option value="f" @selected(($filters['gender'] ?? '') === 'f')>Женщина</option>
                    </select>
                </div>

                <div>
                    <label class="block mb-1 font-medium">Уровень (классика)</label>
                    <input name="classic_level" class="v-input w-full" value="{{ $filters['classic_level'] ?? '' }}" placeholder="1..7" />
                </div>

                <div>
                    <label class="block mb-1 font-medium">Уровень (пляж)</label>
                    <input name="beach_level" class="v-input w-full" value="{{ $filters['beach_level'] ?? '' }}" placeholder="1..7" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block mb-1 font-medium">Возраст от</label>
                        <input name="age_min" class="v-input w-full" value="{{ $filters['age_min'] ?? '' }}" placeholder="например 18" />
                    </div>
                    <div>
                        <label class="block mb-1 font-medium">до</label>
                        <input name="age_max" class="v-input w-full" value="{{ $filters['age_max'] ?? '' }}" placeholder="например 45" />
                    </div>
                </div>

                <div class="md:col-span-3 flex gap-2">
                    <button class="v-btn v-btn--primary" type="submit">Искать</button>
                    <a class="v-btn v-btn--secondary" href="{{ route('users.index') }}">Сбросить</a>
                </div>
            </div>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($users as $u)
                @include('users._card', ['u' => $u])
            @endforeach
        </div>

        <div class="mt-6">
            {{ $users->links() }}
        </div>
    </div>
</x-voll-layout>
