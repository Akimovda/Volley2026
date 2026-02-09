{{-- resources/views/events/index.blade.php --}}
@php
    $fmtDate = function ($occ) {
        $tz = $occ->timezone ?: ($occ->event?->timezone ?: 'UTC');
        $s = $occ->starts_at ? \Illuminate\Support\Carbon::parse($occ->starts_at)->setTimezone($tz) : null;
        $e = $occ->ends_at ? \Illuminate\Support\Carbon::parse($occ->ends_at)->setTimezone($tz) : null;
        if (!$s) return ['date' => '—', 'time' => '—', 'tz' => $tz];
        $date = $s->format('d.m.Y');
        $time = $s->format('H:i') . ($e ? '–' . $e->format('H:i') : '');
        return ['date' => $date, 'time' => $time, 'tz' => $tz];
    };

    $trainersById = $trainersById ?? [];
    $trainerColumn = $trainerColumn ?? null;

    $trainerIconUrl = asset('icons/trainer.png'); // ✅ положи файл в public/icons/trainer.png
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Мероприятия
        </h2>
    </x-slot>

    @if (session('status'))
        <div class="v-container mt-6">
            <div class="v-alert v-alert--success">
                <div class="v-alert__text">{{ session('status') }}</div>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="v-container mt-6">
            <div class="v-alert v-alert--warn">
                <div class="v-alert__text">{{ session('error') }}</div>
            </div>
        </div>
    @endif

    <div class="py-10">
        <div class="v-container">
            @if (($occurrences ?? collect())->isEmpty())
                <div class="v-alert v-alert--info">
                    <div class="v-alert__text">Пока мероприятий нет. Но скоро появятся 🙂</div>
                </div>
            @else
                <div class="grid gap-4">
                    @foreach ($occurrences as $occ)
                        @php
                            $event = $occ->event;

                            $joinedOccurrenceIds = $joinedOccurrenceIds ?? [];
                            $restrictedOccurrenceIds = $restrictedOccurrenceIds ?? [];

                            $isJoined = in_array((int) $occ->id, $joinedOccurrenceIds, true);
                            $joinDisabled = in_array((int) $occ->id, $restrictedOccurrenceIds, true);

                            $dt = $fmtDate($occ);

                            $addressParts = array_filter([
                                $event?->location?->name,
                                $event?->location?->city,
                                $event?->location?->address,
                            ]);
                            $address = $addressParts ? implode(', ', $addressParts) : '—';

                            $coverUrl = $event ? $event->getFirstMediaUrl('cover') : '';

                            $gs = $event?->gameSettings ?? null;
                            $positions = $gs?->positions;
                            if (is_string($positions)) $positions = json_decode($positions, true);

                            $hasPositionRegistration = $gs && (int)($gs->max_players ?? 0) > 0 && is_array($positions) && !empty($positions);
                            $registrationEnabled = (int)(bool)($event?->allow_registration);

                            // ✅ trainer
                            $trainerLabel = null;
                            if ($trainerColumn && $event) {
                                $tid = (int)($event->{$trainerColumn} ?? 0);
                                if ($tid > 0 && isset($trainersById[$tid])) {
                                    $tu = $trainersById[$tid];
                                    $trainerLabel = trim(($tu->name ?? '') ?: ($tu->email ?? '')) . ' (#' . (int)$tid . ')';
                                }
                            }
                            $isTrainingFmt = in_array((string)($event?->format ?? ''), ['training','training_game'], true);
                        @endphp

                        <div class="v-card">
                            @if(!empty($coverUrl))
                                <div class="mb-3">
                                    <img src="{{ $coverUrl }}" alt="" class="w-full h-44 object-cover rounded-xl">
                                </div>
                            @endif

                            <div class="v-card__title flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900">
                                        {{ $event?->title ?? '—' }}
                                    </div>

                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $dt['date'] }} · {{ $dt['time'] }} ({{ $dt['tz'] }})
                                    </div>

                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $address }}
                                    </div>

                                    {{-- ✅ trainer line --}}
                                    @if($isTrainingFmt && !empty($trainerLabel))
                                        <div class="mt-2 text-sm text-gray-700 flex items-center gap-2">
                                            <img src="{{ $trainerIconUrl }}" alt="trainer" style="width:18px;height:18px;opacity:.85;">
                                            <span class="opacity-80">Тренер:</span>
                                            <span class="font-semibold">{{ $trainerLabel }}</span>
                                        </div>
                                    @endif

                                    <div
                                        class="mt-2 text-sm text-gray-700"
                                        data-seatline
                                        data-occurrence-id="{{ (int)$occ->id }}"
                                        data-registration-enabled="{{ $registrationEnabled }}"
                                        style="display:flex;align-items:center;gap:.4rem;"
                                    >
                                        <span class="opacity-80">🧑‍🧑‍🧒</span>
                                        <span class="opacity-80">Осталось мест:</span>
                                        <span class="font-semibold">—</span>
                                        <span class="opacity-80">из</span>
                                        <span class="font-semibold">—</span>
                                        <span class="opacity-80">!</span>
                                    </div>
                                </div>

                                <a class="v-btn v-btn--secondary" href="{{ url('/events/' . (int)$event->id) . '?occurrence=' . (int)$occ->id }}">
                                    Подробнее
                                </a>
                            </div>

                            <div class="v-card__meta">
                                @if ($event?->requires_personal_data)
                                    <span class="v-badge v-badge--warn">Нужны ваши персональные данные</span>
                                @endif
                                @if (!is_null($event?->classic_level_min))
                                    <span class="v-badge v-badge--info">Классика от {{ $event->classic_level_min }}</span>
                                @endif
                                @if (!is_null($event?->beach_level_min))
                                    <span class="v-badge v-badge--info">Пляж от {{ $event->beach_level_min }}</span>
                                @endif
                                @auth
                                    @if ($isJoined)
                                        <span class="v-badge v-badge--success">Уже записан</span>
                                    @endif
                                @endauth
                            </div>

                            <div class="v-actions">
                                @auth
                                    @if ($isJoined)
                                        <form method="POST" action="{{ route('occurrences.leave', ['occurrence' => $occ->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="v-btn v-btn--secondary">
                                                Отменить запись
                                            </button>
                                        </form>
                                    @else
                                        @if ($joinDisabled)
                                            <button type="button" class="v-btn v-btn--primary" disabled style="opacity:.5;cursor:not-allowed;">
                                                Записаться
                                            </button>
                                            <div class="text-xs mt-2" style="color:#b91c1c;">
                                                У вашей учетной записи есть ограничения для этого мероприятия.
                                            </div>
                                        @else
                                            @if (!$registrationEnabled)
                                                <button type="button" class="v-btn v-btn--primary" disabled style="opacity:.5;cursor:not-allowed;">
                                                    Регистрация выключена
                                                </button>
                                            @elseif (!$hasPositionRegistration)
                                                <form method="POST" action="{{ route('occurrences.join', ['occurrence' => $occ->id]) }}">
                                                    @csrf
                                                    <button type="submit" class="v-btn v-btn--primary">
                                                        Записаться
                                                    </button>
                                                </form>
                                            @else
                                                <button
                                                    type="button"
                                                    class="v-btn v-btn--primary js-open-join"
                                                    data-occurrence-id="{{ (int)$occ->id }}"
                                                    data-title="{{ e($event?->title ?? '') }}"
                                                    data-date="{{ e($dt['date']) }}"
                                                    data-time="{{ e($dt['time']) }}"
                                                    data-tz="{{ e($dt['tz']) }}"
                                                    data-address="{{ e($address) }}"
                                                >
                                                    Записаться
                                                </button>
                                            @endif
                                        @endif
                                    @endif
                                @else
                                    <a class="v-btn v-btn--primary" href="/login">Войти, чтобы записаться</a>
                                @endauth

                                <a class="v-btn v-btn--secondary" href="/user/profile">Профиль</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- JOIN MODAL (без изменений, как было у тебя) --}}
    <div id="joinModalBackdrop" class="fixed inset-0 z-50 hidden" style="background:rgba(0,0,0,.55);">
        <div class="min-h-full flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-4">
                    <div>
                        <div id="jmTitle" class="font-semibold text-gray-900 text-lg">Запись</div>
                        <div id="jmMeta" class="text-xs text-gray-500 mt-1"></div>
                        <div id="jmAddr" class="text-xs text-gray-500 mt-1"></div>
                    </div>
                    <button type="button" class="v-btn v-btn--secondary js-close-join">✕</button>
                </div>
                <div class="p-5">
                    <div id="jmError" class="hidden mb-3 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100 text-sm"></div>
                    <div id="jmHint" class="text-sm text-gray-600 mb-3">
                        Выбери позицию (показаны только свободные):
                    </div>
                    <div id="jmLoading" class="hidden text-sm text-gray-500 mb-3">
                        Загружаю доступные позиции…
                    </div>
                    <div id="jmPositions" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
                    <div class="mt-4 text-xs text-gray-500">
                        После выбора позиции вы сразу будете записаны.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="joinForm" method="POST" action="" class="hidden">
        @csrf
        <input type="hidden" name="position" id="joinPosition" value="">
    </form>

    <script>
        (function () {
            const backdrop = document.getElementById('joinModalBackdrop');
            const titleEl  = document.getElementById('jmTitle');
            const metaEl   = document.getElementById('jmMeta');
            const addrEl   = document.getElementById('jmAddr');
            const posWrap  = document.getElementById('jmPositions');
            const errBox   = document.getElementById('jmError');
            const loadingEl = document.getElementById('jmLoading');
            const joinForm = document.getElementById('joinForm');
            const joinPos  = document.getElementById('joinPosition');

            function showError(message) {
                if (!errBox) { alert(message); return; }
                errBox.textContent = message;
                errBox.classList.remove('hidden');
            }
            function clearError() {
                if (!errBox) return;
                errBox.textContent = '';
                errBox.classList.add('hidden');
            }
            function setLoading(isLoading) {
                if (!loadingEl) return;
                loadingEl.classList.toggle('hidden', !isLoading);
            }
            function openModalShell(payload) {
                clearError();
                setLoading(true);
                titleEl.textContent = payload.title || 'Запись';
                metaEl.textContent  = [payload.date, payload.time, payload.tz ? '('+payload.tz+')' : ''].filter(Boolean).join(' ');
                addrEl.textContent  = payload.address || '';
                posWrap.innerHTML = '';
                backdrop.classList.remove('hidden');
            }
            function closeModal() {
                backdrop.classList.add('hidden');
                posWrap.innerHTML = '';
                clearError();
                setLoading(false);
            }
            function renderPositions(occurrenceId, freePositions) {
                posWrap.innerHTML = '';
                if (!Array.isArray(freePositions) || freePositions.length === 0) {
                    showError('Свободных мест больше нет (или нет доступных позиций по ограничениям).');
                    return;
                }
                freePositions.forEach(p => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'v-btn v-btn--primary w-full';
                    btn.innerHTML = `${p.label || p.key} <span class="ml-2 text-xs opacity-80">(${p.free ?? 0})</span>`;
                    btn.addEventListener('click', () => {
                        joinForm.action = `/occurrences/${occurrenceId}/join`;
                        joinPos.value = p.key;
                        joinForm.submit();
                    });
                    posWrap.appendChild(btn);
                });
            }
            async function fetchAvailability(occurrenceId) {
                const res = await fetch(`/occurrences/${occurrenceId}/availability`, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                let data = null;
                try { data = await res.json(); } catch (e) {}

                if (data && data.redirect_url) {
                    window.location = data.redirect_url;
                    return null;
                }
                if (!res.ok || !data || data.ok === false) {
                    const msg = (data && data.message) ? data.message : 'Не удалось получить доступность мероприятия.';
                    showError(msg);
                    return null;
                }
                return data;
            }

            document.querySelectorAll('.js-open-join').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const occurrenceId = btn.dataset.occurrenceId;
                    openModalShell({
                        title: btn.dataset.title,
                        date: btn.dataset.date,
                        time: btn.dataset.time,
                        tz: btn.dataset.tz,
                        address: btn.dataset.address,
                    });
                    const data = await fetchAvailability(occurrenceId);
                    setLoading(false);
                    if (!data) return;
                    renderPositions(occurrenceId, data.free_positions || []);
                });
            });

            document.querySelectorAll('.js-close-join').forEach(btn => {
                btn.addEventListener('click', closeModal);
            });
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) closeModal();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeModal();
            });

            // ===== Seats line =====
            const seatLines = Array.from(document.querySelectorAll('[data-seatline]'));
            if (!seatLines.length) return;

            async function loadSeatLine(el) {
                const occId = el.dataset.occurrenceId;
                const regEnabled = el.dataset.registrationEnabled === '1';
                if (!regEnabled) return;

                const spans = el.querySelectorAll('span');
                const leftEl  = spans[2] || null;
                const totalEl = spans[4] || null;

                try {
                    const res = await fetch(`/occurrences/${occId}/availability`, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    let data = null;
                    try { data = await res.json(); } catch (e) {}

                    if (!res.ok || !data || data.ok === false) return;
                    if (!data.meta) return;

                    const maxPlayers = Number(data.meta.max_players ?? 0) || 0;

                    let remainingTotal = Number(data.meta.remaining_total);
                    if (!Number.isFinite(remainingTotal)) {
                        const registeredTotal = Number(data.meta.registered_total ?? 0) || 0;
                        remainingTotal = Math.max(0, maxPlayers - registeredTotal);
                    }

                    if (leftEl)  leftEl.textContent  = String(remainingTotal);
                    if (totalEl) totalEl.textContent = String(maxPlayers);
                } catch (e) {}
            }

            const concurrency = 3;
            let i = 0;
            async function worker() {
                while (i < seatLines.length) {
                    const idx = i++;
                    await loadSeatLine(seatLines[idx]);
                }
            }
            for (let k = 0; k < concurrency; k++) worker();
        })();
    </script>
</x-app-layout>
