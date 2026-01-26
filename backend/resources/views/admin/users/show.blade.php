{{-- resources/views/admin/users/show.blade.php --}}
<x-app-layout>
    {{-- =========================
         HEADER
    ========================== --}}
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate">
                    User #{{ $user->id }} — {{ $user->name }}
                </h2>
                <div class="text-sm text-gray-600 truncate">{{ $user->email }}</div>
            </div>

            <a href="{{ route('admin.users.index') }}" class="v-btn v-btn--secondary">
                ← Назад
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- =========================
                 FLASH / ERRORS
            ========================== --}}
            @if (session('status'))
                <div class="v-alert v-alert--success">
                    <div class="v-alert__text">{{ session('status') }}</div>
                </div>
            @endif

            @if (session('error'))
                <div class="v-alert v-alert--warn">
                    <div class="v-alert__title">Ошибка</div>
                    <div class="v-alert__text">{{ session('error') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="v-alert v-alert--warn">
                    <div class="v-alert__title">Ошибки:</div>
                    <ul class="v-alert__text list-disc ml-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- =========================
                     LEFT COLUMN
                ========================== --}}
                <div class="lg:col-span-2 space-y-4">

                    {{-- -------- Profile card -------- --}}
                    <div class="v-card">
                        <div class="v-card__title">Профиль</div>

                        <div class="flex items-center gap-4">
                            <img
                                src="{{ $user->profile_photo_url }}"
                                alt="avatar"
                                class="rounded-full border border-gray-200"
                                style="width:64px;height:64px;object-fit:cover;"
                            >

                            <div class="min-w-0">
                                <div class="font-extrabold text-gray-900 truncate">{{ $user->name }}</div>
                                <div class="text-sm text-gray-700 truncate">{{ $user->email }}</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    created: {{ $user->created_at?->format('Y-m-d H:i') ?? '—' }}
                                    @if(property_exists($user, 'deleted_at') && $user->deleted_at)
                                        • <span class="text-red-600 font-bold">DELETED</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 text-sm text-gray-800 space-y-1">
                            <div><b>Имя/Фамилия:</b> {{ $user->last_name }} {{ $user->first_name }}</div>
                            <div><b>Телефон:</b> {{ $user->phone ?? '—' }}</div>
                        </div>
                    </div>

                    {{-- -------- Providers (TG / VK / Yandex) -------- --}}
                    <div class="v-card">
                        <div class="v-card__title">Providers</div>

                        <div class="text-sm text-gray-800 space-y-3">

                            {{-- TG --}}
                            <div class="flex items-center gap-2">
                                <span class="w-20 text-gray-500">TG:</span>
                                <b>{{ $user->telegram_id ? 'yes' : '—' }}</b>

                                @if($user->telegram_username)
                                    <span class="text-xs text-gray-500 break-all">(@{{ $user->telegram_username }})</span>
                                @endif

                                @if($user->telegram_id)
                                    <span class="text-xs text-gray-500 break-all">telegram_id: {{ $user->telegram_id }}</span>
                                @endif
                            </div>

                            {{-- VK --}}
                            <div class="flex items-center gap-2">
                                <span class="w-20 text-gray-500">VK:</span>
                                <b>{{ $user->vk_id ? 'yes' : '—' }}</b>

                                @if($user->vk_id)
                                    <span class="text-xs text-gray-500 break-all">vk_id: {{ $user->vk_id }}</span>
                                @endif

                                @if($user->vk_email)
                                    <span class="text-xs text-gray-500 break-all">vk_email: {{ $user->vk_email }}</span>
                                @endif
                            </div>

                            {{-- Yandex (вернули как было + красиво) --}}
                            <div class="flex items-center gap-2">
                                <span class="w-20 text-gray-500">Yandex:</span>
                                <b>{{ $user->yandex_id ? 'yes' : '—' }}</b>

                                @if($user->yandex_id)
                                    <span class="text-xs text-gray-500 break-all">yandex_id: {{ $user->yandex_id }}</span>
                                @endif

                                @if($user->yandex_email)
                                    <span class="text-xs text-gray-500 break-all">yandex_email: {{ $user->yandex_email }}</span>
                                @endif
                            </div>

                        </div>
                    </div>

                    {{-- -------- Admin audits -------- --}}
                    <div class="v-card">
                        <div class="v-card__title">Admin audits (последние 50)</div>

                        @if(empty($adminAudits) || count($adminAudits) === 0)
                            <div class="text-sm text-gray-600">Пока нет.</div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="text-gray-600">
                                        <tr>
                                            <th class="text-left py-2 pr-4">At</th>
                                            <th class="text-left py-2 pr-4">Action</th>
                                            <th class="text-left py-2 pr-4">Admin</th>
                                            <th class="text-left py-2 pr-4">Meta</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-800">
                                        @foreach($adminAudits as $a)
                                            <tr class="border-t align-top">
                                                <td class="py-2 pr-4 whitespace-nowrap">
                                                    {{ \Illuminate\Support\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}
                                                </td>
                                                <td class="py-2 pr-4 whitespace-nowrap">
                                                    {{ $a->action }}
                                                </td>
                                                <td class="py-2 pr-4">
                                                    {{ $a->admin_id ?? '—' }}
                                                </td>
                                                <td class="py-2 pr-4">
                                                    <div class="text-xs text-gray-600 break-all">
                                                        {{ is_string($a->meta ?? null) ? $a->meta : json_encode($a->meta, JSON_UNESCAPED_UNICODE) }}
                                                    </div>
                                                    @if(!empty($a->note))
                                                        <div class="text-xs text-gray-500 mt-1">note: {{ $a->note }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- -------- Account link audits -------- --}}
                    <div class="v-card">
                        <div class="v-card__title">Account link audits (последние 50)</div>

                        @if(empty($linkAudits) || count($linkAudits) === 0)
                            <div class="text-sm text-gray-600">Пока нет.</div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="text-gray-600">
                                        <tr>
                                            <th class="text-left py-2 pr-4">At</th>
                                            <th class="text-left py-2 pr-4">Action</th>
                                            <th class="text-left py-2 pr-4">Provider</th>
                                            <th class="text-left py-2 pr-4">Meta</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-800">
                                        @foreach($linkAudits as $a)
                                            <tr class="border-t align-top">
                                                <td class="py-2 pr-4 whitespace-nowrap">
                                                    {{ \Illuminate\Support\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}
                                                </td>
                                                <td class="py-2 pr-4 whitespace-nowrap">
                                                    {{ $a->action ?? '—' }}
                                                </td>
                                                <td class="py-2 pr-4 whitespace-nowrap">
                                                    {{ $a->provider ?? '—' }}
                                                </td>
                                                <td class="py-2 pr-4">
                                                    <div class="text-xs text-gray-600 break-all">
                                                        {{ is_string($a->meta ?? null) ? $a->meta : json_encode($a->meta, JSON_UNESCAPED_UNICODE) }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                </div>

                {{-- =========================
                     RIGHT COLUMN (Actions)
                ========================== --}}
                <div class="space-y-4">

                    {{-- -------- Role editor -------- --}}
                    <div class="v-card">
                        <div class="v-card__title">Role</div>

                        <form method="POST" action="{{ route('admin.users.role.update', $user) }}" class="space-y-3">
                            @csrf

                            <select name="role" class="v-input w-full">
                                @foreach($roles as $r)
                                    <option value="{{ $r }}" @selected(($user->role ?? 'user') === $r)>{{ $r }}</option>
                                @endforeach
                            </select>

                            <button class="v-btn v-btn--primary w-full" type="submit">
                                Сохранить роль
                            </button>

                            <div class="text-xs text-gray-500">
                                Все изменения роли должны логироваться в admin_audits (action: user.role.update).
                            </div>
                        </form>
                    </div>

                    {{-- =========================
                         RESTRICTIONS (events only)
                    ========================== --}}
                    <div class="v-card">
                        <div class="v-card__title">Ограничения / блокировка записи на мероприятия (events)</div>

                        {{-- Active restrictions list --}}
                        @php
                            $restrictions = $restrictions ?? [];
                            $hasRestrictions = is_countable($restrictions) && count($restrictions) > 0;
                        @endphp

                        @if(!$hasRestrictions)
                            <div class="v-alert v-alert--success">
                                <div class="v-alert__title">Активных ограничений нет</div>
                                <div class="v-alert__text">Пользователь может записываться на все мероприятия.</div>
                            </div>
                        @else
                            <div class="v-alert v-alert--warn">
                                <div class="v-alert__title">Есть активные ограничения</div>
                                <div class="v-alert__text">Ниже список того, что действует прямо сейчас.</div>
                            </div>

                            <div class="overflow-x-auto" style="margin-top:10px;">
                                <table class="min-w-full text-sm">
                                    <thead class="text-gray-600">
                                        <tr>
                                            <th class="text-left py-2 pr-4">До</th>
                                            <th class="text-left py-2 pr-4">Event IDs</th>
                                            <th class="text-left py-2 pr-4">Reason</th>
                                            <th class="text-left py-2 pr-4">Created</th>
                                        </tr>
                                    </thead>

                                    <tbody class="text-gray-800">
                                        @foreach($restrictions as $r)
                                            @php
                                                $until = $r->ends_at
                                                    ? \Carbon\Carbon::parse($r->ends_at)->format('d:m:Y H:i')
                                                    : 'пожизненно';

                                                $ids = [];
                                                if (!empty($r->event_ids)) {
                                                    $decoded = is_string($r->event_ids) ? json_decode($r->event_ids, true) : $r->event_ids;
                                                    $ids = is_array($decoded) ? $decoded : [];
                                                }
                                            @endphp

                                            <tr class="border-t align-top">
                                                <td class="py-2 pr-4 whitespace-nowrap">
                                                    <span class="v-badge v-badge--warn">{{ $until }}</span>
                                                </td>

                                                <td class="py-2 pr-4">
                                                    @if(count($ids))
                                                        <div class="v-card__meta" style="margin:0;">
                                                            @foreach($ids as $eid)
                                                                <span class="v-badge v-badge--info">#{{ (int)$eid }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        —
                                                    @endif
                                                </td>

                                                <td class="py-2 pr-4">
                                                    {{ $r->reason ?: '—' }}
                                                </td>

                                                <td class="py-2 pr-4 whitespace-nowrap">
                                                    {{ \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- -------- Create/replace events restriction -------- --}}
                        <div class="v-card" style="margin-top:16px;">
                            <div class="v-card__title">Установить / обновить блокировку (events)</div>

                            <form method="POST" action="{{ route('admin.users.restrictions.events', $user) }}" class="space-y-3">
                                @csrf

                                {{-- Event IDs --}}
                                <div>
                                    <div class="text-xs text-gray-600 mb-1">Event IDs (числа через запятую)</div>
                                    <input class="v-input w-full" type="text" name="event_ids" placeholder="12, 18, 25" required />
                                </div>

                                {{-- Mode --}}
                                <div class="text-sm text-gray-700">
                                    <label class="inline-flex items-center gap-2">
                                        <input type="radio" name="mode" value="forever" checked>
                                        <span>Пожизненно</span>
                                    </label>

                                    <label class="inline-flex items-center gap-2" style="margin-left:14px;">
                                        <input type="radio" name="mode" value="until">
                                        <span>До даты</span>
                                    </label>
                                </div>

                                {{-- Until datetime --}}
                                <div>
                                    <div class="text-xs text-gray-600 mb-1">Дата окончания (если выбрано “До даты”)</div>
                                    <input class="v-input w-full" type="datetime-local" name="until" />
                                </div>

                                {{-- Reason --}}
                                <div>
                                    <div class="text-xs text-gray-600 mb-1">Причина (опционально)</div>
                                    <input class="v-input w-full" type="text" name="reason" placeholder="Напр.: запрет на конкретные турниры" />
                                </div>

                                <button class="v-btn v-btn--primary w-full" type="submit">
                                    Установить events-ограничение
                                </button>
                            </form>
                        </div>

                        {{-- -------- Clear all restrictions -------- --}}
                        <div class="v-card" style="margin-top:16px;">
                            <div class="v-card__title" style="color:#b91c1c;">Снять все активные ограничения</div>

                            <form method="POST"
                                  action="{{ route('admin.users.restrictions.clear', $user) }}"
                                  onsubmit="
                                    if (!confirm('Вы точно хотите снять ВСЕ активные ограничения?')) return false;
                                    const v = prompt('Для подтверждения введите: yes');
                                    if ((v || '').trim().toLowerCase() !== 'yes') { alert('Отмена.'); return false; }
                                    return true;
                                  "
                                  class="space-y-3">
                                @csrf
                                {{-- ВАЖНО: у тебя в web.php clearAll = POST, поэтому НЕ ставим DELETE --}}
                                <input type="hidden" name="confirm" value="yes">

                                <button class="v-btn w-full"
                                        type="submit"
                                        style="background:#ef4444;color:#fff;border-color:#ef4444;">
                                    Снять ограничения
                                </button>

                                <div class="text-xs text-gray-500">
                                    Действие необратимо. Для выполнения нужно подтвердить и ввести <b>yes</b>.
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- =========================
                         DANGER ZONE: PURGE USER
                    ========================== --}}
                    <div class="v-card">
                        <div class="v-card__title text-red-700">Danger zone</div>

                        <form method="POST"
                              action="{{ route('admin.users.purge', $user) }}"
                              class="space-y-3"
                              onsubmit="
                                if (!confirm('Вы точно хотите удалить все данные пользователя?! Это действие безвозвратное и вы не сможете восстановить данные!')) return false;
                                const v = prompt('Для подтверждения введите: yes');
                                if ((v || '').trim().toLowerCase() !== 'yes') { alert('Удаление отменено.'); return false; }
                                return true;
                              ">
                            @csrf
                            @method('DELETE')

                            <input type="hidden" name="confirm" value="yes">

                            <textarea name="note"
                                      class="v-input w-full"
                                      rows="3"
                                      placeholder="Комментарий (почему удаляем)"></textarea>

                            <button type="submit"
                                    class="inline-flex items-center justify-center w-full px-4 py-2 rounded-lg font-extrabold text-sm border border-red-200 bg-red-600 text-white hover:bg-red-700">
                                Удаление пользователя!
                            </button>

                            <div class="text-xs text-gray-500">
                                Действие необратимо. Для выполнения нужно подтвердить и ввести <b>yes</b>.
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>
