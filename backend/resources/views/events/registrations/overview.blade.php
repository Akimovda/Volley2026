{{-- resources/views/events/registrations/overview.blade.php --}}
@php
$fmtDate = function ($startsAt, $tz) {
    if (!$startsAt) return '—';
    $tz = $tz ?: 'UTC';
    $dt = \Carbon\Carbon::parse($startsAt, 'UTC')->setTimezone($tz);
    $days = ['Mon' => 'Пн', 'Tue' => 'Вт', 'Wed' => 'Ср', 'Thu' => 'Чт', 'Fri' => 'Пт', 'Sat' => 'Сб', 'Sun' => 'Вс'];
    $dow = $days[$dt->format('D')] ?? $dt->format('D');
    return $dt->translatedFormat('j M') . ', ' . $dow . ' · ' . $dt->format('H:i');
};

$fmtRegs = function ($row) {
    $isTournament = ($row->format ?? '') === 'tournament';
    if ($isTournament) {
        $registered = (int) $row->active_teams;
        $max        = (int) ($row->tournament_teams_count ?? 0);
    } else {
        $registered = (int) $row->active_regs;
        $max        = (int) $row->max_players;
        if (!(bool) $row->allow_registration) return ['str' => '—', 'full' => false];
    }
    $str  = $max > 0 ? "{$registered}/{$max}" : "{$registered}/—";
    $full = $max > 0 && $registered >= $max;
    return compact('str', 'full');
};

$fmtAddress = function ($row) {
    return trim((string)($row->loc_address ?? '')) ?: trim((string)($row->loc_name ?? '')) ?: '—';
};

$sortOptions = [
    'date'    => __('events.overview_sort_date'),
    'title'   => __('events.overview_sort_title'),
    'address' => __('events.overview_sort_address'),
];

$toggleDir = $dir === 'asc' ? 'desc' : 'asc';
$baseQuery  = request()->except(['dir', 'page']);
@endphp

