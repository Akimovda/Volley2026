{{-- resources/views/organizer-pro/index.blade.php --}}
<x-voll-layout body_class="organizer-pro-page">

    <x-slot name="title">Организатор Pro</x-slot>
    <x-slot name="h1">⭐ Организатор Pro</x-slot>
    <x-slot name="h2">Профессиональный инструментарий для организаторов</x-slot>
    <x-slot name="t_description">Свой бот, виджет на сайт и расширенные возможности.</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Организатор Pro</span>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>

    <x-slot name="style">
    <style>
        .pro-plan-card {
            position: relative;
            transition: transform .2s, box-shadow .2s;
        }
        .pro-plan-card:hover {
            transform: translateY(-3px);
        }
        .pro-plan-card.is-popular {
            border: 0.2rem solid #2967BA !important;
        }
        .pro-badge {
            display: inline-block;
            background: #E7612F;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 600;
            padding: .3rem 1rem;
            border-radius: 2rem;
            margin-bottom: .8rem;
            letter-spacing: .03em;
        }
        .pro-price {
            font-size: 3.2rem;
            font-weight: 700;
            color: #2967BA;
            line-height: 1.1;
        }
        body.dark .pro-price { color: #58a6ff; }
        .pro-feature-list {
            list-style: none;
            padding: 0;
            margin: 0 0 1.5rem;
        }
        .pro-feature-list li {
            font-size: 1.4rem;
            padding: .4rem 0;
            display: flex;
            align-items: flex-start;
            gap: .6rem;
        }
        .pro-feature-list li::before {
            content: '✓';
            color: #2967BA;
            font-weight: 700;
            flex-shrink: 0;
        }
        .pro-icon-card {
            text-align: center;
            padding: 2rem 1.5rem;
        }
        .pro-icon-card .icon {
            font-size: 3.6rem;
            margin-bottom: 1rem;
            display: block;
        }
        .pro-active-banner {
            background: linear-gradient(135deg, rgba(41,103,186,.12) 0%, rgba(41,103,186,.06) 100%);
            border: .15rem solid rgba(41,103,186,.3);
            border-radius: 1.4rem;
            padding: 2rem 2.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .pro-active-banner .star {
            font-size: 3.6rem;
            flex-shrink: 0;
        }
    </style>
    </x-slot>

    <div class="container">

        @if(session('status'))
            <div class="alert alert-success mb-2">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger mb-2">{{ $errors->first() }}</div>
        @endif

        {{-- Активная подписка --}}
        @auth
        @if($active)
        <div class="ramka">
            <div class="pro-active-banner">
                <span class="star">⭐</span>
                <div>
                    <div class="f-18 b-600 mb-05">Организатор Pro активен</div>
                    <div class="f-15" style="opacity:.7">
                        Тариф: <strong>{{ \App\Models\OrganizerSubscription::planLabel($active->plan) }}</strong> —
                        действует до <strong>{{ $active->expires_at->format('d.m.Y') }}</strong>
                        ({{ $active->expires_at->diffForHumans() }})
                    </div>
                </div>
            </div>
        </div>
        @endif
        @endauth

        {{-- Ожидает оплаты --}}
        @auth
        @if($pending)
        <div class="ramka">
            <div class="card mb-1" style="border:0.2rem solid #f5c842">
                <div class="f-17 b-600 mb-1">⏳ Заявка на «{{ \App\Models\OrganizerSubscription::planLabel($pending->plan) }}» — {{ number_format((float) $pending->amount_rub, 0, '.', ' ') }} ₽</div>
                @if($pendingPayment && !$pendingPayment->user_confirmed)
                <div class="f-15 mb-1" style="opacity:.75">
                    Переведите оплату и нажмите «Я оплатил» — после проверки мы активируем подписку
                    @if($active) (срок прибавится к текущему) @endif.
                </div>
                @if($platformPayment)
                <div class="mb-1">
                    @if($platformPayment->method === 'tbank_link' && $platformPayment->tbank_link)
                    <a href="{{ $platformPayment->tbank_link }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">🏦 Открыть Т-Банк</a>
                    @elseif($platformPayment->method === 'sber_link' && $platformPayment->sber_link)
                    <a href="{{ $platformPayment->sber_link }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">💚 Открыть Сбер</a>
                    @endif
                </div>
                @endif
                <form method="POST" action="{{ route('organizer_pro.confirm_payment', $pending->payment_id) }}">
                    @csrf
                    <button class="btn">✅ Я оплатил</button>
                </form>
                @else
                <div class="f-16 b-600 cs">✅ Оплата отмечена вами — ожидаем проверки администратором</div>
                @endif
            </div>
        </div>
        @endif
        @endauth

        {{-- Преимущества --}}
        <div class="ramka">
            <h2 class="-mt-05">Что входит в Организатор Pro</h2>
            <div class="row">
                @foreach([
                    ['🤖', 'Свой бот',          'Анонсы от вашего персонального бота в Telegram и MAX. Ваш бренд, ваш стиль.'],
                    ['🌐', 'Виджет на сайт',     'Встройте список мероприятий на ваш сайт через iFrame или JS-скрипт.'],
                    ['📊', 'Аналитика',           'Аналитика игроков (аудитория, топы, отток, экспорт CSV/PDF) и аналитика турниров.'],
                    ['🔔', 'Умные уведомления',   'Автоматические напоминания и сводки для участников ваших мероприятий.'],
                    ['⚡', 'Приоритет',           'Ваши мероприятия в топе поиска и рекомендаций платформы.'],
                    ['🛠', 'Поддержка',           'Приоритетная поддержка и ранний доступ к новым функциям.'],
                ] as [$icon, $title, $desc])
                <div class="col-lg-4 col-sm-6">
                    <div class="card pro-icon-card mb-1">
                        <span class="icon">{{ $icon }}</span>
                        <div class="f-16 b-600 mb-05">{{ $title }}</div>
                        <div class="f-14" style="opacity:.6">{{ $desc }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- White Label: своё приложение --}}
        <div class="ramka">
            <h2 class="-mt-05">📱 Своё приложение — White Label</h2>
            <p class="f-15 mb-2" style="opacity:.75">
                Ваш клуб, школа или лига получает отдельное мобильное приложение под своим именем:
                игроки устанавливают его из магазина приложений, видят только ваши мероприятия
                и ваш фирменный стиль. Работает на той же платформе — записи, оплаты, турниры и
                уведомления остаются общими, ничего переносить не нужно.
            </p>
            <div class="row">
                @foreach([
                    ['🎨', 'Ваш бренд',        'Название, иконка и экран загрузки, логотип и цвета в светлой и тёмной теме.'],
                    ['🏐', 'Только ваши игры', 'В ленте приложения — мероприятия вашей организации, без чужих анонсов.'],
                    ['🧭', 'Своё меню',        'Скрывайте ненужные разделы и добавляйте свои ссылки — на сайт, чат или правила клуба.'],
                    ['📲', 'В магазинах',      'App Store и RuStore: игроки ставят приложение как обычное, уведомления приходят пушем.'],
                ] as [$icon, $title, $desc])
                <div class="col-lg-3 col-sm-6">
                    <div class="card pro-icon-card mb-1">
                        <span class="icon">{{ $icon }}</span>
                        <div class="f-16 b-600 mb-05">{{ $title }}</div>
                        <div class="f-14" style="opacity:.6">{{ $desc }}</div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="card mb-1">
                <div class="f-16 b-600 mb-05">Как подключить</div>
                <ul class="pro-feature-list" style="margin-bottom:1rem">
                    <li>Напишите нам в Telegram (@akimovda) — обсудим название, цвета и состав меню.</li>
                    <li>Мы настроим оформление и подготовим приложение к публикации.</li>
                    <li>Вы передаёте игрокам ссылку на установку — они входят тем же аккаунтом.</li>
                </ul>
                <div class="f-14 mb-1" style="opacity:.6">
                    White Label подключается отдельно от подписки Организатор Pro. Стоимость зависит от объёма настройки — по запросу.
                </div>
                <a href="https://t.me/akimovda" target="_blank" rel="noopener noreferrer" class="btn">Обсудить подключение в Telegram</a>
            </div>
        </div>

        {{-- Тарифы --}}
        <div class="ramka">
            <h2 class="-mt-05">Тарифы</h2>
            <div class="row">
                @foreach($plans as $planKey => $plan)
                <div class="col-lg-3 col-sm-6">
                    <div class="card pro-plan-card mb-1 {{ $planKey === 'quarter' ? 'is-popular' : '' }}">

                        @if($plan['badge'])
                            <div class="pro-badge">{{ $plan['badge'] }}</div>
                        @endif

                        <div class="f-18 b-600 mb-05">{{ $plan['label'] }}</div>
                        @if($plan['sublabel'])
                            <div class="f-13 mb-1" style="opacity:.5">{{ $plan['sublabel'] }}</div>
                        @endif

                        <div class="pro-price mb-1">
                            @if($plan['price'] === 0)
                                Бесплатно
                            @else
                                {{ number_format($plan['price'], 0, '.', ' ') }} <span class="f-18">₽</span>
                            @endif
                        </div>

                        <ul class="pro-feature-list">
                            @foreach($plan['features'] as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>

                        @auth
                            @if($active && $active->plan === $planKey)
                                <button class="btn btn-secondary w-100" disabled style="opacity:.5;cursor:default">
                                    ✅ Текущий тариф
                                </button>
                            @else
                                <form method="POST" action="{{ $plan['price'] === 0 ? route('organizer_pro.activate') : route('organizer_pro.pay') }}">
                                    @csrf
                                    <input type="hidden" name="plan" value="{{ $planKey }}">
                                    <button type="submit"
                                            class="btn w-100 {{ $planKey === 'quarter' ? '' : 'btn-secondary' }}">
                                        @if($plan['price'] === 0)
                                            Попробовать бесплатно
                                        @elseif($active)
                                            Продлить
                                        @else
                                            Оплатить
                                        @endif
                                    </button>
                                </form>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="btn btn-secondary w-100">
                                Войти для подключения
                            </a>
                        @endauth
                    </div>
                </div>
                @endforeach
            </div>
            <div class="f-13 mt-1" style="opacity:.5">
                * Оплата переводом. После оплаты нажмите «Я оплатил» — подписка активируется после проверки платежа.
                Пробный период — только для новых пользователей, 1 раз.
            </div>
        </div>

        {{-- FAQ --}}
        <div class="ramka">
            <h2 class="-mt-05">Частые вопросы</h2>
            <div class="row">
                @foreach([
                    ['Как создать своего бота?',
                     'Откройте @BotFather в Telegram, создайте бота командой /newbot, скопируйте токен и добавьте его в настройках профиля → Каналы уведомлений.'],
                    ['Как встроить виджет на сайт?',
                     'После активации подписки перейдите в профиль → Виджет на сайт. Там вы найдёте готовый код для вставки — iFrame или JS-скрипт.'],
                    ['Можно ли отменить подписку?',
                     'Да, в любой момент. Подписка действует до конца оплаченного периода.'],
                    ['Что будет с ботом после окончания подписки?',
                     'Бот останется подключённым, но пока подписка не продлена, анонсы идут через системного бота сервиса. Чтобы они продолжили приходить в ваш канал или чат, добавьте системного бота администратором. После продления анонсы снова пойдут от вашего бота.'],
                    ['Что такое приложение White Label?',
                     'Отдельное мобильное приложение под вашим именем, иконкой и цветами: игроки видят только ваши мероприятия и входят тем же аккаунтом. Подробности — в разделе «Своё приложение» выше.'],
                    ['Входит ли White Label в Организатор Pro?',
                     'Нет, это отдельная услуга. Она подключается независимо от подписки Pro, стоимость зависит от объёма настройки — напишите нам в Telegram @akimovda.'],
                ] as [$q, $a])
                <div class="col-lg-6">
                    <div class="card mb-1">
                        <div class="f-15 b-600 mb-05">{{ $q }}</div>
                        <div class="f-14" style="opacity:.6">{{ $a }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>

</x-voll-layout>
