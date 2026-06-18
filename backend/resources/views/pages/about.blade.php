{{-- resources/views/pages/about.blade.php --}}
<x-voll-layout body_class="about">
<x-slot name="title">{{ __('pages.about_title') }}</x-slot>
<x-slot name="description">{{ __('pages.about_description') }}</x-slot>
<x-slot name="t_description">{{ __('pages.about_t_description') }}</x-slot>
<x-slot name="canonical">{{ route('about') }}</x-slot>
<x-slot name="breadcrumbs">
<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
<a href="{{ route('about') }}" itemprop="item"><span itemprop="name">{{ __('pages.about_breadcrumb') }}</span></a>
<meta itemprop="position" content="2">
</li>
</x-slot>
<x-slot name="h1">{{ __('pages.about_h1') }}</x-slot>

<div class="container">

{{-- HERO --}}
<div class="ramka text-center" style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);color:#fff;padding:3rem 2rem">
    <div style="font-size:3rem;margin-bottom:1rem">🏐</div>
    <h2 style="color:#fff;font-size:1.8rem;margin-bottom:1rem">VolleyPlay.Club</h2>
    <p style="font-size:1.15rem;opacity:.9;max-width:660px;margin:0 auto 1.5rem">
        Полноценная экосистема для волейбольного сообщества — от любительских игр до профессиональных лиг. Запись на мероприятия, турниры, рейтинги, школы волейбола и CRM для организаторов.
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="{{ route('events.index') }}" class="btn">Найти мероприятие</a>
        <a href="{{ route('players.rating') }}" class="btn btn-secondary">Рейтинг игроков</a>
        <a href="{{ route('about') }}#org" class="btn btn-secondary">Стать организатором</a>
    </div>
</div>

{{-- ДЛЯ КОГО --}}
<div class="ramka">
    <h2 class="-mt-05">Для кого создан сервис</h2>
    <div class="row row2 mt-2">
        <div class="col-md-6 col-lg-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">🙋</div>
                <h3 style="margin:.5rem 0">Игрокам</h3>
                <p class="f-14">Находите игры рядом с домом, записывайтесь в один клик, следите за своим рейтингом OpenSkill и историей партнёров.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">📋</div>
                <h3 style="margin:.5rem 0">Организаторам</h3>
                <p class="f-14">Создавайте мероприятия, управляйте участниками и оплатой. CRM-дашборд с аналитикой, список листа ожидания, турниры и лиги.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">🏫</div>
                <h3 style="margin:.5rem 0">Школам волейбола</h3>
                <p class="f-14">Собственная страница школы, расписание тренировок с онлайн-записью, абонементы и управление группами.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">🏅</div>
                <h3 style="margin:.5rem 0">Тренерам</h3>
                <p class="f-14">Ведите тренировочные группы, принимайте оплату онлайн и развивайте аудиторию через каталог мероприятий.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">🏆</div>
                <h3 style="margin:.5rem 0">Лигам и федерациям</h3>
                <p class="f-14">Проводите сезоны с дивизионами, автоматическим промоушеном, кросс-таблицами и отслеживанием рейтинга игроков.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">🏟️</div>
                <h3 style="margin:.5rem 0">Спортивным центрам</h3>
                <p class="f-14">Привлекайте игроков, размещайте расписание площадок и управляйте загрузкой залов через единую платформу.</p>
            </div>
        </div>
    </div>
</div>

{{-- ВОЗМОЖНОСТИ ДЛЯ ИГРОКОВ --}}
<div class="ramka">
    <h2 class="-mt-05">🙋 Возможности для игроков</h2>
    <div class="row row2">
        <div class="col-md-6">
            <ul class="list f-15">
                <li>🔍 Поиск мероприятий по городу, уровню и формату игры</li>
                <li>✅ Запись на мероприятие в один клик</li>
                <li>⏳ Лист ожидания — автоматическая запись при освобождении места</li>
                <li>🔔 Уведомления в Telegram, ВКонтакте и MAX</li>
                <li>📍 Карта площадок с адресом и маршрутом</li>
                <li>🤝 Приглашение друзей на мероприятие</li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="list f-15">
                <li>📈 Рейтинг OpenSkill — объективная оценка уровня игры</li>
                <li>🎯 Профиль с позициями, уровнем, историей турниров и партнёров</li>
                <li>🤜 Сохранение любимых связок и команд</li>
                <li>🔒 Приватные мероприятия — только по ссылке-приглашению</li>
                <li>💳 Онлайн-оплата через ЮKassa, Т-Банк или Сбер</li>
                <li>📲 Мобильное приложение для iOS и Android в RuStore</li>
            </ul>
        </div>
    </div>
