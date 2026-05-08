<x-voll-layout body_class="premium-page">

    <x-slot name="title">{{ __('profile.premium_title') }}</x-slot>

    <x-slot name="description">
        Приоритет записи, друзья, аналитика и умные уведомления для волейболистов
    </x-slot>

    <x-slot name="canonical">{{ route('premium.index') }}</x-slot>

    <x-slot name="style">
        <style>
            .premium-plans {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 2rem;
                margin-top: 4rem;
            }
            @media (max-width: 992px) {
                .premium-plans { grid-template-columns: repeat(2, 1fr); }
            }
            @media (max-width: 480px) {
                .premium-plans { grid-template-columns: 1fr; }
            }

            .plan-card {
                border-radius: 2rem;
                padding: 3rem 2rem;
                text-align: center;
                border: 0.2rem solid var(--border-color, #e0e0e0);
                position: relative;
                transition: transform .2s, box-shadow .2s;
            }
            .plan-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 1rem 3rem rgba(0,0,0,.1);
            }
            .plan-card.popular {
                border-color: #f5c842;
            }
            .plan-card .badge-popular {
                position: absolute;
                top: -1.3rem;
                left: 50%;
                transform: translateX(-50%);
                background: #f5c842;
                color: #333;
                font-size: 1.2rem;
                font-weight: 700;
                padding: .4rem 1.6rem;
                border-radius: 2rem;
                white-space: nowrap;
                letter-spacing: .05em;
            }
            .plan-card .plan-label {
                font-size: 1.3rem;
                color: #888;
                text-transform: uppercase;
                letter-spacing: .05em;
                margin-bottom: 1rem;
            }
            .plan-card .plan-price {
                font-size: 4rem;
                font-weight: 800;
                line-height: 1;
                margin-bottom: .5rem;
            }
            .plan-card .plan-economy {
                font-size: 1.3rem;
                font-weight: 600;
                color: #4caf50;
                min-height: 2rem;
                margin-bottom: 2rem;
            }

            /* Преимущества */
            .premium-features {
                margin: 4rem 0;
            }
            .premium-features .feature-item {
                display: flex;
                gap: 1.5rem;
                align-items: flex-start;
                margin-bottom: 2rem;
            }
            .feature-item .feature-icon {
                font-size: 2.6rem;
                line-height: 1.2;
                flex-shrink: 0;
            }
            .feature-item .feature-title {
                font-size: 1.6rem;
                font-weight: 700;
                margin-bottom: .4rem;
            }
            .feature-item .feature-desc {
                font-size: 1.4rem;
                color: #888;
            }

            /* Активный баннер */
            @keyframes goldShimmer {
                0%   { background-position: 0% 50%; }
                50%  { background-position: 100% 50%; }
                100% { background-position: 0% 50%; }
            }
            .premium-active-banner {
                background: linear-gradient(
                    120deg,
                    #f5c842 0%,
                    #ffe066 20%,
                    #e6a800 35%,
                    #fff0a0 50%,
                    #f5c842 65%,
                    #c8860a 80%,
                    #f5c842 100%
                );
                background-size: 300% 300%;
                animation: goldShimmer 4s ease infinite;
                border-radius: 1.6rem;
                padding: 2rem 3rem;
                text-align: center;
                color: #5a3a00;
                font-weight: 700;
                margin-bottom: 3rem;
                font-size: 1.7rem;
                box-shadow:
                    0 0.4rem 2rem rgba(245, 180, 30, 0.4),
                    0 0.1rem 0.4rem rgba(245, 180, 30, 0.3);
                text-shadow: 0 0.1rem 0.2rem rgba(255,255,255,0.4);
            }

            body.dark .plan-card {
                border-color: #333;
            }
            body.dark .plan-card.popular {
                border-color: #f5c842;
            }
        </style>
    </x-slot>

    <x-slot name="h1">{{ __('profile.premium_h1') }}</x-slot>

    <x-slot name="t_description">
        Больше возможностей для игры, общения и роста
    </x-slot>

    <div class="container">
        <div class="ramka">

            @if($active)
            <div class="premium-active-banner">
                👑 Ваш Premium активен до <strong>{{ $active->expires_at->format('d.m.Y') }}</strong>
                &nbsp;·&nbsp;
                {{ match($active->plan) {
                    'trial'   => 'Пробный период',
                    'month'   => '1 месяц',
                    'quarter' => '3 месяца',
                    'year'    => 'Год',
                } }}
            </div>
            <div class="text-center mt-2">
                <a href="{{ route('premium.settings') }}" class="btn btn-secondary">⚙️ Настройки уведомлений</a>
            </div>
            @endif

            {{-- Продление при активном Premium --}}
            @if($active)
            <div class="card mb-2" style="border-radius:1.6rem;padding:2rem;">
                <div class="f-17 b-600 mb-2">Продлить подписку</div>
                <div class="row row2">
                    @foreach([
                        ['month',   '1 месяц',   '199₽',  ''],
                        ['quarter', '3 месяца',  '499₽',  'Выгода 15%'],
                        ['year',    '1 год',     '1699₽', 'Выгода 30%'],
                    ] as [$plan, $label, $price, $economy])
                    <div class="col-md-4">
                        <div class="card text-center" style="padding:1.5rem;">
                            <div class="f-15 b-600">{{ $label }}</div>
                            <div class="f-22 b-700 cd">{{ $price }}</div>
                            @if($economy)
                            <div class="f-13" style="color:#4caf50;">{{ $economy }}</div>
                            @endif
                            <form method="POST" action="{{ route('premium.renew') }}" class="form mt-1">
                                @csrf
                                <input type="hidden" name="plan" value="{{ $plan }}">
                                <button class="btn btn-secondary" style="width:100%;">Продлить</button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Ожидает оплаты --}}
            @if($pending && !$active)
            <div class="card mb-2" style="border:0.2rem solid #f5c842;border-radius:1.6rem;padding:2rem;">
                <div class="f-17 b-600 mb-1">⏳ Ожидаем подтверждения оплаты</div>
                <div class="f-15 mb-2" style="opacity:.6;">
                    План: {{ match($pending->plan) {
                        'month'   => '1 месяц — 199₽',
                        'quarter' => '3 месяца — 499₽',
                        'year'    => '1 год — 1699₽',
                    } }}
                </div>
                @if($platformPayment && !$pending->payment?->user_confirmed)
                <div class="f-15 mb-2">
                    Переведите оплату:
                    @if($platformPayment->method === 'tbank_link' && $platformPayment->tbank_link)
                    <a href="{{ $platformPayment->tbank_link }}" target="_blank" class="btn btn-secondary">🏦 Открыть Т-Банк</a>
                    @elseif($platformPayment->method === 'sber_link' && $platformPayment->sber_link)
                    <a href="{{ $platformPayment->sber_link }}" target="_blank" class="btn btn-secondary">💚 Открыть Сбер</a>
                    @endif
                </div>
                <form method="POST" action="{{ route('premium.confirm', $pending->payment_id) }}">
                    @csrf
                    <button class="btn">✅ Я оплатил</button>
                </form>
                @elseif($pending->payment?->user_confirmed)
                <div class="f-16 cs b-600">✅ Оплата подтверждена вами — ожидаем проверки администратором</div>
                @endif
            </div>
            @endif

            {{-- Преимущества --}}
            <div class="premium-features">
                <div class="row">
                    @foreach([
                        ['👑', 'Золотой аватар',       'Золотое свечение — вас заметят среди игроков'],
                        ['🥇', 'Приоритет записи',     'Ваше место сразу после абонементов, первый в очереди ожидания'],
                        ['👥', 'Друзья и гости',       'Список друзей, уведомления об их играх, кто заходил на ваш профиль'],
                        ['📊', 'Аналитика игр',        'История, статистика по позициям, организаторам и площадкам'],
                        ['🔔', 'Умные уведомления',    'Недельная сводка и фильтр игр по вашему уровню'],
                    ] as [$icon, $title, $desc])
                    <div class="col-6">
                        <div class="feature-item">
                            <div class="feature-icon">{{ $icon }}</div>
                            <div>
                                <div class="feature-title">{{ $title }}</div>
                                <div class="feature-desc">{{ $desc }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Тарифы --}}
            <div class="premium-plans">

                {{-- Пробный --}}
                <div class="plan-card">
                    <div class="plan-label">Пробный</div>
                    <div class="plan-price">0₽</div>
                    <div class="plan-economy">7 дней</div>
                    @auth
                    <form method="POST" action="{{ route('premium.trial') }}">
                        @csrf
                        <button class="btn btn-secondary w-100" {{ $active ? 'disabled' : '' }}>
                            Попробовать
                        </button>
                    </form>
                    @else
                    <a href="{{ route('login') }}" class="btn btn-secondary w-100">Войти</a>
                    @endauth
                </div>

                {{-- Месяц --}}
                <div class="plan-card">
                    <div class="plan-label">1 месяц</div>
                    <div class="plan-price">199₽</div>
                    <div class="plan-economy"></div>
                    @auth
                    <form method="POST" action="{{ route('premium.pay') }}">
                        @csrf
                        <input type="hidden" name="plan" value="month">
                        <button class="btn w-100">Подключить</button>
                    </form>
                    @else
                    <a href="{{ route('login') }}" class="btn w-100">Войти</a>
                    @endauth
                </div>

                {{-- 3 месяца --}}
                <div class="plan-card popular">
                    <div class="badge-popular">ПОПУЛЯРНЫЙ</div>
                    <div class="plan-label">3 месяца</div>
                    <div class="plan-price">499₽</div>
                    <div class="plan-economy">Выгода 15%</div>
                    @auth
                    <form method="POST" action="{{ route('premium.pay') }}">
                        @csrf
                        <input type="hidden" name="plan" value="quarter">
                        <button class="btn w-100" style="background:#f5c842;color:#333;font-weight:700;">
                            Подключить
                        </button>
                    </form>
                    @else
                    <a href="{{ route('login') }}" class="btn w-100" style="background:#f5c842;color:#333;font-weight:700;">Войти</a>
                    @endauth
                </div>

                {{-- Год --}}
                <div class="plan-card">
                    <div class="plan-label">1 год</div>
                    <div class="plan-price">1699₽</div>
                    <div class="plan-economy">Выгода 30%</div>
                    @auth
                    <form method="POST" action="{{ route('premium.pay') }}">
                        @csrf
                        <input type="hidden" name="plan" value="year">
                        <button class="btn w-100">Подключить</button>
                    </form>
                    @else
                    <a href="{{ route('login') }}" class="btn w-100">Войти</a>
                    @endauth
                </div>

            </div>



        </div>
    </div>

    <x-slot name="script">
        <script>
        document.addEventListener("DOMContentLoaded", function() {

            @if(session("success"))
            swal({
                title: "Готово!",
                text: "{{ session("success") }}",
                icon: "success",
                button: "Отлично!",
            });
            @endif

            @if(session("error"))
            swal({
                title: "Ошибка",
                text: "{{ session("error") }}",
                icon: "error",
                button: "Понятно",
            });
            @endif

            @if(session("status"))
            swal({
                title: "Готово!",
                text: "{{ session("status") }}",
                icon: "success",
                button: "OK",
            });
            @endif

            @if(session("payment_pending"))
            swal({
                title: "💳 Оплатите подписку",
                text: "Переведите оплату по ссылке и нажмите «Я оплатил». После проверки Premium будет активирован.",
                icon: "info",
                button: "Понятно",
            });
            @endif

        });
        </script>
    </x-slot>

</x-voll-layout>