<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin Dashboard</h2>
            <div class="v-actions">
                <a class="v-btn v-btn--primary" href="{{ route('admin.users.index') }}">Пользователи</a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="v-alert v-alert--success"><div class="v-alert__text">{{ session('status') }}</div></div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="v-card"><div class="v-card__body">
                    <div class="text-sm text-gray-500">Всего пользователей</div>
                    <div class="text-3xl font-bold">{{ $totalUsers }}</div>
                </div></div>

                <div class="v-card"><div class="v-card__body">
                    <div class="text-sm text-gray-500">Регистрации</div>
                    <div class="text-2xl font-bold">{{ $registeredToday }}</div>
                    <div class="text-xs text-gray-500 mt-1">сегодня</div>
                    <div class="text-xs text-gray-500 mt-2">7д: <b>{{ $registered7 }}</b> · 30д: <b>{{ $registered30 }}</b></div>
                </div></div>

                <div class="v-card"><div class="v-card__body">
                    <div class="text-sm text-gray-500">Активные</div>
                    <div class="text-sm mt-2">за 7 дней: <b>{{ $active7 }}</b></div>
                    <div class="text-sm">за 30 дней: <b>{{ $active30 }}</b></div>
                    <div class="text-xs text-gray-500 mt-2">метрика: updated_at</div>
                </div></div>

                <div class="v-card"><div class="v-card__body">
                    <div class="text-sm text-gray-500">Провайдеры</div>
                    <div class="text-sm mt-2">TG only: <b>{{ $tgOnly }}</b></div>
                    <div class="text-sm">VK only: <b>{{ $vkOnly }}</b></div>
                    <div class="text-sm">TG+VK: <b>{{ $both }}</b></div>
                    <div class="text-sm">none: <b>{{ $none }}</b></div>
                </div></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="v-card"><div class="v-card__body">
                    <div class="text-lg font-semibold mb-2">Удаления аккаунтов</div>
                    @if (!$hasDeletedAt)
                        <div class="text-sm text-gray-600">
                            В таблице users нет deleted_at → статистика удалений недоступна.
                        </div>
                    @else
                        <div class="text-sm">Сегодня: <b>{{ $deletedToday }}</b></div>
                        <div class="text-sm">За 7 дней: <b>{{ $deleted7 }}</b></div>
                    @endif
                </div></div>

                <div class="v-card"><div class="v-card__body">
                    <div class="text-lg font-semibold mb-2">Аудит</div>

                    <div class="text-sm text-gray-700">
                        Привязки аккаунтов (7д):
                        <b>{{ $linkCount7 === null ? '—' : $linkCount7 }}</b>
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $hasLinkAudits ? 'account_link_audits' : 'таблица отсутствует' }}
                    </div>

                    <div class="mt-3 text-sm text-gray-700">
                        Действия админа (7д):
                        <b>{{ $adminActions7 === null ? '—' : $adminActions7 }}</b>
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $hasAdminAudits ? 'admin_audits' : 'таблица отсутствует' }}
                    </div>

                    {{-- На следующем шаге сделаем admin/audits --}}
                    {{-- <div class="v-actions mt-4">
                        <a class="v-btn v-btn--secondary" href="{{ route('admin.audits.index') }}">Audit log</a>
                    </div> --}}
                </div></div>
            </div>

        </div>
    </div>
</x-app-layout>