<x-voll-layout body_class="regs-overview-page">

    <x-slot name="title">{{ __('events.registrations_manage') }}</x-slot>
    <x-slot name="h1">{{ __('events.registrations_manage') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.create.event_management') }}" itemprop="item">
                <span itemprop="name">{{ __('events.mgmt_breadcrumb') }}</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('events.registrations_manage') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">
    <div class="row row2">
        <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
            <div class="sticky">
                <div class="card-ramka">
                    @include('profile._menu', [
                        'menuUser'   => auth()->user(),
                        'activeMenu' => 'regs_manage',
                    ])
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-xl-9 order-1">

        {{-- Фильтры --}}
        <form method="GET" action="{{ route('events.registrations.manage') }}" class="mb-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">

                {{-- Сортировка --}}
                <div class="d-flex align-items-center gap-1">
                    <label class="f-14 text-muted mb-0">{{ __('events.overview_sort_label') }}:</label>
                    <select name="sort" class="form-input form-input--small" style="max-width:160px;" onchange="this.form.submit()">
                        @foreach($sortOptions as $val => $label)
                            <option value="{{ $val }}" @selected($sort === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Направление ↑↓ --}}
                <a href="{{ request()->fullUrlWithQuery(array_merge($baseQuery, ['dir' => $toggleDir, 'page' => 1])) }}"
                   class="btn btn-small btn-secondary"
                   title="{{ $dir === 'asc' ? __('events.overview_dir_asc') : __('events.overview_dir_desc') }}">
                    {{ $dir === 'asc' ? '↑' : '↓' }}
                </a>

                {{-- Прошедшие --}}
                <label class="d-flex align-items-center gap-1 f-14 mb-0" style="cursor:pointer;">
                    <input type="checkbox" name="past" value="1"
                           @checked($showPast)
                           onchange="this.form.submit()">
                    {{ __('events.overview_show_past') }}
                </label>

                {{-- Скрытые поля чтобы сохранить остальные параметры --}}
                <input type="hidden" name="dir" value="{{ $dir }}">
            </div>
        </form>

        @if($occurrences->isEmpty())
            <div class="ramka">
                <p class="text-muted f-15 mb-0">{{ __('events.overview_empty') }}</p>
            </div>
        @else

        {{-- Таблица (десктоп) --}}
        <div class="ramka d-none d-md-block" style="overflow-x:auto;">
            <table class="table w-100" style="min-width:600px;">
                <thead>
                    <tr>
                        <th class="f-13 text-muted" style="white-space:nowrap;">{{ __('events.overview_col_date') }}</th>
                        <th class="f-13 text-muted">{{ __('events.overview_col_title') }}</th>
                        <th class="f-13 text-muted">{{ __('events.overview_col_address') }}</th>
                        <th class="f-13 text-muted text-center" style="white-space:nowrap;">{{ __('events.overview_col_regs') }}</th>
                        <th style="width:44px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($occurrences as $row)
                    @php
                        $regsInfo  = $fmtRegs($row);
                        $regsStr   = $regsInfo['str'];
                        $regsClass = $regsInfo['full'] ? 'text-danger b-600' : '';
                    @endphp
                    <tr>
                        <td class="f-14" style="white-space:nowrap;">{{ $fmtDate($row->starts_at, $row->timezone) }}</td>
                        <td class="f-14">
                            <a href="{{ url('/events/' . (int)$row->event_id) }}" class="link-primary">
                                {{ $row->title }}
                            </a>
                        </td>
                        <td class="f-14 text-muted">{{ $fmtAddress($row) }}</td>
                        <td class="f-14 text-center {{ $regsClass }}">{{ $regsStr }}</td>
                        <td class="text-center">
                            <a href="{{ url('/events/' . (int)$row->event_id . '/registrations') }}"
                               class="btn btn-small btn-secondary p-0 d-inline-flex align-items-center justify-content-center"
                               style="width:36px;height:36px;"
                               title="{{ __('events.overview_btn_manage') }}">
                                <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor">
                                    <circle cx="332.8" cy="166.4" r="128"/>
                                    <path d="M332.8,320c-99.84,0-179.2,79.36-179.2,179.2H512C512,399.36,432.64,320,332.8,320z"/>
                                    <path d="M215.04,33.28c-17.92-12.8-38.4-20.48-61.44-20.48c-56.32,0-102.4,46.08-102.4,102.4s46.08,102.4,102.4,102.4c2.56,0,5.12,0,7.68,0c-5.12-15.36-7.68-33.28-7.68-51.2C153.6,112.64,176.64,64,215.04,33.28z"/>
                                    <path d="M171.52,243.2c-5.12,0-12.8,0-17.92,0C69.12,243.2,0,312.32,0,396.8h128c17.92-38.4,48.64-71.68,87.04-94.72C197.12,286.72,181.76,266.24,171.52,243.2z"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Карточки (мобильный) --}}
        <div class="d-md-none">
            @foreach($occurrences as $row)
            @php
                $regsInfo  = $fmtRegs($row);
                $regsStr   = $regsInfo['str'];
                $regsClass = $regsInfo['full'] ? 'text-danger b-600' : '';
            @endphp
            <div class="ramka mb-2" style="padding:12px 14px;">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div style="flex:1;min-width:0;">
                        <div class="f-13 text-muted mb-1" style="white-space:nowrap;">{{ $fmtDate($row->starts_at, $row->timezone) }}</div>
                        <div class="f-15 b-600 mb-1">
                            <a href="{{ url('/events/' . (int)$row->event_id) }}" class="link-primary">
                                {{ $row->title }}
                            </a>
                        </div>
                        @if($fmtAddress($row) !== '—')
                        <div class="f-13 text-muted">📍 {{ $fmtAddress($row) }}</div>
                        @endif
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1" style="flex-shrink:0;">
                        <span class="f-14 {{ $regsClass }}">{{ $regsStr }}</span>
                        <a href="{{ url('/events/' . (int)$row->event_id . '/registrations') }}"
                           class="btn btn-small btn-secondary p-0 d-inline-flex align-items-center justify-content-center"
                           style="width:36px;height:36px;"
                           title="{{ __('events.overview_btn_manage') }}">
                            <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor">
                                <circle cx="332.8" cy="166.4" r="128"/>
                                <path d="M332.8,320c-99.84,0-179.2,79.36-179.2,179.2H512C512,399.36,432.64,320,332.8,320z"/>
                                <path d="M215.04,33.28c-17.92-12.8-38.4-20.48-61.44-20.48c-56.32,0-102.4,46.08-102.4,102.4s46.08,102.4,102.4,102.4c2.56,0,5.12,0,7.68,0c-5.12-15.36-7.68-33.28-7.68-51.2C153.6,112.64,176.64,64,215.04,33.28z"/>
                                <path d="M171.52,243.2c-5.12,0-12.8,0-17.92,0C69.12,243.2,0,312.32,0,396.8h128c17.92-38.4,48.64-71.68,87.04-94.72C197.12,286.72,181.76,266.24,171.52,243.2z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Пагинация --}}
        @if($occurrences->hasPages())
        <div class="mt-3 d-flex justify-content-center">
            {{ $occurrences->links() }}
        </div>
        @endif

        @endif

        </div>{{-- col-lg-8 --}}
    </div>{{-- row --}}
    </div>{{-- container --}}

</x-voll-layout>
