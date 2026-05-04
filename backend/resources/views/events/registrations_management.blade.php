{{-- resources/views/events/registrations_management.blade.php --}}
@php
    $tz = $event->timezone ?: 'UTC';

    $fmtWhen = function () use ($event, $tz) {
        if (!$event->starts_at) return '—';
        $s = \Illuminate\Support\Carbon::parse($event->starts_at)->setTimezone($tz);
        $e = $event->ends_at ? \Illuminate\Support\Carbon::parse($event->ends_at)->setTimezone($tz) : null;
        \Illuminate\Support\Carbon::setLocale('ru');
        $datePart = $s->translatedFormat('l, j F');
        $timePart = $s->format('H:i') . ($e ? ' - ' . $e->format('H:i') : '');
        return $datePart . ' @ ' . $timePart . ' (' . $tz . ')';
    };

    $fmtPlace = function () use ($location) {
        if (!$location) return '—';
        $parts = array_filter([$location->city?->name ?? null, $location->name ?? null, $location->address ?? null]);
        return $parts ? implode(', ', $parts) : '—';
    };

    $statusLabel = function ($r) use ($hasStatus, $hasIsCancelled, $hasCancelledAt) {
        $cancelled = false;
        if ($hasCancelledAt && !empty($r->cancelled_at)) $cancelled = true;
        if ($hasIsCancelled && (bool)$r->is_cancelled) $cancelled = true;
        if ($hasStatus && (string)($r->status ?? '') === 'cancelled') $cancelled = true;
        return $cancelled
            ? ['text' => 'отменено',     'cls' => 'bg-red-50 text-red-700 border-red-100']
            : ['text' => 'подтверждено', 'cls' => 'bg-green-50 text-green-700 border-green-100'];
    };

    $seatsText = function () use ($event, $maxPlayers, $activeRegs, $freeSeats) {
        if (!(bool)$event->allow_registration) return 'Регистрация выключена';
        if ($maxPlayers <= 0) return 'Доступно мест: —';
        return 'Доступно мест: ' . (int)$freeSeats . '/' . (int)$maxPlayers;
    };

    $hasUserPhone = \Illuminate\Support\Facades\Schema::hasColumn('users', 'phone')
        || \Illuminate\Support\Facades\Schema::hasColumn('users', 'phone_number')
        || \Illuminate\Support\Facades\Schema::hasColumn('users', 'telegram_phone');

    $phoneValue = function ($userId) {
        foreach (['phone', 'phone_number', 'telegram_phone'] as $c) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', $c)) {
                $v = \Illuminate\Support\Facades\DB::table('users')->where('id', (int)$userId)->value($c);
                if (!empty($v)) return (string)$v;
            }
        }
        return '';
    };

    $isBeach   = ($direction ?? 'classic') === 'beach';
    $isClassic = !$isBeach;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Управление: {{ $event->title }}
                    @if($isBeach)
                        <span class="ml-2 text-xs font-normal text-blue-500">🏖 Пляжка</span>
                    @else
                        <span class="ml-2 text-xs font-normal text-gray-400">🏐 Классика · {{ $gameSubtype }}</span>
                    @endif
                </h2>
                <div class="text-sm text-gray-500 mt-1">Страница регистрации и управления игроками.</div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('events.create.event_management', ['tab' => 'mine']) }}"
                   class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                    ← К управлению
                </a>
                <a href="{{ url('/events/' . (int)$event->id) }}"
                   class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                    Открыть мероприятие
                </a>
            </div>
        </div>
    </x-slot>

    {{-- FLASH --}}
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 mt-6">

        @if($event->format === 'tournament')
        <div class="mb-4 p-4 rounded-lg bg-blue-50 text-blue-800 border border-blue-100" id="tournament-redirect-notice">
            <div class="font-bold text-lg mb-1">🏆 Это турнирное мероприятие</div>
            <p class="mb-2">Управление командами и составами турнира осуществляется на странице настройки турнира.</p>
            <a href="{{ route('tournament.setup', $event) }}" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Перейти к управлению турниром →
            </a>
        </div>
        @endif

        @if (session('status'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100 text-sm">
                <div class="font-semibold mb-2">Ошибки:</div>
                <ul class="list-disc ml-5 space-y-1">
                    @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- SUMMARY --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div class="text-xs text-gray-500">Места</div>
                    <div class="mt-2 font-semibold text-gray-900">{{ $seatsText() }}</div>
                    <div class="mt-1 text-xs text-gray-500">Записано (активных): {{ (int)$activeRegs }}</div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div class="text-xs text-gray-500">Дата</div>
                    <div class="mt-2 font-semibold text-gray-900">{{ $fmtWhen() }}</div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div class="text-xs text-gray-500">Место</div>
                    <div class="mt-2 font-semibold text-gray-900">{{ $fmtPlace() }}</div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════
                 ДОБАВИТЬ ИГРОКА — автокомплит через UserSearchController
                 ═══════════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="font-semibold text-gray-900">Добавить игрока</div>
                    <div class="text-xs text-gray-500 mt-1">
                        Начните вводить имя или email — список появится автоматически.
                        Введите <strong>bot</strong> или <strong>бот</strong> для поиска ботов.
                    </div>
                </div>

                <div class="p-6">
                    <form method="POST"
                          action="{{ route('events.registrations.add', ['event' => $event->id]) }}"
                          id="add-player-form"
                          class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @csrf

                        {{-- Автокомплит поиска игрока --}}
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Поиск игрока</label>
                            <div class="relative" id="player-ac-wrap">
                                <input
                                    type="text"
                                    id="player-ac-input"
                                    autocomplete="off"
                                    class="w-full rounded-lg border-gray-200 pr-8"
                                    placeholder="Имя, email или «bot» для поиска ботов…"
                                >
                                {{-- Скрытое поле для POST --}}
                                <input type="hidden" name="user_id" id="player-ac-userid">

                                {{-- Dropdown --}}
                                <div id="player-ac-dd"
                                     class="hidden absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden max-h-64 overflow-y-auto">
                                </div>
                            </div>
                            {{-- Выбранный игрок --}}
                            <div id="player-ac-selected" class="hidden mt-2 text-sm text-green-700 font-semibold"></div>
                        </div>

                        {{-- Позиция (только классика и если есть позиции) --}}
                        @if($isClassic && $hasPosition && count($availablePositions) > 0)
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Позиция</label>
                            <select name="position" class="w-full rounded-lg border-gray-200">
                                <option value="">— выбрать —</option>
                                @foreach($availablePositions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @elseif($isBeach)
                        <div class="flex items-end">
                            <div class="text-xs text-gray-400">Для пляжного волейбола позиции не используются.</div>
                        </div>
                        @endif

                        <div class="md:col-span-3">
                            <button type="submit" id="add-player-btn" disabled
                                    class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm bg-gray-900 text-white hover:bg-black disabled:opacity-40 disabled:cursor-not-allowed">
                                + Добавить игрока
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════
                 СПИСОК ЗАРЕГИСТРИРОВАННЫХ
                 ═══════════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="font-semibold text-gray-900">Зарегистрированные игроки</div>
                    <div class="text-xs text-gray-500 mt-1">Отклонить — без удаления. Удалить — полное удаление записи.</div>
                </div>

                @if($registrations->isEmpty())
                    <div class="p-6 text-sm text-gray-600">Пока никто не записался.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="text-left px-4 py-3">Игрок</th>
                                    <th class="text-left px-4 py-3">Телефон</th>
                                    @if($isClassic && $hasPosition && count($availablePositions) > 0)
                                    <th class="text-left px-4 py-3">Позиция</th>
                                    @endif
                                    <th class="text-left px-4 py-3">Статус</th>
                                    <th class="text-right px-4 py-3">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($registrations as $r)
                                    @php
                                        $st          = $statusLabel($r);
                                        $displayName = $r->name ?: ($r->email ?: ('User_' . $r->user_id));
                                        $phone       = $hasUserPhone ? $phoneValue($r->user_id) : '';
                                    @endphp
                                    <tr class="hover:bg-gray-50/60">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-900">
                                                <a href="{{ route('users.show', ['user' => (int)$r->user_id]) }}" class="hover:underline">
                                                    {{ $displayName }}
                                                </a>
                                                @if(!empty($r->is_bot))
                                                    <span title="Бот-помощник" class="ml-1 text-xs text-gray-400">🤖</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                #{{ (int)$r->user_id }} · Reg #{{ (int)$r->id }}
                                            </div>
                                        </td>

                                        <td class="px-4 py-3 text-gray-700">
                                            @if($phone !== '')
                                                {{ $phone }}
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>

                                        {{-- Позиция — только классика с позициями --}}
                                        @if($isClassic && $hasPosition && count($availablePositions) > 0)
                                        <td class="px-4 py-3">
                                            <form method="POST"
                                                  action="{{ route('events.registrations.position', ['event' => $event->id, 'registration' => (int)$r->id]) }}"
                                                  class="flex gap-2 items-center">
                                                @csrf
                                                @method('PATCH')
                                                <select name="position" class="rounded-lg border-gray-200 text-sm">
                                                    <option value="">— позиция —</option>
                                                    @foreach($availablePositions as $key => $label)
                                                        <option value="{{ $key }}" @selected((string)($r->position ?? '') === $key)>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit"
                                                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 bg-white hover:bg-gray-50">
                                                    ✓
                                                </button>
                                            </form>
                                        </td>
                                        @endif

                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $st['cls'] }}">
                                                {{ $st['text'] }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <div class="inline-flex gap-2 justify-end">
                                                <form method="POST"
                                                      action="{{ route('events.registrations.cancel', ['event' => $event->id, 'registration' => (int)$r->id]) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-semibold border border-red-200 bg-red-50 text-red-700 hover:bg-red-100">
                                                        Отклонить
                                                    </button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('events.registrations.destroy', ['event' => $event->id, 'registration' => (int)$r->id]) }}"
                                                      onsubmit="return confirm('Точно удалить регистрацию?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-semibold border border-gray-200 bg-white hover:bg-gray-50">
                                                        Удалить
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ═══════════════════════════════════════════════════════════
                 ГРУППЫ / ПАРЫ — только для ПЛЯЖКИ
                 ═══════════════════════════════════════════════════════════ --}}
            @if($isBeach && $groupInvites->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="font-semibold text-gray-900">Приглашения в пары / группы</div>
                    <div class="text-xs text-gray-500 mt-1">Актуально только для пляжного волейбола.</div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="text-left px-4 py-3">От</th>
                                <th class="text-left px-4 py-3">Кому</th>
                                <th class="text-left px-4 py-3">Группа</th>
                                <th class="text-left px-4 py-3">Статус</th>
                                <th class="text-left px-4 py-3">Дата</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($groupInvites as $inv)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3">{{ $inv->from_user_name ?: $inv->from_user_email }}</td>
                                <td class="px-4 py-3">{{ $inv->to_user_name ?: $inv->to_user_email }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $inv->group_key }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $inv->status === 'accepted' ? 'bg-green-50 text-green-700' : ($inv->status === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-700') }}">
                                        {{ $inv->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    {{ \Carbon\Carbon::parse($inv->created_at)->format('d.m H:i') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         JS: Автокомплит поиска игрока
         ═══════════════════════════════════════════════════════════ --}}
    <script>
    (function () {
        var input    = document.getElementById('player-ac-input');
        var dd       = document.getElementById('player-ac-dd');
        var hidden   = document.getElementById('player-ac-userid');
        var selected = document.getElementById('player-ac-selected');
        var addBtn   = document.getElementById('add-player-btn');
        var timer    = null;

        if (!input) return;

        // URL роута поиска пользователей
        var searchUrl = '{{ route("api.users.search") }}';

        function clearSelection() {
            hidden.value = '';
            addBtn.disabled = true;
            selected.classList.add('hidden');
            selected.textContent = '';
        }

        function setSelection(id, label) {
            hidden.value = id;
            addBtn.disabled = false;
            selected.classList.remove('hidden');
            selected.textContent = '✅ Выбран: ' + label;
            dd.innerHTML = '';
            dd.classList.add('hidden');
            input.value = label;
        }

        function renderDropdown(items) {
            dd.innerHTML = '';
            if (!items.length) {
                dd.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500">Ничего не найдено</div>';
                dd.classList.remove('hidden');
                return;
            }
            items.forEach(function (item) {
                var div = document.createElement('div');
                div.className = 'px-4 py-2.5 cursor-pointer hover:bg-gray-50 flex items-center justify-between gap-2';
                div.innerHTML =
                    '<span class="font-semibold text-gray-900">' +
                    (item.is_bot ? '🤖 ' : '') +
                    escHtml(item.label || item.name || '') +
                    '</span>' +
                    (item.meta ? '<span class="text-xs text-gray-400 shrink-0">' + escHtml(item.meta) + '</span>' : '');
                div.addEventListener('click', function () {
                    setSelection(item.id, (item.is_bot ? '🤖 ' : '') + (item.label || item.name));
                });
                dd.appendChild(div);
            });
            dd.classList.remove('hidden');
        }

        function escHtml(s) {
            return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function doSearch(q) {
            if (!q || q.length < 2) { dd.classList.add('hidden'); return; }

            // Спецзапрос для ботов — идём через серверный поиск, он знает про "bot"/"бот"
            var url = searchUrl + '?q=' + encodeURIComponent(q);

            fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var items = Array.isArray(data) ? data : (data.items || []);
                    renderDropdown(items);
                })
                .catch(function () {
                    dd.innerHTML = '<div class="px-4 py-3 text-sm text-red-500">Ошибка поиска</div>';
                    dd.classList.remove('hidden');
                });
        }

        input.addEventListener('input', function () {
            clearSelection();
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { dd.classList.add('hidden'); return; }
            dd.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400">Поиск…</div>';
            dd.classList.remove('hidden');
            timer = setTimeout(function () { doSearch(q); }, 250);
        });

        // Закрыть при клике вне
        document.addEventListener('click', function (e) {
            if (!document.getElementById('player-ac-wrap').contains(e.target)) {
                dd.classList.add('hidden');
            }
        });

        // Escape
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') dd.classList.add('hidden');
        });

        // Валидация перед отправкой
        document.getElementById('add-player-form').addEventListener('submit', function (e) {
            if (!hidden.value) {
                e.preventDefault();
                input.focus();
                alert('Выберите игрока из списка.');
            }
        });
    })();
    </script>

</x-app-layout>