</div>

{{-- РЕЙТИНГОВАЯ СИСТЕМА --}}
<div class="ramka" id="rating">
    <h2 class="-mt-05">📈 Рейтинговая система OpenSkill</h2>
    <p class="f-15" style="opacity:.8;margin-bottom:1.5rem">
        VolleyPlay.Club использует три независимых рейтинга для объективной оценки уровня каждого игрока.
    </p>
    <div class="row row2">
        <div class="col-md-4">
            <div class="card" style="height:100%">
                <h3 style="margin:.25rem 0 .5rem">⚡ OpenSkill (μ/σ)</h3>
                <p class="f-14">Байесовский алгоритм: учитывает не только победы, но и уровень соперников. Консервативный рейтинг CR&nbsp;= μ&nbsp;−&nbsp;3σ — публичная оценка, которой можно доверять. Обновляется после каждого матча.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="height:100%">
                <h3 style="margin:.25rem 0 .5rem">🎯 Elo-рейтинг</h3>
                <p class="f-14">Классический рейтинг по итогам завершённых турниров. Отдельно считается за сезон и за всю карьеру — удобно отслеживать прогресс.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="height:100%">
                <h3 style="margin:.25rem 0 .5rem">📊 WinRate</h3>
                <p class="f-14">Процент побед в матчах, турнирах и серии. Показывает стабильность результатов и хорошо работает как дополнение к OpenSkill.</p>
            </div>
        </div>
    </div>
    <div class="row row2 mt-1">
        <div class="col-md-6">
            <ul class="list f-15">
                <li>📉 График рейтинга за последние матчи в профиле игрока</li>
                <li>🤜 Рейтинг связок (пар) — статистика совместных побед</li>
                <li>⚔️ Статистика встреч с соперниками</li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="list f-15">
                <li>🏄 Отдельные рейтинги для пляжного и классического волейбола</li>
                <li>📅 Сезонный рейтинг — каждый сезон стартуете заново</li>
                <li>🔝 Пик рейтинга — история лучшего показателя</li>
            </ul>
        </div>
    </div>
    <div class="mt-2 d-flex gap-2 flex-wrap">
        <a href="{{ route('players.rating') }}" class="btn btn-secondary">Рейтинг игроков</a>
        <a href="{{ route('players.teams') }}" class="btn btn-secondary">Рейтинг связок</a>
        <a href="{{ route('pages.rating_info') }}" class="btn btn-secondary">Как считается рейтинг</a>
    </div>
</div>

{{-- ТУРНИРЫ --}}
<div class="ramka" id="tournaments">
    <h2 class="-mt-05">🏆 Турниры и соревнования</h2>
    <p class="f-15" style="opacity:.8;margin-bottom:1.5rem">
        Полноценная система проведения турниров: от жеребьёвки до финального протокола.
    </p>
    <div class="row row2">
        <div class="col-md-6">
            <h3 style="margin:.25rem 0 .75rem">Форматы для классики</h3>
            <ul class="list f-15">
                <li>🔁 Круговой турнир (Round Robin)</li>
                <li>🏟️ Групповой этап + плей-офф</li>
                <li>❌ Олимпийская сетка (Single Elimination)</li>
                <li>🇨🇭 Швейцарская система (Swiss)</li>
            </ul>
        </div>
        <div class="col-md-6">
            <h3 style="margin:.25rem 0 .75rem">Форматы для пляжного</h3>
            <ul class="list f-15">
                <li>🏖️ Пул-плей с плей-офф (Pool Play)</li>
                <li>👑 Король пляжа (King of the Court)</li>
                <li>🔄 Двойное выбывание (Double Elimination)</li>
                <li>🇹🇭 Тайская система</li>
            </ul>
        </div>
    </div>
    <div class="row row2 mt-1">
        <div class="col-md-6">
            <ul class="list f-15">
                <li>📋 Кросс-таблицы и турнирные сетки в реальном времени</li>
                <li>🤝 Командная регистрация — captain + участники</li>
                <li>👤 Индивидуальная запись на турнир (распределение в команды)</li>
                <li>⚖️ Система ничьих и дополнительных критериев</li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="list f-15">
                <li>📺 Режим TV — трансляция турнирной сетки на экран</li>
                <li>📄 Экспорт протокола в PDF</li>
                <li>🔀 Жеребьёвка и перестановка команд организатором</li>
                <li>📊 Рейтинги обновляются автоматически после каждого матча</li>
            </ul>
        </div>
    </div>
