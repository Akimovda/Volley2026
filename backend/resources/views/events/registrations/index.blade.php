{{-- resources/views/events/registrations/index.blade.php --}}
@php
    $posLabels = [
        'setter' => 'Связующий',
        'outside' => 'Доигровщик',
        'opposite' => 'Диагональный',
        'middle' => 'Центральный',
        'libero' => 'Либеро',
    ];

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
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500">Управление</div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $eventTitle }}
                </h2>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('events.create.event_management', ['tab' => 'mine']) }}"
                   class="v-btn v-btn--secondary">
                    ← К управлению
                </a>
                <a href="{{ route('events.index') }}" class="v-btn v-btn--secondary">
                    ← К мероприятиям
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="v-container space-y-6">
            {{-- Flash --}}
            @if (session('status'))
                <div class="p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">
                    <div class="font-semibold mb-2">Ошибки:</div>
                    <ul class="list-disc ml-5 space-y-1 text-sm">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Summary card --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Доступно мест</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">
                            {{ $capacityLine }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            Активных: {{ $activeCount }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Дата</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">
                            {{ $dateLine }} ({{ $tz }})
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Место</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">
                            {{ $address }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Add player --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="font-semibold text-gray-900">Добавить игрока из списка</div>
                <div class="text-xs text-gray-500 mt-1">
                    Без JS: показываем последние 200 пользователей. Можно вставить поиск позже.
                </div>

                <form method="POST"
                      action="{{ route('events.registrations.add', ['event' => $event->id]) }}"
                      class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                    @csrf

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Игрок</label>
                        <select name="user_id" class="w-full rounded-lg border-gray-200" required>
                            <option value="">— выбрать игрока —</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}">
                                    #{{ $u->id }} — {{ $u->name ?? $u->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Место (позиция)</label>
                        <select name="position" class="w-full rounded-lg border-gray-200">
                            <option value="">— без позиции —</option>
                            @foreach ($posLabels as $k => $lbl)
                                <option value="{{ $k }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" class="v-btn v-btn--primary">
                            Добавить
                        </button>
                    </div>
                </form>
            </div>

            {{-- Group management --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="font-semibold text-gray-900">Объединить игроков в пару / группу</div>
                <div class="text-xs text-gray-500 mt-1">
                    Организатор, staff или администратор может вручную создать группу и отправить приглашение второму игроку.
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
                                    #{{ (int) $r->user_id }} — {{ $r->name ?? $r->email }}
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
                                    #{{ (int) $r->user_id }} — {{ $r->name ?? $r->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="v-btn v-btn--primary">
                            Отправить приглашение
                        </button>
                    </div>
                </form>
            </div>

            {{-- Group invites --}}
            @if(isset($groupInvites) && $groupInvites->count())
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-gray-100">
                        <div class="font-semibold text-gray-900">Приглашения в группы</div>
                        <div class="text-xs text-gray-500 mt-1">
                            История приглашений по текущему мероприятию.
                        </div>
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
                                        <td class="px-4 py-3 font-semibold text-gray-900">
                                            {{ $i->group_key ?: '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ $i->from_user_name ?: ($i->from_user_email ?: ('User_' . $i->from_user_id)) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ $i->to_user_name ?: ($i->to_user_email ?: ('User_' . $i->to_user_id)) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full border text-xs font-semibold
                                                @if($i->status === 'pending') bg-yellow-50 text-yellow-700 border-yellow-100
                                                @elseif($i->status === 'accepted') bg-green-50 text-green-700 border-green-100
                                                @elseif($i->status === 'declined') bg-red-50 text-red-700 border-red-100
                                                @else bg-gray-50 text-gray-700 border-gray-100
                                                @endif">
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

            {{-- Table --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100">
                    <div class="font-semibold text-gray-900">Зарегистрированные игроки</div>
                    <div class="text-xs text-gray-500 mt-1">
                        Здесь можно отменить, удалить, сменить место и управлять группой.
                    </div>
                </div>

                @if ($registrations->isEmpty())
                    <div class="p-5 text-sm text-gray-600">
                        Пока никто не записан.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="text-left px-4 py-3 font-semibold">Игрок</th>
                                    <th class="text-left px-4 py-3 font-semibold">Телефон</th>
                                    <th class="text-left px-4 py-3 font-semibold">Место</th>
                                    <th class="text-left px-4 py-3 font-semibold">Группа</th>
                                    <th class="text-left px-4 py-3 font-semibold">Статус</th>
                                    <th class="text-right px-4 py-3 font-semibold">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($registrations as $r)
                                    @php
                                        $name = $r->name ?: ($r->email ?: ('User_' . $r->user_id));
                                        $phone = property_exists($r, 'phone') ? ($r->phone ?: '—') : '—';
                                        $posKey = $r->position ?: '';
                                        $posLabel = $posKey ? ($posLabels[$posKey] ?? $posKey) : '—';
                                        $st = $statusText($r);
                                        $groupKey = $r->group_key ?: '';
                                    @endphp

                                    <tr class="{{ $st === 'отменено' ? 'opacity-70' : '' }}">
                                        <td class="px-4 py-3">
                                            <a class="text-blue-600 hover:text-blue-700 font-semibold"
                                               href="{{ route('users.show', ['user' => $r->user_id]) }}">
                                                {{ $name }}
                                            </a>
                                            <div class="text-xs text-gray-500">#{{ $r->user_id }}</div>
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ $phone }}
                                        </td>

                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-900">{{ $posLabel }}</div>

                                            <form class="mt-2 flex gap-2 items-center"
                                                  method="POST"
                                                  action="{{ route('events.registrations.position', ['event' => $event->id, 'registration' => $r->id]) }}">
                                                @csrf
                                                @method('PATCH')

                                                <select name="position" class="rounded-lg border-gray-200 text-sm">
                                                    <option value="">— без позиции —</option>
                                                    @foreach ($posLabels as $k => $lbl)
                                                        <option value="{{ $k }}" @selected($posKey === $k)>{{ $lbl }}</option>
                                                    @endforeach
                                                </select>

                                                <button class="v-btn v-btn--secondary" type="submit">
                                                    Изменить
                                                </button>
                                            </form>
                                        </td>

                                        <td class="px-4 py-3">
                                            @if ($groupKey !== '')
                                                <div class="font-semibold text-gray-900">{{ $groupKey }}</div>

                                                @if ($st !== 'отменено')
                                                    <form method="POST"
                                                          action="{{ route('events.registrations.group.leave', ['event' => $event->id, 'registration' => $r->id]) }}"
                                                          class="mt-2">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="v-btn v-btn--secondary">
                                                            Убрать из группы
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                @if ($st !== 'отменено')
                                                    <form method="POST"
                                                          action="{{ route('events.registrations.group.create', ['event' => $event->id, 'registration' => $r->id]) }}">
                                                        @csrf
                                                        <button type="submit" class="v-btn v-btn--secondary">
                                                            Создать группу
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-gray-400">—</span>
                                                @endif
                                            @endif
                                        </td>

                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full border text-xs font-semibold {{ $statusBadgeClass($st) }}">
                                                {{ $st }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <form method="POST"
                                                      action="{{ route('events.registrations.cancel', ['event' => $event->id, 'registration' => $r->id]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="v-btn v-btn--secondary">
                                                        {{ $st === 'отменено' ? 'Восстановить' : 'Отклонить' }}
                                                    </button>
                                                </form>

                                                <form method="POST"
                                                      action="{{ route('events.registrations.destroy', ['event' => $event->id, 'registration' => $r->id]) }}"
                                                      onsubmit="return confirm('Удалить регистрацию полностью?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="v-btn v-btn--secondary">
                                                        Удалить
                                                    </button>
                                                </form>
                                            </div>

                                            <div class="text-xs text-gray-400 text-right mt-2">
                                                reg #{{ $r->id }}
                                            </div>
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
</x-app-layout>