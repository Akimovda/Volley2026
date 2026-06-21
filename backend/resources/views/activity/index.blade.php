<x-voll-layout body_class="activity-dashboard-page">
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
                            <div class="b-600 cd">{{ $lastSession->started_at?->format('d.m.Y') ?? '—' }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Фильтр --}}
                <div class="mb-2 mt-1">
                    <div class="tabs w-100" style="max-width:360px">
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
                            <div class="b-600">{{ $title }}</div>
                            <div class="f-13" style="opacity:.6">{{ $session->started_at?->format('d.m.Y H:i') }}</div>
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
                            <div class="b-600">{{ $session->avg_hr ? $session->avg_hr . ' ' . __('activity.live_bpm') : '—' }}</div>
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
