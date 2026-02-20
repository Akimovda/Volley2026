{{-- resources/views/event_templates/index.blade.php --}}
@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator|\App\Models\EventTemplate[] $templates */
    /** @var \Illuminate\Support\Collection $locationsById */

   
    $formats = [
        'game' => 'Игра',
        'training' => 'Тренировка',
        'training_game' => 'Тренировка + Игра',
        'coach_student' => 'Тренер + ученик',
        'tournament' => 'Турнир',
        'camp' => 'КЕМП',
    ];

    $get = function ($payload, string $key, $default = null) {
        if (!is_array($payload)) return $default;
        return array_key_exists($key, $payload) ? $payload[$key] : $default;
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Шаблоны мероприятий
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('events.create.from_template') }}" class="v-btn v-btn--secondary">
                    ← К созданию из шаблона
                </a>
                <a href="{{ route('event_templates.create') }}" class="v-btn v-btn--primary">
                    + Новый шаблон
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 py-10">
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

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <div class="text-sm text-gray-600">
                    Нажми <span class="font-semibold">Применить</span>, чтобы открыть создание мероприятия с предзаполненными полями.
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <th class="px-6 py-3">Шаблон</th>
                        <th class="px-6 py-3">Превью</th>
                        <th class="px-6 py-3">Действия</th>
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                    @forelse($templates as $tpl)
                        @php
                            $payload = is_array($tpl->payload) ? $tpl->payload : [];

                            $title = (string)($get($payload,'title',''));
                            $direction = (string)($get($payload,'direction',''));

                           $formatKey = (string)($get($payload,'format',''));

// нормализация: любой неизвестный формат -> training
if ($formatKey === '' || !array_key_exists($formatKey, $formats)) {
    $formatKey = 'training';
}

$formatLabel = $formats[$formatKey];


                            $locationId = $get($payload,'location_id', null);
                            $startsLocal = (string)($get($payload,'starts_at_local',''));
                            $timezone = (string)($get($payload,'timezone',''));

                            $dirLabel = $direction === 'beach' ? 'Пляж' : ($direction === 'classic' ? 'Классика' : '');

                            $dtLabel = '—';
                            if ($startsLocal !== '') {
                                $dtLabel = $startsLocal;
                                if ($timezone !== '') $dtLabel .= ' (' . $timezone . ')';
                            }

                            $locLabel = '—';
                            if (is_numeric($locationId)) {
                                $loc = $locationsById->get((int)$locationId);
                                if ($loc) {
                                    $meta = trim(implode(' • ', array_filter([
                                        $loc->city ?? null,
                                        $loc->address ?? null,
                                    ])));
                                    $locLabel = $loc->name . ($meta ? (' — ' . $meta) : '');
                                } else {
                                    $locLabel = '#' . (int)$locationId . ' (не найдена)';
                                }
                            }

                            $isPrivate = (string)($get($payload,'is_private','0')) === '1';
                            $allowReg = (string)($get($payload,'allow_registration','0')) === '1';
                            $isPaid = (string)($get($payload,'is_paid','0')) === '1';

                            // ✅ recurrence_rule 
                            $recRule = trim((string)($get($payload,'recurrence_rule','')));
                            $rec = $allowReg && $recRule !== '';

                            $chips = [];
                            if ($isPrivate) $chips[] = ['key' => 'private', 'label' => 'Приватное'];
                            if ($allowReg)  $chips[] = ['key' => 'reg',     'label' => 'Регистрация'];
                            if ($isPaid)    $chips[] = ['key' => 'paid',    'label' => 'Платное'];
                            if ($rec)       $chips[] = ['key' => 'rec',     'label' => 'Повторяется'];
                        @endphp

                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 align-top">
                                <div class="font-semibold text-gray-900">
                                    {{ $tpl->name }}
                                </div>
                                @if($title !== '')
                                    <div class="mt-1 text-sm text-gray-600">
                                        {{ $title }}
                                    </div>
                                @endif
                                <div class="mt-1 text-xs text-gray-400">
                                    #{{ $tpl->id }}
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div class="p-3 rounded-xl border border-gray-100 bg-white">
                                        <div class="text-xs text-gray-500">Формат</div>
                                        <div class="mt-1 font-semibold text-gray-900">
                                            {{ $formatLabel }}
                                        </div>
                                        @if($dirLabel !== '')
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ $dirLabel }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="p-3 rounded-xl border border-gray-100 bg-white">
                                        <div class="text-xs text-gray-500">Локация</div>
                                        <div class="mt-1 font-semibold text-gray-900">
                                            {{ $locLabel }}
                                        </div>
                                    </div>

                                    <div class="p-3 rounded-xl border border-gray-100 bg-white">
                                        <div class="text-xs text-gray-500">Дата</div>
                                        <div class="mt-1 font-semibold text-gray-900">
                                            {{ $dtLabel }}
                                        </div>
                                    </div>
                                </div>

                                @if(!empty($chips))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($chips as $c)
                                            <span class="px-2 py-1 rounded-full text-xs border border-gray-200 text-gray-700 bg-white">
                                                {{ $c['label'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-col gap-2">
                                    <form method="POST" action="{{ route('event_templates.apply', $tpl) }}">
                                        @csrf
                                        <button class="v-btn v-btn--primary w-full" type="submit">
                                            Применить
                                        </button>
                                    </form>

                                    <a href="{{ route('event_templates.edit', $tpl) }}" class="v-btn v-btn--secondary w-full">
                                        Редактировать
                                    </a>

                                    <form method="POST" action="{{ route('event_templates.destroy', $tpl) }}"
                                          onsubmit="return confirm('Удалить шаблон?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="v-btn v-btn--danger w-full" type="submit">
                                            Удалить
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-10 text-center text-gray-500" colspan="3">
                                Пока нет шаблонов.
                                <div class="mt-3">
                                    <a href="{{ route('event_templates.create') }}" class="v-btn v-btn--primary">
                                        Создать первый шаблон
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($templates, 'links'))
                <div class="p-6 border-t border-gray-100">
                    {{ $templates->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
