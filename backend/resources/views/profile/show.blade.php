<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Аккаунт
        </h2>
    </x-slot>

    {{-- ===== [FLASH] Глобальное сообщение об успехе ===== --}}
    @if (session('status'))
        <div class="v-container mt-6">
            <div class="v-alert v-alert--success">
                <div class="v-alert__text">
                    {{ session('status') }}
                </div>
            </div>
        </div>
    @endif

    @php
        /**
         * ===== [DATA] Подготовка данных для страницы "Аккаунт" =====
         * Здесь только чтение/форматирование (никаких сохранений).
         */
        /** @var \App\Models\User $u */
        $u = auth()->user();
        $u->loadMissing(['city', 'classicPositions', 'beachZones']);

        // Карта амплуа (классика)
        $posMap = [
            'setter'   => 'Связующий',
            'outside'  => 'Доигровщик',
            'opposite' => 'Диагональный',
            'middle'   => 'Центральный блокирующий',
            'libero'   => 'Либеро',
        ];

        // Классика: основное + доп.
        $classicPrimary = optional($u->classicPositions)->firstWhere('is_primary', true)?->position;
        $classicExtras  = optional($u->classicPositions)
            ?->where('is_primary', false)
            ->pluck('position')
            ->values()
            ->all() ?? [];

        // Пляж: основная зона + доп.
        $beachPrimary = optional($u->beachZones)->firstWhere('is_primary', true)?->zone;
        $beachExtras  = optional($u->beachZones)
            ?->where('is_primary', false)
            ->pluck('zone')
            ->values()
            ->all() ?? [];

        // Возраст / дата рождения для вывода
        $age = $u->ageYears();
        $birth = $u->birth_date ? $u->birth_date->format('Y-m-d') : '—';
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-10">

            {{-- ================================================================= --}}
            {{-- ===== [SECTION] Анкета игрока (ТОЛЬКО просмотр) =================== --}}
            {{-- ================================================================= --}}
            <x-action-section>
                <x-slot name="title">
                    Анкета игрока
                </x-slot>

                <x-slot name="description">
                    Здесь отображаются данные анкеты. Для изменения нажмите «Редактировать профиль».
                </x-slot>

                <x-slot name="content">
                    <div class="flex items-start gap-4">
                        <img
                            src="{{ $u->profile_photo_url }}"
                            alt="avatar"
                            class="rounded-full"
                            style="width:84px;height:84px;object-fit:cover;"
                        />

                        <div class="min-w-0 w-full">
                            <div class="text-2xl font-bold">{{ $u->displayName() }}</div>

                            @if(!is_null($age))
                                <div class="text-sm text-gray-600 mt-1">{{ $age }} лет</div>
                            @endif

                            {{-- ===== [BLOCK] Персональные данные ===== --}}
                            <div class="mt-5">
                                <div class="font-semibold text-lg mb-2">Персональные данные</div>

                                <div class="space-y-1 text-sm">
                                    <div>Фамилия: <span class="font-semibold">{{ $u->last_name ?? '—' }}</span></div>
                                    <div>Имя: <span class="font-semibold">{{ $u->first_name ?? '—' }}</span></div>
                                    <div>Отчество: <span class="font-semibold">{{ $u->patronymic ?? '—' }}</span></div>
                                    <div>Телефон: <span class="font-semibold">{{ $u->phone ?? '—' }}</span></div>

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

                                    <div>Дата рождения: <span class="font-semibold">{{ $birth }}</span></div>
                                </div>
                            </div>

                            {{-- ===== [BLOCK] Навыки ===== --}}
                            <div class="mt-6">
                                <div class="font-semibold text-lg mb-2">Навыки в волейболе</div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {{-- --- [CARD] Классика --- --}}
                                    <div class="v-card">
                                        <div class="v-card__body space-y-2">
                                            <div class="font-semibold">Классический волейбол</div>

                                            <div>
                                                Уровень (классика):
                                                <span class="font-semibold">{{ $u->classic_level ?? '—' }}</span>
                                            </div>

                                            <div>
                                                Амплуа игрока:
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

                                    {{-- --- [CARD] Пляж --- --}}
                                    <div class="v-card">
                                        <div class="v-card__body space-y-2">
                                            <div class="font-semibold">Пляжный волейбол</div>

                                            <div>
                                                Уровень (пляж):
                                                <span class="font-semibold">{{ $u->beach_level ?? '—' }}</span>
                                            </div>

                                            <div>
                                                Зона игры:
                                                <span class="font-semibold">
                                                    @if($u->beach_universal)
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

                            {{-- ===== [CTA] Редактировать профиль ===== --}}
                            <div class="pt-5">
                                <a href="{{ url('/profile/complete') }}" class="v-btn v-btn--primary">
                                    Редактировать профиль
                                </a>
                            </div>
                        </div>
                    </div>
                </x-slot>
            </x-action-section>

            <x-section-border />

            {{-- ================================================================= --}}
            {{-- ===== [SECTION] Привязка Telegram / VK =========================== --}}
            {{-- ================================================================= --}}
            @php
                /**
                 * ===== [LINKING] Логика показа секции привязки =====
                 * Показываем только если есть что привязывать (не привязаны оба провайдера).
                 */
                $provider = session('auth_provider'); // 'vk' | 'telegram' | null
                $hasTg = !empty($u?->telegram_id);
                $hasVk = !empty($u?->vk_id);

                // какую привязку предлагать “по умолчанию”
                $suggestProvider = null;
                if ($provider === 'vk' && !$hasTg) $suggestProvider = 'telegram';
                if ($provider === 'telegram' && !$hasVk) $suggestProvider = 'vk';
            @endphp

