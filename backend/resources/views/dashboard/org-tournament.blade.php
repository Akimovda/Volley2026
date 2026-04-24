<x-voll-layout body_class="org-analytics-page">
<x-slot name="title">Аналитика турниров</x-slot>
<x-slot name="h1">Аналитика турниров</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('org.dashboard') }}" itemprop="item"><span itemprop="name">Панель организатора</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Аналитика турниров</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<div class="container">
<div class="ramka">

    {{-- Общие метрики --}}
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3">
            <div class="card text-center p-3">
                <div class="f-28 fw-bold">{{ $totalTournaments }}</div>
                <div class="text-muted f-14">Турниров</div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card text-center p-3">
                <div class="f-28 fw-bold">{{ $totalMatches }}</div>
                <div class="text-muted f-14">Матчей сыграно</div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card text-center p-3">
                <div class="f-28 fw-bold">{{ $uniquePlayers }}</div>
                <div class="text-muted f-14">Уникальных игроков</div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card text-center p-3">
                <div class="f-28 fw-bold">{{ $totalTeams }}</div>
                <div class="text-muted f-14">Команд</div>
            </div>
        </div>
    </div>

    {{-- Заполняемость, доход, retention --}}
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card p-3">
                <div class="f-14 text-muted mb-1">Средняя заполняемость</div>
                <div class="f-22 fw-bold">{{ $avgFillRate ? number_format($avgFillRate, 0) . '%' : '—' }}</div>
                <div class="f-12 text-muted">% команд от максимума</div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card p-3">
                <div class="f-14 text-muted mb-1">Доход с турниров</div>
                <div class="f-22 fw-bold">
                    @if($revenue)
                        {{ number_format($revenue / 100, 0, ',', ' ') }} ₽
                    @else
                        —
                    @endif
                </div>
                <div class="f-12 text-muted">стоимость × кол-во команд</div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card p-3">
                <div class="f-14 text-muted mb-1">Retention</div>
                <div class="f-22 fw-bold">{{ $retentionData['rate'] }}%</div>
                <div class="f-12 text-muted">
                    {{ $retentionData['returning'] }} из {{ $retentionData['total'] }} вернулись на 2+ турнира
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- LEFT: Динамика + турниры --}}
        <div class="col-lg-7">

            {{-- График участников по месяцам --}}
            @if($monthlyParticipants->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header"><strong>Участники по месяцам</strong></div>
                    <div class="card-body">
                        <canvas id="monthlyChart" height="200"></canvas>
                    </div>
                </div>
            @endif

            {{-- Список турниров --}}
            <div class="card mb-4">
                <div class="card-header"><strong>Турниры</strong></div>
                <div class="card-body p-0">
                    @if($tournaments->isNotEmpty())
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr><th>Турнир</th><th>Формат</th><th>Команд</th><th>Матчей</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach($tournaments as $t)
                                    <tr>
                                        <td>
                                            <a href="{{ route('tournament.setup', $t->id) }}">{{ $t->title }}</a>
                                            <div class="f-12 text-muted">{{ \Carbon\Carbon::parse($t->created_at)->format('d.m.Y') }}</div>
                                        </td>
                                        <td class="f-14">{{ $t->game_scheme ?? $t->direction ?? '—' }}</td>
                                        <td>{{ $t->teams_count }}</td>
                                        <td>{{ $t->matches_played }}</td>
                                        <td>
                                            <a href="{{ route('tournament.public.show', $t->id) }}" class="f-12">Публичная →</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="p-3 text-muted">Нет турниров</div>
                    @endif
                </div>
            </div>

            {{-- Сезоны --}}
            @if($seasons->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between">
                        <strong>Сезоны</strong>
                        <a href="{{ route('leagues.index') }}" class="f-14">Мои лиги →</a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Сезон</th><th>Лиг</th><th>Туров сыграно</th><th>Статус</th></tr></thead>
                            <tbody>
                                @foreach($seasons as $s)
                                    <tr>
                                        <td><a href="{{ route('seasons.edit', $s) }}">{{ $s->name }}</a></td>
                                        <td>{{ $s->leagues_count }}</td>
                                        <td>{{ $s->seasonEvents->count() }}</td>
                                        <td>
                                            <span class="badge bg-{{ $s->status === 'active' ? 'success' : ($s->status === 'completed' ? 'secondary' : 'warning') }}">
                                                {{ $s->status === 'active' ? 'Активен' : ($s->status === 'completed' ? 'Завершён' : 'Черновик') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- RIGHT: Топ игроков --}}
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header"><strong>Топ игроков по WinRate</strong></div>
                <div class="card-body p-0">
                    @if($topPlayers->isNotEmpty())
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>#</th><th>Игрок</th><th>WR%</th><th>В/И</th><th>Турн.</th></tr></thead>
                            <tbody>
                                @foreach($topPlayers as $i => $p)
                                    <tr>
                                        <td>
                                            @if($i < 3)
                                                <strong>{{ ['🥇','🥈','🥉'][$i] }}</strong>
                                            @else
                                                {{ $i + 1 }}
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('users.show', $p->id) }}">
                                                {{ trim($p->first_name . ' ' . $p->last_name) ?: 'Игрок #' . $p->id }}
                                            </a>
                                        </td>
                                        <td><strong>{{ $p->win_rate }}%</strong></td>
                                        <td class="f-14">{{ $p->total_wins }}/{{ $p->total_matches }}</td>
                                        <td class="f-14">{{ $p->tournaments }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="p-3 text-muted">Нет данных (минимум 3 матча)</div>
                    @endif
                </div>
            </div>

            {{-- Ссылки --}}
            <div class="card">
                <div class="card-body">
                    <a href="{{ route('players.rating') }}" class="btn btn-outline-primary btn-sm w-100 mb-2">Публичный рейтинг игроков</a>
                    <a href="{{ route('org.dashboard') }}" class="btn btn-outline-secondary btn-sm w-100">← Панель организатора</a>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

{{-- Chart.js --}}
@if($monthlyParticipants->isNotEmpty())
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlyChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($monthlyParticipants->pluck('month')) !!},
            datasets: [
                {
                    label: 'Игроков',
                    data: {!! json_encode($monthlyParticipants->pluck('players')) !!},
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderRadius: 4,
                },
                {
                    label: 'Команд',
                    data: {!! json_encode($monthlyParticipants->pluck('teams')) !!},
                    backgroundColor: 'rgba(255, 159, 64, 0.7)',
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
});
</script>
@endif

</x-voll-layout>
