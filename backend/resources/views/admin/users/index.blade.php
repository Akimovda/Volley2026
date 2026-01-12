<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Users</h2>
    </x-slot>
    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="v-card"><div class="v-card__body">
                <form class="flex flex-col md:flex-row gap-3" method="GET" action="{{ route('admin.users.index') }}">
                    <input class="v-input w-full" name="q" value="{{ $q }}" placeholder="Поиск: имя/фамилия/email/tg/vk" />

                    <select class="v-input md:w-56" name="role">
                        <option value="">Все роли</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r }}" @selected($role===$r)>{{ $r }}</option>
                        @endforeach
                    </select>

                    <button class="v-btn v-btn--primary" type="submit">Найти</button>
                </form>
            </div></div>

            <div class="v-card"><div class="v-card__body overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-gray-600">
                        <tr>
                            <th class="text-left py-2 pr-4">ID</th>
                            <th class="text-left py-2 pr-4">User</th>
                            <th class="text-left py-2 pr-4">Role</th>
                            <th class="text-left py-2 pr-4">TG</th>
                            <th class="text-left py-2 pr-4">VK</th>
                            <th class="text-left py-2 pr-4">Created</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-800">
                        @foreach ($users as $u)
                            <tr class="border-t">
                                <td class="py-2 pr-4">{{ $u->id }}</td>

                                <td class="py-2 pr-4">
                                    <a class="underline" href="{{ route('admin.users.show', $u) }}">
                                        {{ $u->name }}
                                    </a>
                                    <div class="text-xs text-gray-500">{{ $u->email }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ $u->last_name }} {{ $u->first_name }}
                                    </div>
                                </td>

                                <td class="py-2 pr-4">{{ $u->role ?? 'user' }}</td>
                                <td class="py-2 pr-4">{{ $u->telegram_id ? 'yes' : '—' }}</td>
                                <td class="py-2 pr-4">{{ $u->vk_id ? 'yes' : '—' }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $u->created_at?->format('Y-m-d') }}</td>

                                    </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4">{{ $users->links() }}</div>
            </div></div>

        </div>
    </div>
</x-app-layout>
