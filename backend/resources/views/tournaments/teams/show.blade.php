<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 py-6">
        <div class="mb-4">
            <a
                href="{{ route('events.show', $event) }}"
                class="inline-flex items-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-200"
            >
                ← Назад к турниру
            </a>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $team->name }}</h1>

                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                            {{ $team->team_kind === 'classic_team' ? 'Классическая команда' : 'Пляжная команда' }}
                        </span>

                        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">
                            Статус: {{ $team->status }}
                        </span>

                        @if($team->is_complete)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">
                                Состав готов
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">
                                Состав не готов
                            </span>
                        @endif
                    </div>

                    <div class="mt-4 text-sm text-gray-600">
                        Капитан:
                        <span class="font-medium text-gray-900">
                            {{ $team->captain->name ?? ('#' . $team->captain_user_id) }}
                        </span>
                    </div>

                    @if($team->occurrence)
                        <div class="mt-1 text-sm text-gray-600">
                            Игровой слот:
                            <span class="font-medium text-gray-900">
                                {{ $team->occurrence->starts_at ?? ('#' . $team->occurrence->id) }}
                            </span>
                        </div>
                    @endif
                </div>

                <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-gray-50 p-4">
                    <div class="text-sm font-semibold text-gray-800">Приглашение по ссылке</div>
                    <div class="mt-2 text-sm text-gray-600">
                        Капитан создаёт персональную ссылку для игрока на нужную роль и позицию.
                    </div>
                    <div class="mt-2 text-xs text-gray-500">
                        Если игрок принимает одну ссылку, остальные активные приглашения для него автоматически деактивируются.
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <div class="mb-2 font-semibold">Есть ошибки:</div>
                <ul class="list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $settings = $team->event->tournamentSetting;
            $confirmedCount = $team->members->where('confirmation_status', 'confirmed')->count();
            $pendingCount = ($team->invites ?? collect())->where('status', 'pending')->count();
        @endphp

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Состав команды</h2>
                        <div class="text-sm text-gray-500">
                            Всего: {{ $team->members->count() }}
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($team->members as $member)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <div class="font-semibold text-gray-900">
                                            {{ $member->user->name ?? ('#' . $member->user_id) }}
                                        </div>

                                        <div class="mt-1 flex flex-wrap gap-2">
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                                {{ match($member->team_role) {
                                                    'captain' => 'Капитан',
                                                    'player' => 'Основной',
                                                    'reserve' => 'Запасной',
                                                    default => $member->team_role ?? $member->role_code,
                                                } }}
                                            </span>

                                            @if($team->team_kind === 'classic_team' && $member->position_code)
                                                <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-1 text-xs font-medium text-purple-700">
                                                    {{ match($member->position_code) {
                                                        'setter' => 'Связующий',
                                                        'outside' => 'Доигровщик',
                                                        'opposite' => 'Диагональный',
                                                        'middle' => 'Центральный блокирующий',
                                                        'libero' => 'Либеро',
                                                        default => $member->position_code,
                                                    } }}
                                                </span>
                                            @endif

                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                                                @if($member->confirmation_status === 'confirmed') bg-emerald-100 text-emerald-700
                                                @elseif($member->confirmation_status === 'joined') bg-amber-100 text-amber-700
                                                @elseif($member->confirmation_status === 'invited') bg-sky-100 text-sky-700
                                                @elseif($member->confirmation_status === 'declined') bg-rose-100 text-rose-700
                                                @else bg-slate-100 text-slate-700 @endif
                                            ">
                                                {{ match($member->confirmation_status) {
                                                    'confirmed' => 'Подтверждён',
                                                    'joined' => 'Ожидает подтверждения',
                                                    'invited' => 'Приглашён',
                                                    'declined' => 'Отклонён',
                                                    default => $member->confirmation_status,
                                                } }}
                                            </span>
                                        </div>
                                    </div>

                                    @if((int) $team->captain_user_id === (int) auth()->id() && (int) $member->user_id !== (int) $team->captain_user_id)
                                        <div class="flex flex-wrap gap-2">
                                            @if($member->confirmation_status !== 'confirmed')
                                                <form method="POST" action="{{ route('tournamentTeams.members.confirm', [$event, $team, $member]) }}">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                                                    >
                                                        Подтвердить
                                                    </button>
                                                </form>
                                            @endif

                                            @if($member->confirmation_status !== 'declined')
                                                <form method="POST" action="{{ route('tournamentTeams.members.decline', [$event, $team, $member]) }}">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700"
                                                    >
                                                        Отклонить
                                                    </button>
                                                </form>
                                            @endif

                                            <form
                                                method="POST"
                                                action="{{ route('tournamentTeams.members.destroy', [$event, $team, $member]) }}"
                                                data-confirm-remove-member
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700"
                                                >
                                                    Удалить
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500">Состав пока пуст.</div>
                        @endforelse
                    </div>
                </div>
                @if((int) $team->captain_user_id === (int) auth()->id())
                    <div class="rounded-2xl border border-gray-100 bg-white p-6">
                        <h2 class="text-lg font-semibold text-gray-900">Созданные приглашения</h2>
                
                        <div class="mt-4 space-y-3">
                            @forelse(($team->invites ?? collect())->sortByDesc('id') as $invite)
                                @php
                                    $inviteUser = $invite->invitedUser;
                                    $inviteMeta = is_array($invite->meta) ? $invite->meta : [];
                
                                    $roleLabel = match($invite->team_role) {
                                        'player' => 'Основной игрок',
                                        'reserve' => 'Запасной',
                                        default => $invite->team_role,
                                    };
                
                                    $positionLabel = match($invite->position_code) {
                                        'setter' => 'Связующий',
                                        'outside' => 'Доигровщик',
                                        'opposite' => 'Диагональный',
                                        'middle' => 'Центральный блокирующий',
                                        'libero' => 'Либеро',
                                        default => $invite->position_code ?: '—',
                                    };
                
                                    $statusLabel = match($invite->status) {
                                        'pending' => 'Ожидает',
                                        'accepted' => 'Принято',
                                        'declined' => 'Отклонено',
                                        'revoked' => 'Отозвано',
                                        'expired' => 'Истекло',
                                        default => $invite->status,
                                    };
                
                                    $statusClass = match($invite->status) {
                                        'accepted' => 'bg-emerald-100 text-emerald-700',
                                        'declined' => 'bg-rose-100 text-rose-700',
                                        'revoked', 'expired' => 'bg-slate-100 text-slate-700',
                                        default => 'bg-amber-100 text-amber-700',
                                    };
                
                                    $inviteUrl = $inviteMeta['invite_url'] ?? route('tournamentTeamInvites.show', ['token' => $invite->token]);
                                    $channels = collect($inviteMeta['sent_channels'] ?? [])->filter()->values();
                                @endphp
                
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div class="min-w-0">
                                            <div class="font-semibold text-gray-900">
                                                {{ $inviteUser->name ?? $inviteUser->email ?? ('#' . $invite->invited_user_id) }}
                                            </div>
                
                                            <div class="mt-1 flex flex-wrap gap-2">
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                                    {{ $roleLabel }}
                                                </span>
                
                                                <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-1 text-xs font-medium text-purple-700">
                                                    {{ $positionLabel }}
                                                </span>
                
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClass }}">
                                                    {{ $statusLabel }}
                                                </span>
                                            </div>
                
                                            <div class="mt-2 text-xs text-gray-500">
                                                Отправлено:
                                                @if($channels->isNotEmpty())
                                                    {{ $channels->join(', ') }}
                                                @else
                                                    ссылка создана
                                                @endif
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500">
                                                Создано: {{ $invite->created_at?->format('d.m.Y H:i') ?? '—' }}
                                            </div>
                                            <div class="mt-2 text-xs text-gray-500 break-all">
                                                <span class="font-medium">Ссылка:</span>
                                                <a href="{{ $inviteUrl }}" target="_blank" class="text-blue-600 underline">
                                                    {{ $inviteUrl }}
                                                </a>
                                            </div>
                                        </div>
                
                                        <div class="shrink-0">
                                            <a
                                                href="{{ $inviteUrl }}"
                                                target="_blank"
                                                class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                            >
                                                Приглашение 🔗
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-sm text-gray-500">
                                    Приглашений пока нет.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif
                <div class="rounded-2xl border border-gray-100 bg-white p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Подача заявки</h2>

                    <div class="mt-3 text-sm text-gray-600">
                        @if($team->application)
                            Заявка уже существует.
                            <span class="font-medium text-gray-900">Статус: {{ $team->application->status }}</span>
                        @else
                            Если состав готов, капитан может подать заявку на турнир.
                        @endif
                    </div>

                    @if((int) $team->captain_user_id === (int) auth()->id() && !$team->application)
                        <form
                            method="POST"
                            action="{{ route('tournamentTeams.submit', [$event, $team]) }}"
                            class="mt-4"
                            data-confirm-submit-team
                        >
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700"
                            >
                                Подать заявку
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                @if((int) $team->captain_user_id === (int) auth()->id())
                    <div class="rounded-2xl border border-gray-100 bg-white p-6">
                        <h2 class="text-lg font-semibold text-gray-900">Создать ссылку-приглашение</h2>

                        <form
                            method="POST"
                            action="{{ route('tournamentTeamInvites.store', [$event, $team]) }}"
                            class="mt-4 space-y-3"
                        >
                            @csrf

                            <div>
                                <label class="mb-2 block text-sm font-medium">Поиск игрока</label>
                                <input
                                    type="text"
                                    id="team-invite-user-search"
                                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"
                                    placeholder="Введите имя, email или username"
                                    autocomplete="off"
                                    data-search-url="{{ route('api.users.search') }}"
                                >
                                <input type="hidden" name="invited_user_id" id="team-invite-user-id">
                                <div
                                    id="team-invite-search-results"
                                    class="mt-2 hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
                                ></div>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium">Роль в команде</label>
                                <select
                                    name="team_role"
                                    id="team_role"
                                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="player">Основной игрок</option>
                                    <option value="reserve">Запасной</option>
                                </select>
                            </div>

                            @if($team->team_kind === 'classic_team')
                                <div id="position_code_wrap">
                                    <label class="mb-2 block text-sm font-medium">Амплуа (позиция)</label>
                                    <select
                                        name="position_code"
                                        id="position_code"
                                        class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"
                                    >
                                        <option value="">Не выбрано</option>
                                        @foreach(($positionOptions ?? []) as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-xs text-gray-500">
                                        Для основного игрока классической команды позиция обязательна.
                                    </small>
                                </div>
                            @endif

                            <div id="team-invite-selected-user" class="text-sm text-gray-600">
                                Игрок не выбран
                            </div>

                            <button
                                type="submit"
                                class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                            >
                                Создать ссылку
                            </button>
                        </form>
                    </div>
                @endif

                <div class="rounded-2xl border border-gray-100 bg-white p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Проверка готовности</h2>
                    <div class="mt-4 space-y-2 text-sm text-gray-700">
                        <div>Подтверждённых игроков: <span class="font-semibold">{{ $confirmedCount }}</span></div>
                        <div>Ожидают решения: <span class="font-semibold">{{ $pendingCount }}</span></div>

                        @if($settings)
                            <div>Схема игры: <span class="font-semibold">{{ $settings->game_scheme ?? '—' }}</span></div>
                            <div>Минимум игроков: <span class="font-semibold">{{ $settings->team_size_min ?? '—' }}</span></div>
                            <div>Макс. запасных: <span class="font-semibold">{{ $settings->reserve_players_max ?? '—' }}</span></div>
                            <div>Макс. всего: <span class="font-semibold">{{ $settings->total_players_max ?? $settings->team_size_max ?? '—' }}</span></div>
                            <div>Либеро обязателен: <span class="font-semibold">{{ $settings->require_libero ? 'Да' : 'Нет' }}</span></div>
                            <div>Лимит суммы рейтингов: <span class="font-semibold">{{ $settings->max_rating_sum ?? '—' }}</span></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const removeForms = document.querySelectorAll('[data-confirm-remove-member]');
            const submitForms = document.querySelectorAll('[data-confirm-submit-team]');
            const searchInput = document.getElementById('team-invite-user-search');
            const userIdInput = document.getElementById('team-invite-user-id');
            const resultsBox = document.getElementById('team-invite-search-results');
            const selectedUserBox = document.getElementById('team-invite-selected-user');
            const teamRoleSelect = document.getElementById('team_role');
            const positionWrap = document.getElementById('position_code_wrap');
            const positionSelect = document.getElementById('position_code');

            let searchTimer = null;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            removeForms.forEach((form) => {
                form.addEventListener('submit', function (e) {
                    if (!window.confirm('Удалить игрока из команды?')) {
                        e.preventDefault();
                    }
                });
            });

            submitForms.forEach((form) => {
                form.addEventListener('submit', function (e) {
                    if (!window.confirm('Подать заявку команды на турнир?')) {
                        e.preventDefault();
                    }
                });
            });

            function syncPositionVisibility() {
                if (!teamRoleSelect || !positionWrap || !positionSelect) return;

                const role = teamRoleSelect.value;
                const show = role === 'player';

                positionWrap.style.display = show ? '' : 'none';

                if (!show) {
                    positionSelect.value = '';
                }
            }

            if (teamRoleSelect) {
                teamRoleSelect.addEventListener('change', syncPositionVisibility);
                syncPositionVisibility();
            }

            if (!searchInput || !userIdInput || !resultsBox || !selectedUserBox) {
                return;
            }

            const searchUrl = searchInput.getAttribute('data-search-url') || '';

            searchInput.addEventListener('input', function () {
                const q = searchInput.value.trim();

                userIdInput.value = '';
                selectedUserBox.textContent = 'Игрок не выбран';

                clearTimeout(searchTimer);

                if (q.length < 2) {
                    resultsBox.innerHTML = '';
                    resultsBox.classList.add('hidden');
                    return;
                }

                searchTimer = setTimeout(async () => {
                    try {
                        const url = new URL(searchUrl, window.location.origin);
                        url.searchParams.set('q', q);

                        const res = await fetch(url.toString(), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            }
                        });

                        const payload = await res.json();
                        const users = Array.isArray(payload) ? payload : (payload.items || []);

                        if (!Array.isArray(users) || users.length === 0) {
                            resultsBox.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">Ничего не найдено</div>';
                            resultsBox.classList.remove('hidden');
                            return;
                        }

                        resultsBox.innerHTML = users.map((u) => {
                            const primary =
                                u.full_name ||
                                u.name ||
                                u.fio ||
                                u.telegram_username ||
                                u.username ||
                                ('ID ' + u.id);

                          const secondary =
                                u.meta ||
                                u.telegram_username ||
                                u.username ||
                                  '';

                            const label = secondary ? `${primary} — ${secondary}` : primary;

                            return `
                                <button
                                    type="button"
                                    class="block w-full border-0 border-b border-gray-100 px-3 py-2 text-left text-sm hover:bg-gray-50 last:border-b-0"
                                    data-user-id="${escapeHtml(u.id)}"
                                    data-user-label="${escapeHtml(label)}"
                                >
                                    <div class="font-medium text-gray-900">${escapeHtml(primary)}</div>
                                    ${secondary ? `<div class="text-xs text-gray-500">${escapeHtml(secondary)}</div>` : ''}
                                </button>
                            `;
                        }).join('');

                        resultsBox.classList.remove('hidden');

                        resultsBox.querySelectorAll('[data-user-id]').forEach((btn) => {
                            btn.addEventListener('click', function () {
                                const userId = btn.getAttribute('data-user-id') || '';
                                const label = btn.getAttribute('data-user-label') || '';

                                userIdInput.value = userId;
                                searchInput.value = label;
                                selectedUserBox.textContent = 'Выбран игрок: ' + label;

                                resultsBox.innerHTML = '';
                                resultsBox.classList.add('hidden');
                            });
                        });
                    } catch (e) {
                        console.error(e);
                        resultsBox.innerHTML = '<div class="px-3 py-2 text-sm text-red-600">Ошибка поиска</div>';
                        resultsBox.classList.remove('hidden');
                    }
                }, 250);
            });

            document.addEventListener('click', function (e) {
                if (!resultsBox.contains(e.target) && e.target !== searchInput) {
                    resultsBox.classList.add('hidden');
                }
            });
        })();
    </script>
</x-app-layout>