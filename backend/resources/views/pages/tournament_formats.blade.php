<x-voll-layout body_class="tournament-formats-page">
<x-slot name="title">{{ __('pages.tf_title') }}</x-slot>
<x-slot name="description">{{ __('pages.tf_description') }}</x-slot>
<x-slot name="canonical">{{ route('tournament_formats') }}</x-slot>
<x-slot name="h1">{{ __('pages.tf_h1') }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ __('pages.tf_breadcrumb') }}</span>
        <meta itemprop="position" content="2">
    </li>
</x-slot>

<div class="container">

<div class="ramka">
    <div class="card p-3 mb-3" style="background:rgba(231,97,47,.06);border:1px solid rgba(231,97,47,.2)">
        <div class="b-700 f-16 mb-1">📋 Шпаргалка для организатора</div>
        <div class="f-14" style="color:#6b7280">
            Определить абсолютно сильнейшего — <strong>Круговая</strong> или <strong>Двойное выбывание</strong><br>
            Зрелищно и быстро (1–2 дня) — <strong>Олимпийка</strong><br>
            Много команд + ограниченное время — <strong>Швейцарская система</strong><br>
            Стандарт международных турниров — <strong>Группы + плей-офф</strong><br>
            Неформально и весело — <strong>Король площадки</strong> или <strong>Тайский формат</strong>
        </div>
    </div>
</div>

{{-- Круговая --}}
<div class="ramka">
    <h2 class="-mt-05">🔄 Круговая система (Round Robin)</h2>
    <div class="card p-3 mb-2">
        <div class="f-14 mb-2"><strong>Суть:</strong> Каждая команда играет с каждой. Оптимально для 4–8 команд.</div>
        <div class="f-14 mb-2"><strong>Как работает:</strong> Игры проводятся в один или два круга. Победа 3:0 или 3:1 — 3 очка, победа 3:2 — 2 очка, поражение 2:3 — 1 очко, поражение 0:3 или 1:3 — 0 очков. Места распределяются по очкам, соотношению партий, соотношению мячей и личным встречам.</div>
        <div class="row">
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#10b981"><strong>Плюсы:</strong></div>
                <div class="f-13" style="color:#6b7280">Максимально справедливо, много игр, нет случайностей</div>
            </div>
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#dc2626"><strong>Минусы:</strong></div>
                <div class="f-13" style="color:#6b7280">Большое количество матчей (10 команд = 45 игр), физически тяжело</div>
            </div>
        </div>
        <div class="f-13 mt-2" style="color:#9ca3af"><strong>Где используют:</strong> Чемпионаты стран, предварительные этапы ЧМ и ОИ</div>
    </div>
</div>

{{-- Группы + плей-офф --}}
<div class="ramka">
    <h2 class="-mt-05">🏆 Группы + плей-офф</h2>
    <div class="card p-3 mb-2">
        <div class="f-14 mb-2"><strong>Суть:</strong> Сначала круговая система в группах, затем лучшие выходят в плей-офф на вылет.</div>
        <div class="f-14 mb-2"><strong>Как работает:</strong> Обычно 4 группы по 4 команды (16 участников). Из группы выходят 2 лучшие команды. Плей-офф: четвертьфинал → полуфинал → финал. Иногда есть матч за 3-е место.</div>
        <div class="row">
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#10b981"><strong>Плюсы:</strong></div>
                <div class="f-13" style="color:#6b7280">Сочетает справедливость отбора и высокую драму плей-офф</div>
            </div>
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#dc2626"><strong>Минусы:</strong></div>
                <div class="f-13" style="color:#6b7280">Обидные вылеты сильных команд из-за неудачной сетки</div>
            </div>
        </div>
        <div class="f-13 mt-2" style="color:#9ca3af"><strong>Где используют:</strong> Олимпийские игры, чемпионаты мира, Лига наций</div>
    </div>
</div>

{{-- Олимпийка --}}
<div class="ramka">
    <h2 class="-mt-05">⚡ Олимпийка (Single Elimination)</h2>
    <div class="card p-3 mb-2">
        <div class="f-14 mb-2"><strong>Суть:</strong> Чистая сетка на вылет. Проиграл — вылетел. Участников: 4, 8, 16, 32.</div>
        <div class="f-14 mb-2"><strong>Как работает:</strong> Команды сеют по рейтингу, чтобы сильные не встретились рано. Матчи до 3 побед или до 2 побед. Возможен матч за 3-е место.</div>
        <div class="row">
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#10b981"><strong>Плюсы:</strong></div>
                <div class="f-13" style="color:#6b7280">Максимальная интрига, короткий турнир (3–4 дня)</div>
            </div>
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#dc2626"><strong>Минусы:</strong></div>
                <div class="f-13" style="color:#6b7280">Несправедлива — фаворит может вылететь из-за плохого дня</div>
            </div>
        </div>
        <div class="f-13 mt-2" style="color:#9ca3af"><strong>Где используют:</strong> Кубковые турниры, отборочные олимпийские турниры, коммерческие турниры</div>
    </div>
</div>

