{{-- resources/views/admin/dashboard/index.blade.php --}}
<x-app-layout>
    {{-- =========================
         HEADER
    ========================== --}}
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate">
                    Admin / Dashboard
                </h2>
                <div class="text-xs text-gray-500">
                    Сводка по пользователям / провайдерам / блокировкам / мероприятиям
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a class="v-btn v-btn--secondary" href="{{ route('admin.users.index') }}">Users</a>
                <a class="v-btn v-btn--secondary" href="{{ route('events.index') }}">/events</a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- =========================
                 FLASH
            ========================== --}}
            @if (session('status'))
                <div class="v-alert v-alert--success">
                    <div class="v-alert__text">{{ session('status') }}</div>
                </div>
            @endif

            @php
                // providers map from controller
                $p = $providers ?? [];

                // total “has at least 1 provider”
                $totalConnected =
                    ($p['tg_only'] ?? 0) + ($p['vk_only'] ?? 0) + ($p['ya_only'] ?? 0) +
                    ($p['tg_vk'] ?? 0) + ($p['tg_ya'] ?? 0) + ($p['ya_vk'] ?? 0) +
                    ($p['ya_vk_tg'] ?? 0);

                // small helper: provider "chip" (в стиле profile, но компактнее)
                $chip = function(string $label) {
                    return '<span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full border border-gray-200 bg-white text-xs font-semibold">'.
                           '<span class="inline-flex items-center justify-center rounded-full border border-gray-300" style="width:18px;height:18px;font-size:11px;">'.e($label).'</span>'.
                           '<span>'.e($label).'</span>'.
                           '</span>';
                };

                // small helper: row like "TG:" from profile
                $rowLabel = function(string $label) {
                    return '<span class="w-24 text-gray-500">'.e($label).'</span>';
                };
            @endphp

            {{-- =========================
                 ROW 1: KPI CARDS
                 (современно: 4 карточки сверху)
            ========================== --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                {{-- Users total --}}
                <div class="v-card">
                    <div class="v-card__body">
                        <div class="text-xs text-gray-500">Users</div>
                        <div class="text-3xl font-extrabold text-gray-900 mt-1">{{ $totalUsers }}</div>
                        <div class="text-xs text-gray-500 mt-2">Всего пользователей</div>
                    </div>
                </div>

                {{-- Active users --}}
                <div class="v-card">
                    <div class="v-card__body">
                        <div class="text-xs text-gray-500">Active</div>
                        <div class="text-3xl font-extrabold text-gray-900 mt-1">{{ $activeUsers }}</div>
                        <div class="text-xs text-gray-500 mt-2">Без deleted_at</div>
                    </div>
                </div>

                {{-- Events count --}}
                <div class="v-card">
                    <div class="v-card__body">
                        <div class="text-xs text-gray-500">Events</div>
                        <div class="text-3xl font-extrabold text-gray-900 mt-1">{{ $eventsCount ?? 0 }}</div>
                        <div class="text-xs text-gray-500 mt-2">Кол-во мероприятий</div>
                    </div>
                </div>

                {{-- Restrictions count --}}
                <div class="v-card">
                    <div class="v-card__body">
                        <div class="text-xs text-gray-500">Restrictions</div>
                        <div class="text-3xl font-extrabold text-gray-900 mt-1">{{ $eventAllRestrictions ?? 0 }}</div>
                        <div class="text-xs text-gray-500 mt-2">Event All (active)</div>
                    </div>
                </div>

            </div>

            {{-- =========================
                 ROW 2: USERS DETAILS (как было, но компактнее)
            ========================== --}}
            <div class="v-card">
                <div class="v-card__body">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-lg font-semibold">Users / динамика</div>
                        <a class="v-btn v-btn--primary" href="{{ route('admin.users.index') }}">Открыть пользователей</a>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-sm mt-4">
                        <div>
                            <div class="text-gray-500">Всего</div>
                            <div class="text-xl font-bold">{{ $totalUsers }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Активные</div>
                            <div class="text-xl font-bold">{{ $activeUsers }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Удалённые</div>
                            <div class="text-xl font-bold">{{ $deletedUsers }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Регистраций сегодня</div>
                            <div class="text-xl font-bold">{{ $usersCreatedToday }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Удалений сегодня</div>
                            <div class="text-xl font-bold">{{ $usersDeletedToday }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- =========================
                 ROW 3: PROVIDERS + RESTRICTIONS (2 колонки)
            ========================== --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- PROVIDERS (2/3 ширины) --}}
                <div class="v-card lg:col-span-2">
                    <div class="v-card__body">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <div class="text-lg font-semibold">Провайдеры</div>
                            <div class="text-xs text-gray-500">
                                С ≥1 провайдером: <b>{{ $totalConnected }}</b>
                            </div>
                        </div>

                        {{-- “как в profile”, только про сегменты --}}
                        <div class="text-sm text-gray-800 space-y-3">

                            {{-- TG only --}}
                            <div class="flex items-center justify-between gap-3 border-t pt-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    {!! $rowLabel('TG only:') !!}
                                    {!! $chip('TG') !!}
                                    <span class="text-xs text-gray-500">только Telegram</span>
                                </div>
                                <div class="font-extrabold text-gray-900">{{ $p['tg_only'] ?? 0 }}</div>
                            </div>

                            {{-- VK only --}}
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    {!! $rowLabel('VK only:') !!}
                                    {!! $chip('VK') !!}
                                    <span class="text-xs text-gray-500">только VK</span>
                                </div>
                                <div class="font-extrabold text-gray-900">{{ $p['vk_only'] ?? 0 }}</div>
                            </div>

                            {{-- Ya only --}}
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    {!! $rowLabel('Ya only:') !!}
                                    {!! $chip('Ya') !!}
                                    <span class="text-xs text-gray-500">только Yandex</span>
                                </div>
                                <div class="font-extrabold text-gray-900">{{ $p['ya_only'] ?? 0 }}</div>
                            </div>

                            {{-- TG+VK --}}
                            <div class="flex items-center justify-between gap-3 border-t pt-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    {!! $rowLabel('TG+VK:') !!}
                                    {!! $chip('TG') !!}{!! $chip('VK') !!}
                                </div>
                                <div class="font-extrabold text-gray-900">{{ $p['tg_vk'] ?? 0 }}</div>
                            </div>

                            {{-- TG+Ya --}}
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    {!! $rowLabel('TG+Ya:') !!}
                                    {!! $chip('TG') !!}{!! $chip('Ya') !!}
                                </div>
                                <div class="font-extrabold text-gray-900">{{ $p['tg_ya'] ?? 0 }}</div>
                            </div>

                            {{-- Ya+VK --}}
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    {!! $rowLabel('Ya+VK:') !!}
                                    {!! $chip('Ya') !!}{!! $chip('VK') !!}
                                </div>
                                <div class="font-extrabold text-gray-900">{{ $p['ya_vk'] ?? 0 }}</div>
                            </div>

                            {{-- Ya+VK+TG --}}
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    {!! $rowLabel('Ya+VK+TG:') !!}
                                    {!! $chip('Ya') !!}{!! $chip('VK') !!}{!! $chip('TG') !!}
                                </div>
                                <div class="font-extrabold text-gray-900">{{ $p['ya_vk_tg'] ?? 0 }}</div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- RESTRICTIONS (1/3 ширины) --}}
                <div class="v-card">
                    <div class="v-card__body">
                        <div class="text-lg font-semibold mb-2">Блокировки</div>
                        <div class="text-xs text-gray-500 mb-3">
                            Активные: ends_at NULL или ends_at &gt; now()
                        </div>

                        {{-- Event All --}}
                        <div class="flex items-center justify-between border-t py-2 text-sm">
                            <div class="font-mono">Event All</div>
                            <div class="font-extrabold">{{ $eventAllRestrictions ?? 0 }}</div>
                        </div>

                        {{-- By event_id --}}
                        @php($map = $restrictionByEvent ?? [])
                        @if(!empty($map))
                            @foreach($map as $eid => $cnt)
                                <div class="flex items-center justify-between border-t py-2 text-sm">
                                    <div class="font-mono">Event_{{ (int)$eid }}</div>
                                    <div class="font-extrabold">{{ (int)$cnt }}</div>
                                </div>
                            @endforeach
                        @else
                            <div class="border-t py-2 text-xs text-gray-500">
                                Нет активных блокировок по конкретным event_id.
                            </div>
                        @endif

                        <div class="mt-4">
                            <a class="v-btn v-btn--secondary w-full text-center" href="{{ route('admin.users.index') }}">
                                Перейти к пользователям
                            </a>
                        </div>
                    </div>
                </div>

            </div>

            {{-- =========================
                 ROW 4: ROLES + ORGANIZER REQUESTS (как было)
            ========================== --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- Roles --}}
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

                {{-- Organizer requests --}}
                <div class="v-card">
                    <div class="v-card__body">
                        <div class="text-lg font-semibold mb-3">Organizer requests</div>
                        <div class="text-sm text-gray-700">
                            @forelse($organizerRequests as $r)
                                <div class="flex justify-between border-t py-2">
                                    <div class="font-mono">{{ $r->status ?? 'null' }}</div>
                                    <div class="font-semibold">{{ $r->c }}</div>
                                </div>
                            @empty
                                <div class="text-xs text-gray-500">Нет данных (или таблицы organizer_requests).</div>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
