<x-voll-layout body_class="seasons-page">
<x-slot name="title">{{ $season->name }}</x-slot>
<x-slot name="h1">{{ $season->name }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Сезоны</span>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ $season->name }}</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<div class="container">
<div class="ramka">

    {{-- Инфо --}}
    <div class="d-flex flex-wrap gap-3 mb-4 f-14">
        <span>Организатор: <strong>{{ $season->organizer->name }}</strong></span>
        <span>{{ $season->direction === 'beach' ? 'Пляжный' : 'Классический' }}</span>
        @if($season->starts_at)
            <span>{{ $season->starts_at->format('d.m.Y') }} @if($season->ends_at)— {{ $season->ends_at->format('d.m.Y') }}@endif</span>
        @endif
        <span class="badge bg-{{ $season->status === 'active' ? 'success' : ($season->status === 'completed' ? 'secondary' : 'warning') }}">
            {{ $season->status === 'active' ? 'Активен' : ($season->status === 'completed' ? 'Завершён' : 'Черновик') }}
        </span>
    </div>

    {{-- Лиги --}}
    @foreach($season->leagues as $league)
        <div class="card mb-4">
            <div class="card-header">
                <strong class="f-18">{{ $league->name }}</strong>
                <span class="text-muted ms-2 f-14">{{ $league->activeTeams->count() }} команд</span>
            </div>
            <div class="card-body p-0">
                @if($league->activeTeams->isNotEmpty())
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>#</th><th>Команда / Игрок</th></tr></thead>
                        <tbody>
                        @foreach($league->activeTeams as $i => $lt)
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td>
                                    @if($lt->team)
                                        {{ $lt->team->name }}
                                    @elseif($lt->user)
                                        {{ $lt->user->name }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-3 text-muted">Нет команд</div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- Расписание туров --}}
    @if($season->seasonEvents->isNotEmpty())
        <h3 class="f-18 fw-bold mt-4 mb-3">Расписание туров</h3>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Тур</th><th>Турнир</th><th>Лига</th><th>Статус</th></tr></thead>
                <tbody>
                @foreach($season->seasonEvents->sortBy('round_number') as $se)
                    <tr>
                        <td>{{ $se->round_number }}</td>
                        <td><a href="{{ route('events.show', $se->event) }}">{{ $se->event->title }}</a></td>
                        <td>{{ $se->league->name }}</td>
                        <td>
                            <span class="badge bg-{{ $se->isCompleted() ? 'success' : 'warning' }}">
                                {{ $se->isCompleted() ? 'Сыгран' : 'Предстоит' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Рейтинг игроков сезона --}}
    @if($season->stats->isNotEmpty())
        <h3 class="f-18 fw-bold mt-4 mb-3">Рейтинг игроков</h3>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr><th>#</th><th>Игрок</th><th>Матчей</th><th>Побед</th><th>WinRate</th><th>Сеты</th><th>Очки ±</th></tr>
                </thead>
                <tbody>
                @foreach($season->stats->take(20) as $i => $stat)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            <a href="{{ route('users.show', $stat->user_id) }}">{{ $stat->user->name ?? 'Игрок #'.$stat->user_id }}</a>
                        </td>
                        <td>{{ $stat->matches_played }}</td>
                        <td>{{ $stat->matches_won }}</td>
                        <td><strong>{{ number_format($stat->match_win_rate, 1) }}%</strong></td>
                        <td>{{ $stat->sets_won }}:{{ $stat->sets_lost }}</td>
                        <td class="{{ $stat->pointDiff() >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $stat->pointDiff() >= 0 ? '+' : '' }}{{ $stat->pointDiff() }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
</div>

</x-voll-layout>
