<x-voll-layout body_class="org-dashboard-page">

    <x-slot name="title">Панель организатора</x-slot>
    <x-slot name="h1">Панель организатора</x-slot>
    <x-slot name="t_description">Аналитика ваших мероприятий</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">Профиль</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Панель организатора</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <x-slot name="d_description">
        <div class="d-flex gap-2 mt-2 flex-wrap">
            <a href="{{ route('events.create.event_management') }}" class="btn btn-secondary">📋 Мои мероприятия</a>
            <a href="{{ route('profile.payment_settings') }}" class="btn btn-secondary">💳 Настройки оплаты</a>
            <a href="{{ route('profile.transactions') }}" class="btn btn-secondary">💰 Транзакции</a>
        </div>
    </x-slot>

    <div class="container">

        {{-- СВОДКА --}}
        <div class="ramka">
            <h2 class="-mt-05">📊 Сводка</h2>
            <div class="row row2">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Всего мероприятий</div>
                        <div class="f-36 b-700">{{ $totalEvents }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Активных</div>
                        <div class="f-36 b-700 cs">{{ $activeEvents }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Регулярных</div>
                        <div class="f-36 b-700 cd">{{ $recurringEvents }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Разовых</div>
                        <div class="f-36 b-700">{{ $oneTimeEvents }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ИГРОКИ --}}
        <div class="ramka">
            <h2 class="-mt-05">👥 Игроки</h2>
            <div class="row row2">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Уникальных игроков</div>
                        <div class="f-36 b-700">{{ $playersStats->unique_players ?? 0 }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Всего записей</div>
                        <div class="f-36 b-700">{{ $playersStats->total_registrations ?? 0 }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Новых за 30 дней</div>
                        <div class="f-36 b-700 cs">{{ $newPlayers ?? 0 }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Просмотров страниц (30д)</div>
                        <div class="f-36 b-700">{{ $pageViews }}</div>
                        <div class="f-12" style="opacity:.5">профиля: {{ $profileViews }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ДИНАМИКА ПО МЕСЯЦАМ --}}
        <div class="ramka">
            <h2 class="-mt-05">📈 Динамика записей (12 месяцев)</h2>
            <div class="card">
                <canvas id="monthlyChart" height="80"></canvas>
            </div>
        </div>

        {{-- ЗАГРУЗКА МЕРОПРИЯТИЙ --}}
        @if($occurrenceLoad->count() > 0)
        <div class="ramka">
            <h2 class="-mt-05">🏐 Загрузка мероприятий (последние 3 месяца)</h2>
            <div class="card">
                <canvas id="loadChart" height="100"></canvas>
            </div>
            <div class="table-scrollable mt-2">
                <table class="table f-16">
                    <thead>
                        <tr>
                            <th>Мероприятие</th>
                            <th>Повторов</th>
                            <th>Всего записей</th>
                            <th>Средняя загрузка</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($occurrenceLoad as $row)
                        <tr>
                            <td>{{ $row->title }}</td>
                            <td>{{ $row->occurrences_count }}</td>
                            <td>{{ $row->total_registered }}</td>
                            <td>
                                @php $pct = round($row->avg_load_pct ?? 0); @endphp
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="flex:1;background:#eee;border-radius:4px;height:8px">
                                        <div style="width:{{ min(100,$pct) }}%;background:{{ $pct>=75?'#28a745':($pct>=40?'#ffc107':'#dc3545') }};height:8px;border-radius:4px"></div>
                                    </div>
                                    <span class="f-14 b-600">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- БОТЫ --}}
        @if($botEffect)
        <div class="ramka">
            <h2 class="-mt-05">🤖 Эффективность ботов</h2>
            <div class="row row2">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Мероприятий с ботами</div>
                        <div class="f-36 b-700">{{ $botEffect->occurrences_with_bots }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Без ботов</div>
                        <div class="f-36 b-700">{{ $botEffect->occurrences_without_bots }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Ср. записей с ботами</div>
                        <div class="f-36 b-700 cd">{{ round($botEffect->avg_with_bots ?? 0, 1) }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Ср. записей без ботов</div>
                        <div class="f-36 b-700">{{ round($botEffect->avg_without_bots ?? 0, 1) }}</div>
                    </div>
                </div>
            </div>
            @php
                $withBots = round($botEffect->avg_with_bots ?? 0, 1);
                $withoutBots = round($botEffect->avg_without_bots ?? 0, 1);
                $diff = $withBots - $withoutBots;
            @endphp
            <div class="card mt-2 f-16">
                @if($diff > 0)
                    🟢 Боты привлекают в среднем <strong>+{{ round($diff, 1) }}</strong> дополнительных записей на мероприятие
                @elseif($diff < 0)
                    🔴 Мероприятия без ботов набирают больше участников (+{{ round(abs($diff), 1) }})
                @else
                    ⚪ Боты не влияют на количество записей
                @endif
            </div>
        </div>
        @endif

        {{-- ТОП ИГРОКОВ --}}
        <div class="ramka">
            <div class="row row2">
                <div class="col-md-6">
                    <h2 class="-mt-05">🏆 Самые активные игроки</h2>
                    <div class="card">
                        @forelse($topPlayers as $i => $player)
                        <div class="d-flex between fvc py-1 {{ $i > 0 ? 'border-top' : '' }}">
                            <div class="d-flex fvc gap-2">
                                <span class="f-14 b-600" style="width:20px;opacity:.5">{{ $i+1 }}</span>
                                <a href="{{ route('users.show', $player->id) }}" class="f-16">
                                    {{ trim($player->first_name . ' ' . $player->last_name) ?: '#'.$player->id }}
                                </a>
                            </div>
                            <div class="text-right">
                                <span class="f-16 b-600">{{ $player->visits }}</span>
                                <span class="f-13" style="opacity:.5"> игр</span>
                                @if($player->visits_30d > 0)
                                <span class="f-13 cs"> +{{ $player->visits_30d }} за 30д</span>
                                @endif
                            </div>
                        </div>
                        @empty
                        <div class="f-16" style="opacity:.5">Нет данных</div>
                        @endforelse
                    </div>
                </div>
                <div class="col-md-6">
                    <h2 class="-mt-05">❌ Часто отменяют</h2>
                    <div class="card">
                        @forelse($topCancellers as $i => $player)
                        <div class="d-flex between fvc py-1 {{ $i > 0 ? 'border-top' : '' }}">
                            <div class="d-flex fvc gap-2">
                                <span class="f-14 b-600" style="width:20px;opacity:.5">{{ $i+1 }}</span>
                                <a href="{{ route('users.show', $player->id) }}" class="f-16">
                                    {{ trim($player->first_name . ' ' . $player->last_name) ?: '#'.$player->id }}
                                </a>
                            </div>
                            <div class="text-right">
                                <span class="f-16 b-600 red">{{ $player->cancellations }}</span>
                                <span class="f-13" style="opacity:.5"> отмен</span>
                            </div>
                        </div>
                        @empty
                        <div class="f-16" style="opacity:.5">Нет данных</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>


        {{-- АБОНЕМЕНТЫ И КУПОНЫ --}}
        @if(isset($subStats) && $subStats->total > 0)
        <div class="ramka">
            <h2 class="-mt-05">🎫 Абонементы</h2>
            <div class="row row2">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Всего выдано</div>
                        <div class="f-32 b-700">{{ $subStats->total }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Активных</div>
                        <div class="f-32 b-700 cs">{{ $subStats->active }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Посещений использовано</div>
                        <div class="f-32 b-700 cd">{{ $subStats->total_visits_used ?? 0 }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Доход (оплаченные)</div>
                        <div class="f-32 b-700">{{ number_format(($subRevenue ?? 0)/100, 0) }} ₽</div>
                    </div>
                </div>
            </div>

            @if($topSubTemplates->isNotEmpty())
            <h3 class="mt-2">Топ шаблонов</h3>
            <div class="table-scrollable">
                <table class="table f-16">
                    <thead>
                        <tr><th>Шаблон</th><th>Продано</th><th>Посещений использовано</th></tr>
                    </thead>
                    <tbody>
                        @foreach($topSubTemplates as $t)
                        <tr>
                            <td>{{ $t->name }}</td>
                            <td class="b-600">{{ $t->sold }}</td>
                            <td>{{ $t->visits_used ?? 0 }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <div class="d-flex gap-2 mt-2">
                <a href="{{ route('subscriptions.index') }}" class="btn btn-secondary btn-small">📋 Все абонементы</a>
                <a href="{{ route('subscription_templates.index') }}" class="btn btn-secondary btn-small">📝 Шаблоны</a>
            </div>
        </div>
        @endif

        @if(isset($couponStats) && $couponStats->total > 0)
        <div class="ramka">
            <h2 class="-mt-05">🎟 Купоны</h2>
            <div class="row row2">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Всего выдано</div>
                        <div class="f-32 b-700">{{ $couponStats->total }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Активных</div>
                        <div class="f-32 b-700 cs">{{ $couponStats->active }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Использовано</div>
                        <div class="f-32 b-700 cd">{{ $couponStats->used }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Всего применений</div>
                        <div class="f-32 b-700">{{ $couponStats->total_uses ?? 0 }}</div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-2">
                <a href="{{ route('coupons.org_index') }}" class="btn btn-secondary btn-small">📋 Все купоны</a>
                <a href="{{ route('coupon_templates.index') }}" class="btn btn-secondary btn-small">🏷 Шаблоны купонов</a>
            </div>
        </div>
        @endif

    <div class="ramka text-center">
        <a href="{{ route('staff.index') }}" class="btn btn-secondary mr-2">🧑‍💻 Мои помощники</a>
        <a href="{{ route('staff.logs') }}" class="btn btn-secondary">📋 Логи действий Staff</a>
    </div>

    </div>

    <x-slot name="script">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // Динамика по месяцам
        const monthlyData = @json($monthlyStats);
        const monthlyCtx = document.getElementById('monthlyChart');
        if (monthlyCtx && monthlyData.length) {
            new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: monthlyData.map(r => r.month),
                    datasets: [
                        {
                            label: 'Записи',
                            data: monthlyData.map(r => r.registrations),
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderRadius: 4,
                        },
                        {
                            label: 'Отмены',
                            data: monthlyData.map(r => r.cancellations),
                            backgroundColor: 'rgba(220, 53, 69, 0.5)',
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: { x: { stacked: false }, y: { beginAtZero: true } }
                }
            });
        }

        // Загрузка мероприятий
        const loadData = @json($occurrenceLoad);
        const loadCtx = document.getElementById('loadChart');
        if (loadCtx && loadData.length) {
            new Chart(loadCtx, {
                type: 'bar',
                data: {
                    labels: loadData.map(r => r.title.length > 25 ? r.title.substring(0, 25) + '…' : r.title),
                    datasets: [{
                        label: 'Средняя загрузка %',
                        data: loadData.map(r => Math.round(r.avg_load_pct || 0)),
                        backgroundColor: loadData.map(r => {
                            const pct = r.avg_load_pct || 0;
                            return pct >= 75 ? 'rgba(40,167,69,0.7)' : pct >= 40 ? 'rgba(255,193,7,0.7)' : 'rgba(220,53,69,0.7)';
                        }),
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, max: 100 } }
                }
            });
        }
    });
    </script>
    </x-slot>

</x-voll-layout>
