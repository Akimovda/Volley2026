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
                    <a href="{{ route('activity.record') }}" class="btn w-100" style="min-height:44px;font-size:1.7rem">
                        {{ __('activity.record_btn') }}
                    </a>
                </div>
                @endif

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
                        <div class="text-right">
                            @if($session->direction)
                                <span class="badge badge-sm {{ $session->direction === 'beach' ? 'badge-orange' : 'badge-blue' }}">
                                    {{ __('activity.filter_' . $session->direction) }}
                                </span>
                            @endif
                        </div>
                    </div>
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