</div>

{{-- ЛИГИ И СЕЗОНЫ --}}
<div class="ramka" id="leagues">
    <h2 class="-mt-05">🏅 Лиги и сезоны</h2>
    <p class="f-15" style="opacity:.8;margin-bottom:1.5rem">
        Система долгосрочных соревнований с дивизионами, промоушеном и накопленной статистикой.
    </p>
    <div class="row row2">
        <div class="col-md-6">
            <ul class="list f-15">
                <li>🏢 Иерархия: Лига → Сезон → Дивизионы → Туры</li>
                <li>⬆️ Автоматический промоушен и вылет по итогам сезона</li>
                <li>📋 Командный состав дивизиона с историей перемещений</li>
                <li>🔄 Замены игроков внутри сезона с двойным подтверждением</li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="list f-15">
                <li>📊 Накопленная таблица за весь сезон</li>
                <li>🗓️ Привязка туров к конкретным дивизионам</li>
                <li>👥 Резерв лиги — очередь команд на место в дивизионе</li>
                <li>🌐 Публичная страница лиги и каждого сезона</li>
            </ul>
        </div>
    </div>
    <div class="mt-2">
        <a href="{{ route('leagues.public') }}" class="btn btn-secondary">Все лиги</a>
    </div>
</div>

{{-- CRM ДЛЯ ОРГАНИЗАТОРОВ --}}
<div class="ramka" id="org">
    <h2 class="-mt-05">📋 CRM и инструменты для организаторов</h2>
    <p class="f-15" style="opacity:.8;margin-bottom:1.5rem">
        Полный набор инструментов для управления мероприятиями, командой и финансами.
    </p>
    <div class="row row2">
        <div class="col-md-6">
            <h3 style="margin:.25rem 0 .75rem">Управление мероприятиями</h3>
            <ul class="list f-15">
                <li>📅 Разовые и повторяющиеся серии мероприятий</li>
                <li>👥 Добавление/перемещение участников, принудительная запись</li>
                <li>⏳ Лист ожидания с ручным управлением очерёдностью</li>
                <li>🔔 Push-уведомления участникам об изменениях</li>
                <li>📢 Анонсы в Telegram-каналах, группах ВКонтакте и MAX</li>
                <li>📄 Экспорт списка участников в PDF и TXT</li>
            </ul>
        </div>
        <div class="col-md-6">
            <h3 style="margin:.25rem 0 .75rem">Аналитика и финансы</h3>
            <ul class="list f-15">
                <li>📊 Дашборд организатора: активность, записи, выручка</li>
                <li>🧑‍🤝‍🧑 Аналитика игроков: топ активных, новые, риск оттока</li>
                <li>💰 Приём оплаты: ЮKassa, Т-Банк, Сбербанк</li>
                <li>👛 Виртуальный кошелёк игрока у организатора</li>
                <li>🎫 Абонементы и купоны на скидку</li>
                <li>👨‍💼 Команда сотрудников (staff) с разграничением прав</li>
            </ul>
        </div>
    </div>
    <div class="mt-2">
        <a href="{{ route('profile.show') }}#organizer-request" class="btn">Подать заявку на организатора</a>
        <a href="{{ route('org.dashboard') }}" class="btn btn-secondary ml-1">Дашборд организатора</a>
    </div>
</div>

{{-- ШКОЛЫ ВОЛЕЙБОЛА --}}
<div class="ramka" id="school">
    <h2 class="-mt-05">🏫 Школы волейбола</h2>
    <p class="f-15" style="opacity:.8;margin-bottom:1.5rem">
        Отдельный модуль для тренеров и руководителей волейбольных школ.
    </p>
    <div class="row row2">
        <div class="col-md-6">
            <ul class="list f-15">
                <li>📄 Страница школы с описанием, фото и контактами</li>
                <li>📅 Расписание тренировочных групп с онлайн-записью</li>
                <li>🎓 Группы по уровню: начинающие, продвинутые, смешанные</li>
                <li>💳 Абонементы — продажа пакетов тренировок онлайн</li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="list f-15">
                <li>🔔 Автоматические напоминания ученикам о тренировках</li>
                <li>📍 Привязка к площадке/залу с картой</li>
                <li>📢 Продвижение в общем каталоге мероприятий</li>
                <li>👁 Публичная страница школы в каталоге</li>
            </ul>
        </div>
    </div>
    <div class="mt-2">
        <a href="{{ route('volleyball_school.index') }}" class="btn btn-secondary">Каталог школ</a>
    </div>
