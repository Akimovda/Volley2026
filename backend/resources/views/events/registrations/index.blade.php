@php
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
        if ($endsLocal) $dateLine .= ' - ' . $endsLocal->format('H:i');
    }

    $capacityLine = ($maxPlayers > 0) ? "{$freeCount}/{$maxPlayers}" : "—/—";

    $statusText = function ($r) {
        if (property_exists($r, 'cancelled_at') && $r->cancelled_at) return 'отменено';
        if (property_exists($r, 'is_cancelled') && !is_null($r->is_cancelled) && (bool)$r->is_cancelled) return 'отменено';
        if (property_exists($r, 'status') && (string)$r->status === 'cancelled') return 'отменено';
        return 'подтверждено';
    };

    $isCancelled = function ($r) use ($statusText) {
        return $statusText($r) === 'отменено';
    };

    $activeRegistrations = $registrations->filter(fn($r) => !$isCancelled($r))->values();
    $searchUrl = route('api.users.search');
@endphp

<x-voll-layout body_class="registrations-page">

    <x-slot name="title">Управление записью — {{ $eventTitle }}</x-slot>
    <x-slot name="h1">Управление записью</x-slot>
    <x-slot name="t_description">{{ $eventTitle }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.create.event_management') }}" itemprop="item"><span itemprop="name">Мои мероприятия</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.show', $event->id) }}" itemprop="item"><span itemprop="name">{{ $eventTitle }}</span></a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Запись</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    <x-slot name="d_description">
        <div class="d-flex between fvc mt-1" style="flex-wrap:wrap;gap:1rem;">
            <div class="f-15" style="opacity:.6;">
                @if($isBeach)
                    🏖 Пляжный волейбол
                @else
                    🏐 Классика @if(!empty($gameSubtype)) · {{ $gameSubtype }} @endif
                @endif
            </div>
            <div class="d-flex gap-1">
                <a href="{{ route('events.create.event_management') }}" class="btn btn-secondary">← К управлению</a>
                <a href="{{ route('events.show', $event->id) }}" class="btn btn-secondary">← К мероприятию</a>
            </div>
        </div>
    </x-slot>

    <x-slot name="style">
    <style>
        .reg-table { width: 100%; border-collapse: collapse; }
        .reg-table th { font-size: 1.3rem; opacity: .6; padding: .8rem 1rem; text-align: left; border-bottom: 0.1rem solid var(--border-color, #eee); }
        .reg-table td { font-size: 1.5rem; padding: 1rem; border-bottom: 0.1rem solid var(--border-color, #eee); vertical-align: middle; }
        .reg-table tr.cancelled td { opacity: .5; }
        .badge { display: inline-block; padding: .3rem 1rem; border-radius: 2rem; font-size: 1.3rem; font-weight: 600; }
        .badge-ok  { background: rgba(76,175,80,.12); color: #2e7d32; }
        .badge-err { background: rgba(244,67,54,.12);  color: #c62828; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; }
        @media (max-width: 768px) { .summary-grid { grid-template-columns: 1fr; } }
    </style>
    </x-slot>

    <div class="container">

        {{-- Flash --}}
        @if(session('status'))
        <div class="ramka" style="padding:1.5rem 2rem;background:rgba(76,175,80,.1);border-radius:1.2rem;margin-bottom:2rem;">
            ✅ {{ session('status') }}
        </div>
        @endif
        @if(session('error'))
        <div class="ramka" style="padding:1.5rem 2rem;background:rgba(244,67,54,.1);border-radius:1.2rem;margin-bottom:2rem;">
            ❌ {{ session('error') }}
        </div>
        @endif

        {{-- Сводка --}}
        <div class="ramka">
            <div class="summary-grid">
                <div class="card text-center">
                    <div class="f-13" style="opacity:.6">Свободных мест</div>
                    <div class="f-32 b-700 cd">{{ $capacityLine }}</div>
                    <div class="f-13" style="opacity:.5">Активных: {{ $activeCount }}</div>
                </div>
                <div class="card text-center">
                    <div class="f-13" style="opacity:.6">Дата</div>
                    <div class="f-16 b-600">{{ $dateLine }}</div>
                    <div class="f-13" style="opacity:.5">{{ $tz }}</div>
                </div>
                <div class="card text-center">
                    <div class="f-13" style="opacity:.6">Место</div>
                    <div class="f-15 b-600">{{ $address }}</div>
                </div>
            </div>
        </div>

        {{-- Добавить игрока --}}
        <div class="ramka">
            <h2 class="-mt-05">➕ Добавить игрока</h2>
            <div class="f-15 mb-2" style="opacity:.6;">
                Начните вводить имя или email. Введите <strong>bot</strong> для поиска ботов-помощников.
            </div>
            <form method="POST"
                  action="{{ route('events.registrations.add', ['event' => $event->id]) }}"
                  id="add-player-form"
                  class="form">
                @csrf
                <div class="row row2">
                    <div class="col-md-6">
                        <label>Игрок</label>
                        <div style="position:relative;" id="ac-wrap">
                            <input type="text" id="ac-input" autocomplete="off"
                                   placeholder="Имя, email или «bot»…">
                            <input type="hidden" name="user_id" id="ac-userid">
                            <div id="ac-dd" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:.4rem;z-index:50;background:var(--bg-card,#fff);border:0.1rem solid var(--border-color,#eee);border-radius:1.2rem;box-shadow:0 1rem 3rem rgba(0,0,0,.1);max-height:24rem;overflow-y:auto;"></div>
                        </div>
                        <div id="ac-selected" style="display:none;margin-top:.5rem;font-size:1.4rem;color:#4caf50;font-weight:600;"></div>
                    </div>
                    @if($hasPositions)
                    <div class="col-md-4">
                        <label>Позиция</label>
                        <select name="position">
                            <option value="">— без позиции —</option>
                            @foreach($posLabels as $k => $lbl)
                            <option value="{{ $k }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-md-2 d-flex" style="align-items:flex-end;">
                        <button type="submit" id="add-player-btn" disabled
                                class="btn btn-secondary w-100" style="opacity:.4;">
                            Добавить
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Таблица игроков --}}
        <div class="ramka">
            <h2 class="-mt-05">👥 Зарегистрированные игроки</h2>

            @if($registrations->isEmpty())
            <div class="card text-center" style="padding:3rem;opacity:.5;">
                Пока никто не записан
            </div>
            @else
            <div style="overflow-x:auto;">
                <table class="reg-table">
                    <thead>
                        <tr>
                            <th>Игрок</th>
                            <th>Телефон</th>
                            @if($hasPositions)<th>Позиция</th>@endif
                            @if($isBeach)<th>Группа</th>@endif
                            <th>Статус</th>
                            <th style="text-align:right;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($registrations as $r)
                        @php
                            $name     = $r->name ?: ($r->email ?: ('User_' . $r->user_id));
                            $phone    = property_exists($r, 'phone') ? ($r->phone ?: '—') : '—';
                            $posKey   = $r->position ?: '';
                            $posLabel = $posKey ? ($posLabels[$posKey] ?? $posKey) : '—';
                            $st       = $statusText($r);
                            $groupKey = $r->group_key ?: '';
                            $isBot    = !empty($r->is_bot);
                        @endphp
                        <tr class="{{ $st === 'отменено' ? 'cancelled' : '' }}">
                            <td>
                                <div class="d-flex fvc gap-1">
                                    <a href="{{ route('users.show', $r->user_id) }}" class="b-600 cd">
                                        {{ $name }}
                                    </a>
                                    @if($isBot)<span title="Бот" style="opacity:.4;">🤖</span>@endif
                                </div>
                                <div class="f-13" style="opacity:.4;">#{{ $r->user_id }} · reg #{{ $r->id }}</div>
                            </td>
                            <td>{{ $phone }}</td>
                            @if($hasPositions)
                            <td>
                                <div class="f-14 b-600 mb-05">{{ $posLabel }}</div>
                                <form class="d-flex gap-1 fvc" method="POST"
                                      action="{{ route('events.registrations.position', ['event' => $event->id, 'registration' => $r->id]) }}">
                                    @csrf @method('PATCH')
                                    <select name="position" style="font-size:1.3rem;">
                                        <option value="">— без —</option>
                                        @foreach($posLabels as $k => $lbl)
                                        <option value="{{ $k }}" @selected($posKey === $k)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-small btn-secondary" type="submit">✓</button>
                                </form>
                            </td>
                            @endif
                            @if($isBeach)
                            <td>
                                @if($groupKey)
                                <div class="b-600">{{ $groupKey }}</div>
                                @if($st !== 'отменено')
                                <form method="POST" action="{{ route('events.registrations.group.leave', ['event' => $event->id, 'registration' => $r->id]) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-small btn-secondary mt-05">Убрать</button>
                                </form>
                                @endif
                                @else
                                @if($st !== 'отменено')
                                <form method="POST" action="{{ route('events.registrations.group.create', ['event' => $event->id, 'registration' => $r->id]) }}">
                                    @csrf
                                    <button class="btn btn-small btn-secondary">+ Группа</button>
                                </form>
                                @endif
                                @endif
                            </td>
                            @endif
                            <td>
                                <span class="badge {{ $st === 'отменено' ? 'badge-err' : 'badge-ok' }}">
                                    {{ $st }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div class="d-flex gap-1" style="justify-content:flex-end;">
                                    <form method="POST"
                                          action="{{ route('events.registrations.cancel', ['event' => $event->id, 'registration' => $r->id]) }}">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-small btn-secondary">
                                            {{ $st === 'отменено' ? '✅ Восстановить' : '❌ Отклонить' }}
                                        </button>
                                    </form>
                                    <form method="POST"
                                          action="{{ route('events.registrations.destroy', ['event' => $event->id, 'registration' => $r->id]) }}"
                                          onsubmit="return confirm('Удалить регистрацию полностью?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-small btn-secondary">🗑 Удалить</button>
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

        {{-- Группы (только пляжка) --}}
        @if($isBeach)
        <div class="ramka">
            <h2 class="-mt-05">👫 Объединить в пару</h2>
            <form method="POST"
                  action="{{ route('events.registrations.group.invite', ['event' => $event->id]) }}"
                  class="form">
                @csrf
                <div class="row row2">
                    <div class="col-md-4">
                        <label>Первый игрок</label>
                        <select name="from_user_id" required>
                            <option value="">— выбрать —</option>
                            @foreach($activeRegistrations as $r)
                            <option value="{{ $r->user_id }}">#{{ $r->user_id }} — {{ $r->name ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Второй игрок</label>
                        <select name="to_user_id" required>
                            <option value="">— выбрать —</option>
                            @foreach($activeRegistrations as $r)
                            <option value="{{ $r->user_id }}">#{{ $r->user_id }} — {{ $r->name ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex" style="align-items:flex-end;">
                        <button class="btn btn-secondary w-100">Отправить приглашение</button>
                    </div>
                </div>
            </form>
        </div>
        @endif

    </div>

    <x-slot name="script">
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
            addBtn.style.opacity = '.4';
            sel.style.display = 'none';
        }

        function setSel(id, label) {
            hidden.value = id;
            addBtn.disabled = false;
            addBtn.style.opacity = '1';
            sel.style.display = 'block';
            sel.textContent = '✅ ' + label;
            dd.innerHTML = '';
            dd.style.display = 'none';
            input.value = label.replace(/^🤖\s*/, '');
        }

        function esc(s) {
            return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function render(items) {
            dd.innerHTML = '';
            if (!items.length) {
                dd.innerHTML = '<div style="padding:1.2rem 1.6rem;font-size:1.5rem;opacity:.5;">Ничего не найдено</div>';
                dd.style.display = 'block';
                return;
            }
            items.forEach(function(item) {
                var div = document.createElement('div');
                div.style.cssText = 'padding:1rem 1.6rem;cursor:pointer;font-size:1.5rem;border-bottom:0.1rem solid var(--border-color,#eee);display:flex;justify-content:space-between;align-items:center;';
                div.innerHTML =
                    '<span class="b-600">' + (item.is_bot ? '🤖 ' : '') + esc(item.label || item.name) + '</span>' +
                    (item.meta ? '<span style="font-size:1.3rem;opacity:.5;">' + esc(item.meta) + '</span>' : '');
                div.addEventListener('mouseover', function() { this.style.background = 'var(--bg-hover,#f5f5f5)'; });
                div.addEventListener('mouseout',  function() { this.style.background = ''; });
                div.addEventListener('click', function() {
                    setSel(item.id, (item.is_bot ? '🤖 ' : '') + (item.label || item.name));
                });
                dd.appendChild(div);
            });
            dd.style.display = 'block';
        }

        function search(q) {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { render(data.items || []); })
            .catch(function() {
                dd.innerHTML = '<div style="padding:1.2rem 1.6rem;color:#e53935;">Ошибка поиска</div>';
                dd.style.display = 'block';
            });
        }

        input.addEventListener('input', function() {
            clearSel();
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { dd.style.display = 'none'; return; }
            dd.innerHTML = '<div style="padding:1.2rem 1.6rem;font-size:1.5rem;opacity:.5;">Поиск…</div>';
            dd.style.display = 'block';
            timer = setTimeout(function() { search(q); }, 250);
        });

        document.addEventListener('click', function(e) {
            if (!document.getElementById('ac-wrap').contains(e.target)) {
                dd.style.display = 'none';
            }
        });

        document.getElementById('add-player-form').addEventListener('submit', function(e) {
            if (!hidden.value) {
                e.preventDefault();
                input.focus();
            }
        });
    })();
    </script>
    </x-slot>

</x-voll-layout>
