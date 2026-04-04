<x-app-layout>
    @php
        $team = $invite->team ?? $team ?? null;
        $event = $invite->event ?? $event ?? ($team?->event);
        $captain = $team?->captain;
        $settings = $event?->tournamentSetting;
        $members = $team?->members ?? collect();
        $confirmedMembers = $members->where('confirmation_status', 'confirmed');
        $pendingMembers = $members->whereIn('confirmation_status', ['invited', 'joined']);

        $positionLabels = [
            'setter' => 'Связующий',
            'outside' => 'Доигровщик',
            'opposite' => 'Диагональный',
            'middle' => 'Центральный блокирующий',
            'libero' => 'Либеро',
        ];

        $teamRoleLabels = [
            'captain' => 'Капитан',
            'player' => 'Основной игрок',
            'reserve' => 'Запасной',
        ];

        $inviteRole = $invite->team_role ?? null;
        $invitePosition = $invite->position_code ?? null;
        $eventTitle = $event->title ?? 'Турнир';

        $locationLine = collect([
            $event?->location?->city?->name,
            $event?->location?->name,
            $event?->location?->address,
        ])->filter()->implode(', ');

        $scheme = $settings?->game_scheme ?? $settings?->getGameScheme() ?? '—';

        $statusBadgeClass = match ((string) ($invite->status ?? 'pending')) {
            'accepted' => 'bg-emerald-100 text-emerald-700',
            'declined' => 'bg-rose-100 text-rose-700',
            'cancelled', 'revoked', 'expired' => 'bg-slate-200 text-slate-700',
            default => 'bg-amber-100 text-amber-700',
        };

        $statusLabel = match ((string) ($invite->status ?? 'pending')) {
            'accepted' => 'Принято',
            'declined' => 'Отклонено',
            'cancelled' => 'Отменено',
            'revoked' => 'Отозвано',
            'expired' => 'Истекло',
            default => 'Ожидает ответа',
        };

        $canRespond = in_array((string) ($invite->status ?? 'pending'), ['pending'], true);
    @endphp

    <div class="max-w-6xl mx-auto px-4 py-6">
        @if($event)
            <div class="mb-4">
                <a
                    href="{{ route('events.show', $event) }}"
                    class="inline-flex items-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-200"
                >
                    ← Назад к турниру
                </a>
            </div>
        @endif

        @if(session('success'))
            <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <div class="mb-2 font-semibold">Есть ошибки:</div>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">
                                    Приглашение в команду
                                </span>
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $statusBadgeClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>

                            <h1 class="text-2xl font-bold text-gray-900">
                                {{ $team?->name ?? 'Команда' }}
                            </h1>

                            <div class="mt-2 text-sm text-gray-600">
                                Турнир:
                                <span class="font-medium text-gray-900">{{ $eventTitle }}</span>
                            </div>

                            @if($locationLine)
                                <div class="mt-1 text-sm text-gray-600">
                                    Локация:
                                    <span class="font-medium text-gray-900">{{ $locationLine }}</span>
                                </div>
                            @endif

                            @if(!empty($event?->starts_at))
                                <div class="mt-1 text-sm text-gray-600">
                                    Дата:
                                    <span class="font-medium text-gray-900">
                                        {{ \Illuminate\Support\Carbon::parse($event->starts_at)->timezone($event->timezone ?? config('app.timezone'))->format('d.m.Y H:i') }}
                                    </span>
                                </div>
                            @endif

                            @if(!empty($event?->description))
                                <div class="mt-3 text-sm text-gray-600">
                                    <div class="font-medium text-gray-900">Описание:</div>
                                    <div class="mt-1 whitespace-pre-wrap">{{ $event->description }}</div>
                                </div>
                            @endif
                        </div>

                        <div class="w-full max-w-sm rounded-2xl border border-gray-200 bg-gray-50 p-4">
                            <div class="text-sm font-semibold text-gray-800">Вас приглашают на роль</div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if($inviteRole)
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                        {{ $teamRoleLabels[$inviteRole] ?? $inviteRole }}
                                    </span>
                                @endif

                                @if($invitePosition)
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-700">
                                        {{ $positionLabels[$invitePosition] ?? $invitePosition }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-3 text-xs text-gray-500">
                                Если вы примете приглашение, вас добавят в состав этой команды.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Информация о турнире</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2 text-sm text-gray-700">
                        <div class="rounded-xl bg-gray-50 px-4 py-3">
                            <div class="text-xs text-gray-500">Формат регистрации</div>
                            <div class="mt-1 font-semibold text-gray-900">
                                {{ match($settings?->registration_mode) {
                                    'team_classic' => 'Классическая команда',
                                    'team_beach' => 'Пляжная команда / пара',
                                    default => '—',
                                } }}
                            </div>
                        </div>

                        <div class="rounded-xl bg-gray-50 px-4 py-3">
                            <div class="text-xs text-gray-500">Схема игры</div>
                            <div class="mt-1 font-semibold text-gray-900">
                                {{ $scheme }}
                            </div>
                        </div>

                        <div class="rounded-xl bg-gray-50 px-4 py-3">
                            <div class="text-xs text-gray-500">Минимум игроков</div>
                            <div class="mt-1 font-semibold text-gray-900">
                                {{ $settings?->team_size_min ?? '—' }}
                            </div>
                        </div>

                        <div class="rounded-xl bg-gray-50 px-4 py-3">
                            <div class="text-xs text-gray-500">Максимум игроков</div>
                            <div class="mt-1 font-semibold text-gray-900">
                                {{ $settings?->total_players_max ?? $settings?->team_size_max ?? '—' }}
                            </div>
                        </div>

                        <div class="rounded-xl bg-gray-50 px-4 py-3">
                            <div class="text-xs text-gray-500">Макс. запасных</div>
                            <div class="mt-1 font-semibold text-gray-900">
                                {{ $settings?->reserve_players_max ?? '—' }}
                            </div>
                        </div>

                        <div class="rounded-xl bg-gray-50 px-4 py-3">
                            <div class="text-xs text-gray-500">Либеро обязателен</div>
                            <div class="mt-1 font-semibold text-gray-900">
                                {{ !empty($settings?->require_libero) ? 'Да' : 'Нет' }}
                            </div>
                        </div>

                        @if(!is_null($settings?->max_rating_sum))
                            <div class="rounded-xl bg-gray-50 px-4 py-3 md:col-span-2">
                                <div class="text-xs text-gray-500">Лимит суммы рейтинга</div>
                                <div class="mt-1 font-semibold text-gray-900">
                                    {{ $settings->max_rating_sum }}
                                </div>
                            </div>
                        @endif

                        @if(!empty($event?->location?->map_link))
                            <div class="rounded-xl bg-gray-50 px-4 py-3 md:col-span-2">
                                <div class="text-xs text-gray-500">Местоположение</div>
                                <div class="mt-1">
                                    <a 
                                        href="{{ $event->location->map_link }}" 
                                        target="_blank"
                                        class="text-blue-600 hover:text-blue-800 underline text-sm"
                                    >
                                        Открыть на карте
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Кто приглашает</h2>
                    <div class="mt-4 flex items-center gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-200 text-lg font-bold text-slate-700">
                            {{ mb_substr($captain->name ?? $captain->email ?? 'C', 0, 1) }}
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900">
                                {{ $captain->name ?? $captain->email ?? ('#' . ($team?->captain_user_id ?? '—')) }}
                            </div>
                            @if(!empty($captain?->email))
                                <div class="mt-1 text-sm text-gray-500">
                                    {{ $captain->email }}
                                </div>
                            @endif
                            <div class="mt-1 text-sm text-gray-600">
                                Капитан команды
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Текущий состав команды</h2>
                        <div class="text-sm text-gray-500">
                            В команде: {{ $members->count() }}
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($members as $member)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <div class="font-semibold text-gray-900">
                                            {{ $member->user->name ?? $member->user->email ?? ('#' . $member->user_id) }}
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-2">
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                                {{ $teamRoleLabels[$member->team_role] ?? $member->team_role }}
                                            </span>

                                            @if(!empty($member->position_code))
                                                <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-1 text-xs font-medium text-purple-700">
                                                    {{ $positionLabels[$member->position_code] ?? $member->position_code }}
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

                                    @if((int) $member->user_id === (int) ($team?->captain_user_id))
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                                            Капитан
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500">
                                Состав пока пуст.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-100 bg-white p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Ваше решение</h2>
                    <div class="mt-3 text-sm text-gray-600">
                        @if($canRespond)
                            Вы можете принять приглашение или отказаться.
                        @else
                            По этому приглашению уже принято решение.
                        @endif
                    </div>

                    @if($canRespond)
                        <div class="mt-4 space-y-3">
                            <form method="POST" action="{{ route('tournamentTeamInvites.accept', $invite->token) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700"
                                >
                                    Принять приглашение
                                </button>
                            </form>

                            <form method="POST" action="{{ route('tournamentTeamInvites.decline', $invite->token) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-rose-700"
                                >
                                    Отклонить приглашение
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            Статус приглашения: <span class="font-semibold text-gray-900">{{ $statusLabel }}</span>
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Сводка по составу</h2>
                    <div class="mt-4 space-y-2 text-sm text-gray-700">
                        <div>
                            Подтверждено:
                            <span class="font-semibold">{{ $confirmedMembers->count() }}</span>
                        </div>
                        <div>
                            Ожидают ответа:
                            <span class="font-semibold">{{ $pendingMembers->count() }}</span>
                        </div>
                        <div>
                            Всего в составе:
                            <span class="font-semibold">{{ $members->count() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>