{{-- Швейцарская --}}
<div class="ramka">
    <h2 class="-mt-05">🇨🇭 Швейцарская система</h2>
    <div class="card p-3 mb-2">
        <div class="f-14 mb-2"><strong>Суть:</strong> Нет фиксированной сетки. После каждого тура команды с одинаковым числом побед играют друг с другом. Никто не вылетает.</div>
        <div class="f-14 mb-2"><strong>Как работает:</strong> Для 16–32 команд: 5–7 туров. Первый тур — по рейтингу (1 vs 16, 2 vs 15). Далее победители играют с победителями. Ранжирование: победы → сила соперников → разница партий.</div>
        <div class="row">
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#10b981"><strong>Плюсы:</strong></div>
                <div class="f-13" style="color:#6b7280">Оптимальна для большого числа команд, каждый тур — равный соперник</div>
            </div>
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#dc2626"><strong>Минусы:</strong></div>
                <div class="f-13" style="color:#6b7280">Сложна для зрителей, требуется софт для жеребьёвки</div>
            </div>
        </div>
        <div class="f-13 mt-2" style="color:#9ca3af"><strong>Где используют:</strong> Юниорские и студенческие турниры, квалификации</div>
    </div>
</div>

{{-- Двойное выбывание --}}
<div class="ramka">
    <h2 class="-mt-05">♻️ Двойное выбывание (Double Elimination)</h2>
    <div class="card p-3 mb-2">
        <div class="f-14 mb-2"><strong>Суть:</strong> Чтобы вылететь — нужно проиграть дважды. Есть верхняя (победители) и нижняя (проигравшие один раз) сетки.</div>
        <div class="f-14 mb-2"><strong>Как работает:</strong> Проигравший в верхней сетке попадает в нижнюю. В нижней — матчи на вылет. В финале победитель верхней сетки имеет преимущество.</div>
        <div class="row">
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#10b981"><strong>Плюсы:</strong></div>
                <div class="f-13" style="color:#6b7280">Очень справедливо — одна осечка не фатальна, много топ-матчей</div>
            </div>
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#dc2626"><strong>Минусы:</strong></div>
                <div class="f-13" style="color:#6b7280">Сложная логистика, длинный турнир, зрителям трудно следить</div>
            </div>
        </div>
        <div class="f-13 mt-2" style="color:#9ca3af"><strong>Где используют:</strong> Пляжный волейбол (AVP — стандарт), коммерческие турниры</div>
    </div>
</div>

{{-- Король площадки --}}
<div class="ramka">
    <h2 class="-mt-05">👑 Король площадки (King of the Court)</h2>
    <div class="card p-3 mb-2">
        <div class="f-14 mb-2"><strong>Суть:</strong> На площадке 3+ команды. «Король» защищает площадку. Проигравший уходит, выходит следующий. Король остаётся пока побеждает.</div>
        <div class="f-14 mb-2"><strong>Как работает:</strong> Очки начисляются за время пребывания королём и количество побед подряд. Укороченные партии до 15–21 очка. Чаще используется в пляжном волейболе 2×2 и 3×3.</div>
        <div class="row">
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#10b981"><strong>Плюсы:</strong></div>
                <div class="f-13" style="color:#6b7280">Высокий темп, динамика, зрелищность, минимум организации</div>
            </div>
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#dc2626"><strong>Минусы:</strong></div>
                <div class="f-13" style="color:#6b7280">Неравномерная нагрузка, не для официальных чемпионатов</div>
            </div>
        </div>
        <div class="f-13 mt-2" style="color:#9ca3af"><strong>Где используют:</strong> Тренировки, пляжные фестивали, неформальные турниры</div>
    </div>
</div>

{{-- Тайский формат --}}
<div class="ramka">
    <h2 class="-mt-05">🇹🇭 Тайский формат</h2>
    <div class="card p-3 mb-2">
        <div class="f-14 mb-2"><strong>Суть:</strong> Смесь «Короля площадки» с накопительными очками и системой «жизней». Популярен в Таиланде и на пляжных фестивалях.</div>
        <div class="f-14 mb-2"><strong>Как работает:</strong> Команды играют короткие матчи (до 15 или 21 очка). Победитель забирает «жизнь» у проигравшего. Команда с 0 жизней выбывает. Турнир идёт, пока не останется одна команда. Возможна система «вызов» — выбираешь соперника.</div>
        <div class="row">
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#10b981"><strong>Плюсы:</strong></div>
                <div class="f-13" style="color:#6b7280">Азартный, стратегический, короткие матчи, высокая плотность игр</div>
            </div>
            <div class="col-md-6">
                <div class="f-14 mb-1" style="color:#dc2626"><strong>Минусы:</strong></div>
                <div class="f-13" style="color:#6b7280">Не для официальных рейтингов, хаотичное расписание, нужен ведущий</div>
            </div>
        </div>
        <div class="f-13 mt-2" style="color:#9ca3af"><strong>Где используют:</strong> Любительские турниры, пляжные фестивали, корпоративные турниры</div>
    </div>
</div>

</div>
</x-voll-layout>
