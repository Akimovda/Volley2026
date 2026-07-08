<x-voll-layout body_class="activity-dashboard-page">
    <x-slot name="style">
    <style>
    :root {
        --act-brand:  #2967BA;
        --act-hr-z0:  #9ca3af;
        --act-hr-z1:  #2967BA;
        --act-hr-z2:  #22c55e;
        --act-hr-z3:  #eab308;
        --act-hr-z4:  #f97316;
        --act-hr-z5:  #ef4444;
    }
    /* Segmented filter — full width, equal 3 segments */
    .act-filter-tabs {
        display: flex !important;
        width: 100%;
        max-width: none !important;
        box-sizing: border-box;
    }
    .act-filter-tabs .tab {
        flex: 1;
        min-width: 0;
        padding: 0.9rem 0.4rem;
        font-size: 1.3rem;
    }
    .act-filter-tabs .tab.active {
        background: var(--act-brand);
        border-radius: 999px;
    }
    body.dark .act-filter-tabs .tab.active {
        background: #E7612F;
    }
    /* Card session title */
    .act-session-title {
        color: var(--act-brand);
        font-weight: 700;
    }
    body.dark .act-session-title {
        color: #FFB171;
    }
    /* HR value colored by zone */
    .act-hr-val { font-weight: 700; }
    </style>
    </x-slot>
    @php $user = auth()->user(); @endphp

    <x-slot name="title">{{ __('activity.dashboard_title') }} — VolleyPlay</x-slot>
    <x-slot name="h1">{{ __('activity.dashboard_title') }}</x-slot>
    <x-slot name="h2">
        @if($user->first_name || $user->last_name)
            {{ trim($user->first_name . ' ' . $user->last_name) }}
        @else
            {{ __('profile.dash_player_user_n', ['id' => $user->id]) }}
        @endif
    </x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">{{ __('profile.show_title') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('activity.dashboard_title') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">
        <div class="row row2">

            {{-- Боковое меню --}}
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka">
                        @include('profile._menu', ['activeMenu' => 'activity'])
                    </div>
                </div>
            </div>

            {{-- Основной контент --}}
            <div class="col-lg-8 col-xl-9 order-1">

                {{-- CTA: Записать тренировку --}}
                @if($canRecord)
                <div class="mb-2">
                    <button id="btn-record-activity" class="btn w-100" style="min-height:44px;font-size:1.7rem"
                        data-preferred-type="{{ $preferredDeviceType ?? '' }}"
                        data-preferred-device-id="{{ $preferredDevice?->id ?? '' }}"
                        data-preferred-ble-id="{{ $preferredDevice?->ble_identifier ?? '' }}"
                        data-record-url="{{ route('activity.record') }}">
                        {{ __('activity.record_btn') }}
                    </button>
                </div>
                @endif

                {{-- Импорт из HealthKit / Health Connect --}}
                <div id="healthkit-import-section" style="display:none" class="mb-2">
                    <button id="btn-import-healthkit" class="btn btn-outline-secondary w-100"
                            style="min-height:44px">
                        📲 {{ __('activity.import_from_health') }}
                    </button>
                    <div id="healthkit-import-status" style="display:none" class="mt-1">
                        <div class="alert mb-0" id="healthkit-import-message">
                            {{ __('activity.import_loading') }}
                        </div>
                    </div>
                </div>

                {{-- Сводка --}}
                <div class="ramka">
                    <div class="row">
                        <div class="col-6 col-md-4 text-center">
                            <div class="f-13" style="opacity:.65">{{ __('activity.total_sessions') }}</div>
                            <div class="b-600 cd" style="font-size:2rem">{{ $totalCount }}</div>
                        </div>
                        @if($lastSession)
                        <div class="col-6 col-md-4 text-center">
                            <div class="f-13" style="opacity:.65">{{ __('activity.last_load') }}</div>
                            <div class="b-600 cd" style="font-size:2rem">
                                {{ $lastSession->load_score ? number_format($lastSession->load_score, 0) : '—' }}
                            </div>
                        </div>
                        <div class="col-12 col-md-4 text-center mt-1 mt-md-0">
                            <div class="f-13" style="opacity:.65">{{ __('activity.last_date') }}</div>
                            <div class="b-600 cd">{{ $lastSession->started_at?->setTimezone($userTimezone)->format('d.m.Y') ?? '—' }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Фильтр --}}
                <div class="mb-2 mt-1">
                    <div class="tabs w-100 act-filter-tabs">
                        <a href="{{ route('activity.index', ['direction' => 'all']) }}"
                           class="tab {{ $direction === 'all' ? 'active' : '' }}">{{ __('activity.filter_all') }}</a>
                        <a href="{{ route('activity.index', ['direction' => 'classic']) }}"
                           class="tab {{ $direction === 'classic' ? 'active' : '' }}">{{ __('activity.filter_classic') }}</a>
                        <a href="{{ route('activity.index', ['direction' => 'beach']) }}"
                           class="tab {{ $direction === 'beach' ? 'active' : '' }}">{{ __('activity.filter_beach') }}</a>
                        <div class="tab-highlight"></div>
                    </div>
                </div>

                {{-- Список сессий --}}
                @php
                $hrZoneColors = ['#9ca3af','#2967BA','#22c55e','#eab308','#f97316','#ef4444'];
                $hrColorFn = function(?int $bpm) use ($zoneThresholds, $hrZoneColors): string {
                    if (!$bpm) return $hrZoneColors[0];
                    if ($zoneThresholds) {
                        $zone = 0;
                        for ($z = 5; $z >= 1; $z--) {
                            if ($bpm >= $zoneThresholds["z{$z}"]['low']) { $zone = $z; break; }
                        }
                    } else {
                        if ($bpm >= 160)     $zone = 5;
                        elseif ($bpm >= 140) $zone = 4;
                        elseif ($bpm >= 120) $zone = 3;
                        elseif ($bpm >= 100) $zone = 2;
                        else                 $zone = 1;
                    }
                    return $hrZoneColors[$zone];
                };
                @endphp
                @forelse($sessions as $session)
                @php
                    $hasJumps = is_array($session->tracked_capabilities) && in_array('jumps', $session->tracked_capabilities);
                    $title = $session->occurrence?->event?->title ?? __('activity.session_free_training');
                    $syncStatus = $session->sync_status;
                    $dur = $session->duration_sec ?? 0;
                    $durStr = $dur >= 3600
                        ? sprintf('%d:%02d:%02d', intdiv($dur, 3600), intdiv($dur % 3600, 60), $dur % 60)
                        : sprintf('%d:%02d', intdiv($dur, 60), $dur % 60);
                @endphp
                <a href="{{ route('activity.show', $session) }}" class="ramka mb-1" style="display:block;text-decoration:none;color:inherit">
                    <div class="d-flex justify-between align-center">
                        <div>
                            <div class="act-session-title">{{ $title }}</div>
                            <div class="f-13" style="opacity:.6">{{ $session->started_at?->setTimezone($userTimezone)->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="text-right" style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
                            @if($syncStatus === 'pending')
                                <span class="badge badge-sm" style="background:rgba(41,103,186,.15);color:#2967BA">⏳ {{ __('activity.sync_pending') }}</span>
                            @elseif($syncStatus === 'stale')
                                <span class="badge badge-sm" style="background:rgba(239,68,68,.15);color:#ef4444">{{ __('activity.sync_stale') }}</span>
                            @endif
                            @if($session->direction)
                                <span class="badge badge-sm {{ $session->direction === 'beach' ? 'badge-orange' : 'badge-blue' }}">
                                    {{ __('activity.filter_' . $session->direction) }}
                                </span>
                            @endif
                            @if(($session->source ?? 'watch') === 'healthkit_import')
                                <span class="badge badge-sm" style="background:rgba(120,120,128,.18);color:inherit">📲 {{ $session->source_name }}</span>
                            @elseif(($session->source ?? 'watch') === 'ble')
                                <span class="badge badge-sm badge-blue" style="opacity:.75">📡 BLE</span>
                            @endif
                        </div>
                    </div>
                    @if($syncStatus === 'completed')
                    <div class="row mt-1" style="gap:4px 0">
                        <div class="col-6 col-md-3">
                            <div class="f-13" style="opacity:.6">{{ __('activity.duration') }}</div>
                            <div class="b-600">{{ $durStr }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="f-13" style="opacity:.6">{{ __('activity.avg_hr') }}</div>
                            <div class="b-600">
                                @if($session->avg_hr)
                                    <span class="act-hr-val" style="color:{{ $hrColorFn($session->avg_hr) }}">{{ $session->avg_hr }}</span>
                                    <span style="opacity:.75;font-weight:400;font-size:.9em"> {{ __('activity.live_bpm') }}</span>
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="f-13" style="opacity:.6">{{ __('activity.load_score') }}</div>
                            <div class="b-600">{{ $session->load_score ? number_format($session->load_score, 0) : '—' }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            @if($session->calories_kcal)
                                <div class="f-13" style="opacity:.6">{{ __('activity.calories') }}</div>
                                <div class="b-600">≈{{ number_format($session->calories_kcal, 0) }} ккал</div>
                            @elseif($hasJumps)
                                <div class="f-13" style="opacity:.6">{{ __('activity.jumps_count') }}</div>
                                <div class="b-600">{{ $session->jump_count ?? 0 }}</div>
                            @endif
                        </div>
                    </div>
                    @else
                    <div class="f-13 mt-1" style="opacity:.6">
                        {{ $syncStatus === 'pending' ? __('activity.sync_pending_hint') : __('activity.sync_stale_hint') }}
                    </div>
                    @endif
                </a>
                @empty
                <div class="ramka text-center" style="opacity:.6">
                    <p>{{ __('activity.no_sessions') }}</p>
                </div>
                @endforelse

                {{-- Пагинация --}}
                @if($sessions->hasPages())
                <div class="mt-2">
                    {{ $sessions->links() }}
                </div>
                @endif

            </div>{{-- col-lg-8 --}}
        </div>{{-- row --}}
    </div>{{-- container --}}
</x-voll-layout>
<script>
document.getElementById('btn-record-activity')?.addEventListener('click', async function (e) {
    e.preventDefault();
    var btn       = e.currentTarget;
    var type      = btn.dataset.preferredType;
    var recordUrl = btn.dataset.recordUrl;

    if (!type) {
        window.location.href = recordUrl;
        return;
    }

    if (type === 'healthkit') {
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.ActivityBridge) {
            try {
                await window.Capacitor.Plugins.ActivityBridge.startWatchRecording({});
                window.location.href = recordUrl + '?started=watch';
            } catch (err) {
                console.error('[QuickStart] Watch start failed:', err);
                window.location.href = recordUrl;
            }
        } else {
            window.location.href = recordUrl;
        }
        return;
    }

    if (type === 'ble') {
        var deviceId = btn.dataset.preferredDeviceId;
        window.location.href = recordUrl + '?quick_start_device_id=' + deviceId;
        return;
    }

    window.location.href = recordUrl;
});

// ── HealthKit / Health Connect import ────────────────────────────────────────
if (window.Capacitor) {
    var importSection = document.getElementById('healthkit-import-section');
    if (importSection) importSection.style.display = '';

    var importBtn = document.getElementById('btn-import-healthkit');
    if (importBtn && Capacitor.getPlatform() === 'android') {
        importBtn.textContent = '📲 ' + @json(__('activity.import_from_health_connect'));
    }
}

document.getElementById('btn-import-healthkit')?.addEventListener('click', async function () {
    var statusDiv = document.getElementById('healthkit-import-status');
    var messageEl = document.getElementById('healthkit-import-message');
    var btn       = this;

    btn.disabled = true;
    statusDiv.style.display = '';
    messageEl.className = 'alert alert-info mb-0';
    messageEl.textContent = @json(__('activity.import_loading'));

    try {
        var platform = Capacitor.getPlatform();
        var workouts = [];

        if (platform === 'ios') {
            await window.Capacitor.Plugins.ActivityBridge.requestHealthKitPermissions();
            var res = await window.Capacitor.Plugins.ActivityBridge.getHealthKitWorkouts({ daysBack: 30 });
            workouts = res.workouts ?? [];
        } else if (platform === 'android') {
            var permResult = await window.Capacitor.Plugins.ActivityBridge.requestHealthConnectPermissions();
            if (!permResult.granted) {
                messageEl.className = 'alert alert-warning mb-0';
                messageEl.textContent = @json(__('activity.import_permissions'));
                btn.disabled = false;
                return;
            }
            var res = await window.Capacitor.Plugins.ActivityBridge.getHealthConnectWorkouts({ daysBack: 30 });
            workouts = res.workouts ?? [];
        } else {
            throw new Error('Platform not supported');
        }

        if (!workouts.length) {
            messageEl.className = 'alert alert-warning mb-0';
            messageEl.textContent = @json(__('activity.import_no_workouts'));
            btn.disabled = false;
            return;
        }

        var response = await fetch('/api/activity/import/healthkit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ workouts: workouts }),
        });

        if (!response.ok) throw new Error('Server error: ' + response.status);

        var data = await response.json();

        messageEl.className = 'alert alert-success mb-0';
        var msg = @json(__('activity.import_done', ['count' => ':count'])).replace(':count', data.imported);
        if (data.skipped > 0) {
            msg += ' ' + @json(__('activity.import_skipped', ['count' => ':count'])).replace(':count', data.skipped);
        }
        messageEl.textContent = msg;

        if (data.imported > 0) {
            setTimeout(function () { location.reload(); }, 2000);
        }

    } catch (err) {
        console.error('[Health import]', err);
        const code = err.errorMessage ?? err.code ?? '';
        const msg = err.message ?? '';

        if (code === 'health_connect_unavailable') {
            // Health Connect не установлен (Android < 14)
            messageEl.className = 'alert alert-warning mb-0';
            messageEl.textContent = @json(__('activity.import_hc_not_installed'));
        } else if (code === 'health_connect_error' || code === 'health_connect_query_failed') {
            // Техническая ошибка Health Connect
            messageEl.className = 'alert alert-danger mb-0';
            messageEl.textContent = @json(__('activity.import_error'));
        } else if (msg.includes('Server error: 5')) {
            messageEl.className = 'alert alert-danger mb-0';
            messageEl.textContent = @json(__('activity.import_server_error'));
        } else if (msg.includes('permission') || msg.includes('denied')) {
            // iOS HealthKit отказ
            messageEl.className = 'alert alert-warning mb-0';
            messageEl.textContent = @json(__('activity.import_permissions'));
        } else if (msg.includes('cancelled') || msg.includes('canceled')) {
            messageEl.className = 'alert alert-info mb-0';
            messageEl.textContent = @json(__('activity.import_cancelled'));
        } else {
            messageEl.className = 'alert alert-danger mb-0';
            messageEl.textContent = @json(__('activity.import_error'));
        }
        btn.disabled = false;
    }
});
</script>
