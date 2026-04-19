<x-voll-layout body_class="rating-page">
<x-slot name="title">Рейтинг игроков</x-slot>
<x-slot name="h1">Рейтинг игроков</x-slot>

<div class="container">
<div class="ramka">

    {{-- Фильтры --}}
    <form method="GET" action="{{ route('players.rating') }}" class="row g-2 mb-4 align-items-end">
        <div class="col-sm-3">
            <label class="form-label">Направление</label>
            <select name="direction" class="form-select" onchange="this.form.submit()">
                <option value="classic" {{ $direction === 'classic' ? 'selected' : '' }}>Классический</option>
                <option value="beach" {{ $direction === 'beach' ? 'selected' : '' }}>Пляжный</option>
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label">Сезон</label>
            <select name="season_id" class="form-select" onchange="this.form.submit()">
                <option value="">Карьерный (все)</option>
                @foreach($seasons as $s)
                    <option value="{{ $s->id }}" {{ $seasonId == $s->id ? 'selected' : '' }}>
                        {{ $s->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label">Сортировка</label>
            <select name="sort" class="form-select" onchange="this.form.submit()">
                <option value="match_win_rate" {{ $sort === 'match_win_rate' ? 'selected' : '' }}>WinRate</option>
                <option value="elo_rating" {{ $sort === 'elo_rating' ? 'selected' : '' }}>Elo</option>
                <option value="matches_played" {{ $sort === 'matches_played' ? 'selected' : '' }}>Матчей</option>
            </select>
        </div>
        <div class="col-sm-3">
            <button type="submit" class="btn btn-outline-primary w-100">Обновить</button>
        </div>
    </form>

    {{-- Таблица рейтинга --}}
    @if($players->isEmpty())
        <div class="alert alert-info">
            Нет данных для отображения. Рейтинг формируется после завершения турниров.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Игрок</th>
                        @if($seasonId)
                            <th>Лига</th>
                            <th>Туров</th>
                        @endif
                        <th>Матчей</th>
                        <th>Побед</th>
                        <th>WinRate</th>
                        <th>Сеты</th>
                        <th>Очки ±</th>
                        @if(!$seasonId)
                            <th>Elo</th>
                            <th>Турниров</th>
                        @else
                            <th>Streak</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($players as $i => $stat)
                        @php
                            $rank = $players->firstItem() + $i;
                            $user = $stat->user;
                            $isSeasonMode = (bool) $seasonId;
                        @endphp
                        <tr>
                            <td>
                                @if($rank <= 3)
                                    <strong class="f-18">{{ ['🥇','🥈','🥉'][$rank - 1] }}</strong>
                                @else
                                    {{ $rank }}
                                @endif
                            </td>
                            <td>
                                @if($user)
                                    <a href="{{ route('users.show', $user->id) }}">{{ $user->name }}</a>
                                @else
                                    Игрок #{{ $stat->user_id }}
                                @endif
                            </td>
                            @if($isSeasonMode)
                                <td>{{ $stat->league?->name ?? '—' }}</td>
                                <td>{{ $stat->rounds_played }}</td>
                            @endif
                            <td>{{ $isSeasonMode ? $stat->matches_played : $stat->total_matches }}</td>
                            <td>{{ $isSeasonMode ? $stat->matches_won : $stat->total_wins }}</td>
                            <td>
                                <strong>{{ number_format($stat->match_win_rate, 1) }}%</strong>
                            </td>
                            <td>
                                @php
                                    $sw = $isSeasonMode ? $stat->sets_won : $stat->total_sets_won;
                                    $sl = $isSeasonMode ? $stat->sets_lost : $stat->total_sets_lost;
                                @endphp
                                {{ $sw }}:{{ $sl }}
                            </td>
                            <td class="{{ $stat->pointDiff() >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $stat->pointDiff() >= 0 ? '+' : '' }}{{ $stat->pointDiff() }}
                            </td>
                            @if(!$isSeasonMode)
                                <td>{{ $stat->elo_rating }}</td>
                                <td>{{ $stat->total_tournaments }}</td>
                            @else
                                <td>
                                    @if($stat->current_streak > 0)
                                        <span class="text-success">🔥 {{ $stat->current_streak }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $players->links() }}
    @endif

</div>
</div>

</x-voll-layout>
