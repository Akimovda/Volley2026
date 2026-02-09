{{-- resources/views/event_templates/form.blade.php --}}
@php
    /** @var \App\Models\EventTemplate|null $template */
    $isEdit = isset($template) && $template;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $isEdit ? 'Редактировать шаблон' : 'Новый шаблон' }}
            </h2>

            <div class="flex gap-2">
                <a href="{{ route('event_templates.index') }}" class="v-btn v-btn--secondary">← К шаблонам</a>

                @if($isEdit)
                    <form method="POST"
                          action="{{ route('event_templates.destroy', $template) }}"
                          onsubmit="return confirm('Удалить шаблон?')">
                        @csrf
                        @method('DELETE')
                        <button class="v-btn v-btn--danger" type="submit">Удалить</button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 py-10">
        {{-- FLASH --}}
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

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <form method="POST"
                  action="{{ $isEdit ? route('event_templates.update', $template) : route('event_templates.store') }}">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                {{-- NAME --}}
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Название шаблона</label>
                    <input name="name"
                           class="w-full rounded-lg border-gray-200"
                           value="{{ old('name', $isEdit ? $template->name : '') }}"
                           required>
                    <div class="text-xs text-gray-500 mt-1">
                        Например: “Пляж 2×2 — вечер, платно, регистрация”
                    </div>
                </div>

                {{-- PAYLOAD TEXT --}}
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        payload_text (JSON)
                    </label>

                    <textarea name="payload_text"
                              rows="14"
                              class="w-full rounded-lg border-gray-200 font-mono text-xs"
                              placeholder='{"title":"...","direction":"classic","format":"game","timezone":"Europe/Berlin",...}'>{{ old('payload_text', $isEdit ? ($template->payload_text ?? '') : '') }}</textarea>

                    <div class="text-xs text-gray-500 mt-2">
                        Это полный “снимок” полей формы создания мероприятия. Он применяется через кнопку “Использовать”.
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button class="v-btn v-btn--primary" type="submit">
                        {{ $isEdit ? 'Сохранить' : 'Создать' }}
                    </button>

                    <a href="{{ route('event_templates.index') }}" class="v-btn v-btn--secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