</div>

{{-- МОБИЛЬНОЕ ПРИЛОЖЕНИЕ --}}
<div class="ramka" id="app">
    <h2 class="-mt-05">📱 Мобильное приложение</h2>
    <div class="row row2">
        <div class="col-md-6">
            <ul class="list f-15">
                <li>🍎 iOS — доступно в App Store</li>
                <li>🤳 Face ID / Touch ID для быстрого входа (iOS)</li>
                <li>🔔 Push-уведомления о записях, напоминаниях и турнирах</li>
                <li>🌐 Весь функционал сайта в удобном мобильном виде</li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="list f-15">
                <li>🤖 Android — доступно в RuStore</li>
                <li>🌙 Тёмная тема — автоматически под системную</li>
                <li>🔗 Universal Links — ссылки открываются прямо в приложении</li>
                <li>🔑 Вход через Apple ID, Google, Telegram — не для РФ и ВКонтакте, Яндекс — для РФ</li>
            </ul>
        </div>
    </div>
</div>

{{-- БОТЫ --}}
<div class="ramka">
    <h2 class="-mt-05">🤖 Боты и уведомления</h2>
    <div class="row row2">
        <div class="col-md-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem">✈️</div>
                <h3 style="margin:.5rem 0">Telegram</h3>
                <p class="f-14">Уведомления о записи, отмене, напоминаниях за 2 часа и изменениях. Анонсы в каналах и группах организатора.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem">💙</div>
                <h3 style="margin:.5rem 0">ВКонтакте</h3>
                <p class="f-14">Уведомления в сообщениях ВКонтакте. Публикация анонсов в беседах и сообществах.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem">🟣</div>
                <h3 style="margin:.5rem 0">MAX</h3>
                <p class="f-14">Бот в мессенджере MAX — уведомления и анонсы для аудитории на платформе.</p>
            </div>
        </div>
    </div>
</div>

{{-- ГОРОДА --}}
<div class="ramka">
    <h2 class="-mt-05">📍 Сервис работает в городах</h2>
    <div class="d-flex flex-wrap gap-1 mt-2">
        @foreach([
            ['Москва', '🏙️'],
            ['Санкт-Петербург', '🌊'],
            ['Сестрорецк', '🌊'],
            ['Новосибирск', '❄️'],
            ['Воронеж', '🌿'],
            ['Липецк', '🌿'],
            ['Саратов', '🌾'],
            ['Сочи', '☀️'],
            ['Сириус', '☀️'],
        ] as [$city, $emoji])
        <div class="card" style="padding:.5rem 1rem;margin:0">
            <span>{{ $emoji }} {{ $city }}</span>
        </div>
        @endforeach
    </div>
    <p class="f-14 mt-2" style="opacity:.6">Список городов постоянно расширяется. Хотите добавить свой город? <a href="{{ route('help') }}">Свяжитесь с нами</a>.</p>
</div>

{{-- КАК НАЧАТЬ --}}
<div class="ramka">
    <h2 class="-mt-05">🚀 Как начать</h2>
    <div class="row row2 mt-2">
        <div class="col-md-3">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem;font-weight:700;opacity:.3">01</div>
                <h3 style="margin:.25rem 0">Зарегистрируйтесь</h3>
                <p class="f-14">Войдите через Telegram, ВКонтакте, Google или Apple ID — без паролей и лишних данных.</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem;font-weight:700;opacity:.3">02</div>
                <h3 style="margin:.25rem 0">Заполните профиль</h3>
                <p class="f-14">Укажите позицию, уровень и город — система подберёт подходящие мероприятия и начнёт считать рейтинг.</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem;font-weight:700;opacity:.3">03</div>
                <h3 style="margin:.25rem 0">Найдите мероприятие</h3>
                <p class="f-14">Выберите город, формат и уровень — запишитесь на ближайшую игру, тренировку или турнир.</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem;font-weight:700;opacity:.3">04</div>
                <h3 style="margin:.25rem 0">Играйте и растите</h3>
                <p class="f-14">После матчей рейтинг обновляется автоматически. Участвуйте в лигах и турнирах, следите за прогрессом.</p>
            </div>
        </div>
    </div>
    <div class="text-center mt-3">
        <a href="{{ route('events.index') }}" class="btn">Найти мероприятие →</a>
    </div>
</div>

</div>
</x-voll-layout>
