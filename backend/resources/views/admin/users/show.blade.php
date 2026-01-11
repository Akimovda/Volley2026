<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                User #{{ $user->id }}
            </h2>
            <div class="v-actions">
                <a class="v-btn v-btn--secondary" href="{{ route('admin.users.index') }}">← Назад</a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="v-alert v-alert--success">
                    <div class="v-alert__text">{{ session('status') }}</div>
                </div>
            @endif

            {{-- ===== USER CARD ===== --}}
            <div class="v-card">
                <div class="v-card__body space-y-4">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-gray-500">Имя</div>
                            <div class="text-lg font-semibold">{{ $user->name }}</div>
                            <div class="text-sm text-gray-600">{{ $user->last_name }} {{ $user->first_name }} {{ $user->patronymic }}</div>
                            <div class="text-sm text-gray-600">{{ $user->email }}</div>
                        </div>

                        <div>
                            <div class="text-sm text-gray-500">Статус</div>
                            <div class="text-sm mt-1">
                                Created: <b>{{ $user->created_at?->format('Y-m-d H:i') }}</b><br>
                                Updated: <b>{{ $user->updated_at?->format('Y-m-d H:i') }}</b>
                            </div>

                            @if (property_exists($user, 'deleted_at') || \Illuminate\Support\Facades\Schema::hasColumn('users','deleted_at'))
                                <div class="text-sm mt-2">
                                    Deleted:
                                    @if (!empty($user->deleted_at))
                                        <b>{{ \Illuminate\Support\Carbon::parse($user->deleted_at)->format('Y-m-d H:i') }}</b>
                                    @else
                                        <b>—</b>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ===== PROVIDERS ===== --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-3 rounded bg-gray-50">
                            <div class="text-sm text-gray-500 mb-1">Telegram</div>
                            <div class="text-sm">telegram_id: <b>{{ $user->telegram_id ?? '—' }}</b></div>
                            <div class="text-sm">username: <b>{{ $user->telegram_username ?? '—' }}</b></div>
                        </div>

                        <div class="p-3 rounded bg-gray-50">
                            <div class="text-sm text-gray-500 mb-1">VK</div>
                            <div class="text-sm">vk_id: <b>{{ $user->vk_id ?? '—' }}</b></div>
                            <div class="text-sm">vk_email: <b>{{ $user->vk_email ?? '—' }}</b></div>
                        </div>
                    </div>

                    {{-- ===== ROLE UPDATE ===== --}}
                    <div class="border-t pt-4">
                        <div class="text-lg font-semibold mb-2">Роль</div>

                        <form method="POST" action="{{ route('admin.users.role.update', $user) }}" class="flex flex-col md:flex-row gap-3 items-start md:items-end">
                            @csrf
                            <div class="w-full md:w-64">
                                <label class="block mb-1 text-sm text-gray-600">Выбрать роль</label>
                                <select class="v-input w-full" name="role" required>
                                    @foreach ($roles as $r)
                                        <option value="{{ $r }}" @selected(($user->role ?? 'user') === $r)>{{ $r }}</option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="w-full md:flex-1">
                                <label class="block mb-1 text-sm text-gray-600">Комментарий (опционально)</label>
                                <input class="v-input w-full" name="note" value="{{ old('note') }}" placeholder="Почему меняем роль" />
                                @error('note')
                                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <button class="v-btn v-btn--primary" type="submit">Сохранить роль</button>
                        </form>

                        <div class="text-xs text-gray-500 mt-2">
                            Важно: смену роли логируем в admin_audits (сделаем в контроллере роли).
                        </div>
                    </div>

                </div>
            </div>

            {{-- ===== LINK AUDITS ===== --}}
            <div class="v-card">
                <div class="v-card__body">
                    <div class="text-lg font-semibold mb-3">История привязок аккаунтов</div>

                    @if (empty($linkAudits) || count($linkAudits) === 0)
                        <div class="text-sm text-gray-600">Нет записей.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-gray-600">
                                    <tr>
                                        <th class="text-left py-2 pr-4">Когда</th>
                                        <th class="text-left py-2 pr-4">Provider</th>
                                        <th class="text-left py-2 pr-4">Provider ID</th>
                                        <th class="text-left py-2 pr-4">From user</th>
                                        <th class="text-left py-2 pr-4">Method</th>
                                        <th class="text-left py-2 pr-4">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-800">
                                    @foreach ($linkAudits as $a)
                                        <tr class="border-t">
                                            <td class="py-2 pr-4 whitespace-nowrap">
                                                {{ \Illuminate\Support\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}
                                            </td>
                                            <td class="py-2 pr-4">{{ $a->provider ?? '—' }}</td>
                                            <td class="py-2 pr-4"><span class="font-mono text-xs">{{ $a->provider_user_id ?? '—' }}</span></td>
                                            <td class="py-2 pr-4">{{ $a->linked_from_user_id ?? '—' }}</td>
                                            <td class="py-2 pr-4">{{ $a->method ?? '—' }}</td>
                                            <td class="py-2 pr-4">{{ $a->ip ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ===== ADMIN AUDITS FOR THIS USER ===== --}}
            <div class="v-card">
                <div class="v-card__body">
                    <div class="text-lg font-semibold mb-3">Admin audit по пользователю</div>

                    @if (empty($adminAudits) || count($adminAudits) === 0)
                        <div class="text-sm text-gray-600">Нет записей.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-gray-600">
                                    <tr>
                                        <th class="text-left py-2 pr-4">Когда</th>
                                        <th class="text-left py-2 pr-4">Admin</th>
                                        <th class="text-left py-2 pr-4">Action</th>
                                        <th class="text-left py-2 pr-4">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-800">
                                    @foreach ($adminAudits as $a)
                                        <tr class="border-t">
                                            <td class="py-2 pr-4 whitespace-nowrap">
                                                {{ \Illuminate\Support\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}
                                            </td>
                                            <td class="py-2 pr-4">{{ $a->admin_user_id ?? '—' }}</td>
                                            <td class="py-2 pr-4">
                                                <span class="font-mono text-xs">{{ $a->action ?? '—' }}</span>
                                            </td>
                                            <td class="py-2 pr-4">{{ $a->ip ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
