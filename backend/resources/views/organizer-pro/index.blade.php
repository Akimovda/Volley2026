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

    <div class="container">

        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if($errors->any())
            <div class="ramka"><div class="alert alert-error">{{ $errors->first() }}</div></div>
        @endif

        {{-- Активная подписка --}}
        @auth
        @if($active)
        <div class="ramka">
            <div class="alert alert-success">
                <div class="alert-title">✅ Организатор Pro активен</div>
                <p>
                    Тариф: <strong>{{ \App\Models\OrganizerSubscription::planLabel($active->plan) }}</strong> —
                    действует до <strong>{{ $active->expires_at->format('d.m.Y') }}</strong>
                    ({{ $active->expires_at->diffForHumans() }})
                </p>
            </div>
        </div>
        @endif
        @endauth

        {{-- Преимущества --}}
        <div class="ramka">
            <h2 class="mt-0">Что входит в Организатор Pro</h2>
            <div class="row row2">
                @foreach([
                    ['🤖', 'Свой бот', 'Анонсы от вашего персонального бота в Telegram и MAX. Ваш бренд, ваш стиль.'],
                    ['🌐', 'Виджет на сайт', 'Встройте список мероприятий на ваш сайт через iFrame или JS-скрипт.'],
                    ['📊', 'Аналитика', 'Детальная статистика по мероприятиям, заполняемости и активности игроков.'],
                    ['🔔', 'Умные уведомления', 'Автоматические напоминания и сводки для участников ваших мероприятий.'],
                    ['⚡', 'Приоритет', 'Ваши мероприятия в топе поиска и рекомендаций платформы.'],
                    ['🛠', 'Поддержка', 'Приоритетная поддержка и ранний доступ к новым функциям.'],
                ] as [$icon, $title, $desc])
                <div class="col-lg-4 col-sm-6">
                    <div class="card mb-1">
                        <div class="f-24 mb-05">{{ $icon }}</div>
                        <div class="f-16 b-700 mb-05">{{ $title }}</div>
                        <div class="f-14 text-muted">{{ $desc }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Тарифы --}}
        <div class="ramka">
            <h2 class="mt-0">Тарифы</h2>
            <div class="row row2">
                @foreach($plans as $planKey => $plan)
                <div class="col-lg-3 col-sm-6">
                    <div class="card mb-1 {{ $planKey === 'quarter' ? 'card-highlight' : '' }}"
                         style="{{ $planKey === 'quarter' ? 'border-color:var(--primary);' : '' }}">

                        @if($plan['badge'])
                            <div class="badge badge-orange mb-1">{{ $plan['badge'] }}</div>
                        @endif

                        <div class="f-20 b-700">{{ $plan['label'] }}</div>
                        @if($plan['sublabel'])
                            <div class="f-13 text-muted mb-05">{{ $plan['sublabel'] }}</div>
                        @endif

                        <div class="f-28 b-700 my-1" style="color:var(--primary)">
                            @if($plan['price'] === 0)
                                Бесплатно
                            @else
                                {{ number_format($plan['price'], 0, '.', ' ') }} ₽
                            @endif
                        </div>

                        <ul class="list f-14 mb-1">
                            @foreach($plan['features'] as $feature)
                                <li>✓ {{ $feature }}</li>
                            @endforeach
                        </ul>

                        @auth
                            @if($active && $active->plan === $planKey)
                                <button class="btn btn-disabled w-100 btn-small" disabled>
                                    Текущий тариф
                                </button>
                            @else
                                <form method="POST" action="{{ route('organizer_pro.activate') }}">
                                    @csrf
                                    <input type="hidden" name="plan" value="{{ $planKey }}">
                                    <button type="submit"
                                            class="btn w-100 btn-small {{ $planKey === 'quarter' ? 'btn-primary' : 'btn-secondary' }}">
                                        @if($plan['price'] === 0)
                                            Попробовать бесплатно
                                        @else
                                            Подключить
                                        @endif
                                    </button>
                                </form>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="btn btn-secondary w-100 btn-small">
                                Войти для подключения
                            </a>
                        @endauth
                    </div>
                </div>
                @endforeach
            </div>
            <div class="f-13 text-muted mt-1">
                * Оплата через Т-Банк. После оплаты подписка активируется автоматически.
                Пробный период — только для новых пользователей, 1 раз.
            </div>
        </div>

        {{-- FAQ --}}
        <div class="ramka">
            <h2 class="mt-0">Частые вопросы</h2>
            <div class="row row2">
                @foreach([
                    ['Как создать своего бота?', 'Откройте @BotFather в Telegram, создайте бота командой /newbot, скопируйте токен и добавьте его в настройках профиля → Каналы уведомлений.'],
                    ['Как встроить виджет на сайт?', 'После активации подписки перейдите в профиль → Виджет на сайт. Там вы найдёте готовый код для вставки — iFrame или JS-скрипт.'],
                    ['Можно ли отменить подписку?', 'Да, в любой момент. Подписка действует до конца оплаченного периода.'],
                    ['Что будет с ботом после окончания подписки?', 'Бот останется подключённым, но анонсы перестанут отправляться через него — вернётся системный бот сервиса.'],
                ] as [$q, $a])
                <div class="col-lg-6">
                    <div class="card mb-1">
                        <div class="f-15 b-700 mb-05">{{ $q }}</div>
                        <div class="f-14 text-muted">{{ $a }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>

</x-voll-layout>
