{{-- COMPONENT: event registration --}}
<div class="ramka">
    <h2 class="-mt-05">Запись на мероприятие</h2>

    @php
        $maxPlayers = $occurrence->effectiveMaxPlayers();
    @endphp

    {{-- ===============================
    СТАТИСТИКА ИГРОКОВ
    =============================== --}}
    @if(!is_null($registeredTotal))
        <div class="text-muted small mb-1">
            Участников:
            <span id="players-count">{{ $registeredTotal }}</span>
            @if($maxPlayers)
                / {{ $maxPlayers }}
            @endif
        </div>
    @endif

    @php
        $percent = ($maxPlayers && $registeredTotal !== null)
            ? min(100, ($registeredTotal / $maxPlayers) * 100)
            : 0;

        $barClass = 'bg-danger';
        if ($percent >= 75) {
            $barClass = 'bg-success';
        } elseif ($percent >= 40) {
            $barClass = 'bg-warning';
        }
    @endphp



    @if($maxPlayers)
        <div class="progress mb-2">
            <div
                id="players-progress"
                class="progress-bar {{ $barClass }}"
                role="progressbar"
                aria-valuenow="{{ $percent }}"
                aria-valuemin="0"
                aria-valuemax="100"
                style="width: {{ $percent }}%">
            </div>
        </div>
    @endif

    {{-- ===============================
    СТАТУС СОБЫТИЯ
    =============================== --}}
    @if($occurrence->isFinished())
        <div class="alert alert-info">
            🏁 Мероприятие завершено!
        </div>

    @elseif($occurrence->isRunning())
        <div class="alert alert-warning">
            ⚠️ Мероприятие уже идет!
        </div>

    @else

        {{-- ===============================
        УЖЕ ЗАПИСАН
        =============================== --}}
        @if ($isRegistered)
            <div class="alert alert-success">
                Вы уже записаны
                @if($userPosition)
                   <div><span class="f-16">позиция:</span> {{ $userPosition }}</div>
                @endif
            </div>

            @if ($cancel->allowed)
                <form method="POST" action="{{ $leaveAction }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="mt-2 btn btn-danger w-100">
                        Отменить запись
                    </button>
                </form>
            @else
                <div class="alert alert-warning mt-2">
                    {{ $cancel->message ?? $cancel->errors[0] ?? 'Отмена записи недоступна.' }}
                </div>
            @endif

        {{-- ===============================
        GUARD ЗАПРЕЩАЕТ
        =============================== --}}
        @elseif (! $join->allowed)
		 {{-- 
            <button class="btn btn-primary w-100" disabled>
                Записаться
            </button>
--}}
            @if (!empty($join->errors))
                <div class="alert alert-info mt-2">
                    {{ $join->errors[0] }}
                </div>
            @endif

        {{-- ===============================
        МОЖНО ЗАПИСАТЬСЯ
        =============================== --}}
        @else
            @if (empty($freePositions))
                <div class="alert alert-warning">
                    Свободных мест нет.
                </div>
            @else
                @foreach ($freePositions as $pos)
                    @php
                        $key = (string)($pos['key'] ?? '');
                        $free = (int)($pos['free'] ?? 0);
                    @endphp

                    <form method="POST" action="{{ $joinAction }}">
                        @csrf
                        <input type="hidden" name="position" value="{{ $key }}">
                        <button
                            type="submit"
                            class="d-flex between btn btn-primary w-100 mb-1"
                            {{ $free <= 0 ? 'disabled' : '' }}>
                            {{ position_name($key) }}
							<span>
                            <span class="pl-1 pr-1 f-11">Свободно:</span> {{ $free }} 
							</span>
                        </button>
                    </form>
                @endforeach

                <div class="text-muted small">
                    Выбери позицию<br>(показаны только свободные).
                </div>
            @endif
        @endif
    @endif
