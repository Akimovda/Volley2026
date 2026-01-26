{{-- resources/views/user/public.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate">
                    Профиль игрока
                </h2>
                <div class="text-sm text-gray-500 truncate">
                    {{ method_exists($user, 'displayName') ? $user->displayName() : ($user->name ?? '—') }}
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('users.index') }}"
                   class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                    ← К списку игроков
                </a>

                @auth
                    @if(auth()->id() === $user->id)
                        <a href="{{ route('user.photos') }}"
                           class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm bg-indigo-600 text-white hover:bg-indigo-700">
                            Фото
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </x-slot>

    {{-- FLASH --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6">
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
    </div>

    @php
        /** @var \App\Models\User $user */
        $age = method_exists($user, 'ageYears') ? $user->ageYears() : null;

        $posMap = [
            'setter'   => 'Связующий',
            'outside'  => 'Доигровщик',
            'opposite' => 'Диагональный',
            'middle'   => 'Центральный блокирующий',
            'libero'   => 'Либеро',
        ];

        $classicPrimary = optional($user->classicPositions)->firstWhere('is_primary', true)?->position;
        $classicExtras  = optional($user->classicPositions)->where('is_primary', false)->pluck('position')->values()->all() ?? [];

        $beachPrimary = optional($user->beachZones)->firstWhere('is_primary', true)?->zone;
        $beachExtras  = optional($user->beachZones)->where('is_primary', false)->pluck('zone')->values()->all() ?? [];

        // --- Contacts logic (аккуратно, без кривых ссылок) ---
        $allowContact = (bool)($user->allow_user_contact ?? true);
        $isAuthed = auth()->check();
        $isSelf = $isAuthed && auth()->id() === $user->id;

        $tgUrl = null;
        $tgUsername = trim((string)($user->telegram_username ?? ''));
        if ($tgUsername !== '') {
            $tgUsername = ltrim($tgUsername, '@');
            if ($tgUsername !== '') {
                $tgUrl = 'https://t.me/' . $tgUsername;
            }
        }

        $vkUrl = null;
        $vkId = trim((string)($user->vk_id ?? ''));
        if ($vkId !== '') {
            $vkUrl = 'https://vk.com/id' . $vkId;
        }

        $hasAnyContact = (bool)($tgUrl || $vkUrl);

        // показываем кнопки только если:
        // - авторизован
        // - не свой профиль
        // - пользователь разрешил контакты
        // - есть хоть один контакт
        $canShowContactButtons = $isAuthed && !$isSelf && $allowContact && $hasAnyContact;
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-10">

            {{-- Main card --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-start gap-6 flex-col md:flex-row">
                    <div class="flex items-center gap-5">
                        <img
                            src="{{ $user->profile_photo_url }}"
                            alt="avatar"
                            class="rounded-full border border-gray-100"
                            style="width:96px;height:96px;object-fit:cover;"
                        />

                        <div class="min-w-0">
                            <div class="text-2xl font-bold text-gray-900 truncate">
                                {{ method_exists($user, 'displayName') ? $user->displayName() : ($user->name ?? '—') }}
                            </div>

                            <div class="text-sm text-gray-600 mt-1">
                                @if(!is_null($age))
                                    {{ $age }} лет
                                @else
                                    —
                                @endif
                            </div>

                            @if(!empty($user->city))
                                <div class="text-sm text-gray-600 mt-1">
                                    {{ $user->city->name }}@if($user->city->region) ({{ $user->city->region }})@endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="w-full md:w-auto md:ml-auto">
                        @auth
                            @if(auth()->id() === $user->id)
                                <div class="flex gap-2">
                                    <a href="{{ route('profile.show') }}"
                                       class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                                        Аккаунт
                                    </a>
                                    <a href="{{ route('user.photos') }}"
                                       class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm bg-indigo-600 text-white hover:bg-indigo-700">
                                        Фото
                                    </a>
                                </div>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>

            {{-- Skills --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="text-lg font-bold text-gray-900">Навыки в волейболе</div>

                <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="v-card">
                        <div class="v-card__body space-y-2">
                            <div class="font-semibold">Классический волейбол</div>
                            <div class="text-sm">
                                Уровень:
                                <span class="font-semibold">{{ $user->classic_level ?? '—' }}</span>
                            </div>
                            <div class="text-sm">
                                Амплуа:
                                <span class="font-semibold">
                                    @if($classicPrimary)
                                        Основное: {{ $posMap[$classicPrimary] ?? $classicPrimary }}
                                        @if(!empty($classicExtras))
                                            ; Доп.: {{ collect($classicExtras)->map(fn($p) => $posMap[$p] ?? $p)->join(', ') }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="v-card">
                        <div class="v-card__body space-y-2">
                            <div class="font-semibold">Пляжный волейбол</div>
                            <div class="text-sm">
                                Уровень:
                                <span class="font-semibold">{{ $user->beach_level ?? '—' }}</span>
                            </div>
                            <div class="text-sm">
                                Зона:
                                <span class="font-semibold">
                                    @if(!empty($user->beach_universal))
                                        Универсал (2 и 4)
                                    @elseif(!is_null($beachPrimary))
                                        Основная: {{ $beachPrimary }}
                                        @if(!empty($beachExtras))
                                            ; Доп.: {{ collect($beachExtras)->join(', ') }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contacts --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="text-lg font-bold text-gray-900">Контакты</div>
                <div class="mt-3 text-sm text-gray-600">
                    Телефон и прочие приватные данные тут не показываем.
                </div>

                @if($isSelf)
                    <div class="mt-3 text-sm text-gray-500">
                        Это ваш профиль — контакты для связи тут не нужны.
                    </div>
                @elseif(!$allowContact)
                    <div class="mt-3 text-sm text-gray-600">
                        Пользователь запретил связываться с ним через Telegram/VK.
                    </div>
                @elseif(!$hasAnyContact)
                    <div class="mt-3 text-sm text-gray-600">
                        Пользователь не указал Telegram/VK для связи.
                    </div>
                @elseif(!$isAuthed)
                    <div class="mt-3 p-3 rounded-lg bg-blue-50 text-blue-900 border border-blue-100 text-sm">
                        Чтобы написать пользователю в Telegram/VK, нужно войти в аккаунт.
                    </div>
                @elseif($canShowContactButtons)
                    <div class="mt-4 flex flex-wrap gap-3">
                        @if($tgUrl)
                            <a class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50"
                               href="{{ $tgUrl }}" target="_blank" rel="noopener noreferrer">
                                Написать в Telegram
                            </a>
                        @endif

                        @if($vkUrl)
                            <a class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50"
                               href="{{ $vkUrl }}" target="_blank" rel="noopener noreferrer">
                                Написать в VK
                            </a>
                        @endif
                    </div>

                    <div class="mt-2 text-xs text-gray-500">
                        Откроется в новой вкладке.
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
