<x-voll-layout body_class="org-players-page">

    <x-slot name="title">Аналитика игроков</x-slot>
    <x-slot name="h1">Аналитика игроков</x-slot>
    <x-slot name="t_description">Детальная статистика по аудитории ваших мероприятий</x-slot>
    <x-slot name="image">
        <div class="top-section-img" data-aos="fade" data-aos-duration="1000">
            <div class="top-section-light-img">
                <img src="/img/top/dashboard-2.webp" alt="img">
            </div>
            <div class="top-section-dark-img">
                <img src="/img/top/dashboard-2-dark.webp" alt="img">
            </div>
        </div>
    </x-slot>
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">Профиль</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('org.dashboard') }}" itemprop="item"><span itemprop="name">Дашборд</span></a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Аналитика игроков</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>
    <x-slot name="d_description">
        <div class="d-flex gap-1 mt-2 flex-wrap">
            <a href="{{ route('org.dashboard') }}" class="btn btn-secondary">← Дашборд</a>
        </div>
    </x-slot>

    <div class="container">

        {{-- СВОДКА --}}
        <div class="ramka">
            <h2 class="-mt-05">📊 Сводка по аудитории</h2>
            <div class="row row2">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Уникальных игроков</div>
                        <div class="f-32 b-700 cd">{{ $topPlayers->count() > 0 ? number_format($topPlayers->sum('v_all') > 0 ? $topPlayers->count() : 0) : 0 }}</div>
                        @php $totalUnique = $topPlayers->count(); @endphp
                        <div class="f-13" style="opacity:.6">из топ-50 активных</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Новых за 30 дней</div>
                        <div class="f-32 b-700" style="color:#10b981">{{ $newPlayers->count() }}</div>
                        <div class="f-13" style="opacity:.6">первый визит</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Риск оттока</div>
                        <div class="f-32 b-700" style="color:#f59e0b">{{ $churnRisk->count() }}</div>
                        <div class="f-13" style="opacity:.6">не приходили 60+ дней</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Часто в резерве</div>
                        <div class="f-32 b-700" style="color:#6366f1">{{ $reservePlayers->count() }}</div>
                        <div class="f-13" style="opacity:.6">игроков</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ТОП АКТИВНЫХ С ПЕРИОДАМИ --}}
        <div class="ramka">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="-mt-05">🏆 Самые активные игроки</h2>
                <div class="d-flex gap-1 flex-wrap" id="period-tabs">
                    <button class="btn btn-sm btn-outline-secondary period-btn active" data-period="30d">30 дней</button>
                    <button class="btn btn-sm btn-outline-secondary period-btn" data-period="90d">3 месяца</button>
                    <button class="btn btn-sm btn-outline-secondary period-btn" data-period="180d">6 месяцев</button>
                    <button class="btn btn-sm btn-outline-secondary period-btn" data-period="365d">Год</button>
                    <button class="btn btn-sm btn-outline-secondary period-btn" data-period="all">Всё время</button>
                </div>
            </div>

            <div id="top-players-list">
                {{-- рендерится через JS --}}
                <div class="text-center" style="opacity:.4;padding:2rem">Загрузка...</div>
            </div>
        </div>

        {{-- НОВЫЕ ИГРОКИ --}}
        <div class="ramka">
            <h2 class="-mt-05">🆕 Новые игроки за 30 дней</h2>
            @forelse($newPlayers as $i => $p)
            <div class="d-flex between fvc py-1 {{ $i > 0 ? 'border-top' : '' }}">
                <div class="d-flex fvc gap-2">
                    <span class="f-13 b-600" style="width:24px;opacity:.4">{{ $i+1 }}</span>
                    <div>
                        <a href="{{ route('users.show', $p->id) }}" class="f-15 b-600">
                            {{ trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: '#'.$p->id }}
                        </a>
                        <div class="f-12" style="opacity:.5">первый визит {{ \Carbon\Carbon::parse($p->first_visit)->format('d.m.Y') }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <span class="f-15 b-600 cs">{{ $p->visit_count }}</span>
                    <span class="f-13" style="opacity:.5"> визит{{ $p->visit_count == 1 ? '' : ($p->visit_count < 5 ? 'а' : 'ов') }}</span>
                </div>
            </div>
            @empty
            <div class="text-center" style="opacity:.5;padding:1rem">За последние 30 дней новых игроков не было</div>
            @endforelse
        </div>

        {{-- РИСК ОТТОКА --}}
        @if($churnRisk->isNotEmpty())
        <div class="ramka">
            <h2 class="-mt-05">⚠️ Риск оттока</h2>
            <div class="f-14 mb-2" style="opacity:.6">Игроки с 3+ визитами, которые не приходили более 60 дней</div>
            @foreach($churnRisk as $i => $p)
            <div class="d-flex between fvc py-1 {{ $i > 0 ? 'border-top' : '' }}">
                <div class="d-flex fvc gap-2">
                    <span class="f-13 b-600" style="width:24px;opacity:.4">{{ $i+1 }}</span>
                    <div>
                        <a href="{{ route('users.show', $p->id) }}" class="f-15 b-600">
                            {{ trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: '#'.$p->id }}
                        </a>
                        <div class="f-12" style="opacity:.5">всего визитов: {{ $p->visits_total }}</div>
                    </div>
                </div>
                <div class="text-right">
                    @php $days = now()->diffInDays(\Carbon\Carbon::parse($p->last_visit)); @endphp
                    <span class="f-14 b-600" style="color:#f59e0b">{{ $days }} дн.</span>
                    <div class="f-12" style="opacity:.5">назад</div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- РАСПРЕДЕЛЕНИЕ --}}
        @if($genderStats->isNotEmpty() || $classicLevels->isNotEmpty() || $beachLevels->isNotEmpty())
        <div class="ramka">
            <h2 class="-mt-05">📈 Распределение аудитории</h2>
            <div class="row row2">

                {{-- Пол --}}
                @if($genderStats->isNotEmpty())
                <div class="col-md-4">
                    <div class="card">
                        <div class="f-14 b-600 mb-2">По полу</div>
                        @php
                            $male   = $genderStats->get('m')?->cnt ?? 0;
                            $female = $genderStats->get('f')?->cnt ?? 0;
                            $total  = $male + $female;
                        @endphp
                        @if($total > 0)
                        <div class="mb-1">
                            <div class="d-flex between f-13 mb-1">
                                <span>👨 Мужчины</span>
                                <span class="b-600">{{ $male }} ({{ $total ? round($male/$total*100) : 0 }}%)</span>
                            </div>
                            <div style="background:#eee;border-radius:4px;height:8px">
                                <div style="width:{{ $total ? round($male/$total*100) : 0 }}%;background:#3b82f6;height:8px;border-radius:4px"></div>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex between f-13 mb-1">
                                <span>👩 Женщины</span>
                                <span class="b-600">{{ $female }} ({{ $total ? round($female/$total*100) : 0 }}%)</span>
                            </div>
                            <div style="background:#eee;border-radius:4px;height:8px">
                                <div style="width:{{ $total ? round($female/$total*100) : 0 }}%;background:#ec4899;height:8px;border-radius:4px"></div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Уровни классика --}}
                @if($classicLevels->isNotEmpty())
                <div class="col-md-4">
                    <div class="card">
                        <div class="f-14 b-600 mb-2">По уровню (классика)</div>
                        <canvas id="classicLevelChart" height="160"></canvas>
                    </div>
                </div>
                @endif

                {{-- Уровни пляжка --}}
                @if($beachLevels->isNotEmpty())
                <div class="col-md-4">
                    <div class="card">
                        <div class="f-14 b-600 mb-2">По уровню (пляжка)</div>
                        <canvas id="beachLevelChart" height="160"></canvas>
                    </div>
                </div>
                @endif

            </div>
        </div>
        @endif

        {{-- ЧАСТО В РЕЗЕРВЕ --}}
        @if($reservePlayers->isNotEmpty())
        <div class="ramka">
            <h2 class="-mt-05">📋 Часто попадают в резерв</h2>
            <div class="f-14 mb-2" style="opacity:.6">Игроки, которые записываются позже всех и чаще оказываются на резервной позиции</div>
            <div class="row row2">
                <div class="col-md-6">
                    @foreach($reservePlayers as $i => $p)
                    <div class="d-flex between fvc py-1 {{ $i > 0 ? 'border-top' : '' }}">
                        <div class="d-flex fvc gap-2">
                            <span class="f-13 b-600" style="width:24px;opacity:.4">{{ $i+1 }}</span>
                            <a href="{{ route('users.show', $p->id) }}" class="f-15">
                                {{ trim(($p->last_name ?? '') . ' ' . ($p->first_name ?? '')) ?: '#'.$p->id }}
                            </a>
                        </div>
                        <div class="text-right">
                            <span class="f-15 b-600" style="color:#6366f1">{{ $p->reserve_count }}</span>
                            <span class="f-13" style="opacity:.5"> раз</span>
                            @if($p->reserve_90d > 0)
                            <div class="f-12" style="opacity:.5">+{{ $p->reserve_90d }} за 3 мес.</div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

    </div>

    <x-slot name="script">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    (function() {
        // ТОП ИГРОКОВ — переключатель периодов
        const players = @json($topPlayers);
        const fieldMap = {
            '30d':  'v_30d',
            '90d':  'v_90d',
            '180d': 'v_180d',
            '365d': 'v_365d',
            'all':  'v_all',
        };
        const labelMap = {
            '30d':  'за 30 дней',
            '90d':  'за 3 месяца',
            '180d': 'за 6 месяцев',
            '365d': 'за год',
            'all':  'за всё время',
        };

        function renderTopPlayers(period) {
            const field = fieldMap[period];
            const sorted = players
                .filter(p => p[field] > 0)
                .sort((a, b) => b[field] - a[field])
                .slice(0, 10);

            const list = document.getElementById('top-players-list');
            if (!sorted.length) {
                list.innerHTML = '<div style="opacity:.4;text-align:center;padding:2rem">Нет данных за выбранный период</div>';
                return;
            }

            let html = '';
            sorted.forEach((p, i) => {
                const name = ((p.last_name || '') + ' ' + (p.first_name || '')).trim() || '#' + p.id;
                const visits = p[field];
                html += `
                <div class="d-flex between fvc py-1 ${i > 0 ? 'border-top' : ''}">
                    <div class="d-flex fvc gap-2">
                        <span class="f-14 b-600" style="width:24px;opacity:.4">${i+1}</span>
                        <a href="/users/${p.id}" class="f-16">${name}</a>
                    </div>
                    <div class="text-right">
                        <span class="f-16 b-600">${visits}</span>
                        <span style="opacity:.5"> игр ${labelMap[period]}</span>
                    </div>
                </div>`;
            });
            list.innerHTML = html;
        }

        document.getElementById('period-tabs').addEventListener('click', function(e) {
            const btn = e.target.closest('.period-btn');
            if (!btn) return;
            this.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderTopPlayers(btn.dataset.period);
        });

        renderTopPlayers('30d');

        // ГРАФИКИ УРОВНЕЙ
        const isDark = document.body.classList.contains('dark');
        const textColor = isDark ? 'rgba(255,255,255,.7)' : 'rgba(0,0,0,.6)';

        const classicData = @json($classicLevels);
        const beachData   = @json($beachLevels);

        const barColors = [
            'rgba(59,130,246,.75)', 'rgba(16,185,129,.75)', 'rgba(245,158,11,.75)',
            'rgba(239,68,68,.75)',  'rgba(139,92,246,.75)', 'rgba(236,72,153,.75)',
            'rgba(20,184,166,.75)', 'rgba(251,146,60,.75)',
        ];

        function makeBarChart(id, data) {
            const ctx = document.getElementById(id);
            if (!ctx || !data.length) return;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(r => r.level),
                    datasets: [{
                        data: data.map(r => r.cnt),
                        backgroundColor: data.map((_, i) => barColors[i % barColors.length]),
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        x: { ticks: { color: textColor, font: { size: 11 } } },
                        y: { beginAtZero: true, ticks: { color: textColor } }
                    }
                }
            });
        }

        makeBarChart('classicLevelChart', classicData);
        makeBarChart('beachLevelChart', beachData);
    })();
    </script>
    </x-slot>

</x-voll-layout>
