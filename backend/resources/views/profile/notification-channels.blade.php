{{-- resources/views/profile/notification-channels.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Каналы уведомлений
                </h2>
                <div class="text-sm text-gray-500 mt-1">
                    Подключай Telegram, VK и MAX каналы для анонсов мероприятий.
                </div>
            </div>

            <a href="{{ route('profile.show') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                ← Назад в профиль
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-white rounded-2xl shadow-sm border border-green-200 p-4 text-sm text-green-900">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="bg-white rounded-2xl shadow-sm border border-red-200 p-4 text-sm text-red-900">
                    {{ session('error') }}
                </div>
            @endif

            @php
                $bindInstruction = session('bind_instruction');
            @endphp

            @if(!empty($bindInstruction))
                <div class="bg-white rounded-2xl shadow-sm border border-blue-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-blue-100 bg-blue-50">
                        <div class="font-semibold text-blue-900">
                            Инструкция по привязке {{ strtoupper($bindInstruction['platform'] ?? '') }}
                        </div>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="text-sm text-gray-700">
                            {{ $bindInstruction['message'] ?? 'Выполни шаги ниже.' }}
                            @if(!empty($bindInstruction['title']))
                                <span class="text-gray-500">{{ $bindInstruction['title'] }}</span>
                            @endif
                        </div>

                        @if(!empty($bindInstruction['link']))
                            <div>
                                <a href="{{ $bindInstruction['link'] }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm bg-gray-900 text-white hover:bg-gray-800">
                                    {{ $bindInstruction['button_text'] ?? 'Открыть' }}
                                </a>
                            </div>
                        @endif

                        @if(($bindInstruction['platform'] ?? '') !== 'max' && !empty($bindInstruction['command']))
                            <div>
                                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                    Команда / токен
                                </div>
                                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm text-gray-900 break-all">
                                    {{ $bindInstruction['command'] }}
                                </div>
                            </div>
                        @endif
                        @if(($bindInstruction['platform'] ?? '') === 'max')
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                Будет привязан именно тот чат MAX, который ты выберешь внутри бота.
                                Если у тебя несколько чатов, выбери нужный из списка.
                            </div>
                        @endif
                        @if(!empty($bindInstruction['instruction']))
                            <div>
                                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                    Что сделать
                                </div>
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 whitespace-pre-line">
                                    {{ $bindInstruction['instruction'] }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- CREATE BIND --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <div class="font-semibold text-gray-900">Подключить новый канал</div>
                        <div class="text-sm text-gray-500 mt-1">
                           Создай ссылку привязки и открой бота нужной платформы.
                        </div>
                    </div>

                    <div class="p-6 space-y-4">
                        <form method="POST" action="{{ route('profile.notification_channels.bind') }}" class="space-y-4">
                            @csrf

                            <div>
                                <label for="platform" class="block text-sm font-semibold text-gray-700 mb-2">
                                    Платформа
                                </label>
                                <select id="platform"
                                        name="platform"
                                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm">
                                    <option value="telegram" {{ old('platform', 'telegram') === 'telegram' ? 'selected' : '' }}>Telegram</option>
                                    <option value="vk" {{ old('platform') === 'vk' ? 'selected' : '' }}>VK</option>
                                    <option value="max" {{ old('platform') === 'max' ? 'selected' : '' }}>MAX</option>
                                </select>
                                @error('platform')
                                    <div class="text-sm text-red-600 mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">
                                    Название канала
                                </label>
                                <input id="title"
                                       type="text"
                                       name="title"
                                       value="{{ old('title') }}"
                                       placeholder="Например: Основной Telegram канал"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm">
                                @error('title')
                                    <div class="text-sm text-red-600 mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm bg-gray-900 text-white hover:bg-gray-800">
                                    Создать ссылку привязки
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- CURRENT CHANNELS --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <div class="font-semibold text-gray-900">Подключённые каналы</div>
                        <div class="text-sm text-gray-500 mt-1">
                            Только подтверждённые каналы можно использовать в анонсах мероприятий.
                        </div>
                    </div>

                    <div class="p-6">
                        @if(($channels ?? collect())->isEmpty())
                            <div class="text-sm text-gray-400">
                                Пока нет подключённых каналов.
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($channels as $channel)
                                    <div class="rounded-2xl border border-gray-200 p-4 flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <div class="font-semibold text-gray-900">
                                                    {{ strtoupper($channel->platform) }}
                                                </div>

                                                @if($channel->is_verified)
                                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">
                                                        подтверждён
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-800">
                                                        ожидает подтверждения
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="text-sm text-gray-900 mt-2 break-words">
                                                {{ $channel->title ?: 'Без названия' }}
                                            </div>

                                            <div class="text-xs text-gray-500 mt-1 break-all">
                                                chat_id: {{ $channel->chat_id }}
                                            </div>

                                            @if(!empty($channel->verified_at))
                                                <div class="text-xs text-gray-400 mt-1">
                                                    Подтверждён: {{ $channel->verified_at->format('d.m.Y H:i') }}
                                                </div>
                                            @endif
                                        </div>

                                        <form method="POST"
                                              action="{{ route('profile.notification_channels.destroy', $channel) }}"
                                              onsubmit="return confirm('Удалить этот канал?');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-semibold border border-red-200 bg-red-50 text-red-700 hover:bg-red-100">
                                                Удалить
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- RECENT BIND REQUESTS --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="font-semibold text-gray-900">Последние запросы привязки</div>
                    <div class="text-sm text-gray-500 mt-1">
                        Удобно для проверки, какой токен ещё ожидает подтверждения.
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="text-left px-4 py-3">ID</th>
                                <th class="text-left px-4 py-3">Платформа</th>
                                <th class="text-left px-4 py-3">Статус</th>
                                <th class="text-left px-4 py-3">Истекает</th>
                                <th class="text-left px-4 py-3">Токен</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse(($bindRequests ?? collect()) as $bind)
                                <tr>
                                    <td class="px-4 py-3 text-gray-900 font-semibold">
                                        #{{ $bind->id }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ strtoupper($bind->platform) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                            {{ $bind->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ $bind->expires_at ? $bind->expires_at->format('d.m.Y H:i') : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-mono text-xs text-gray-600 break-all max-w-md">
                                            {{ $bind->token }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-sm text-gray-400">
                                        Запросов привязки пока нет.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
