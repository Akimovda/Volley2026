{{-- resources/views/profile/show.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Аккаунт
        </h2>
    </x-slot>

    {{-- FLASH --}}
    @if (session('status'))
        <div class="v-container mt-6">
            <div class="v-alert v-alert--success">
                <div class="v-alert__text">{{ session('status') }}</div>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="v-container mt-6">
            <div class="v-alert v-alert--error">
                <div class="v-alert__text">{{ session('error') }}</div>
            </div>
        </div>
    @endif

    @php
        /** @var \App\Models\User $u */
        $u = auth()->user();
        $u->loadMissing(['city', 'classicPositions', 'beachZones']);

        $posMap = [
            'setter'   => 'Связующий',
            'outside'  => 'Доигровщик',
            'opposite' => 'Диагональный',
            'middle'   => 'Центральный блокирующий',
            'libero'   => 'Либеро',
        ];

        $classicPrimary = optional($u->classicPositions)->firstWhere('is_primary', true)?->position;
        $classicExtras  = optional($u->classicPositions)
            ?->where('is_primary', false)
            ->pluck('position')->values()->all() ?? [];

        $beachPrimary = optional($u->beachZones)->firstWhere('is_primary', true)?->zone;
        $beachExtras  = optional($u->beachZones)
            ?->where('is_primary', false)
            ->pluck('zone')->values()->all() ?? [];

        $age   = method_exists($u, 'ageYears') ? $u->ageYears() : null;
        $birth = $u->birth_date ? $u->birth_date->format('Y-m-d') : '—';

        // provider in session: telegram|vk|yandex|null
        $provider = session('auth_provider');

        $hasTg = !empty($u?->telegram_id);
        $hasVk = !empty($u?->vk_id);
        $hasYa = !empty($u?->yandex_id);

        $linkedCount = (int)$hasTg + (int)$hasVk + (int)$hasYa;

        // “provider looks off” (после неуспешной привязки мог остаться мусор в сессии)
        $providerLooksOff = false;
        if ($provider === 'telegram' && !$hasTg && ($hasVk || $hasYa)) $providerLooksOff = true;
        if ($provider === 'vk' && !$hasVk && ($hasTg || $hasYa)) $providerLooksOff = true;
        if ($provider === 'yandex' && !$hasYa && ($hasTg || $hasVk)) $providerLooksOff = true;

        // link urls
        $vkLinkUrl     = route('auth.vk.redirect', ['link' => 1]);
        $yandexLinkUrl = route('auth.yandex.redirect', ['link' => 1]);

        $allLinked = $hasTg && $hasVk && $hasYa;

        // Telegram widget settings
        $tgBotUsername = config('services.telegram.bot_username'); // username бота, без @

        // ✅ Важно для LINK: Telegram widget не вызывает redirect(), поэтому intent передаем в callback явно
        // Laravel: route(name, params, absolute=true)
        $tgAuthUrl = route('auth.telegram.callback', ['intent' => 'link'], true);

        // can unlink only if more than one provider linked (чтобы не потерять доступ)
        $canUnlink = $linkedCount > 1;

        // UI helpers
        $providerIcon = function (?string $p) {
            $p = $p ?: 'unknown';
            $base = 'display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:9999px;background:#fff;';
            $dot  = 'display:inline-block;width:10px;height:10px;border-radius:9999px;';
            $txt  = 'font-weight:600;font-size:14px;line-height:1;color:#111827;';
            if ($p === 'vk') {
                return '<span style="'.$base.'"><span style="'.$dot.'background:#2787F5;"></span><span style="'.$txt.'">VK</span></span>';
            }
            if ($p === 'telegram') {
                return '<span style="'.$base.'"><span style="'.$dot.'background:#2AABEE;"></span><span style="'.$txt.'">Telegram</span></span>';
            }
            if ($p === 'yandex') {
                return '<span style="'.$base.'"><span style="'.$dot.'background:#FF0000;"></span><span style="'.$txt.'">Yandex</span></span>';
            }
            return '<span style="'.$base.'"><span style="'.$dot.'background:#9CA3AF;"></span><span style="'.$txt.'">—</span></span>';
        };

        $badge = function (bool $ok) {
            if ($ok) {
                return '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:9999px;background:#ECFDF5;color:#065F46;font-weight:700;font-size:12px;">✓ привязан</span>';
            }
            return '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:9999px;background:#F3F4F6;color:#6B7280;font-weight:700;font-size:12px;">— не привязан</span>';
        };

        $miniIcon = function (string $p) {
            $dot  = 'display:inline-block;width:10px;height:10px;border-radius:9999px;';
            if ($p === 'vk') return '<span title="VK" style="'.$dot.'background:#2787F5;"></span>';
            if ($p === 'telegram') return '<span title="Telegram" style="'.$dot.'background:#2AABEE;"></span>';
            if ($p === 'yandex') return '<span title="Yandex" style="'.$dot.'background:#FF0000;"></span>';
            return '<span style="'.$dot.'background:#9CA3AF;"></span>';
        };
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-10">

            {{-- Анкета игрока --}}
            <x-action-section>
                <x-slot name="title">Анкета игрока</x-slot>
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
                            <div class="text-2xl font-bold">
                                {{ method_exists($u, 'displayName') ? $u->displayName() : ($u->name ?? '—') }}
                            </div>

                            @if(!is_null($age))
                                <div class="text-sm text-gray-600 mt-1">{{ $age }} лет</div>
                            @endif

                            {{-- КНОПКА "ИЗМЕНИТЬ АВАТАР" --}}
                            <div class="mt-3">
                                <a href="{{ route('user.photos') }}"
                                   class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                                    Изменить аватар / фото
                                </a>
                            </div>

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

                            <div class="mt-6">
                                <div class="font-semibold text-lg mb-2">Навыки в волейболе</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                                    @if(!empty($u->beach_universal))
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

            {{-- Привязка провайдеров --}}
            <x-action-section>
                <x-slot name="title">Привязка входов</x-slot>
                <x-slot name="description">
                    Привяжите дополнительные способы входа к текущему аккаунту.
                </x-slot>

                <x-slot name="content">
                    <div class="flex flex-col gap-4">
                        <div class="text-sm text-gray-700">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-600">Текущий вход (сессия):</span>
                                {!! $providerIcon($provider) !!}
                            </div>

                            <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                                {{-- Telegram card --}}
                                <div class="v-card">
                                    <div class="v-card__body flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            {!! $miniIcon('telegram') !!}
                                            <span class="font-semibold">Telegram</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            {!! $badge($hasTg) !!}
                                            @if($hasTg && $canUnlink)
                                                <form method="POST"
                                                      action="{{ route('account.unlink.telegram') }}"
                                                      onsubmit="return confirm('Отвязать Telegram от аккаунта?');">
                                                    @csrf
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-2 rounded-lg text-xs font-extrabold bg-white text-gray-900 border border-gray-200 hover:bg-gray-100">
                                                        Отвязать
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- VK card --}}
                                <div class="v-card">
                                    <div class="v-card__body flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            {!! $miniIcon('vk') !!}
                                            <span class="font-semibold">VK</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            {!! $badge($hasVk) !!}
                                            @if($hasVk && $canUnlink)
                                                <form method="POST"
                                                      action="{{ route('account.unlink.vk') }}"
                                                      onsubmit="return confirm('Отвязать VK от аккаунта?');">
                                                    @csrf
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-2 rounded-lg text-xs font-extrabold bg-white text-gray-900 border border-gray-200 hover:bg-gray-100">
                                                        Отвязать
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Yandex card --}}
                                <div class="v-card">
                                    <div class="v-card__body flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            {!! $miniIcon('yandex') !!}
                                            <span class="font-semibold">Yandex</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            {!! $badge($hasYa) !!}
                                            @if($hasYa && $canUnlink)
                                                <form method="POST"
                                                      action="{{ route('account.unlink.yandex') }}"
                                                      onsubmit="return confirm('Отвязать Yandex от аккаунта?');">
                                                    @csrf
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-2 rounded-lg text-xs font-extrabold bg-white text-gray-900 border border-gray-200 hover:bg-gray-100">
                                                        Отвязать
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if(($hasTg || $hasVk || $hasYa) && !$canUnlink)
                                <div class="mt-3 v-alert v-alert--info">
                                    <div class="v-alert__text">
                                        Отвязка последнего способа входа запрещена — сначала привяжите ещё один.
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if($providerLooksOff)
                            <div class="v-alert v-alert--info">
                                <div class="v-alert__text">
                                    Провайдер в сессии мог измениться из‑за неуспешной попытки привязки.
                                    Ориентируйтесь на статусы “привязан/не привязан”.
                                </div>
                            </div>
                        @endif

                        @if($allLinked)
                            <div class="v-alert v-alert--success">
                                <div class="v-alert__text flex items-center gap-2">
                                    <span>🔗</span>
                                    <span class="font-semibold">Все способы входа уже привязаны</span>
                                    <span class="inline-flex items-center gap-2">
                                        {!! $miniIcon('telegram') !!}
                                        {!! $miniIcon('vk') !!}
                                        {!! $miniIcon('yandex') !!}
                                    </span>
                                    <span>✅</span>
                                </div>
                            </div>
                        @else
                            <div class="v-alert v-alert--info">
                                <div class="v-alert__text">
                                    <div class="font-semibold mb-1">Как привязать:</div>
                                    <ol class="list-decimal ml-5 space-y-1">
                                        <li>Нажмите кнопку нужного провайдера ниже.</li>
                                        <li>Подтвердите вход у провайдера.</li>
                                        <li>После возврата на сайт провайдер привяжется к текущему аккаунту.</li>
                                    </ol>
                                </div>
                            </div>

                            <div class="v-actions flex flex-col md:flex-row gap-3 flex-wrap items-start">
                                @if(!$hasVk)
                                    <a class="v-btn v-btn--secondary" href="{{ $vkLinkUrl }}">
                                        <span class="inline-flex items-center gap-2">
                                            {!! $miniIcon('vk') !!}
                                            <span>Привязать VK</span>
                                        </span>
                                    </a>
                                @endif

                                @if(!$hasYa)
                                    <a class="v-btn v-btn--secondary" href="{{ $yandexLinkUrl }}">
                                        <span class="inline-flex items-center gap-2">
                                            {!! $miniIcon('yandex') !!}
                                            <span>Привязать Yandex</span>
                                        </span>
                                    </a>
                                @endif

                                @if(!$hasTg)
                                    <div class="v-card">
                                        <div class="v-card__body">
                                            <div class="text-sm text-gray-700 mb-2 flex items-center gap-2">
                                                {!! $miniIcon('telegram') !!}
                                                <span class="font-semibold">Привязать Telegram</span>
                                            </div>

                                            @if(empty($tgBotUsername))
                                                <div class="text-sm text-red-600">
                                                    Не задан <code>services.telegram.bot_username</code> (TELEGRAM_BOT_USERNAME).
                                                </div>
                                            @else
                                                <script
                                                    async
                                                    src="https://telegram.org/js/telegram-widget.js?22"
                                                    data-telegram-login="{{ $tgBotUsername }}"
                                                    data-size="large"
                                                    data-radius="10"
                                                    data-userpic="true"
                                                    data-request-access="write"
                                                    data-auth-url="{{ $tgAuthUrl }}">
                                                </script>
                                            @endif

                                            <div class="text-xs text-gray-500 mt-2">
                                                Если кнопка не появляется — проверьте <code>TELEGRAM_BOT_USERNAME</code> (без @) и разрешённый домен у бота в BotFather.
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </x-slot>
            </x-action-section>

            <x-section-border />

            {{-- ✅ Приватность --}}
            <x-action-section>
                <x-slot name="title">Приватность</x-slot>
                <x-slot name="description">
                    Разрешить другим пользователям писать вам в Telegram/VK со страницы профиля.
                </x-slot>

                <x-slot name="content">
                    <form method="POST" action="{{ route('profile.contact_privacy.update') }}">
                        @csrf

                        <div class="v-card">
                            <div class="v-card__body space-y-3">
                                <label class="flex items-center gap-3">
                                    <input type="hidden" name="allow_user_contact" value="0">
                                    <input type="checkbox" name="allow_user_contact" value="1"
                                           @checked((bool)($u->allow_user_contact ?? true))>
                                    <span class="text-sm font-semibold">
                                        Могут ли со мной связаться другие пользователи (Telegram/VK)
                                    </span>
                                </label>

                                <button type="submit" class="v-btn v-btn--secondary">
                                    Сохранить
                                </button>

                                <div class="text-xs text-gray-500">
                                    Кнопки “Написать” видны только авторизованным пользователям и только если вы включили этот переключатель.
                                </div>
                            </div>
                        </div>
                    </form>
                </x-slot>
            </x-action-section>

            <x-section-border />

            {{-- ✅ Удаление аккаунта — заявка админу (по-взрослому) --}}
            <x-action-section>
                <x-slot name="title">Удаление аккаунта</x-slot>
                <x-slot name="description">
                    Самостоятельное удаление отключено. Вы можете отправить администратору заявку на удаление.
                </x-slot>

                <x-slot name="content">
                    <div class="v-alert v-alert--info">
                        <div class="v-alert__text text-sm">
                            Заявка попадёт администратору. После обработки аккаунт будет удалён/деактивирован.
                        </div>
                    </div>

                    <form method="POST"
                          action="{{ route('account.delete.request') }}"
                          class="mt-4"
                          onsubmit="return confirm('Отправить заявку на удаление аккаунта администратору?');">
                        @csrf
                        <button type="submit" class="v-btn v-btn--secondary">
                            Запросить удаление аккаунта
                        </button>
                    </form>
                </x-slot>
            </x-action-section>

            {{-- ❌ УБРАНО:
                 - @livewire('profile.update-password-form')
                 - @livewire('profile.delete-user-form')
            --}}
        </div>
    </div>
</x-app-layout>
