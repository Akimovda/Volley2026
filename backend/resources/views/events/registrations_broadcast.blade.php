{{-- resources/views/events/registrations_broadcast.blade.php --}}
<x-voll-layout>

    <x-slot name="title">{{ __('events.broadcast_title') }} — {{ $event->title }}</x-slot>
    <x-slot name="h1">{{ __('events.broadcast_title') }}</x-slot>
    <x-slot name="h2">{{ $event->title }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.registrations.index', ['event' => $event->id, 'occurrence' => $occurrenceId]) }}" itemprop="item">
                <span itemprop="name">← {{ __('events.broadcast_back') }}</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- FLASH: отчёт об отправке --}}
            @if(session('broadcast_sent'))
                @php $sent = session('broadcast_sent'); @endphp
                <div class="bg-green-50 border border-green-200 rounded-2xl p-6">
                    <div class="font-semibold text-green-800 text-lg mb-3">
                        ✅ {{ __('events.broadcast_queued', ['n' => $sent['queued']]) }}
                    </div>
                    <table class="w-full text-sm text-gray-700">
                        <thead>
                            <tr class="text-left border-b border-green-200">
                                <th class="pb-2 font-semibold">{{ __('events.broadcast_channel_col') }}</th>
                                <th class="pb-2 font-semibold text-right">{{ __('events.broadcast_with_binding') }}</th>
                                <th class="pb-2 font-semibold text-right">{{ __('events.broadcast_no_binding') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-green-100">
                            @if(in_array('in_app', $sent['channels']))
                            <tr>
                                <td class="py-1">In-app</td>
                                <td class="py-1 text-right text-green-700 text-xs" colspan="2">
                                    {{ __('events.broadcast_inapp_note', ['n' => $sent['queued']]) }}
                                </td>
                            </tr>
                            @endif
                            @foreach(['telegram' => 'Telegram', 'vk' => 'VK', 'max' => 'MAX', 'push' => 'Push'] as $ch => $label)
                                @if(in_array($ch, $sent['channels']))
                                    @php
                                        $with = match($ch) {
                                            'telegram' => $sent['reach']['tg'],
                                            'vk'       => $sent['reach']['vk'],
                                            'max'      => $sent['reach']['max'],
                                            'push'     => $sent['reach']['push'],
                                        };
                                        $without = $sent['reach']['total'] - $with;
                                    @endphp
                                    <tr>
                                        <td class="py-1">{{ $label }}</td>
                                        <td class="py-1 text-right">{{ $with }}</td>
                                        <td class="py-1 text-right text-gray-400">{{ $without }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-4">
                        <a href="{{ route('events.registrations.index', ['event' => $event->id, 'occurrence' => $occurrenceId]) }}"
                           class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                            ← {{ __('events.broadcast_back') }}
                        </a>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 rounded-xl bg-red-50 border border-red-100 text-sm text-red-700">
                    <ul class="list-disc ml-4 space-y-1">
                        @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- СЧЁТЧИК ПОЛУЧАТЕЛЕЙ --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="font-semibold text-gray-800 mb-3">Получатели</div>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <div class="text-xs text-gray-500 mb-1">Основной состав</div>
                        <div class="text-2xl font-bold text-gray-900">{{ $counts['mainCount'] }}</div>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                        <div class="text-xs text-gray-500 mb-1">Резерв</div>
                        <div class="text-2xl font-bold text-gray-900">{{ $counts['reserveCount'] }}</div>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-500">
                    {{ __('events.broadcast_reach', [
                        'tg'    => $reach['tg'],
                        'vk'    => $reach['vk'],
                        'max'   => $reach['max'],
                        'push'  => $reach['push'],
                        'total' => $reach['total'],
                    ]) }}
                    <span class="text-gray-400">(по всем: основной + резерв)</span>
                </div>
            </div>

            {{-- ФОРМА --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="font-semibold text-gray-900">{{ __('events.broadcast_title') }}</div>
                </div>
                <div class="p-6">
                    <form method="POST"
                          action="{{ route('events.registrations.broadcast.send', ['event' => $event->id]) }}">
                        @csrf
                        <input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">

                        {{-- Тема --}}
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                {{ __('events.broadcast_subject') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="title" required maxlength="255"
                                   value="{{ old('title') }}"
                                   class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-blue-400">
                        </div>

                        {{-- Сообщение --}}
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                {{ __('events.broadcast_body') }} <span class="text-red-500">*</span>
                            </label>
                            <textarea name="body" required maxlength="5000" rows="6"
                                      class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-blue-400">{{ old('body') }}</textarea>
                        </div>

                        {{-- Каналы --}}
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                {{ __('events.broadcast_channels') }}
                            </label>
                            <div class="space-y-2">
                                @php
                                    $channelOptions = [
                                        'in_app'   => 'In-app — ' . $reach['total'] . ' получателей (все)',
                                        'telegram' => 'Telegram — ' . $reach['tg'] . ' получателей',
                                        'vk'       => 'VK — ' . $reach['vk'] . ' получателей',
                                        'max'      => 'MAX — ' . $reach['max'] . ' получателей',
                                        'push'     => 'Push — ' . $reach['push'] . ' получателей',
                                    ];
                                    $oldChannels = old('channels', ['in_app','telegram','vk','max','push']);
                                @endphp
                                @foreach($channelOptions as $val => $label)
                                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                                        <input type="checkbox" name="channels[]" value="{{ $val }}"
                                               @checked(in_array($val, $oldChannels))
                                               class="rounded border-gray-300">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Включить резерв --}}
                        <div class="mb-6">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" name="include_reserve" value="1"
                                       @checked(old('include_reserve', true))
                                       class="rounded border-gray-300">
                                {{ __('events.broadcast_include_reserve', ['n' => $counts['reserveCount']]) }}
                            </label>
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="submit"
                                    class="inline-flex items-center px-5 py-2 rounded-lg font-semibold text-sm bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                                    @if($counts['mainCount'] === 0 && $counts['reserveCount'] === 0) disabled @endif>
                                {{ __('events.broadcast_send') }}
                            </button>
                            <a href="{{ route('events.registrations.index', ['event' => $event->id, 'occurrence' => $occurrenceId]) }}"
                               class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                                ← {{ __('events.broadcast_back') }}
                            </a>
                        </div>

                        @if($counts['mainCount'] === 0 && $counts['reserveCount'] === 0)
                            <p class="mt-3 text-sm text-gray-500">Нет активных участников для рассылки.</p>
                        @endif
                    </form>
                </div>
            </div>

        </div>
    </div>

</x-voll-layout>
