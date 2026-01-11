<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Dashboard</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="v-card">
                <div class="v-card__body">
                    <div class="text-lg font-semibold mb-3">Users</div>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
                        <div><div class="text-gray-500">Всего</div><div class="text-xl font-bold">{{ $totalUsers }}</div></div>
                        <div><div class="text-gray-500">Активные</div><div class="text-xl font-bold">{{ $activeUsers }}</div></div>
                        <div><div class="text-gray-500">Удалённые</div><div class="text-xl font-bold">{{ $deletedUsers }}</div></div>
                        <div><div class="text-gray-500">Регистраций сегодня</div><div class="text-xl font-bold">{{ $usersCreatedToday }}</div></div>
                        <div><div class="text-gray-500">Удалений сегодня</div><div class="text-xl font-bold">{{ $usersDeletedToday }}</div></div>
                    </div>

                    <div class="mt-4">
                        <a class="v-btn v-btn--primary" href="{{ route('admin.users.index') }}">Открыть пользователей</a>
                        <a class="v-btn v-btn--secondary" href="{{ route('admin.roles.index') }}">Роли</a>
                    </div>
                </div>
            </div>

            <div class="v-card">
                <div class="v-card__body">
                    <div class="text-lg font-semibold mb-3">Роли</div>
                    <div class="text-sm text-gray-700">
                        @foreach($roles as $r)
                            <div class="flex justify-between border-t py-2">
                                <div class="font-mono">{{ $r->role ?? 'null' }}</div>
                                <div class="font-semibold">{{ $r->c }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="v-card">
                <div class="v-card__body">
                    <div class="text-lg font-semibold mb-3">Organizer requests</div>
                    <div class="text-sm text-gray-700">
                        @foreach($organizerRequests as $r)
                            <div class="flex justify-between border-t py-2">
                                <div class="font-mono">{{ $r->status ?? 'null' }}</div>
                                <div class="font-semibold">{{ $r->c }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
