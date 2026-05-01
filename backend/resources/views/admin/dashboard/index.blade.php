{{-- resources/views/admin/dashboard/index.blade.php --}}
<x-voll-layout body_class="admin-dashboard-page">

    <x-slot name="title">Админ-панель</x-slot>
    <x-slot name="description">Сводка по пользователям, провайдерам, блокировкам и мероприятиям</x-slot>
    <x-slot name="canonical">{{ route('admin.dashboard') }}</x-slot>
    <x-slot name="h1">Админ-панель</x-slot>
    <x-slot name="t_description">Статистика и мониторинг системы</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Админ-панель</span>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>

    <x-slot name="d_description">
        <div data-aos="fade-up" data-aos-delay="250">
            <button class="btn btn-secondary mt-2 ufilter-btn">☰ Навигация</button>
        </div>
    </x-slot>

    <div class="container">

        {{-- НАВИГАЦИЯ --}}
        <div class="users-filter">
            <div class="ramka">
                <div class="row">
                    <div class="col-sm-6 col-lg-3">
                        <nav class="menu-nav">
                            <div class="menu-item-title cd"><span class="menu-text">👥 Пользователи</span></div>
                            <a href="{{ route('admin.users.index') }}" class="menu-item"><span class="menu-text">Список пользователей</span></a>
                            <a href="{{ route('admin.users.index') }}?role=banned" class="menu-item"><span class="menu-text">Бан-список</span></a>
                            <a href="{{ route('admin.organizer_requests.index') }}" class="menu-item"><span class="menu-text">Заявки организаторов</span></a>
                        </nav>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <nav class="menu-nav">
                            <div class="menu-item-title cd"><span class="menu-text">🏐 Мероприятия</span></div>
                            <a href="{{ route('events.create.event_management') }}" class="menu-item"><span class="menu-text">Управление мероприятиями</span></a>
                            <a href="{{ route('events.create') }}" class="menu-item"><span class="menu-text">Создать мероприятие</span></a>
                        </nav>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <nav class="menu-nav">
                            <div class="menu-item-title cd"><span class="menu-text">🔔 Уведомления</span></div>
                            <a href="{{ route('admin.notification_templates.index') }}" class="menu-item"><span class="menu-text">Шаблоны уведомлений</span></a>
                            <a href="{{ route('admin.audits.index') }}" class="menu-item"><span class="menu-text">Журнал аудита</span></a>
                        </nav>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <nav class="menu-nav">
                            <div class="menu-item-title cd"><span class="menu-text">📍 Контент</span></div>
                            <a href="{{ route('admin.locations.index') }}" class="menu-item"><span class="menu-text">Локации</span></a>
                            <a href="{{ route('admin.locations.create') }}" class="menu-item"><span class="menu-text">Создать локацию</span></a>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="ramka">
                <div class="alert alert-success">{{ session('status') }}</div>
            </div>
        @endif

        @php
            $p = $providers ?? [];
            $totalConnected = ($p['tg_only'] ?? 0) + ($p['vk_only'] ?? 0) + ($p['ya_only'] ?? 0)
                + ($p['apple_only'] ?? 0) + ($p['tg_vk'] ?? 0) + ($p['tg_ya'] ?? 0)
                + ($p['ya_vk'] ?? 0) + ($p['ya_vk_tg'] ?? 0);
        @endphp

        {{-- KPI --}}
        <div class="ramka">
            <h2 class="-mt-05">📊 Ключевые показатели</h2>
            <div class="row row2">

                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14 mb-1">Всего пользователей</div>
                        <div class="f-40 b-700 cd">{{ number_format($totalUsers) }}</div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14 mb-1">Активных</div>
                        <div class="f-40 b-700 cs">{{ number_format($activeUsers) }}</div>
                        <div class="f-14 mt-1">Удалённых: <strong>{{ $deletedUsers }}</strong></div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14 mb-1">Мероприятий</div>
                        <div class="f-40 b-700 cd">{{ number_format($eventsCount ?? 0) }}</div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14 mb-1">Блокировок</div>
                        <div class="f-40 b-700 red">{{ $eventAllRestrictions ?? 0 }}</div>
                        <div class="f-14 mt-1">event_all (активных)</div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ПОЛЬЗОВАТЕЛИ ДИНАМИКА --}}
        <div class="ramka">
            <div class="d-flex between fvc mb-2">
                <h2 class="-mt-05 mb-0">👥 Пользователи / динамика</h2>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Открыть список</a>
            </div>
            <div class="row row2 text-center">
                <div class="col-6 col-md">
                    <div class="card">
                        <div class="f-14">Всего</div>
                        <div class="f-28 b-700">{{ $totalUsers }}</div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card">
                        <div class="f-14">Активных</div>
                        <div class="f-28 b-700 cs">{{ $activeUsers }}</div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card">
                        <div class="f-14">Удалённых</div>
                        <div class="f-28 b-700">{{ $deletedUsers }}</div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card">
                        <div class="f-14">Регистраций сегодня</div>
                        <div class="f-28 b-700 cd">{{ $usersCreatedToday }}</div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card">
                        <div class="f-14">Удалений сегодня</div>
                        <div class="f-28 b-700 red">{{ $usersDeletedToday }}</div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <a href="{{ route('admin.users.duplicates') }}" class="card text-center" style="text-decoration:none;display:block;{{ $dupCount > 0 ? 'border-color:#e74c3c' : '' }}">
                        <div class="f-14">👥 Дубли</div>
                        <div class="f-28 b-700 {{ $dupCount > 0 ? 'red' : 'cs' }}">{{ $dupCount }}</div>
                        @if($dupCount > 0)
                        <div class="f-12 red mt-05">Требуют внимания</div>
                        @else
                        <div class="f-12 mt-05" style="opacity:.5">Не найдено</div>
                        @endif
                    </a>
                </div>
            </div>
        </div>

        {{-- ПРОВАЙДЕРЫ + БЛОКИРОВКИ --}}
        <div class="ramka">
            <h2 class="-mt-05">🔗 Провайдеры и блокировки</h2>
            <div class="row">

                <div class="col-lg-8">
                    <div class="card">
                        <div class="d-flex between fvc mb-2">
                            <div class="b-600">Провайдеры авторизации</div>
                            <div class="f-16">С ≥1 провайдером: <strong class="cd">{{ $totalConnected }}</strong></div>
                        </div>

                        @php
                        $providerRows = [
                            ['label' => '🍎 Apple',       'key' => 'apple_any',  'hint' => 'всего с Apple ID', 'bold' => true],
                            ['label' => '🍎 Apple only',  'key' => 'apple_only', 'hint' => 'только Apple'],
                            ['label' => '🤖 TG only',     'key' => 'tg_only',   'hint' => 'только Telegram'],
                            ['label' => '💙 VK only',     'key' => 'vk_only',   'hint' => 'только VK'],
                            ['label' => '🟡 Ya only',     'key' => 'ya_only',   'hint' => 'только Яндекс'],
                            ['label' => 'TG + VK',        'key' => 'tg_vk',     'hint' => ''],
                            ['label' => 'TG + Ya',        'key' => 'tg_ya',     'hint' => ''],
                            ['label' => 'Ya + VK',        'key' => 'ya_vk',     'hint' => ''],
                            ['label' => 'Ya + VK + TG',   'key' => 'ya_vk_tg',  'hint' => 'все три (без Apple)'],
                        ];
                        @endphp

                        <table class="table f-16">
                            @foreach($providerRows as $row)
                            <tr @if(!empty($row['bold'])) style="background:rgba(0,0,0,.03)" @endif>
                                <td class="b-600">{{ $row['label'] }}</td>
                                <td class="f-14" style="opacity:.6">{{ $row['hint'] }}</td>
                                <td class="text-right b-600 cd">{{ $p[$row['key']] ?? 0 }}</td>
                            </tr>
                            @endforeach
                        </table>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="b-600 mb-2">🚫 Блокировки</div>
                        <div class="f-14 mb-2" style="opacity:.6">Активные: ends_at NULL или &gt; now()</div>

                        <table class="table f-16">
                            <tr>
                                <td class="b-600">Event All</td>
                                <td class="text-right b-600 red">{{ $eventAllRestrictions ?? 0 }}</td>
                            </tr>
                            @php($map = $restrictionByEvent ?? [])
                            @if(!empty($map))
                                @foreach($map as $eid => $cnt)
                                <tr>
                                    <td>Event #{{ (int)$eid }}</td>
                                    <td class="text-right b-600">{{ (int)$cnt }}</td>
                                </tr>
                                @endforeach
                            @else
                                <tr><td colspan="2" class="f-14" style="opacity:.6">Нет блокировок по конкретным event_id</td></tr>
                            @endif
                        </table>

                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">К пользователям</a>

                        <div class="mt-3 pt-3" style="border-top:1px solid #eee">
                            <div class="b-600 mb-2">⏱ Удаление аккаунтов</div>
                            <div class="f-14 mb-2" style="opacity:.6">Время на отмену после подтверждения</div>
                            <div class="d-flex align-items-center" style="gap:8px;flex-wrap:wrap">
                                <input type="number"
                                       id="deletion-delay"
                                       class="form-control form-control-sm"
                                       style="width:90px"
                                       min="5"
                                       max="3600"
                                       value="{{ $deletionDelay }}">
                                <span class="f-14">сек</span>
                                <button type="button" class="btn btn-sm btn-primary" id="save-deletion-delay">Сохранить</button>
                                <span class="text-success f-14" id="deletion-delay-saved" style="display:none">✓ Сохранено</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- РОЛИ + ЗАЯВКИ --}}
        <div class="ramka">
            <h2 class="-mt-05">🎭 Роли и заявки</h2>
            <div class="row">

                <div class="col-md-6">
                    <div class="card">
                        <div class="b-600 mb-2">Роли пользователей</div>
                        <table class="table f-16">
                            @foreach($roles as $r)
                            <tr>
                                <td class="b-600 cd">{{ $r->role ?? '—' }}</td>
                                <td class="text-right b-600">{{ $r->c }}</td>
                            </tr>
                            @endforeach
                        </table>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="d-flex between fvc mb-2">
                            <div class="b-600">Заявки организаторов</div>
                            <a href="{{ route('admin.organizer_requests.index') }}" class="f-16 cd b-600">Смотреть все</a>
                        </div>
                        <table class="table f-16">
                            @forelse($organizerRequests as $r)
                            <tr>
                                <td class="b-600">{{ $r->status ?? '—' }}</td>
                                <td class="text-right b-600 cd">{{ $r->c }}</td>
                            </tr>
                            @empty
                            <tr><td class="f-14" style="opacity:.6">Нет данных</td></tr>
                            @endforelse
                        </table>
                    </div>
                </div>

            </div>
        </div>

    </div>

</x-voll-layout>

<script>
(function () {
    var btn = document.getElementById('save-deletion-delay');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var value = document.getElementById('deletion-delay').value;
        btn.disabled = true;
        jQuery.ajax({
            url: '{{ route('admin.settings.deletion_delay') }}',
            method: 'POST',
            data: {
                _token: document.querySelector('meta[name="csrf-token"]').content,
                value: value
            },
            success: function () {
                var saved = document.getElementById('deletion-delay-saved');
                saved.style.display = '';
                setTimeout(function () { saved.style.display = 'none'; }, 2000);
            },
            error: function () {
                swal({ title: 'Ошибка сохранения', icon: 'error', timer: 1500, buttons: false });
            },
            complete: function () {
                btn.disabled = false;
            }
        });
    });
})();
</script>