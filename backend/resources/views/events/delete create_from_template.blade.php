{{-- resources/views/events/create_from_template.blade.php --}}
@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $templates */
    /** @var \Illuminate\Support\Collection|\Illuminate\Support\Enumerable $locationsById */

    $dirLabels = [
        'classic' => 'Классика',
        'beach'   => 'Пляж',
    ];

    $formatLabels = [
        'game'           => 'Игра',
        'training'       => 'Тренировка',
        'training_game'  => 'Тренировка + Игра',
        'coach_student'  => 'Тренер + ученик',
        'tournament'     => 'Турнир',
        'camp'           => 'КЕМП',
    ];

    $fmtDate = function (?string $starts, ?string $tz) {
        $starts = trim((string)$starts);
        if ($starts === '') return '—';
        $pretty = str_replace('T', ' ', $starts);
        return $tz ? ($pretty . ' (' . $tz . ')') : $pretty;
    };

    $payloadToArray = function ($payload): array {
        if (is_array($payload)) return $payload;
        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Создать из шаблона
            </h2>
            <a href="{{ route('events.create') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                ← Назад
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 py-10 space-y-6">
        @if (session('status'))
            <div class="p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <div class="font-semibold text-gray-900 text-lg">Выбери шаблон</div>
                <div class="mt-1 text-sm text-gray-600">
                    Превью берём из payload: направление / формат / локация / дата-время.
                </div>
            </div>

            @if($templates->isEmpty())
                <div class="p-6 text-sm text-gray-600">
                    Шаблонов пока нет.
                    <a href="{{ route('events.create.from_scratch') }}" class="text-blue-600 font-semibold hover:text-blue-700">
                        Создать мероприятие с нуля →
                    </a>
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="text-left p-3">Шаблон</th>
                            <th class="text-left p-3">Направление</th>
                            <th class="text-left p-3">Формат</th>
                            <th class="text-left p-3">Локация</th>
                            <th class="text-left p-3">Дата/время</th>
                            <th class="text-right p-3">Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($templates as $tpl)
                        @php
                            $p = $payloadToArray($tpl->payload);

                            $direction = $p['direction'] ?? null;

                            $format = $p['format'] ?? null;
                            if (!is_string($format) || !array_key_exists($format, $formatLabels)) {
                                $format = 'training';
                            }

                            $locId = $p['location_id'] ?? null;
                            $loc = (is_numeric($locId) && isset($locationsById[(int)$locId]))
                                ? $locationsById[(int)$locId]
                                : null;

                            $locLabel = '—';
                            if ($loc) {
                                $parts = array_filter([
                                    $loc->name ?? null,
                                    !empty($loc->city) ? $loc->city : null,
                                    !empty($loc->address) ? $loc->address : null,
                                ]);
                                $locLabel = implode(' • ', $parts);
                            } elseif (is_numeric($locId)) {
                                $locLabel = '#' . (int)$locId . ' (не найдена)';
                            }

                            $starts = $p['starts_at_local'] ?? null;
                            $tz = $p['timezone'] ?? null;
                        @endphp

                        <tr class="border-t">
                            <td class="p-3 font-semibold text-gray-900">
                                {{ $tpl->name }}
                            </td>
                            <td class="p-3">
                                {{ $dirLabels[$direction] ?? ($direction ?: '—') }}
                            </td>
                            <td class="p-3">
                                {{ $formatLabels[$format] ?? ($format ?: '—') }}
                            </td>
                            <td class="p-3">
                                {{ $locLabel }}
                            </td>
                            <td class="p-3">
                                {{ $fmtDate($starts, $tz) }}
                            </td>
                            <td class="p-3 text-right">
                                <form method="POST" action="{{ route('event_templates.apply', $tpl) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="v-btn v-btn--primary">
                                        Использовать →
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="p-4">
                    {{ $templates->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
