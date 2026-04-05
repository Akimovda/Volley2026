{{-- resources/views/events/registrations_management.blade.php --}}
@php
    $tz = $event->timezone ?: 'UTC';

    $fmtWhen = function () use ($event, $tz) {
        if (!$event->starts_at) return '—';
        $s = \Illuminate\Support\Carbon::parse($event->starts_at)->setTimezone($tz);
        $e = $event->ends_at ? \Illuminate\Support\Carbon::parse($event->ends_at)->setTimezone($tz) : null;

        // Русский формат
        \Illuminate\Support\Carbon::setLocale('ru');
        $datePart = $s->translatedFormat('l, j F');
        $timePart = $s->format('H:i') . ($e ? ' - ' . $e->format('H:i') : '');

        return $datePart . ' @ ' . $timePart . ' (' . $tz . ')';
    };

    $fmtPlace = function () use ($location) {
        if (!$location) return '—';
        $parts = array_filter([
            $location->city?->name ?? null,
            $location->name ?? null,
            $location->address ?? null,
        ]);
        return $parts ? implode(', ', $parts) : '—';
    };

    $statusLabel = function ($r) use ($hasStatus, $hasIsCancelled, $hasCancelledAt) {
        $cancelled = false;
        if ($hasCancelledAt && !empty($r->cancelled_at)) $cancelled = true;
        if ($hasIsCancelled && (bool)$r->is_cancelled) $cancelled = true;
        if ($hasStatus && (string)($r->status ?? '') === 'cancelled') $cancelled = true;

        return $cancelled ? ['text' => 'отменено', 'cls' => 'bg-red-50 text-red-700 border-red-100'] : ['text' => 'подтверждено', 'cls' => 'bg-green-50 text-green-700 border-green-100'];
    };

    $seatsText = function () use ($event, $maxPlayers, $activeRegs, $freeSeats) {
        if (!(bool)$event->allow_registration) return 'Регистрация выключена';
        if ($maxPlayers <= 0) return 'Доступно мест: —';
        return 'Доступно мест: ' . (int)$freeSeats . '/' . (int)$maxPlayers;
    };

    // попытка вытащить телефон из users, если колонка есть
    $hasUserPhone =
        \Illuminate\Support\Facades\Schema::hasColumn('users', 'phone')
        || \Illuminate\Support\Facades\Schema::hasColumn('users', 'phone_number')
        || \Illuminate\Support\Facades\Schema::hasColumn('users', 'telegram_phone');

    $phoneValue = function ($userId) {
        $cols = ['phone', 'phone_number', 'telegram_phone'];
        foreach ($cols as $c) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', $c)) {
                $v = \Illuminate\Support\Facades\DB::table('users')->where('id', (int)$userId)->value($c);
                if (!empty($v)) return (string)$v;
            }
        }
        return '';
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Управление: {{ $event->title }}
                </h2>
                <div class="text-sm text-gray-500 mt-1">
                    Страница регистрации и управления игроками.
                </div>
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
        @if (session('status'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100 text-sm">
                <div class="font-semibold mb-2">Ошибки:</div>
                <ul class="list-disc ml-5 space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
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
                    <div class="mt-2 font-semibold text-gray-900">
                        {{ $seatsText() }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Записано (активных): {{ (int)$activeRegs }}
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div class="text-xs text-gray-500">Дата</div>
                    <div class="mt-2 font-semibold text-gray-900">
                        {{ $fmtWhen() }}
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div class="text-xs text-gray-500">Место</div>
                    <div class="mt-2 font-semibold text-gray-900">
                        {{ $fmtPlace() }}
                    </div>
                </div>
            </div>

            {{-- ADD PLAYER --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="font-semibold text-gray-900">Добавить игрока</div>
                    <div class="text-xs text-gray-500 mt-1">
                        Введи имя или email, нажми “Найти”, выбери игрока и добавь.
                    </div>
                </div>

                <div class="p-6">
                    <form method="GET" action="{{ route('events.registrations.index', ['event' => $event->id]) }}" class="flex flex-col md:flex-row gap-3">
                        <input type="text"
                               name="q"
                               value="{{ $q }}"
                               class="w-full md:w-1/2 rounded-lg border-gray-200"
                               placeholder="Поиск пользователя: имя или email">
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 rounded-lg font-semibold text-sm bg-gray-900 text-white hover:bg-black">
                            Найти
                        </button>
                    </form>

                    <form method="POST" action="{{ route('events.registrations.add', ['event' => $event->id]) }}" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                        @csrf

                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Пользователь</label>
                            <select name="user_id" class="w-full rounded-lg border-gray-200">
                                <option value="">— выбери пользователя —</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">
                                        #{{ $u->id }} — {{ $u->name ?? $u->email }}
                                    </option>
                                @endforeach
                            </select>
                            @if($q === '')
                                <div class="text-xs text-gray-500 mt-1">Подсказка: сначала введи поиск (q), чтобы появился список.</div>
                            @endif
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Место (опционально)</label>
                            <input type="text"
                                   name="position"
                                   class="w-full rounded-lg border-gray-200"
                                   placeholder="Напр. Setter / Место 3"
                                   @disabled(!$hasPosition)>
                            @if(!$hasPosition)
                                <div class="text-xs text-gray-500 mt-1">
                                    Колонки <span class="font-mono">position</span> нет в event_registrations — поле отключено.
                                </div>
                            @endif
                        </div>

                        <div class="md:col-span-3">
                            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm bg-gray-900 text-white hover:bg-black">
                                + Добавить игрока
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- REGISTRATIONS TABLE --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="font-semibold text-gray-900">Зарегистрированные игроки</div>
                    <div class="text-xs text-gray-500 mt-1">
                        Отклонить — без удаления. Удалить — полное удаление записи.
                    </div>
                </div>

                @if($registrations->isEmpty())
                    <div class="p-6 text-sm text-gray-600">
                        Пока никто не записался.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="text-left px-4 py-3">Игрок</th>
                                    <th class="text-left px-4 py-3">Телефон</th>
                                    <th class="text-left px-4 py-3">Место</th>
                                    <th class="text-left px-4 py-3">Статус</th>
                                    <th class="text-right px-4 py-3">Действия</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100">
                                @foreach($registrations as $r)
                                    @php
                                        $st = $statusLabel($r);
                                        $displayName = $r->name ?: ($r->email ?: ('User_' . $r->user_id));
                                        $phone = $hasUserPhone ? $phoneValue($r->user_id) : '';
                                    @endphp
                                    <tr class="hover:bg-gray-50/60">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-900">
                                                <a href="{{ route('users.show', ['user' => (int)$r->user_id]) }}" class="hover:underline">
                                                    {{ $displayName }}@if(!empty($r->is_bot)) <span title="Бот-помощник" class="text-gray-400 text-xs">🤖</span>@endif
                                                </a>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                User_{{ (int)$r->user_id }} · Reg #{{ (int)$r->id }}
                                            </div>
                                        </td>

                                        <td class="px-4 py-3 text-gray-700">
                                            @if($phone !== '')
                                                {{ $phone }}
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>

                                        <td class="px-4 py-3">
                                            @if($hasPosition)
                                                <form method="POST"
                                                      action="{{ route('events.registrations.position', ['event' => $event->id, 'registration' => (int)$r->id]) }}"
                                                      class="flex gap-2 items-center">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="text"
                                                           name="position"
                                                           value="{{ (string)($r->position ?? '') }}"
                                                           class="w-56 rounded-lg border-gray-200 text-sm"
                                                           placeholder="место / роль">
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-semibold border border-gray-200 bg-white hover:bg-gray-50">
                                                        Изменить
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>

                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $st['cls'] }}">
                                                {{ $st['text'] }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <div class="inline-flex gap-2 justify-end">

                                                {{-- Отклонить (cancel) --}}
                                                <form method="POST"
                                                      action="{{ route('events.registrations.cancel', ['event' => $event->id, 'registration' => (int)$r->id]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-semibold border border-red-200 bg-red-50 text-red-700 hover:bg-red-100">
                                                        Отклонить
                                                    </button>
                                                </form>

                                                {{-- Удалить --}}
                                                <form method="POST"
                                                      action="{{ route('events.registrations.destroy', ['event' => $event->id, 'registration' => (int)$r->id]) }}"
                                                      onsubmit="return confirm('Точно удалить регистрацию? Это действие необратимо.');">
                                                    @csrf
                                                    @method('DELETE')
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

        </div>
    </div>
</x-app-layout>
