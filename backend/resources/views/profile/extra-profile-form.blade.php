@php($required = session('pending_profile_required', []))

<div class="mt-10">
    <div class="text-lg font-semibold text-gray-900">Анкета игрока</div>
    <div class="text-sm text-gray-600 mt-1">
        Эти данные нужны для записи на мероприятия и подбора групп.
    </div>

    @if (!empty($required))
        <div class="mt-4 v-alert v-alert--warn">
            <div class="v-alert__title">Нужно заполнить для записи на мероприятие</div>
            <div class="v-alert__text">
                Осталось: <strong>{{ implode(', ', $required) }}</strong>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('profile.extra.update') }}" class="mt-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700" for="first_name">Имя</label>
                <input
                    id="first_name"
                    name="first_name"
                    type="text"
                    value="{{ old('first_name', auth()->user()->first_name) }}"
                    class="mt-1 block w-full rounded-md border-gray-300 {{ in_array('full_name', $required) ? 'v-required' : '' }}"
                    autocomplete="given-name"
                >
                @error('first_name') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700" for="last_name">Фамилия</label>
                <input
                    id="last_name"
                    name="last_name"
                    type="text"
                    value="{{ old('last_name', auth()->user()->last_name) }}"
                    class="mt-1 block w-full rounded-md border-gray-300 {{ in_array('full_name', $required) ? 'v-required' : '' }}"
                    autocomplete="family-name"
                >
                @error('last_name') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700" for="phone">Телефон</label>
                <input
                    id="phone"
                    name="phone"
                    type="text"
                    value="{{ old('phone', auth()->user()->phone) }}"
                    class="mt-1 block w-full rounded-md border-gray-300 {{ in_array('phone', $required) ? 'v-required' : '' }}"
                    autocomplete="tel"
                    placeholder="+7 ..."
                >
                @error('phone') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700" for="classic_level">Уровень (классика)</label>
                <input
                    id="classic_level"
                    name="classic_level"
                    type="number"
                    min="0"
                    max="100"
                    value="{{ old('classic_level', auth()->user()->classic_level) }}"
                    class="mt-1 block w-full rounded-md border-gray-300 {{ in_array('classic_level', $required) ? 'v-required' : '' }}"
                >
                @error('classic_level') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700" for="beach_level">Уровень (пляж)</label>
                <input
                    id="beach_level"
                    name="beach_level"
                    type="number"
                    min="0"
                    max="100"
                    value="{{ old('beach_level', auth()->user()->beach_level) }}"
                    class="mt-1 block w-full rounded-md border-gray-300 {{ in_array('beach_level', $required) ? 'v-required' : '' }}"
                >
                @error('beach_level') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mt-4 flex gap-3 flex-wrap">
            <button type="submit" class="v-btn v-btn--primary">Сохранить анкету</button>
            <a href="/events" class="v-btn v-btn--secondary">К мероприятиям</a>
        </div>
    </form>
</div>