{{-- ================================================================= --}}
{{-- ===== [SECTION] Привязка Telegram / VK =========================== --}}
{{-- ================================================================= --}}

@if($hasTg && $hasVk)
    {{-- ===== [STATE] Уже всё привязано (только сообщение) ===== --}}
    <x-action-section>
        <x-slot name="title">
            Привязка Telegram / VK
        </x-slot>

        <x-slot name="description">
            Способы входа уже связаны с этим аккаунтом.
        </x-slot>

        <x-slot name="content">
            <div class="text-sm text-gray-700">
                🔗 Telegram и VK уже привязаны ✅
            </div>
        </x-slot>
    </x-action-section>

    <x-section-border />
@else
    {{-- ===== [STATE] Есть что привязывать (генерация/ввод кода) ===== --}}
    <x-action-section>
        <x-slot name="title">
            Привязка Telegram / VK
        </x-slot>

        <x-slot name="description">
            Привяжите второй способ входа к текущему аккаунту.
        </x-slot>

        <x-slot name="content">
            {{-- ===== [INFO] Текущий статус ===== --}}
            <div class="text-sm text-gray-600 mb-3">
                Текущий вход: <b>{{ $provider ?? 'не определён' }}</b><br>
                Telegram: {!! $hasTg ? '<b>привязан</b>' : '<span class="text-gray-500">не привязан</span>' !!}<br>
                VK: {!! $hasVk ? '<b>привязан</b>' : '<span class="text-gray-500">не привязан</span>' !!}
            </div>

            {{-- ===== [HINT] Рекомендуемая привязка ===== --}}
            @if ($suggestProvider)
                <div class="v-alert v-alert--info mb-4">
                    <div class="v-alert__text">
                        Рекомендуем привязать <b>{{ $suggestProvider === 'telegram' ? 'Telegram' : 'VK' }}</b>.
                    </div>
                </div>
            @endif

            {{-- ===== [CODE] Показ одноразового кода, если только что сгенерировали ===== --}}
            @if (session('link_code_plain'))
                <div class="v-alert v-alert--info mb-4">
                    <div class="v-alert__title">Ваш одноразовый код</div>
                    <div class="v-alert__text">
                        <div class="text-2xl font-mono font-bold tracking-widest">
                            {{ session('link_code_plain') }}
                        </div>
                        <div class="mt-2 text-sm text-gray-600">
                            Истекает: {{ session('link_code_expires_at') }}
                        </div>
                    </div>
                </div>
            @endif

            {{-- ===== [FORM] Генерация кода (только когда есть что привязывать) ===== --}}
            <form method="POST" action="{{ route('account.link_code.store') }}" class="space-y-3">
                @csrf

                @if ($suggestProvider)
                    {{-- Автоматически выбираем, что именно привязывать --}}
                    <input type="hidden" name="target_provider" value="{{ $suggestProvider }}">
                @else
                    {{-- Если не смогли определить провайдера — даём выбор (без “любой”) --}}
                    <div>
                        <label class="block mb-1 font-medium">Что привязать?</label>
                        <select name="target_provider" class="v-input w-full" required>
                            <option value="">— выберите —</option>
                            @if(!$hasTg)<option value="telegram">Telegram</option>@endif
                            @if(!$hasVk)<option value="vk">VK</option>@endif
                        </select>
                    </div>
                @endif

                <div class="v-actions">
                    <button type="submit" class="v-btn v-btn--primary">Сгенерировать код</button>
                    <a class="v-btn v-btn--secondary" href="{{ route('account.link.show') }}">У меня уже есть код</a>
                </div>
            </form>

            {{-- ===== [OPTIONAL] Быстрая привязка VK напрямую (если вошли через TG) ===== --}}
            @if($provider === 'telegram' && !$hasVk)
                <div class="v-actions mt-3">
                    <a class="v-btn v-btn--secondary" href="{{ route('auth.vk.redirect') }}">Привязать VK напрямую</a>
                </div>
            @endif
        </x-slot>
    </x-action-section>

    <x-section-border />
@endif

            {{-- ================================================================= --}}
            {{-- ===== [JETSTREAM] Пароль / Сессии / Удаление аккаунта ============= --}}
            {{-- ================================================================= --}}

            {{-- Password --}}
            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div>
                    @livewire('profile.update-password-form')
                </div>
                <x-section-border />
            @endif

            {{-- Logout other sessions --}}
            <div>
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            {{-- Delete account --}}
            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <x-section-border />
                <div>
                    @livewire('profile.delete-user-form')
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