</div>	
    {{-- ===============================
    ГРУППА НА ПЛЯЖКУ
    =============================== --}}
    @if((!empty($groupUi['enabled'])) && ($isRegistered))
		
        <div class="ramka">
		<h2 class="-mt-05">Группа на пляжку</h2>


            {{-- Пользователь еще не записан --}}
            @if(empty($groupUi['registration']))
                @if(!empty($groupUi['pending_invites']) && $groupUi['pending_invites']->count())
                    <div class="alert alert-info mt-2">
                        У вас есть приглашения в группу для этого мероприятия.
                    </div>

                    @foreach($groupUi['pending_invites'] as $invite)
                        <div class="border rounded p-3 mt-2">
                            <div class="text-sm">
                                Приглашение от
                                <strong>{{ $invite->from_user_name ?: $invite->from_user_email ?: ('#'.$invite->from_user_id) }}</strong>
                            </div>

                            <div class="mt-2">
                                <div class="alert alert-secondary mb-2">
                                    Чтобы принять приглашение, сначала зарегистрируйтесь в системе, затем запишитесь на мероприятие.
                                    После этого вы сможете вернуться и принять приглашение в группу.
                                </div>
                            
                                <form method="POST" action="{{ route('events.group.decline', ['event' => $event->id, 'invite' => $invite->id]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary">
                                        Отклонить
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endif

            {{-- Пользователь записан --}}
            @else

                {{-- Пока нет группы --}}
                @if(empty($groupUi['group_key']))
                    <form method="POST" action="{{ route('events.group.create', ['event' => $event->id]) }}" class="mt-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary">
                            Объединиться
                        </button>
                    </form>
                @endif

                {{-- Уже есть группа --}}
                @if(!empty($groupUi['group_key']))
                    <div class="mt-3">
                        <div class="fw-bold">Состав группы</div>

                        @if(!empty($groupUi['group_members']) && $groupUi['group_members']->count())
                            <ul class="mt-2 mb-2">
                                @foreach($groupUi['group_members'] as $member)
                                    <li>
                                        {{ $member->name ?: $member->email ?: ('#'.$member->user_id) }}
                                        @if((int)$member->user_id === (int)auth()->id())
                                            <strong>(Вы)</strong>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-muted small mt-2">
                                Пока в группе только вы.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('events.group.leave', ['event' => $event->id]) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">
                                Выйти из группы
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Приглашение других игроков --}}
                @if(!empty($groupUi['invite_candidates']) && $groupUi['invite_candidates']->count())
                    <div class="mt-4">
                        <label class="form-label">Пригласить игрока в группу</label>

                        <form method="POST" action="{{ route('events.group.invite', ['event' => $event->id]) }}">
                            @csrf

                            <select name="to_user_id" class="form-select" required>
                                <option value="">— выбрать игрока —</option>
                                @foreach($groupUi['invite_candidates'] as $candidate)
                                    <option value="{{ $candidate->id }}">
                                        {{ $candidate->name ?: $candidate->email ?: ('#'.$candidate->id) }}
                                    </option>
                                @endforeach
                            </select>

                            <button type="submit" class="btn btn-outline-primary mt-2">
                                Пригласить
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Входящие приглашения --}}
                @if(!empty($groupUi['pending_invites']) && $groupUi['pending_invites']->count())
                    <div class="mt-4">
                        <div class="fw-bold">Входящие приглашения</div>

                        @foreach($groupUi['pending_invites'] as $invite)
                            <div class="border rounded p-3 mt-2">
                                <div class="text-sm">
                                    Приглашение от
                                    <strong>{{ $invite->from_user_name ?: $invite->from_user_email ?: ('#'.$invite->from_user_id) }}</strong>
                                </div>

                                <div class="mt-2 d-flex gap-2 flex-wrap">
                                    <form method="POST" action="{{ route('events.group.accept', ['event' => $event->id, 'invite' => $invite->id]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary">
                                            Принять
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('events.group.decline', ['event' => $event->id, 'invite' => $invite->id]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary">
                                            Отклонить
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- ===============================
    СПИСОК ИГРОКОВ
    =============================== --}}
 @if($showParticipants)
<div class="ramka">
    <h2 class="-mt-05">Записанные игроки</h2>	
	
    <div id="players-list"></div>
</div>
 @endif