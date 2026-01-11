<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Roles</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="v-alert v-alert--success">
                    <div class="v-alert__text">{{ session('status') }}</div>
                </div>
            @endif

            <div class="v-card">
                <div class="v-card__body">
                    <div class="text-lg font-semibold mb-2">Доступные роли</div>
                    <div class="text-sm text-gray-700 font-mono">
                        {{ implode(', ', $roles) }}
                    </div>

                    <div class="text-sm text-gray-600 mt-3">
                        Назначение роли делаем из карточки пользователя (следующий файл) или через POST endpoint.
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
