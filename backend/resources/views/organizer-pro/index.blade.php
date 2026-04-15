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

        {{-- Преимущества --}}
        <div class="ramka">
            <h2 class="-mt-05">Что входит в Организатор Pro</h2>
            <div class="row row2">
                @foreach([
                    ['🤖', 'Свой бот',          'Анонсы от вашего персонального бота в Telegram и MAX. Ваш бренд, ваш стиль.'],
                    ['🌐', 'Виджет на сайт',     'Встройте список мероприятий на ваш сайт через iFrame или JS-скрипт.'],
                    ['📊', 'Аналитика',           'Детальная статистика по мероприятиям, заполняемости и активности игроков.'],
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

        {{-- Тарифы --}}
        <div class="ramka">
            <h2 class="-mt-05">Тарифы</h2>
            <div class="row row2">
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
                                <form method="POST" action="{{ route('organizer_pro.activate') }}">
                                    @csrf
                                    <input type="hidden" name="plan" value="{{ $planKey }}">
                                    <button type="submit"
                                            class="btn w-100 {{ $planKey === 'quarter' ? '' : 'btn-secondary' }}">
                                        @if($plan['price'] === 0)
                                            Попробовать бесплатно
                                        @else
                                            Подключить
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
                * Оплата через Т-Банк. После оплаты подписка активируется автоматически.
                Пробный период — только для новых пользователей, 1 раз.
            </div>
        </div>

        {{-- FAQ --}}
        <div class="ramka">
            <h2 class="-mt-05">Частые вопросы</h2>
            <div class="row row2">
                @foreach([
                    ['Как создать своего бота?',
                     'Откройте @BotFather в Telegram, создайте бота командой /newbot, скопируйте токен и добавьте его в настройках профиля → Каналы уведомлений.'],
                    ['Как встроить виджет на сайт?',
                     'После активации подписки перейдите в профиль → Виджет на сайт. Там вы найдёте готовый код для вставки — iFrame или JS-скрипт.'],
                    ['Можно ли отменить подписку?',
                     'Да, в любой момент. Подписка действует до конца оплаченного периода.'],
                    ['Что будет с ботом после окончания подписки?',
                     'Бот останется подключённым, но анонсы перестанут отправляться через него — вернётся системный бот сервиса.'],
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
