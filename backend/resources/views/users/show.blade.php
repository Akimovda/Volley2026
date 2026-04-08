{{-- resources/views/users/show.blade.php --}}
<x-app-layout>
    <div class="v-container py-6">
        <div class="v-card">
            <div class="v-card__body">
                @php
                    /** @var \App\Models\User $u */
                    $u = $u ?? ($user ?? null);

                    if ($u) {
                        $u->loadMissing(['city', 'classicPositions', 'beachZones']);
                    }

                    $posMap = [
                        'setter'   => 'Связующий',
                        'outside'  => 'Доигровщик',
                        'opposite' => 'Диагональный',
                        'middle'   => 'Центральный блокирующий',
                        'libero'   => 'Либеро',
                    ];

                    $classicPrimary = optional($u?->classicPositions)->firstWhere('is_primary', true)?->position;
                    $classicExtras = optional($u?->classicPositions)
                        ->where('is_primary', false)
                        ->pluck('position')
                        ->values()
                        ->all() ?? [];

                    $beachPrimaryZone = optional($u?->beachZones)->firstWhere('is_primary', true)?->zone;
                    $beachExtras = optional($u?->beachZones)
                        ->where('is_primary', false)
                        ->pluck('zone')
                        ->values()
                        ->all() ?? [];

                    // контакты
                    $allowContact = (bool) ($u?->allow_user_contact ?? false);

                    $tgUsername = trim((string)($u?->telegram_username ?? ''));
                    $hasTgLink = $tgUsername !== '';
                    $tgLink = $hasTgLink ? ('https://t.me/' . ltrim($tgUsername, '@')) : null;

                    $vkIdRaw = trim((string)($u?->vk_id ?? ''));
                    $hasVkLink = $vkIdRaw !== '';
                    $vkLink = null;
                    if ($hasVkLink) {
                        $vkLink = ctype_digit($vkIdRaw)
                            ? ('https://vk.com/id' . $vkIdRaw)
                            : ('https://vk.com/' . $vkIdRaw);
                    }

                    $genderLabel = $u?->gender === 'm' ? 'Мужчина' : ($u?->gender === 'f' ? 'Женщина' : '—');
                    $age = ($u && method_exists($u, 'ageYears')) ? $u->ageYears() : null;

                    $cityLabel = $u?->city
                        ? ($u->city->name . ($u->city->region ? ' (' . $u->city->region . ')' : ''))
                        : null;

                    $headerMeta = array_values(array_filter([
                        $cityLabel,
                        !is_null($age) ? (($u->gender === 'f' && $u->hide_age) ? '🤷‍♀️' : ($age . ' лет')) : null,
                        $genderLabel !== '—' ? $genderLabel : null,
                        !empty($u?->height_cm) ? ($u->height_cm . ' см') : null,
                    ]));
                @endphp

                @if(!$u)
                    <div class="v-alert v-alert--warn">
                        <div class="v-alert__text">Игрок не найден.</div>
                    </div>
                @else
                    {{-- Header --}}
                    <div class="flex flex-col md:flex-row items-start gap-5">
                        <div class="shrink-0">
                            <img
                                src="{{ $u->profile_photo_url }}"
                                alt="avatar"
                                class="rounded-full border border-gray-200 bg-gray-100"
                                style="width:92px;height:92px;object-fit:cover;"
                                loading="lazy"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight break-words">
                                {{ method_exists($u, 'displayName') ? $u->displayName() : ($u->name ?? '—') }}
                            </h1>

                            @if(!empty($headerMeta))
                                <div class="text-sm text-gray-600 mt-2 break-words">
                                    {{ implode(' · ', $headerMeta) }}
                                </div>
                            @endif

                            <div class="v-actions mt-4 flex flex-wrap gap-2">
                                <a class="v-btn v-btn--secondary" href="{{ route('users.index') }}">← К списку игроков</a>
                                <a class="v-btn v-btn--secondary" href="/events">К мероприятиям</a>
                            </div>
                        </div>
                    </div>

                    {{-- Contacts --}}
                    <div class="mt-6 v-card">
                        <div class="v-card__body">
                            <div class="font-semibold text-lg mb-2">Связаться</div>

                            @auth
                                @if(auth()->id() === $u->id)
                                    <div class="v-alert v-alert--info">
                                        <div class="v-alert__text">
                                            Это ваш профиль. Разрешение “могут ли со мной связаться” настраивается в
                                            <a class="underline" href="{{ route('profile.show') }}">Аккаунт</a>.
                                        </div>
                                    </div>
                                @else
                                    @if(!$allowContact)
                                        <div class="v-alert v-alert--info">
                                            <div class="v-alert__text">
                                                Пользователь запретил связываться с ним.
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex flex-wrap gap-2">
                                            @if($hasTgLink)
                                                <a class="v-btn v-btn--secondary"
                                                   href="{{ $tgLink }}"
                                                   target="_blank" rel="noopener">
                                                    Telegram →
                                                </a>
                                            @endif

                                            @if($hasVkLink)
                                                <a class="v-btn v-btn--secondary"
                                                   href="{{ $vkLink }}"
                                                   target="_blank" rel="noopener">
                                                    VK →
                                                </a>
                                            @endif

                                            @if(!$hasTgLink && !$hasVkLink)
                                                <div class="v-alert v-alert--info">
                                                    <div class="v-alert__text">
                                                        У пользователя не указаны публичные контакты (Telegram/VK).
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        @if(($u->telegram_id ?? null) && !$hasTgLink)
                                            <div class="text-xs text-gray-500 mt-2 break-words">
                                                Telegram привязан, но нет username — ссылку на чат показать нельзя.
                                            </div>
                                        @endif
                                    @endif
                                @endif
                            @else
                                <div class="v-alert v-alert--info">
                                    <div class="v-alert__text">
                                        Чтобы написать пользователю, нужно войти в аккаунт.
                                    </div>
                                </div>
                            @endauth
                        </div>
                    </div>

                    {{-- Personal data --}}
                    <div class="mt-6 v-card">
                        <div class="v-card__body">
                            <div class="font-semibold text-lg mb-3">Персональные данные</div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Фамилия</div>
                                    <div class="font-semibold text-gray-900 break-words">{{ $u->last_name ?? '—' }}</div>
                                </div>

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Имя</div>
                                    <div class="font-semibold text-gray-900 break-words">{{ $u->first_name ?? '—' }}</div>
                                </div>

                                @can('view-sensitive-profile', $u)
                                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                        <div class="text-xs text-gray-500">Отчество</div>
                                        <div class="font-semibold text-gray-900 break-words">{{ $u->patronymic ?? '—' }}</div>
                                    </div>

                                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                        <div class="text-xs text-gray-500">Телефон</div>
                                        <div class="font-semibold text-gray-900 break-words">{{ $u->phone ?? '—' }}</div>
                                    </div>
                                @endcan

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Пол</div>
                                    <div class="font-semibold text-gray-900">{{ $genderLabel }}</div>
                                </div>

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Город</div>
                                    <div class="font-semibold text-gray-900 break-words">
                                        {{ $cityLabel ?? '—' }}
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Рост</div>
                                    <div class="font-semibold text-gray-900">
                                        {{ !empty($u->height_cm) ? ($u->height_cm.' см') : '—' }}
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Дата рождения</div>
                                    <div class="font-semibold text-gray-900">
                                        {{ $u->birth_date?->format('Y-m-d') ?? '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Skills --}}
                    <div class="mt-6">
                        <div class="font-semibold text-lg mb-3">Навыки в волейболе</div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="v-card">
                                <div class="v-card__body space-y-2">
                                    <div class="font-semibold text-gray-900">Классический волейбол</div>

                                    <div class="text-sm">
                                        Уровень:
                                        <span class="font-semibold">{{ $u->classic_level ?? '—' }}</span>
                                    </div>

                                    <div class="text-sm break-words">
                                        Амплуа:
                                        <span class="font-semibold">
                                            @if($classicPrimary)
                                                Основное: {{ $posMap[$classicPrimary] ?? $classicPrimary }}
                                                @if(!empty($classicExtras))
                                                    ; Доп.: {{ collect($classicExtras)->map(fn($p)=>$posMap[$p] ?? $p)->join(', ') }}
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
                                    <div class="font-semibold text-gray-900">Пляжный волейбол</div>

                                    <div class="text-sm">
                                        Уровень:
                                        <span class="font-semibold">{{ $u->beach_level ?? '—' }}</span>
                                    </div>

                                    <div class="text-sm break-words">
                                        Зона:
                                        <span class="font-semibold">
                                            @if($u->beach_universal)
                                                Универсал (2 и 4)
                                            @elseif(!is_null($beachPrimaryZone))
                                                Основная: {{ $beachPrimaryZone }}
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
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
