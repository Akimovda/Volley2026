<x-app-layout>
    <div class="v-container">
        <h1 class="text-2xl font-bold mb-4">Заявки на организатора</h1>

        @if (session('status'))
            <div class="v-alert v-alert--info mb-4">
                <div class="v-alert__text">{{ session('status') }}</div>
            </div>
        @endif

        @if ($requests->isEmpty())
            <div class="v-alert v-alert--info">
                <div class="v-alert__text">Заявок пока нет.</div>
            </div>
        @else
            <div class="v-card">
                <div class="v-card__body">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                            <tr class="text-left">
                                <th class="py-2 pr-4">ID</th>
                                <th class="py-2 pr-4">Пользователь</th>
                                <th class="py-2 pr-4">Роль</th>
                                <th class="py-2 pr-4">Статус</th>
                                <th class="py-2 pr-4">Сообщение</th>
                                <th class="py-2 pr-4">Создана</th>
                                <th class="py-2 pr-4">Ревью</th>
                                <th class="py-2 pr-4">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($requests as $r)
                                @php
                                    $fio = trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? ''));
                                    $label = $fio !== '' ? $fio : (($r->telegram_username ?? '') !== '' ? ('@' . $r->telegram_username) : $r->email);
                                @endphp

                                <tr class="border-t align-top">
                                    <td class="py-2 pr-4">{{ $r->id }}</td>

                                    <td class="py-2 pr-4">
                                        <div class="font-medium">{{ $label }}</div>
                                        <div class="text-gray-500">{{ $r->email }}</div>
                                    </td>

                                    <td class="py-2 pr-4">{{ $r->role }}</td>

                                    <td class="py-2 pr-4">
                                        @if ($r->status === 'pending')
                                            <span class="v-badge v-badge--warn">pending</span>
                                        @elseif ($r->status === 'approved')
                                            <span class="v-badge v-badge--success">approved</span>
                                        @elseif ($r->status === 'rejected')
                                            <span class="v-badge v-badge--secondary">rejected</span>
                                        @else
                                            <span class="v-badge">{{ $r->status }}</span>
                                        @endif
                                    </td>

                                    <td class="py-2 pr-4">
                                        @if (!empty($r->message))
                                            <div class="max-w-md whitespace-pre-wrap">{{ $r->message }}</div>
                                        @else
                                            <span class="text-gray-500">—</span>
                                        @endif
                                    </td>

                                    <td class="py-2 pr-4">
                                        {{ $r->created_at }}
                                    </td>

                                    <td class="py-2 pr-4">
                                        @if (!empty($r->reviewed_at))
                                            <div class="text-gray-700">{{ $r->reviewed_at }}</div>
                                             <div class="text-gray-500 text-xs">
                                                 reviewed_by: {{ $r->reviewer_email ?? $r->reviewed_by }}
                                     </div>

                                        @else
                                            <span class="text-gray-500">—</span>
                                        @endif
                                    </td>

                                    <td class="py-2 pr-4">
                                        @if ($r->status === 'pending')
                                            <form method="POST" action="{{ route('admin.organizer_requests.approve', $r->id) }}" class="inline">
                                                @csrf
                                                <button class="v-btn v-btn--primary" type="submit">Approve</button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.organizer_requests.reject', $r->id) }}" class="inline ml-2">
                                                @csrf
                                                <button class="v-btn v-btn--secondary" type="submit">Reject</button>
                                            </form>
                                        @else
                                            <span class="text-gray-500">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="v-hint mt-4">
                        Одобрение переводит пользователя в роль <b>organizer</b>.
                        Отклонение оставляет роль как есть.
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
