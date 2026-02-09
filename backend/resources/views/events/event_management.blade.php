{{-- resources/views/events/event_management.blade.php --}}
@php
    $tabs = [
        'archive'   => 'Архив',
        'mine'      => 'Мои',
    ];

    $fmtDt = function ($event) {
        $tz = $event->timezone ?: 'UTC';
        $s = $event->starts_at ? \Illuminate\Support\Carbon::parse($event->starts_at)->setTimezone($tz) : null;
        $e = $event->ends_at ? \Illuminate\Support\Carbon::parse($event->ends_at)->setTimezone($tz) : null;
        if (!$s) return '—';
        $date = $s->format('d.m.Y');
        $time = $s->format('H:i') . ($e ? '–' . $e->format('H:i') : '');
        return $date . ' · ' . $time . ' (' . $tz . ')';
    };

    $fmtLocation = function ($event) {
        $parts = array_filter([
            $event->location?->name,
            $event->location?->city,
            $event->location?->address,
        ]);
        return $parts ? implode(', ', $parts) : '—';
    };

    // ВАЖНО: считаем места по тем полям, которые отдаёт контроллер:
    // - max_players (int)
    // - active_regs (int) — только активные (не cancelled)
    $seatMeta = function ($event) {
        $max = (int)($event->max_players ?? 0);
        $registered = (int)($event->active_regs ?? 0);

        if (!(bool)$event->allow_registration) {
            return ['label' => 'Регистрация выключена', 'free' => null, 'max' => null, 'registered' => $registered];
        }
        if ($max <= 0) {
            return ['label' => 'Мест: —', 'free' => null, 'max' => null, 'registered' => $registered];
        }

        $free = max(0, $max - $registered);
        return ['label' => "Мест: {$free}/{$max}", 'free' => $free, 'max' => $max, 'registered' => $registered];
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Управление мероприятиями
                </h2>
                <div class="text-sm text-gray-500 mt-1">
                    Быстрое создание копии (“Создать копию”), а также доступ к регистрации.
                </div>
            </div>

            {{-- ✅ FIX: новая форма теперь живёт на /events/create --}}
            <a href="{{ route('events.create') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                + Создать новое
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Tabs (server-side, без JS) --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <div class="flex flex-wrap gap-2">
                    @foreach($tabs as $key => $label)
                        <a href="{{ route('events.create.event_management', ['tab' => $key]) }}"
                           class="px-4 py-2 rounded-full border text-sm font-semibold
                                  {{ $tab === $key ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Table --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="font-semibold text-gray-900">
                        {{ $tabs[$tab] ?? 'Список' }}
                    </div>

                    {{-- ✅ FIX: обновили подсказку --}}
                    <div class="text-xs text-gray-500 mt-1">
                        “Создать копию” откроет обычное создание:
                        <span class="font-mono">/events/create?from_event_id=ID</span>
                    </div>
                </div>

                @if($events->isEmpty())
                    <div class="p-6 text-sm text-gray-600">
                        Здесь пока пусто.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="text-left px-4 py-3">ID</th>
                                    <th class="text-left px-4 py-3">Название</th>
                                    <th class="text-left px-4 py-3">Местоположение</th>
                                    <th class="text-left px-4 py-3">Дата и время</th>
                                    <th class="text-left px-4 py-3">Организатор</th>
                                    <th class="text-left px-4 py-3">Регистрация</th>
                                    <th class="text-right px-4 py-3">Действия</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100">
                                @foreach($events as $event)
                                    @php
                                        $org = $event->organizer;
                                        $seat = $seatMeta($event);
                                    @endphp

                                    <tr class="hover:bg-gray-50/60">
                                        <td class="px-4 py-3 text-gray-900 font-semibold">
                                            #{{ $event->id }}
                                        </td>

                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-900">{{ $event->title }}</div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ strtoupper((string)$event->direction) }} · {{ (string)$event->format }}
                                                @if(\Illuminate\Support\Facades\Schema::hasColumn('events','is_template') && (bool)$event->is_template)
                                                    · <span class="font-semibold">TEMPLATE</span>
                                                @endif
                                            </div>
                                        </td>

                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $fmtLocation($event) }}
                                        </td>

                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $fmtDt($event) }}
                                        </td>

                                        <td class="px-4 py-3">
                                            @if($org)
                                                <div class="font-semibold text-gray-900">
                                                    #{{ $org->id }} — {{ $org->name ?? $org->email }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ ucfirst((string)($org->role ?? 'user')) }}
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>

                                        {{-- Колонка “Регистрация”: ссылка + места X/Y --}}
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-900">{{ $seat['label'] }}</div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                Записано: {{ (int)$seat['registered'] }}
                                            </div>
                                            <div class="mt-2">
                                                @if((bool)$event->allow_registration)
                                                    <a href="{{ route('events.registrations.index', ['event' => $event->id]) }}"
                                                       class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-semibold border border-gray-200 bg-white hover:bg-gray-50">
                                                        Регистрация →
                                                    </a>
                                                @else
                                                    <span class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-semibold border border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed">
                                                        Регистрация →
                                                    </span>
                                                @endif
                                            </div>
                                        </td>

                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <div class="inline-flex gap-2">
                                                {{-- ✅ FIX: копия через /events/create?from_event_id=ID --}}
                                                <a href="{{ url('/events/create?from_event_id=' . (int)$event->id) }}"
                                                   class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-semibold bg-gray-900 text-white hover:bg-black">
                                                    Создать копию
                                                </a>

                                                <a href="{{ url('/events/' . (int)$event->id) }}"
                                                   class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-semibold border border-gray-200 bg-white hover:bg-gray-50">
                                                    Открыть
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $events->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
