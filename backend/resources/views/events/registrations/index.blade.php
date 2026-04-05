{{-- resources/views/events/registrations/index.blade.php --}}
@php
    // Позиции берём из контроллера (уже отфильтрованы по direction/subtype)
    // Для пляжки $availablePositions = []
    $posLabels = $availablePositions ?? [
        'setter'   => 'Связующий',
        'outside'  => 'Доигровщик',
        'opposite' => 'Диагональный',
        'middle'   => 'Центральный',
        'libero'   => 'Либеро',
    ];

    $isBeach   = ($direction ?? 'classic') === 'beach';
    $isClassic = !$isBeach;
    $hasPositions = $isClassic && count($posLabels) > 0;

    $eventTitle = (string)($event->title ?? 'Мероприятие');

    $addrParts = array_filter([
        $event->location?->city?->name,
        $event->location?->address,
        $event->location?->name,
    ]);
    $address = $addrParts ? implode(', ', $addrParts) : '—';

    $dateLine = '—';
    if ($startsLocal) {
        $dateLine = $startsLocal->translatedFormat('l, j F') . ' @ ' . $startsLocal->format('H:i');
        if ($endsLocal) {
            $dateLine .= ' - ' . $endsLocal->format('H:i');
        }
    }

    $capacityLine = ($maxPlayers > 0)
        ? "{$freeCount}/{$maxPlayers}"
        : "—/—";

    $statusText = function ($r) {
        if (property_exists($r, 'status') && $r->status) {
            return ((string)$r->status === 'cancelled') ? 'отменено' : 'подтверждено';
        }
        if (property_exists($r, 'is_cancelled') && !is_null($r->is_cancelled)) {
            return ((bool)$r->is_cancelled) ? 'отменено' : 'подтверждено';
        }
        if (property_exists($r, 'cancelled_at') && $r->cancelled_at) {
            return 'отменено';
        }
        return 'подтверждено';
    };

    $statusBadgeClass = function ($txt) {
        return $txt === 'отменено'
            ? 'bg-red-50 text-red-700 border-red-100'
            : 'bg-green-50 text-green-700 border-green-100';
    };

    $isCancelled = function ($r) use ($statusText) {
        return $statusText($r) === 'отменено';
    };

    $activeRegistrations = $registrations->filter(function ($r) use ($isCancelled) {
        return !$isCancelled($r);
    })->values();

    // URL для автокомплита
    $searchUrl = route('api.users.search');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500">Управление</div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $eventTitle }}
                    @if($isBeach)
                        <span class="ml-2 text-xs font-normal text-blue-500">🏖 Пляж</span>
                    @else
                        <span class="ml-2 text-xs font-normal text-gray-400">🏐 Классика · {{ $gameSubtype ?? '' }}</span>
                    @endif
                </h2>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('events.create.event_management', ['tab' => 'mine']) }}"
                   class="v-btn v-btn--secondary">← К управлению</a>
                <a href="{{ route('events.index') }}" class="v-btn v-btn--secondary">← К мероприятиям</a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="v-container space-y-6">

            {{-- Flash --}}
            @if (session('status'))
                <div class="p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">
                    <div class="font-semibold mb-2">Ошибки:</div>
                    <ul class="list-disc ml-5 space-y-1 text-sm">
                        @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- Summary --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Доступно мест</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">{{ $capacityLine }}</div>
                        <div class="mt-1 text-xs text-gray-500">Активных: {{ $activeCount }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Дата</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $dateLine }} ({{ $tz }})</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Место</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $address }}</div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════
                 ДОБАВИТЬ ИГРОКА — автокомплит
                 ═══════════════════════════════════════ --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="font-semibold text-gray-900">Добавить игрока</div>
                <div class="text-xs text-gray-500 mt-1">
                    Начните вводить имя или email. Введите <strong>bot</strong> или <strong>бот</strong> для поиска ботов-помощников.
                </div>

                <form method="POST"
                      action="{{ route('events.registrations.add', ['event' => $event->id]) }}"
                      id="add-player-form"
                      class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                    @csrf

                    {{-- Автокомплит --}}
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Игрок</label>
                        <div class="relative" id="ac-wrap">
                            <input
                                type="text"
                                id="ac-input"
                                autocomplete="off"
                                class="w-full rounded-lg border-gray-200"
                                placeholder="Имя, email или «bot»…"
                            >
                            <input type="hidden" name="user_id" id="ac-userid">
                            <div id="ac-dd"
                                 class="hidden absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                            </div>
                        </div>
                        <div id="ac-selected" class="hidden mt-1 text-xs text-green-700 font-semibold"></div>
                    </div>

                    {{-- Позиция (только классика) --}}
                    @if($hasPositions)
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Позиция</label>
                        <select name="position" class="w-full rounded-lg border-gray-200">
                            <option value="">— без позиции —</option>
                            @foreach ($posLabels as $k => $lbl)
                                <option value="{{ $k }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    @elseif($isBeach)
                    <div class="flex items-end pb-1">
                        <span class="text-xs text-gray-400">Для пляжа позиции не используются</span>
                    </div>
                    @endif

                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" id="add-player-btn" disabled
                                class="v-btn v-btn--primary disabled:opacity-40 disabled:cursor-not-allowed">
                            Добавить
                        </button>
                    </div>
                </form>
            </div>

            {{-- ═══════════════════════════════════════
                 ГРУППЫ — только для ПЛЯЖКИ
                 ═══════════════════════════════════════ --}}
            @if($isBeach)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="font-semibold text-gray-900">Объединить игроков в пару / группу</div>
                <div class="text-xs text-gray-500 mt-1">
                    Организатор может вручную создать группу и отправить приглашение второму игроку.
                </div>

                <form method="POST"
                      action="{{ route('events.registrations.group.invite', ['event' => $event->id]) }}"
                      class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                    @csrf

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Первый игрок</label>
                        <select name="from_user_id" class="w-full rounded-lg border-gray-200" required>
                            <option value="">— выбрать —</option>
                            @foreach ($activeRegistrations as $r)
                                <option value="{{ (int) $r->user_id }}">
                                    #{{ (int) $r->user_id }} — {{ $r->name ?? $r->email ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Второй игрок</label>
                        <select name="to_user_id" class="w-full rounded-lg border-gray-200" required>
                            <option value="">— выбрать —</option>
                            @foreach ($activeRegistrations as $r)
                                <option value="{{ (int) $r->user_id }}">
                                    #{{ (int) $r->user_id }} — {{ $r->name ?? $r->email ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="v-btn v-btn--primary">Отправить приглашение</button>
                    </div>
                </form>
            </div>

            {{-- Приглашения в группы --}}
            @if(isset($groupInvites) && $groupInvites->count())
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-gray-100">
                        <div class="font-semibold text-gray-900">Приглашения в группы</div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="text-left px-4 py-3 font-semibold">Группа</th>
                                    <th class="text-left px-4 py-3 font-semibold">От кого</th>
                                    <th class="text-left px-4 py-3 font-semibold">Кому</th>
                                    <th class="text-left px-4 py-3 font-semibold">Статус</th>
                                    <th class="text-left px-4 py-3 font-semibold">Дата</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($groupInvites as $i)
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $i->group_key ?: '—' }}</td>
                                        <td class="px-4 py-3">{{ $i->from_user_name ?: $i->from_user_email ?: ('User_' . $i->from_user_id) }}</td>
                                        <td class="px-4 py-3">{{ $i->to_user_name ?: $i->to_user_email ?: ('User_' . $i->to_user_id) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full border text-xs font-semibold
                                                @if($i->status === 'pending') bg-yellow-50 text-yellow-700 border-yellow-100
                                                @elseif($i->status === 'accepted') bg-green-50 text-green-700 border-green-100
                                                @elseif($i->status === 'declined') bg-red-50 text-red-700 border-red-100
                                                @else bg-gray-50 text-gray-700 border-gray-100 @endif">
                                                {{ $i->status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">
                                            {{ !empty($i->created_at) ? \Illuminate\Support\Carbon::parse($i->created_at)->format('d.m.Y H:i') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            @endif {{-- /isBeach --}}

            {{-- ═══════════════════════════════════════
                 ТАБЛИЦА ИГРОКОВ
                 ═══════════════════════════════════════ --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100">
                    <div class="font-semibold text-gray-900">Зарегистрированные игроки</div>
                    <div class="text-xs text-gray-500 mt-1">Здесь можно отменить, удалить, сменить позицию.</div>
                </div>

                @if ($registrations->isEmpty())
                    <div class="p-5 text-sm text-gray-600">Пока никто не записан.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="text-left px-4 py-3 font-semibold">Игрок</th>
                                    <th class="text-left px-4 py-3 font-semibold">Телефон</th>
                                    @if($hasPositions)
                                    <th class="text-left px-4 py-3 font-semibold">Позиция</th>
                                    @endif
                                    @if($isBeach)
                                    <th class="text-left px-4 py-3 font-semibold">Группа</th>
                                    @endif
                                    <th class="text-left px-4 py-3 font-semibold">Статус</th>
                                    <th class="text-right px-4 py-3 font-semibold">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($registrations as $r)
                                    @php
                                        $name     = $r->name ?: ($r->email ?: ('User_' . $r->user_id));
                                        $phone    = property_exists($r, 'phone') ? ($r->phone ?: '—') : '—';
                                        $posKey   = $r->position ?: '';
                                        $posLabel = $posKey ? ($posLabels[$posKey] ?? $posKey) : '—';
                                        $st       = $statusText($r);
                                        $groupKey = $r->group_key ?: '';
                                        $isBot    = !empty($r->is_bot);
                                    @endphp
                                    <tr class="{{ $st === 'отменено' ? 'opacity-70' : '' }}">

                                        {{-- Игрок --}}
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-1">
                                                <a class="text-blue-600 hover:text-blue-700 font-semibold"
                                                   href="{{ route('users.show', ['user' => $r->user_id]) }}">
                                                    {{ $name }}
                                                </a>
                                                @if($isBot)
                                                    <span title="Бот-помощник" class="text-gray-400 text-xs">🤖</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500">#{{ $r->user_id }} · reg #{{ $r->id }}</div>
                                        </td>

                                        {{-- Телефон --}}
                                        <td class="px-4 py-3">{{ $phone }}</td>

                                        {{-- Позиция (только классика) --}}
                                        @if($hasPositions)
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-900 text-xs mb-1">{{ $posLabel }}</div>
                                            <form class="flex gap-2 items-center"
                                                  method="POST"
                                                  action="{{ route('events.registrations.position', ['event' => $event->id, 'registration' => $r->id]) }}">
                                                @csrf @method('PATCH')
                                                <select name="position" class="rounded-lg border-gray-200 text-sm">
                                                    <option value="">— без позиции —</option>
                                                    @foreach ($posLabels as $k => $lbl)
                                                        <option value="{{ $k }}" @selected($posKey === $k)>{{ $lbl }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="v-btn v-btn--secondary" type="submit">✓</button>
                                            </form>
                                        </td>
                                        @endif

                                        {{-- Группа (только пляж) --}}
                                        @if($isBeach)
                                        <td class="px-4 py-3">
                                            @if ($groupKey !== '')
                                                <div class="font-semibold text-gray-900">{{ $groupKey }}</div>
                                                @if ($st !== 'отменено')
                                                    <form method="POST"
                                                          action="{{ route('events.registrations.group.leave', ['event' => $event->id, 'registration' => $r->id]) }}"
                                                          class="mt-1">
                                                        @csrf @method('PATCH')
                                                        <button type="submit" class="v-btn v-btn--secondary">Убрать</button>
                                                    </form>
                                                @endif
                                            @else
                                                @if ($st !== 'отменено')
                                                    <form method="POST"
                                                          action="{{ route('events.registrations.group.create', ['event' => $event->id, 'registration' => $r->id]) }}">
                                                        @csrf
                                                        <button type="submit" class="v-btn v-btn--secondary">Создать группу</button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-gray-400">—</span>
                                                @endif
                                            @endif
                                        </td>
                                        @endif

                                        {{-- Статус --}}
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full border text-xs font-semibold {{ $statusBadgeClass($st) }}">
                                                {{ $st }}
                                            </span>
                                        </td>

                                        {{-- Действия --}}
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <form method="POST"
                                                      action="{{ route('events.registrations.cancel', ['event' => $event->id, 'registration' => $r->id]) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="v-btn v-btn--secondary">
                                                        {{ $st === 'отменено' ? 'Восстановить' : 'Отклонить' }}
                                                    </button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('events.registrations.destroy', ['event' => $event->id, 'registration' => $r->id]) }}"
                                                      onsubmit="return confirm('Удалить регистрацию полностью?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="v-btn v-btn--secondary">Удалить</button>
                                                </form>
                                            </div>
                                            <div class="text-xs text-gray-400 text-right mt-1">reg #{{ $r->id }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>

    {{-- Автокомплит JS --}}
    <script>
    (function () {
        var input   = document.getElementById('ac-input');
        var dd      = document.getElementById('ac-dd');
        var hidden  = document.getElementById('ac-userid');
        var sel     = document.getElementById('ac-selected');
        var addBtn  = document.getElementById('add-player-btn');
        var timer   = null;
        var searchUrl = '{{ $searchUrl }}';

        if (!input) return;

        function clearSel() {
            hidden.value = '';
            addBtn.disabled = true;
            sel.classList.add('hidden');
        }

        function setSel(id, label) {
            hidden.value = id;
            addBtn.disabled = false;
            sel.classList.remove('hidden');
            sel.textContent = '✅ ' + label;
            dd.innerHTML = '';
            dd.classList.add('hidden');
            input.value = label.replace(/^🤖\s*/, '');
        }

        function esc(s) {
            return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function render(items) {
            dd.innerHTML = '';
            if (!items.length) {
                dd.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400">Ничего не найдено</div>';
                dd.classList.remove('hidden');
                return;
            }
            items.forEach(function(item) {
                var div = document.createElement('div');
                div.className = 'px-4 py-2.5 cursor-pointer hover:bg-gray-50 flex items-center justify-between gap-2 border-b border-gray-50 last:border-0';
                div.innerHTML =
                    '<span class="font-semibold text-gray-900 text-sm">' +
                    (item.is_bot ? '🤖 ' : '') + esc(item.label || item.name) +
                    '</span>' +
                    (item.meta ? '<span class="text-xs text-gray-400 shrink-0">' + esc(item.meta) + '</span>' : '');
                div.addEventListener('click', function() {
                    setSel(item.id, (item.is_bot ? '🤖 ' : '') + (item.label || item.name));
                });
                dd.appendChild(div);
            });
            dd.classList.remove('hidden');
        }

        function search(q) {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { render(data.items || []); })
            .catch(function() {
                dd.innerHTML = '<div class="px-4 py-3 text-sm text-red-500">Ошибка поиска</div>';
                dd.classList.remove('hidden');
            });
        }

        input.addEventListener('input', function() {
            clearSel();
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { dd.classList.add('hidden'); return; }
            dd.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400">Поиск…</div>';
            dd.classList.remove('hidden');
            timer = setTimeout(function() { search(q); }, 250);
        });

        document.addEventListener('click', function(e) {
            if (!document.getElementById('ac-wrap').contains(e.target)) {
                dd.classList.add('hidden');
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') dd.classList.add('hidden');
        });

        document.getElementById('add-player-form').addEventListener('submit', function(e) {
            if (!hidden.value) {
                e.preventDefault();
                input.focus();
                input.classList.add('ring-2', 'ring-red-400');
                setTimeout(function() { input.classList.remove('ring-2', 'ring-red-400'); }, 1500);
            }
        });
    })();
    </script>

</x-app-layout>