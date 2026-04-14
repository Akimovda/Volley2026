{{-- resources/views/pages/about.blade.php --}}
<x-voll-layout body_class="about">
<x-slot name="title">О сервисе — VolleyPlay.Club</x-slot>
<x-slot name="description">VolleyPlay.Club — сервис для игроков, организаторов, тренеров и спортивных центров. Удобная запись на волейбольные мероприятия, управление командами и уведомления.</x-slot>
<x-slot name="t_description">О проекте VolleyPlay.Club</x-slot>
<x-slot name="canonical">{{ route('about') }}</x-slot>
<x-slot name="breadcrumbs">
<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
<a href="{{ route('about') }}" itemprop="item"><span itemprop="name">О сервисе</span></a>
<meta itemprop="position" content="2">
</li>
</x-slot>
<x-slot name="h1">О сервисе</x-slot>

<div class="container">

{{-- HERO --}}
<div class="ramka text-center" style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);color:#fff;padding:3rem 2rem">
    <div style="font-size:3rem;margin-bottom:1rem">🏐</div>
    <h2 style="color:#fff;font-size:1.8rem;margin-bottom:1rem">VolleyPlay.Club</h2>
    <p style="font-size:1.15rem;opacity:.9;max-width:600px;margin:0 auto 1.5rem">
        Объединяем волейбольное сообщество — удобный сервис для записи и управления волейбольными мероприятиями по всей России.
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="{{ route('events.index') }}" class="btn">Найти мероприятие</a>
        <a href="{{ route('about') }}#org" class="btn btn-secondary">Стать организатором</a>
    </div>
</div>

{{-- ДЛЯ КОГО --}}
<div class="ramka">
    <h2 class="-mt-05">Для кого создан сервис</h2>
    <div class="row row2 mt-2">
        <div class="col-md-6 col-lg-3">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">🙋</div>
                <h3 style="margin:.5rem 0">Игрокам</h3>
                <p class="f-14">Находите игры рядом с домом, записывайтесь в один клик, получайте уведомления о свободных местах и новых мероприятиях.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">📋</div>
                <h3 style="margin:.5rem 0">Организаторам</h3>
                <p class="f-14">Создавайте мероприятия, управляйте списком участников, настраивайте оплату и получайте уведомления о записях.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">🏅</div>
                <h3 style="margin:.5rem 0">Тренерам</h3>
                <p class="f-14">Ведите тренировки, набирайте группы, отслеживайте посещаемость и развивайте свою аудиторию через платформу.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">🏟️</div>
                <h3 style="margin:.5rem 0">Спортивным центрам</h3>
                <p class="f-14">Привлекайте новых клиентов, размещайте расписание площадок и управляйте загрузкой залов через единую платформу.</p>
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
                <li>🔔 Уведомления в Telegram, ВКонтакте и MAX о записи, отмене и напоминаниях</li>
                <li>📍 Карта площадок с адресом и маршрутом</li>
                <li>⏳ Лист ожидания — автоматическая запись при освобождении места</li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="list f-15">
                <li>🤝 Приглашение друзей на мероприятие</li>
                <li>🏐 Профиль игрока с уровнем игры и позициями</li>
                <li>📊 История участия в мероприятиях</li>
                <li>🔒 Приватные мероприятия — только по ссылке-приглашению</li>
                <li>💳 Онлайн-оплата через ЮKassa, Т-Банк или Сбер</li>
            </ul>
        </div>
    </div>
</div>

{{-- ВОЗМОЖНОСТИ ДЛЯ ОРГАНИЗАТОРОВ --}}
<div class="ramka" id="org">
    <h2 class="-mt-05">📋 Возможности для организаторов</h2>
    <div class="row row2">
        <div class="col-md-6">
            <ul class="list f-15">
                <li>📅 Создание разовых и повторяющихся мероприятий</li>
                <li>👥 Управление списком участников — добавление, перемещение, отмена</li>
                <li>🤖 Бот-ассистент — автоматическое заполнение свободных мест ботами</li>
                <li>💰 Настройка оплаты — ЮKassa, Т-Банк, Сбербанк</li>
                <li>📢 Рекламные мероприятия с размещением в общем списке</li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="list f-15">
                <li>👨‍💼 Команда сотрудников (staff) с разграничением прав</li>
                <li>🔔 Уведомления участникам об изменениях и отменах</li>
                <li>🏆 Турниры с командной регистрацией</li>
                <li>📊 Статистика по мероприятиям и участникам</li>
                <li>🔗 Приватные мероприятия с доступом по ссылке</li>
            </ul>
        </div>
    </div>
    <div class="mt-2">
        <a href="{{ route('profile.show') }}#organizer-request" class="btn">Подать заявку на организатора</a>
    </div>
</div>

{{-- ВОЗМОЖНОСТИ ДЛЯ ТРЕНЕРОВ --}}
<div class="ramka">
    <h2 class="-mt-05">🏅 Возможности для тренеров</h2>
    <div class="row row2">
        <div class="col-md-6">
            <ul class="list f-15">
                <li>📅 Расписание тренировок с онлайн-записью</li>
                <li>👥 Набор групп — отдельно для начинающих, продвинутых, смешанных</li>
                <li>💳 Приём оплаты за тренировки онлайн</li>
                <li>🔔 Автоматические напоминания участникам</li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="list f-15">
                <li>📊 Отслеживание записей и посещаемости</li>
                <li>🤝 Привязка к конкретной площадке или спортцентру</li>
                <li>📢 Продвижение тренировок в общем каталоге мероприятий</li>
                <li>🏐 Профиль тренера с историей и отзывами</li>
            </ul>
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
        <div class="col-md-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem;font-weight:700;opacity:.3">01</div>
                <h3 style="margin:.25rem 0">Зарегистрируйтесь</h3>
                <p class="f-14">Войдите через Telegram, ВКонтакте или Яндекс — без паролей и лишних данных.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem;font-weight:700;opacity:.3">02</div>
                <h3 style="margin:.25rem 0">Найдите мероприятие</h3>
                <p class="f-14">Выберите город, формат и уровень — и запишитесь на ближайшую игру или тренировку.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem;font-weight:700;opacity:.3">03</div>
                <h3 style="margin:.25rem 0">Играйте!</h3>
                <p class="f-14">Получайте уведомления, приглашайте друзей и наслаждайтесь игрой.</p>
            </div>
        </div>
    </div>
    <div class="text-center mt-3">
        <a href="{{ route('events.index') }}" class="btn">Найти мероприятие →</a>
    </div>
</div>

</div>
</x-voll-layout>
