<x-app-layout>
    <div class="v-container">
        <div class="v-card">
            <div class="v-card__body">
                @php
                    /** @var \App\Models\User $u */
                    // контроллер должен передать $u или $user — подстрахуемся
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
                @endphp

                @if(!$u)
                    <div class="v-alert v-alert--warn">
                        <div class="v-alert__text">Игрок не найден.</div>
                    </div>
                @else
                    <div class="flex items-start gap-4">
                        <img
                            src="{{ $u->profile_photo_url }}"
                            alt="avatar"
                            class="rounded-full"
                            style="width:84px;height:84px;object-fit:cover;"
                        />

                        <div class="min-w-0 w-full">
                            <h1 class="text-2xl font-bold">{{ $u->displayName() }}</h1>

                            <div class="text-sm text-gray-600 mt-1">
                                @php $age = $u->ageYears(); @endphp
                                @if(!is_null($age))
                                    {{ $age }} лет
                                @endif
                            </div>

                            {{-- Персональные данные --}}
                            <div class="mt-5">
                                <div class="font-semibold text-lg mb-2">Персональные данные</div>

                                <div class="space-y-1 text-sm">
                                    <div>Фамилия: <span class="font-semibold">{{ $u->last_name ?? '—' }}</span></div>
                                    <div>Имя: <span class="font-semibold">{{ $u->first_name ?? '—' }}</span></div>

                                    {{-- Чувствительные поля показываем только если разрешено политикой --}}
                                    @can('view-sensitive-profile', $u)
                                        <div>Отчество: <span class="font-semibold">{{ $u->patronymic ?? '—' }}</span></div>
                                        <div>Телефон: <span class="font-semibold">{{ $u->phone ?? '—' }}</span></div>
                                    @endcan

                                    <div>
                                        Пол:
                                        <span class="font-semibold">
                                            @if($u->gender === 'm') Мужчина
                                            @elseif($u->gender === 'f') Женщина
                                            @else — @endif
                                        </span>
                                    </div>

                                    <div>
                                        Рост:
                                        <span class="font-semibold">
                                            {{ !empty($u->height_cm) ? ($u->height_cm.' см') : '—' }}
                                        </span>
                                    </div>

                                    <div>
                                        Город:
                                        <span class="font-semibold">
                                            @if($u->city)
                                                {{ $u->city->name }}@if($u->city->region) ({{ $u->city->region }})@endif
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </div>

                                    <div>
                                        Дата рождения:
                                        <span class="font-semibold">
                                            {{-- на публичной карточке лучше не светить точную дату, но оставляю как было "—/дата" --}}
                                            {{ $u->birth_date?->format('Y-m-d') ?? '—' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Навыки --}}
                            <div class="mt-6">
                                <div class="font-semibold text-lg mb-2">Навыки в волейболе</div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="v-card">
                                        <div class="v-card__body space-y-1">
                                            <div class="font-semibold">Классический волейбол</div>
                                            <div>Уровень (классика): <span class="font-semibold">{{ $u->classic_level ?? '—' }}</span></div>

                                            <div>
                                                Амплуа игрока:
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
                                        <div class="v-card__body space-y-1">
                                            <div class="font-semibold">Пляжный волейбол</div>
                                            <div>Уровень (пляж): <span class="font-semibold">{{ $u->beach_level ?? '—' }}</span></div>

                                            <div>
                                                Зона игры:
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

                            <div class="v-actions mt-6">
                                <a class="v-btn v-btn--secondary" href="{{ route('users.index') }}">← К списку игроков</a>
                                <a class="v-btn v-btn--secondary" href="/events">К мероприятиям</a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
