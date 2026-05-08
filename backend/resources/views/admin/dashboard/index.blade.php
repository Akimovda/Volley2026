{{-- resources/views/admin/dashboard/index.blade.php --}}
<x-voll-layout body_class="admin-dashboard-page">

    <x-slot name="title">{{ __('admin.dash_title') }}</x-slot>
    <x-slot name="description">{{ __('admin.dash_description') }}</x-slot>
    <x-slot name="canonical">{{ route('admin.dashboard') }}</x-slot>
    <x-slot name="h1">{{ __('admin.dash_title') }}</x-slot>
    <x-slot name="t_description">{{ __('admin.dash_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('admin.breadcrumb_dashboard') }}</span>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>

    <x-slot name="d_description">
        <div data-aos="fade-up" data-aos-delay="250">
            <button class="btn btn-secondary mt-2 ufilter-btn">{{ __('admin.dash_btn_nav') }}</button>
        </div>
    </x-slot>

    <div class="container">

        {{-- НАВИГАЦИЯ --}}
        <div class="users-filter">
            <div class="ramka">
                <div class="row">
                    <div class="col-sm-6 col-lg-3">
                        <nav class="menu-nav">
                            <div class="menu-item-title cd"><span class="menu-text">{{ __('admin.nav_users') }}</span></div>
                            <a href="{{ route('admin.users.index') }}" class="menu-item"><span class="menu-text">{{ __('admin.nav_users_list') }}</span></a>
                            <a href="{{ route('admin.users.index') }}?role=banned" class="menu-item"><span class="menu-text">{{ __('admin.nav_ban_list') }}</span></a>
                            <a href="{{ route('admin.organizer_requests.index') }}" class="menu-item"><span class="menu-text">{{ __('admin.nav_organizer_requests') }}</span></a>
                            <a href="{{ route('admin.impersonate.index') }}" class="menu-item"><span class="menu-text">{{ __('admin.nav_impersonate') }}</span></a>
                        </nav>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <nav class="menu-nav">
                            <div class="menu-item-title cd"><span class="menu-text">{{ __('admin.nav_events') }}</span></div>
                            <a href="{{ route('events.create.event_management') }}" class="menu-item"><span class="menu-text">{{ __('admin.nav_events_mgmt') }}</span></a>
                            <a href="{{ route('events.create') }}" class="menu-item"><span class="menu-text">{{ __('admin.nav_events_create') }}</span></a>
                        </nav>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <nav class="menu-nav">
                            <div class="menu-item-title cd"><span class="menu-text">{{ __('admin.nav_notifications') }}</span></div>
                            <a href="{{ route('admin.notification_templates.index') }}" class="menu-item"><span class="menu-text">{{ __('admin.nav_notification_tpls') }}</span></a>
                            <a href="{{ route('admin.audits.index') }}" class="menu-item"><span class="menu-text">{{ __('admin.nav_audits') }}</span></a>
                        </nav>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <nav class="menu-nav">
                            <div class="menu-item-title cd"><span class="menu-text">{{ __('admin.nav_content') }}</span></div>
                            <a href="{{ route('admin.locations.index') }}" class="menu-item"><span class="menu-text">{{ __('admin.nav_locations') }}</span></a>
                            <a href="{{ route('admin.locations.create') }}" class="menu-item"><span class="menu-text">{{ __('admin.nav_locations_create') }}</span></a>
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
            <h2 class="-mt-05">{{ __('admin.kpi_section') }}</h2>
            <div class="row row2">

                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14 mb-1">{{ __('admin.kpi_total_users') }}</div>
                        <div class="f-40 b-700 cd">{{ number_format($totalUsers) }}</div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14 mb-1">{{ __('admin.kpi_active') }}</div>
                        <div class="f-40 b-700 cs">{{ number_format($activeUsers) }}</div>
                        <div class="f-14 mt-1">{{ __('admin.kpi_deleted_users') }} <strong>{{ $deletedUsers }}</strong></div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14 mb-1">{{ __('admin.kpi_events_count') }}</div>
                        <div class="f-40 b-700 cd">{{ number_format($eventsCount ?? 0) }}</div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card text-center">
                        <div class="f-14 mb-1">{{ __('admin.kpi_blocks') }}</div>
                        <div class="f-40 b-700 red">{{ $eventAllRestrictions ?? 0 }}</div>
                        <div class="f-14 mt-1">{{ __('admin.kpi_event_all_active') }}</div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ПОЛЬЗОВАТЕЛИ ДИНАМИКА --}}
        <div class="ramka">
            <div class="d-flex between fvc mb-2">
                <h2 class="-mt-05 mb-0">{{ __('admin.users_dynamic_section') }}</h2>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">{{ __('admin.open_users_list') }}</a>
            </div>
            <div class="row row2 text-center">
                <div class="col-6 col-md">
                    <div class="card">
                        <div class="f-14">{{ __('admin.kpi_total') }}</div>
                        <div class="f-28 b-700">{{ $totalUsers }}</div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card">
                        <div class="f-14">{{ __('admin.kpi_active') }}</div>
                        <div class="f-28 b-700 cs">{{ $activeUsers }}</div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card">
                        <div class="f-14">{{ __('admin.kpi_deleted') }}</div>
                        <div class="f-28 b-700">{{ $deletedUsers }}</div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card">
                        <div class="f-14">{{ __('admin.kpi_today_registrations') }}</div>
                        <div class="f-28 b-700 cd">{{ $usersCreatedToday }}</div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card">
                        <div class="f-14">{{ __('admin.kpi_today_deletions') }}</div>
                        <div class="f-28 b-700 red">{{ $usersDeletedToday }}</div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <a href="{{ route('admin.users.duplicates') }}" class="card text-center" style="text-decoration:none;display:block;{{ $dupCount > 0 ? 'border-color:#e74c3c' : '' }}">
                        <div class="f-14">{{ __('admin.kpi_duplicates') }}</div>
                        <div class="f-28 b-700 {{ $dupCount > 0 ? 'red' : 'cs' }}">{{ $dupCount }}</div>
                        @if($dupCount > 0)
                        <div class="f-12 red mt-05">{{ __('admin.kpi_duplicates_attention') }}</div>
                        @else
                        <div class="f-12 mt-05" style="opacity:.5">{{ __('admin.kpi_duplicates_none') }}</div>
                        @endif
                    </a>
                </div>
            </div>
        </div>

        {{-- ПРОВАЙДЕРЫ + БЛОКИРОВКИ --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('admin.providers_section') }}</h2>
            <div class="row">

                <div class="col-lg-8">
                    <div class="card">
                        <div class="d-flex between fvc mb-2">
                            <div class="b-600">{{ __('admin.providers_title') }}</div>
                            <div class="f-16">{{ __('admin.providers_total') }} <strong class="cd">{{ $totalConnected }}</strong></div>
                        </div>

                        @php
                        $providerRows = [
                            ['label' => __('admin.p_apple_any'), 'key' => 'apple_any',  'hint' => __('admin.p_hint_apple_any'), 'bold' => true],
                            ['label' => __('admin.p_apple_only'), 'key' => 'apple_only', 'hint' => __('admin.p_hint_apple_only')],
                            ['label' => __('admin.p_google_any'), 'key' => 'google_any', 'hint' => __('admin.p_hint_google_any'), 'bold' => true],
                            ['label' => __('admin.p_tg_only'), 'key' => 'tg_only', 'hint' => __('admin.p_hint_tg_only')],
                            ['label' => __('admin.p_vk_only'), 'key' => 'vk_only', 'hint' => __('admin.p_hint_vk_only')],
                            ['label' => __('admin.p_ya_only'), 'key' => 'ya_only', 'hint' => __('admin.p_hint_ya_only')],
                            ['label' => 'TG + VK',        'key' => 'tg_vk',     'hint' => ''],
                            ['label' => 'TG + Ya',        'key' => 'tg_ya',     'hint' => ''],
                            ['label' => 'Ya + VK',        'key' => 'ya_vk',     'hint' => ''],
                            ['label' => __('admin.p_ya_vk_tg'), 'key' => 'ya_vk_tg', 'hint' => __('admin.p_hint_ya_vk_tg')],
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
                        <div class="b-600 mb-2">{{ __('admin.blocks_title') }}</div>
                        <div class="f-14 mb-2" style="opacity:.6">{{ __('admin.blocks_active_hint') }}</div>

                        <table class="table f-16">
                            <tr>
                                <td class="b-600">{{ __('admin.blocks_event_all') }}</td>
                                <td class="text-right b-600 red">{{ $eventAllRestrictions ?? 0 }}</td>
                            </tr>
                            @php($map = $restrictionByEvent ?? [])
                            @if(!empty($map))
                                @foreach($map as $eid => $cnt)
                                <tr>
                                    <td>{{ __('admin.blocks_event_n', ['id' => (int)$eid]) }}</td>
                                    <td class="text-right b-600">{{ (int)$cnt }}</td>
                                </tr>
                                @endforeach
                            @else
                                <tr><td colspan="2" class="f-14" style="opacity:.6">{{ __('admin.blocks_no_event_specific') }}</td></tr>
                            @endif
                        </table>

                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">{{ __('admin.blocks_to_users') }}</a>

                        <div class="mt-3 pt-3" style="border-top:1px solid #eee">
                            <div class="b-600 mb-2">{{ __('admin.deletion_title') }}</div>
                            <div class="f-14 mb-2" style="opacity:.6">{{ __('admin.deletion_hint') }}</div>
                            <div class="d-flex align-items-center" style="gap:8px;flex-wrap:wrap">
                                <input type="number"
                                       id="deletion-delay"
                                       class="form-control form-control-sm"
                                       style="width:90px"
                                       min="5"
                                       max="3600"
                                       value="{{ $deletionDelay }}">
                                <span class="f-14">{{ __('admin.deletion_seconds') }}</span>
                                <button type="button" class="btn btn-sm btn-primary" id="save-deletion-delay">{{ __('admin.deletion_save') }}</button>
                                <span class="text-success f-14" id="deletion-delay-saved" style="display:none">{{ __('admin.deletion_saved') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- РОЛИ + ЗАЯВКИ --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('admin.roles_section') }}</h2>
            <div class="row">

                <div class="col-md-6">
                    <div class="card">
                        <div class="b-600 mb-2">{{ __('admin.roles_users') }}</div>
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
                            <div class="b-600">{{ __('admin.roles_org_requests') }}</div>
                            <a href="{{ route('admin.organizer_requests.index') }}" class="f-16 cd b-600">{{ __('admin.roles_view_all') }}</a>
                        </div>
                        <table class="table f-16">
                            @forelse($organizerRequests as $r)
                            <tr>
                                <td class="b-600">{{ $r->status ?? '—' }}</td>
                                <td class="text-right b-600 cd">{{ $r->c }}</td>
                            </tr>
                            @empty
                            <tr><td class="f-14" style="opacity:.6">{{ __('admin.no_data') }}</td></tr>
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
                swal({ title: @json(__('admin.deletion_save_error')), icon: 'error', timer: 1500, buttons: false });
            },
            complete: function () {
                btn.disabled = false;
            }
        });
    });
})();
</script>