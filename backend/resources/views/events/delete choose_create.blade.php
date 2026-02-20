{{-- resources/views/events/choose_create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Создать мероприятие
            </h2>
            <a href="{{ route('events.index') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                ← К мероприятиям
            </a>
        <div class="mt-6">
    <a href="{{ route('event_templates.index') }}" class="v-btn v-btn--secondary">
        Мои мероприятия для создания →
    </a>
</div>

        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 py-10 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="text-gray-900 font-semibold text-lg">Выбери способ создания</div>
            <div class="mt-2 text-sm text-gray-600">
                Можно создать “с нуля” или использовать существующие мероприяти или архивные!.
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="{{ route('events.create.from_template') }}"
                   class="block rounded-2xl border border-gray-100 p-5 hover:bg-gray-50">
                    <div class="font-semibold text-gray-900">Создать из мероприятия</div>
                    <div class="mt-1 text-sm text-gray-600">
                        Быстро. Предзаполняем формат/локацию/настройки.
                    </div>
                    <div class="mt-3 text-xs text-gray-500">👌 Все работает!</div>
                </a>

                <a href="{{ route('events.create.from_scratch') }}"
                   class="block rounded-2xl border border-gray-100 p-5 hover:bg-gray-50">
                    <div class="font-semibold text-gray-900">Создать с нуля</div>
                    <div class="mt-1 text-sm text-gray-600">
                        Твой текущий экран создания (с превью локации).
                    </div>
                    <div class="mt-3 text-xs text-green-700">✅ Работает сейчас </div>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
