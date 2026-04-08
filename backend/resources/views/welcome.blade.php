{{-- resources/views/welcome.blade.php --}}
<x-voll-layout body_class="main-page">

    <x-slot name="title">Your Volley Club — волейбольный сервис</x-slot>
    <x-slot name="description">Платформа для волейболистов, тренеров, организаторов и спортивных центров. Записывайтесь на игры, находите партнёров, управляйте мероприятиями.</x-slot>
    <x-slot name="canonical">{{ url('/') }}</x-slot>
    <x-slot name="h1">Your Volley Club</x-slot>
    <x-slot name="h2">Волейбольный сервис</x-slot>
    <x-slot name="t_description">Объединяем волейбольное сообщество — от любителей до профессионалов</x-slot>

    <x-slot name="d_description">
        <div class="d-flex flex-wrap gap-2 mt-2" data-aos="fade-up" data-aos-delay="200">
            @guest
                <a href="{{ route('register') }}" class="btn">🏐 Начать бесплатно</a>
                <a href="{{ route('events.index') }}" class="btn btn-secondary">Смотреть игры</a>
            @else
                <a href="{{ route('events.index') }}" class="btn">🏐 Найти игру</a>
                <a href="{{ route('dashboard') }}" class="btn btn-secondary">Мой профиль</a>
            @endguest
        </div>
    </x-slot>

    <x-slot name="style">
        <style>
            .home-section { margin-bottom: 0; }

            /* Цифры-достижения */
            .stat-number {
                font-size: 4.8rem;
                font-weight: 800;
                line-height: 1;
                color: var(--cd);
            }
            .stat-label {
                font-size: 1.4rem;
                opacity: .65;
                margin-top: .4rem;
            }

            /* Карточки аудиторий */
            .audience-card {
                border-radius: 1.6rem;
                padding: 2.4rem 2rem;
                height: 100%;
                transition: transform .2s;
            }
            .audience-card:hover { transform: translateY(-4px); }
            .audience-icon { font-size: 4rem; line-height: 1; margin-bottom: 1.2rem; }
            .audience-title { font-size: 2rem; font-weight: 700; margin-bottom: .8rem; }
            .audience-card .list li { font-size: 1.5rem; line-height: 1.5; }
            .audience-card .list li::before { content: "✓ "; color: var(--cd); font-weight: 700; }

            /* Таб-переключатель аудиторий */
            .audience-tabs .tab { font-size: 1.6rem; }

            /* Как это работает */
            .step-num {
                width: 4.8rem;
                height: 4.8rem;
                border-radius: 50%;
                background: var(--cd);
                color: #fff;
                font-size: 2rem;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            /* Блок CTA */
            .cta-block {
                border-radius: 2rem;
                padding: 4rem 3rem;
                text-align: center;
            }
            .cta-block .f-32 { font-size: 3.2rem; font-weight: 700; }

            @media (max-width: 768px) {
                .stat-number { font-size: 3.6rem; }
                .cta-block { padding: 3rem 1.6rem; }
            }
        </style>
    </x-slot>

    <div class="container">

        {{-- ===== ЦИФРЫ ===== --}}
        <div class="ramka" data-aos="fade-up">
            <div class="row text-center">
                @php
                    $usersCount     = \App\Models\User::where('is_bot', false)->count();
                    $eventsCount    = \DB::table('events')->count();
                    $locationsCount = \DB::table('locations')->whereNull('organizer_id')->count();
                    $citiesCount    = \DB::table('locations')->whereNull('organizer_id')->distinct('city_id')->count('city_id');
                @endphp
                <div class="col-6 col-md-3">
                    <div class="card">
                        <div class="stat-number">{{ number_format($usersCount) }}</div>
                        <div class="stat-label">игроков в сообществе</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card">
                        <div class="stat-number">{{ number_format($eventsCount) }}</div>
                        <div class="stat-label">мероприятий создано</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card">
                        <div class="stat-number">{{ number_format($locationsCount) }}</div>
                        <div class="stat-label">площадок и кортов</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card">
                        <div class="stat-number">{{ number_format($citiesCount) }}</div>
                        <div class="stat-label">городов</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== ДЛЯ КОГО ===== --}}
        <div class="ramka" data-aos="fade-up">
            <h2 class="-mt-05 text-center">Для кого это создано</h2>

            <div class="tabs-content audience-tabs">
                <div class="tabs">
                    <div class="tab active" data-tab="tab-players">🏐 Игрокам</div>
                    <div class="tab" data-tab="tab-trainers">🎓 Тренерам</div>
                    <div class="tab" data-tab="tab-organizers">📣 Организаторам</div>
                    <div class="tab" data-tab="tab-centers">🏟️ Спортцентрам</div>
                    <div class="tab-highlight"></div>
                </div>

                <div class="tab-panes">

                    {{-- ИГРОКИ --}}
                    <div class="tab-pane active" id="tab-players">
                        <div class="row row2 mt-2">
                            <div class="col-md-6">
                                <div class="card audience-card">
                                    <div class="audience-icon">🏐</div>
                                    <div class="audience-title">Для игроков</div>
                                    <div class="f-17 mb-2" style="opacity:.7">Играйте больше, находите партнёров, развивайтесь.</div>
                                    <ul class="list">
                                        <li>Находите игры и тренировки рядом с домом</li>
                                        <li>Записывайтесь онлайн в один клик</li>
                                        <li>Классика и пляжный волейбол — любой формат</li>
                                        <li>Оценивайте уровень других игроков</li>
                                        <li>Отмечайте тех, с кем приятно играть ❤️</li>
                                        <li>Профиль с вашим уровнем, амплуа и статистикой</li>
                                        <li>Получайте уведомления в Telegram, VK и MAX</li>
                                        <li>Резервный список — займёте место, если кто-то отменит</li>
                                    </ul>
                                    <div class="mt-2">
                                        <a href="{{ route('events.index') }}" class="btn">Найти игру</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card audience-card">
                                    <div class="audience-icon">👥</div>
                                    <div class="audience-title">Найдите своих</div>
                                    <div class="f-17 mb-2" style="opacity:.7">Сообщество игроков вашего уровня.</div>
                                    <ul class="list">
                                        <li>Каталог игроков с уровнем и амплуа</li>
                                        <li>Фильтр по городу, уровню, направлению</li>
                                        <li>Пляжные пары и командная запись</li>
                                        <li>Приглашайте партнёров на мероприятия</li>
                                        <li>Авторизация через Telegram, VK или Яндекс</li>
                                    </ul>
                                    <div class="mt-2">
                                        <a href="{{ route('users.index') }}" class="btn btn-secondary">Каталог игроков</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ТРЕНЕРЫ --}}
                    <div class="tab-pane" id="tab-trainers">
                        <div class="row row2 mt-2">
                            <div class="col-md-6">
                                <div class="card audience-card">
                                    <div class="audience-icon">🎓</div>
                                    <div class="audience-title">Для тренеров</div>
                                    <div class="f-17 mb-2" style="opacity:.7">Организуйте тренировки и развивайте учеников.</div>
                                    <ul class="list">
                                        <li>Создавайте тренировки и тренировки+игра</li>
                                        <li>Формат «Тренер + ученик» (пляж)</div>
                                        <li>Повторяющиеся занятия по расписанию</li>
                                        <li>Управление списком участников</li>
                                        <li>Автоматические напоминания ученикам</li>
                                        <li>Анонсы тренировок в Telegram и VK каналы</li>
                                        <li>Ваш профиль тренера виден всем игрокам</li>
                                        <li>Создайте страницу своей школы волейбола</li>
                                    </ul>
                                    <div class="mt-2">
                                        <a href="{{ route('volleyball_school.index') }}" class="btn">Школы волейбола</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card audience-card">
                                    <div class="audience-icon">📋</div>
                                    <div class="audience-title">Станьте организатором</div>
                                    <div class="f-17 mb-2" style="opacity:.7">Хотите проводить тренировки через сервис?</div>
                                    <ul class="list">
                                        <li>Подайте заявку на статус организатора</li>
                                        <li>Бесплатный доступ к инструментам</li>
                                        <li>Ваши мероприятия — в общем каталоге</li>
                                        <li>Поддержка команды платформы</li>
                                    </ul>
                                    <div class="mt-2">
                                        @guest
                                            <a href="{{ route('register') }}" class="btn btn-secondary">Зарегистрироваться</a>
                                        @else
                                            <a href="{{ route('profile.complete') }}" class="btn btn-secondary">Мой профиль</a>
                                        @endguest
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ОРГАНИЗАТОРЫ --}}
                    <div class="tab-pane" id="tab-organizers">
                        <div class="row row2 mt-2">
                            <div class="col-md-6">
                                <div class="card audience-card">
                                    <div class="audience-icon">📣</div>
                                    <div class="audience-title">Для организаторов</div>
                                    <div class="f-17 mb-2" style="opacity:.7">Проводите игры, турниры и лиги.</div>
                                    <ul class="list">
                                        <li>Создавайте мероприятия любого формата</li>
                                        <li>Игры, тренировки, турниры, кемпы</li>
                                        <li>Классика и пляжный волейбол</li>
                                        <li>Повторяющиеся мероприятия по расписанию</li>
                                        <li>Гендерные ограничения и уровни допуска</li>
                                        <li>Управление списком участников вручную</li>
                                        <li>Автоотмена при нехватке кворума</li>
                                        <li>Помощник записи 🤖 — боты заполняют места</li>
                                    </ul>
                                    <div class="mt-2">
                                        <a href="{{ route('events.create.event_management') }}" class="btn">Управление играми</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card audience-card">
                                    <div class="audience-icon">📢</div>
                                    <div class="audience-title">Анонсы и уведомления</div>
                                    <div class="f-17 mb-2" style="opacity:.7">Держите игроков в курсе автоматически.</div>
                                    <ul class="list">
                                        <li>Анонсы в Telegram и VK каналы</li>
                                        <li>Уведомления о записи и отмене</li>
                                        <li>Напоминания за N часов до начала</li>
                                        <li>Личные уведомления каждому игроку</li>
                                        <li>Настраиваемые шаблоны сообщений</li>
                                        <li>Приватные мероприятия по ссылке</li>
                                    </ul>
                                    <div class="mt-2">
                                        <a href="{{ route('events.create') }}" class="btn btn-secondary">Создать мероприятие</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- СПОРТЦЕНТРЫ --}}
                    <div class="tab-pane" id="tab-centers">
                        <div class="row row2 mt-2">
                            <div class="col-md-6">
                                <div class="card audience-card">
                                    <div class="audience-icon">🏟️</div>
                                    <div class="audience-title">Для спортивных центров</div>
                                    <div class="f-17 mb-2" style="opacity:.7">Привлекайте игроков и наполняйте залы.</div>
                                    <ul class="list">
                                        <li>Страница вашей локации с фото и картой</li>
                                        <li>Игроки находят вас через каталог площадок</li>
                                        <li>Все мероприятия на вашей площадке — в одном месте</li>
                                        <li>Фильтр «Только с активными играми»</li>
                                        <li>Карта локаций для удобного поиска</li>
                                        <li>Страница школы/сообщества для вашего бренда</li>
                                    </ul>
                                    <div class="mt-2">
                                        <a href="{{ route('locations.index') }}" class="btn">Каталог локаций</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card audience-card">
                                    <div class="audience-icon">🏫</div>
                                    <div class="audience-title">Школа волейбола</div>
                                    <div class="f-17 mb-2" style="opacity:.7">Создайте публичную страницу вашей школы.</div>
                                    <ul class="list">
                                        <li>Логотип, обложка, описание, контакты</li>
                                        <li>Все ваши мероприятия на одной странице</li>
                                        <li>Профиль организатора / тренера</li>
                                        <li>Ваш бренд в каталоге школ волейбола</li>
                                        <li>Классика и пляжный — любое направление</li>
                                    </ul>
                                    <div class="mt-2">
                                        <a href="{{ route('volleyball_school.index') }}" class="btn btn-secondary">Школы волейбола</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ===== КАК ЭТО РАБОТАЕТ ===== --}}
        <div class="ramka" data-aos="fade-up">
            <h2 class="-mt-05 text-center">Как это работает</h2>
            <div class="row row2 mt-2">

                <div class="col-md-6 col-lg-3">
                    <div class="card">
                        <div class="d-flex fvc gap-2 mb-1">
                            <div class="step-num">1</div>
                            <div class="b-600 f-18">Регистрация</div>
                        </div>
                        <div class="f-16" style="opacity:.7">
                            Войдите через Telegram, VK или Яндекс. Заполните профиль — укажите уровень, амплуа и город.
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card">
                        <div class="d-flex fvc gap-2 mb-1">
                            <div class="step-num">2</div>
                            <div class="b-600 f-18">Найдите игру</div>
                        </div>
                        <div class="f-16" style="opacity:.7">
                            Откройте каталог мероприятий. Фильтруйте по городу, уровню, дате и формату — классика или пляж.
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card">
                        <div class="d-flex fvc gap-2 mb-1">
                            <div class="step-num">3</div>
                            <div class="b-600 f-18">Запишитесь</div>
                        </div>
                        <div class="f-16" style="opacity:.7">
                            Выберите позицию и нажмите «Записаться». Получите подтверждение и напоминание в мессенджер.
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card">
                        <div class="d-flex fvc gap-2 mb-1">
                            <div class="step-num">4</div>
                            <div class="b-600 f-18">Играйте!</div>
                        </div>
                        <div class="f-16" style="opacity:.7">
                            Приходите на игру, знакомьтесь с новыми партнёрами и оценивайте уровень друг друга.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ===== ЛОКАЦИИ ===== --}}
        <div class="ramka" data-aos="fade-up">
            <div class="d-flex between fvc mb-2">
                <h2 class="-mt-05">📍 Площадки и корты</h2>
                <a href="{{ route('locations.index') }}" class="btn btn-secondary">Все локации</a>
            </div>
            <div class="f-17 mb-2" style="opacity:.7">
                {{ \DB::table('locations')->whereNull('organizer_id')->count() }} площадок
                в {{ \DB::table('locations')->whereNull('organizer_id')->distinct('city_id')->count('city_id') }} городах России
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach(\DB::table('cities')->join('locations','cities.id','=','locations.city_id')->whereNull('locations.organizer_id')->select('cities.id','cities.name',\DB::raw('count(locations.id) as cnt'))->groupBy('cities.id','cities.name')->orderByDesc('cnt')->limit(8)->get() as $city)
                    <a href="{{ route('locations.index', ['city_id' => $city->id]) }}"
                       class="btn btn-secondary">
                        {{ $city->name }} <span class="f-14" style="opacity:.6">({{ $city->cnt }})</span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- ===== CTA ===== --}}
        @guest
        <div class="ramka" data-aos="fade-up">
            <div class="cta-block card">
                <div class="f-32 mb-1">🏐 Готовы играть?</div>
                <div class="f-18 mb-3" style="opacity:.7">
                    Присоединяйтесь к сообществу волейболистов — бесплатно и без лишних шагов.
                </div>
                <div class="d-flex flex-wrap gap-2" style="justify-content:center">
                    <a href="{{ route('register') }}" class="btn">Зарегистрироваться</a>
                    <a href="{{ route('events.index') }}" class="btn btn-secondary">Смотреть игры</a>
                </div>
            </div>
        </div>
        @endguest

    </div>

</x-voll-layout>