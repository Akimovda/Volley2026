<x-voll-layout body_class="player-dashboard-page">

    <x-slot name="title">Моя статистика</x-slot>
    <x-slot name="h1">Моя статистика</x-slot>
    <x-slot name="t_description">Ваша активность на площадке</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">Профиль</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Статистика</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">

        {{-- СВОДКА --}}
        <div class="ramka">
            <h2 class="-mt-05">🏐 Активность</h2>
            <div class="row row2">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Всего игр</div>
                        <div class="f-36 b-700 cs">{{ $totalVisits }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">В этом месяце</div>
                        <div class="f-36 b-700 cd">{{ $visitsThisMonth }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Отмен</div>
                        <div class="f-36 b-700 red">{{ $totalCancellations }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Серия недель</div>
                        <div class="f-36 b-700">{{ $streak }} 🔥</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- РЕЙТИНГ И ОЦЕНКИ --}}
        <div class="ramka">
            <h2 class="-mt-05">⭐ Рейтинг и оценки</h2>
            <div class="row row2">
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Оценок уровня</div>
                        <div class="f-36 b-700">{{ $totalVotes }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Средний уровень</div>
                        <div class="f-36 b-700 cd">{{ $avgLevel ? round($avgLevel, 1) : '—' }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">❤️ Нравится с ними играть</div>
                        <div class="f-36 b-700 cs">{{ $likesCount }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">Топ активности</div>
                        <div class="f-36 b-700">{{ $percentile }}%</div>
                        <div class="f-12" style="opacity:.5">игроков</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ПРОСМОТРЫ ПРОФИЛЯ --}}
        <div class="ramka">
            <h2 class="-mt-05">👁 Просмотры профиля</h2>
            <div class="row row2">
                <div class="col-6">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">За все время</div>
                        <div class="f-36 b-700">{{ $profileViews }}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center">
                        <div class="f-13" style="opacity:.6">За последние 30 дней</div>
                        <div class="f-36 b-700 cd">{{ $profileViews30d }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ДИНАМИКА --}}
        <div class="ramka">
            <h2 class="-mt-05">📈 Активность по месяцам</h2>
            <div class="card">
                <canvas id="playerMonthlyChart" height="80"></canvas>
            </div>
        </div>

        {{-- ПОЗИЦИИ + ЛОКАЦИИ + ОРГАНИЗАТОРЫ --}}
        <div class="ramka">
            <div class="row row2">
                {{-- Позиции --}}
                <div class="col-md-4">
                    <h2 class="-mt-05">🏃 Позиции</h2>
                    <div class="card">
                        @forelse($positions as $pos)
                        <div class="d-flex between fvc py-1">
                            <span class="f-16">{{ position_name($pos->position) }}</span>
                            <span class="f-16 b-600">{{ $pos->cnt }}</span>
                        </div>
                        @empty
                        <div class="f-16" style="opacity:.5">Нет данных</div>
                        @endforelse
                    </div>
                </div>

                {{-- Локации --}}
                <div class="col-md-4">
                    <h2 class="-mt-05">📍 Любимые площадки</h2>
                    <div class="card">
                        @forelse($topLocations as $i => $loc)
                        <div class="d-flex between fvc py-1 {{ $i > 0 ? 'border-top' : '' }}">
                            <a href="{{ route('locations.show', [$loc->id, \Illuminate\Support\Str::slug($loc->name)]) }}" class="f-16">
                                {{ $loc->name }}
                            </a>
                            <span class="f-16 b-600">{{ $loc->visits }}</span>
                        </div>
                        @empty
                        <div class="f-16" style="opacity:.5">Нет данных</div>
                        @endforelse
                    </div>
                </div>

                {{-- Организаторы --}}
                <div class="col-md-4">
                    <h2 class="-mt-05">🧑‍💼 Любимые организаторы</h2>
                    <div class="card">
                        @forelse($topOrganizers as $i => $org)
                        <div class="d-flex between fvc py-1 {{ $i > 0 ? 'border-top' : '' }}">
                            <a href="{{ route('users.show', $org->id) }}" class="f-16">
                                {{ trim($org->first_name . ' ' . $org->last_name) ?: '#'.$org->id }}
                            </a>
                            <span class="f-16 b-600">{{ $org->visits }}</span>
                        </div>
                        @empty
                        <div class="f-16" style="opacity:.5">Нет данных</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>

    <x-slot name="script">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const data = @json($monthlyVisits);
        const ctx = document.getElementById('playerMonthlyChart');
        if (ctx && data.length) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(r => r.month),
                    datasets: [{
                        label: 'Игр',
                        data: data.map(r => r.visits),
                        borderColor: 'rgba(13, 110, 253, 0.8)',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    });
    </script>
    </x-slot>

</x-voll-layout>
