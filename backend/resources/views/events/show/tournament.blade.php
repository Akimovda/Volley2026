@php
    $tournamentUi = $tournamentUi ?? [
        'enabled' => false,
        'setting' => null,
        'myTeams' => collect(),
    ];

    $tournamentSetting = $tournamentUi['setting'] ?? null;
    $isTournament = (bool) ($tournamentUi['enabled'] ?? false);
    $myTournamentTeams = $tournamentUi['myTeams'] ?? collect();

    $isBeachTournament = $tournamentSetting
        && (string) $tournamentSetting->registration_mode === 'team_beach';

    $isClassicTournament = $tournamentSetting
        && (string) $tournamentSetting->registration_mode === 'team_classic';
@endphp

@if($isTournament)
    <div class="ramka mb-4">
        <div class="p-4 rounded bg-white border">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
                <div>
                    <h3 class="mb-2">
                        {{ $isBeachTournament ? 'Турнирная регистрация пары' : 'Турнирная регистрация команды' }}
                    </h3>

                    <div class="text-muted">
                        @if($isBeachTournament)
                            Для этого турнира регистрация идёт парами / мини-командами.
                        @else
                            Для этого турнира регистрация идёт командами с капитаном и составом.
                        @endif
                    </div>
                </div>

                <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
                    <span class="badge bg-primary">
                        {{ $tournamentSetting->registration_mode }}
                    </span>

                    @if(!is_null($tournamentSetting->team_size_min) || !is_null($tournamentSetting->team_size_max))
                        <span class="badge bg-secondary">
                            Состав: {{ $tournamentSetting->team_size_min ?? '—' }}–{{ $tournamentSetting->team_size_max ?? '—' }}
                        </span>
                    @endif

                    @if($tournamentSetting->require_libero)
                        <span class="badge bg-warning text-dark">
                            Нужен либеро
                        </span>
                    @endif

                    @if(!empty($tournamentSetting->max_rating_sum))
                        <span class="badge bg-success">
                            Лимит рейтинга: {{ $tournamentSetting->max_rating_sum }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="border rounded p-3 bg-light h-100">
                        <h5 class="mb-3">
                            {{ $isBeachTournament ? 'Создать пару' : 'Создать команду' }}
                        </h5>

                        <p class="text-muted small">
                            После создания откроется страница команды, где можно управлять составом и подать заявку.
                        </p>

                        @auth
                            <form method="POST" action="{{ route('tournamentTeams.store', $event) }}">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">
                                        {{ $isBeachTournament ? 'Название пары' : 'Название команды' }}
                                    </label>

                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ old('name') }}"
                                        class="form-control"
                                        placeholder="{{ $isBeachTournament ? 'Например: Sand Bros' : 'Например: Volley Tigers' }}"
                                        required
                                    >
                                </div>
                                @if($isClassicTournament)
                                    <div class="mb-3">
                                        <label class="form-label">Ваша позиция в команде</label>
                                        <select name="captain_position_code" class="form-select" required>
                                            <option value="">— выбрать амплуа —</option>
                                            <option value="setter" @selected(old('captain_position_code') === 'setter')>Связующий</option>
                                            <option value="outside" @selected(old('captain_position_code') === 'outside')>Доигровщик</option>
                                            <option value="opposite" @selected(old('captain_position_code') === 'opposite')>Диагональный</option>
                                            <option value="middle" @selected(old('captain_position_code') === 'middle')>Центральный блокирующий</option>
                                            <option value="libero" @selected(old('captain_position_code') === 'libero')>Либеро</option>
                                        </select>
                                        <div class="form-text">
                                            При создании команды вы сразу становитесь капитаном и занимаете это амплуа в основном составе.
                                        </div>
                                    </div>
                                @endif
                                @if(($event->occurrences->count() ?? 0) > 1)
                                    <div class="mb-3">
                                        <label class="form-label">Игровой слот / этап</label>

                                        <select name="occurrence_id" class="form-select">
                                            <option value="">Без привязки</option>
                                            @foreach($event->occurrences as $itemOccurrence)
                                                <option
                                                    value="{{ $itemOccurrence->id }}"
                                                    @selected((string) old('occurrence_id') === (string) $itemOccurrence->id)
                                                >
                                                    #{{ $itemOccurrence->id }}
                                                    @if(!empty($itemOccurrence->starts_at))
                                                        — {{ \Illuminate\Support\Carbon::parse($itemOccurrence->starts_at)->format('d.m.Y H:i') }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <input
                                    type="hidden"
                                    name="team_kind"
                                    value="{{ $isBeachTournament ? 'beach_pair' : 'classic_team' }}"
                                >

                                <button type="submit" class="btn btn-primary">
                                    {{ $isBeachTournament ? 'Создать пару' : 'Создать команду' }}
                                </button>
                            </form>
                        @else
                            <div class="alert alert-warning mb-0">
                                Чтобы создать {{ $isBeachTournament ? 'пару' : 'команду' }}, нужно войти в аккаунт.
                            </div>
                        @endauth
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="border rounded p-3 bg-light h-100">
                        <h5 class="mb-3">Мои команды / пары</h5>

                        @auth
                            @if($myTournamentTeams->count() > 0)
                                <div class="d-flex flex-column gap-3">
                                    @foreach($myTournamentTeams as $team)
                                        <div class="border rounded bg-white p-3">
                                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold">{{ $team->name }}</div>

                                                    <div class="mt-2 d-flex flex-wrap gap-2">
                                                        <span class="badge bg-secondary">
                                                            {{ $team->team_kind === 'beach_pair' ? 'Пляжная пара' : 'Классическая команда' }}
                                                        </span>
                                                        <span class="badge bg-primary">Статус: {{ $team->status }}</span>

                                                        @if($team->application)
                                                            <span class="badge bg-success">Заявка: {{ $team->application->status }}</span>
                                                        @endif

                                                        @if($team->is_complete)
                                                            <span class="badge bg-success">Состав готов</span>
                                                        @else
                                                            <span class="badge bg-warning text-dark">Состав не готов</span>
                                                        @endif
                                                    </div>

                                                    {{-- Статистика состава (без деталей, только количество) --}}
                                                    <div class="mt-2 small text-muted">
                                                        @php
                                                            $confirmedCount = $team->members->where('confirmation_status', 'confirmed')->count();
                                                            $pendingCount = $team->members->whereIn('confirmation_status', ['invited', 'joined'])->count();
                                                        @endphp
                                                        Подтверждено: {{ $confirmedCount }}
                                                        @if($pendingCount > 0)
                                                            | Ожидают: {{ $pendingCount }}
                                                        @endif
                                                    </div>

                                                    @if($team->occurrence)
                                                        <div class="mt-2 small text-muted">
                                                            Этап / слот:
                                                            @if(!empty($team->occurrence->starts_at))
                                                                {{ \Illuminate\Support\Carbon::parse($team->occurrence->starts_at)->format('d.m.Y H:i') }}
                                                            @else
                                                                #{{ $team->occurrence->id }}
                                                            @endif
                                                        </div>
                                                    @endif

                                                    <div class="mt-2 small text-muted d-flex flex-wrap align-items-center gap-2">
                                                        <span>Инвайт-код:</span>
                                                        <code>{{ $team->invite_code }}</code>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            data-copy-invite-code="{{ $team->invite_code }}"
                                                        >
                                                            Копировать
                                                        </button>
                                                    </div>
                                                </div>

                                                <div>
                                                    <a
                                                        href="{{ route('tournamentTeams.show', [$event, $team]) }}"
                                                        class="btn btn-success"
                                                    >
                                                        Открыть команду
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted">
                                    У вас пока нет команды или пары для этого турнира.
                                </div>
                            @endif
                        @else
                            <div class="text-muted">
                                Войдите в аккаунт, чтобы увидеть свои команды и пары.
                            </div>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif