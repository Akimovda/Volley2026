<x-voll-layout body_class="seasons-page">
<x-slot name="title">{{ $season->name }} — Управление</x-slot>
<x-slot name="h1">{{ $season->name }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('seasons.index') }}" itemprop="item"><span itemprop="name">Мои сезоны</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ $season->name }}</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<div class="container">
<div class="ramka">

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row">
        {{-- LEFT: Настройки сезона --}}
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Настройки</strong>
                    <span class="badge bg-{{ $season->status === 'active' ? 'success' : ($season->status === 'completed' ? 'secondary' : 'warning') }}">
                        {{ $season->status === 'active' ? 'Активен' : ($season->status === 'completed' ? 'Завершён' : 'Черновик') }}
                    </span>
                </div>
                <div class="card-body">
                    <form action="{{ route('seasons.update', $season) }}" method="POST">
                        @csrf @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Название</label>
                            <input type="text" name="name" class="form-control" value="{{ $season->name }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Направление</label>
                            <select name="direction" class="form-select">
                                <option value="classic" {{ $season->direction === 'classic' ? 'selected' : '' }}>Классический</option>
                                <option value="beach" {{ $season->direction === 'beach' ? 'selected' : '' }}>Пляжный</option>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Начало</label>
                                <input type="date" name="starts_at" class="form-control" value="{{ $season->starts_at?->format('Y-m-d') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Конец</label>
                                <input type="date" name="ends_at" class="form-control" value="{{ $season->ends_at?->format('Y-m-d') }}">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-100">Сохранить</button>
                    </form>
                </div>
                <div class="card-footer">
                    <div class="d-flex gap-2">
                        @if($season->isDraft())
                            <form action="{{ route('seasons.activate', $season) }}" method="POST" class="flex-fill">
                                @csrf
                                <button class="btn btn-success btn-sm w-100">Активировать</button>
                            </form>
                            <form action="{{ route('seasons.destroy', $season) }}" method="POST" class="flex-fill"
                                  onsubmit="return confirm('Удалить сезон?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm w-100">Удалить</button>
                            </form>
                        @elseif($season->isActive())
                            <form action="{{ route('seasons.complete', $season) }}" method="POST" class="flex-fill"
                                  onsubmit="return confirm('Завершить сезон?')">
                                @csrf
                                <button class="btn btn-secondary btn-sm w-100">Завершить сезон</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Публичная ссылка --}}
            <div class="mt-3 f-14">
                <strong>Публичная страница:</strong>
                <a href="{{ route('seasons.show.slug', $season->slug) }}" target="_blank">/s/{{ $season->slug }}</a>
            </div>
        </div>

        {{-- RIGHT: Лиги + турниры --}}
        <div class="col-lg-8">
            {{-- Добавить лигу --}}
            <div class="card mb-4">
                <div class="card-header"><strong>Лиги</strong></div>
                <div class="card-body">
                    <form action="{{ route('seasons.leagues.store', $season) }}" method="POST" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-sm-5">
                            <label class="form-label">Название лиги</label>
                            <input type="text" name="name" class="form-control" placeholder="Hard / Lite / Open" required>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">Макс. команд</label>
                            <input type="number" name="max_teams" class="form-control" min="2" placeholder="—">
                        </div>
                        <div class="col-sm-4">
                            <button type="submit" class="btn btn-primary w-100">Добавить лигу</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Список лиг --}}
            @forelse($season->leagues as $league)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>{{ $league->name }}</strong>
                        <span class="text-muted f-14">
                            {{ $league->activeTeams->count() }} команд{{ $league->max_teams ? ' / макс. ' . $league->max_teams : '' }}
                            @if($league->reserveTeams->count() > 0)
                                · резерв: {{ $league->reserveTeams->count() }}
                            @endif
                        </span>
                    </div>
                    <div class="card-body p-0">
                        {{-- Активные команды --}}
                        @if($league->activeTeams->isNotEmpty())
                            <table class="table table-sm mb-0">
                                <tbody>
                                @foreach($league->activeTeams as $lt)
                                    <tr>
                                        <td>
                                            @if($lt->team)
                                                {{ $lt->team->name }}
                                                @if($lt->team->captain)
                                                    <span class="text-muted f-14">({{ $lt->team->captain->name }})</span>
                                                @endif
                                            @elseif($lt->user)
                                                {{ $lt->user->name }}
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <form action="{{ route('leagues.teams.destroy', $lt) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Убрать из лиги?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger py-0 px-2">✕</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="p-3 text-muted f-14">Нет команд</div>
                        @endif

                        {{-- Резерв --}}
                        @if($league->reserveTeams->isNotEmpty())
                            <div class="px-3 py-2 bg-light border-top">
                                <strong class="f-14">Резерв:</strong>
                                @foreach($league->reserveTeams as $lt)
                                    <span class="badge bg-secondary ms-1">
                                        #{{ $lt->reserve_position }}
                                        {{ $lt->team?->name ?? $lt->user?->name ?? '—' }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <span class="f-14 text-muted">
                            Повышение: {{ $league->promoteCount() }} · Вылет: {{ $league->eliminateCount() }}
                        </span>
                        <form action="{{ route('leagues.destroy', $league) }}" method="POST"
                              onsubmit="return confirm('Удалить лигу «{{ $league->name }}»?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0">Удалить лигу</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="alert alert-warning">Добавьте лиги для сезона.</div>
            @endforelse

            {{-- Привязанные турниры --}}
            <div class="card mt-4">
                <div class="card-header"><strong>Турниры сезона</strong></div>
                <div class="card-body">
                    @if($season->seasonEvents->isNotEmpty())
                        <table class="table table-sm">
                            <thead><tr><th>Тур</th><th>Турнир</th><th>Лига</th><th>Статус</th><th></th></tr></thead>
                            <tbody>
                            @foreach($season->seasonEvents->sortBy('round_number') as $se)
                                <tr>
                                    <td>{{ $se->round_number }}</td>
                                    <td><a href="{{ route('events.show', $se->event) }}">{{ $se->event->title }}</a></td>
                                    <td>{{ $se->league->name }}</td>
                                    <td>
                                        <span class="badge bg-{{ $se->isCompleted() ? 'success' : 'warning' }}">
                                            {{ $se->isCompleted() ? 'Завершён' : 'Ожидает' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <form action="{{ route('seasons.events.detach', [$season, $se->event]) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Отвязать турнир?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger py-0 px-2">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted mb-3">Нет привязанных турниров.</p>
                    @endif

                    {{-- Привязать новый --}}
                    @if($availableEvents->isNotEmpty() && $season->leagues->isNotEmpty())
                        <form action="{{ route('seasons.events.attach', $season) }}" method="POST" class="row g-2 align-items-end border-top pt-3">
                            @csrf
                            <div class="col-sm-4">
                                <label class="form-label">Турнир</label>
                                <select name="event_id" class="form-select" required>
                                    <option value="">Выберите...</option>
                                    @foreach($availableEvents as $ev)
                                        <option value="{{ $ev->id }}">{{ $ev->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label">Лига</label>
                                <select name="league_id" class="form-select" required>
                                    @foreach($season->leagues as $lg)
                                        <option value="{{ $lg->id }}">{{ $lg->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-2">
                                <label class="form-label">Тур #</label>
                                <input type="number" name="round_number" class="form-control" min="1" value="{{ $season->currentRound() + 1 }}">
                            </div>
                            <div class="col-sm-3">
                                <button type="submit" class="btn btn-primary w-100">Привязать</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

        </div>
    </div>

</div>
</div>

</x-voll-layout>
