{{-- resources/views/events/event_management_edit.blade.php --}}
@php
    $isAdmin = (auth()->user()?->role ?? null) === 'admin';
    $remMin = (int) old('remind_registration_minutes_before', $event->remind_registration_minutes_before ?? 600);
    if ($remMin < 0) $remMin = 600;

    // Вычисляем реальные значения окна регистрации из сохранённых UTC-меток
    $evStartsAt = $event->starts_at ? \Carbon\Carbon::parse($event->starts_at, 'UTC') : null;
    $regStartsDaysSaved  = 3;
    $regStartsHoursSaved = 0;
    $regEndsMinSaved     = 15;
    $cancelMinSaved      = 60;

    if ($evStartsAt && $event->registration_starts_at) {
        $regStartsTs = \Carbon\Carbon::parse($event->registration_starts_at, 'UTC');
        $diffSec = $evStartsAt->timestamp - $regStartsTs->timestamp;
        if ($diffSec > 0) {
            $regStartsDaysSaved  = (int) floor($diffSec / 86400);
            $regStartsHoursSaved = (int) floor(($diffSec % 86400) / 3600);
        }
    }
    if ($evStartsAt && $event->registration_ends_at) {
        $regEndsMinSaved = (int) abs($evStartsAt->diffInMinutes(\Carbon\Carbon::parse($event->registration_ends_at, 'UTC')));
    }
    if ($evStartsAt && $event->cancel_self_until) {
        $cancelMinSaved = (int) abs($evStartsAt->diffInMinutes(\Carbon\Carbon::parse($event->cancel_self_until, 'UTC')));
    }

    $cancelWaitlistMinSaved = 0;
    if ($evStartsAt && $event->cancel_self_until_waitlist) {
        $cancelWaitlistMinSaved = (int) abs($evStartsAt->diffInMinutes(\Carbon\Carbon::parse($event->cancel_self_until_waitlist, 'UTC')));
    }

    $regEndsMinCurrent = (int) old('reg_ends_minutes_before', $regEndsMinSaved);
    $regEndsHours      = (int) floor($regEndsMinCurrent / 60);
    $regEndsMins       = $regEndsMinCurrent % 60;

    $cancelMinCurrent = (int) old('cancel_lock_minutes_before', $cancelMinSaved);
    $cancelHours      = (int) floor($cancelMinCurrent / 60);
    $cancelMins       = $cancelMinCurrent % 60;

    $cancelWaitlistMinCurrent = (int) old('cancel_lock_waitlist_minutes_before', $cancelWaitlistMinSaved);
    $cancelWaitlistHours      = (int) floor($cancelWaitlistMinCurrent / 60);
    $cancelWaitlistMins       = $cancelWaitlistMinCurrent % 60;
@endphp

<x-voll-layout>

    <x-slot name="title">{{ __('events.edit_title', ['id' => (int)$event->id]) }}</x-slot>
    <x-slot name="h1">{{ __('events.edit_title_h1') }}</x-slot>
    <x-slot name="h2">{{ $event->title }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.create.event_management') }}" itemprop="item">
                <span itemprop="name">{{ __('events.mgmt_breadcrumb') }}</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ url('/events/' . (int)$event->id) }}" itemprop="item">
                <span itemprop="name">#{{ (int)$event->id }} {{ $event->title }}</span>
            </a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('events.edit_breadcrumb') }}</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    <x-slot name="t_description">
        {{ __('events.edit_active_regs') }} <strong>{{ (int)$activeRegs }}</strong>.
        {{ __('events.edit_active_note') }}
    </x-slot>

    <x-slot name="style">
        <link href="/assets/org.css" rel="stylesheet">
    </x-slot>

    <div class="container">

        {{-- FLASH / ERRORS --}}
        @if (session('status'))
            <div class="ramka">

@if(($event->format ?? '') === 'tournament')
<div class="alert alert-info mb-4 d-flex justify-content-between align-items-center">
    <div>
        <strong>{{ __('events.tournament_redirect_title') }}</strong> {{ __('events.tournament_redirect_text') }}
    </div>
    <a href="{{ route('tournament.setup', $event) }}" class="btn btn-primary btn-sm">
        {{ __('events.tournament_redirect_btn') }}
    </a>
</div>
@endif

                <div class="alert alert-success">{{ session('status') }}</div>
            </div>
        @endif
        @if (session('error'))
            <div class="ramka">
                <div class="alert alert-error">{{ session('error') }}</div>
            </div>
        @endif
        @if ($errors->any())
            <div class="ramka">
                <div class="alert alert-error">
                    <div class="alert-title">{{ __('events.edit_check_fields') }}</div>
                    <ul class="list">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="form">
            <form method="POST"
                  action="{{ route('events.event_management.update', ['event' => (int)$event->id]) }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @if(($event->format ?? '') === 'tournament' && !empty($seasonInfo))
                {{-- ===== БЛОК 0: Серия турниров (read-only) ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('events.season_title') }}</h2>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <label>{{ __('events.season_league_label') }}</label>
                                <div class="b-600 f-15">
                                    @if(!empty($seasonInfo['league_url']))
                                        <a href="{{ $seasonInfo['league_url'] }}" class="link">{{ $seasonInfo['league_name'] ?? '—' }}</a>
                                    @else
                                        {{ $seasonInfo['league_name'] ?? '—' }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <label>{{ __('events.season_label') }}</label>
                                <div class="b-600 f-15">
                                    @if(!empty($seasonInfo['season_url']))
                                        <a href="{{ $seasonInfo['season_url'] }}" class="link">{{ $seasonInfo['season_name'] ?? '—' }}</a>
                                    @else
                                        {{ $seasonInfo['season_name'] ?? '—' }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <label>{{ __('events.division_label') }}</label>
                                <div class="b-600 f-15">{{ $seasonInfo['division_name'] ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <ul class="list f-13 mt-1" style="opacity:.7">
                        <li>{{ __('events.edit_season_readonly_hint') ?? 'Привязка к лиге, сезону и дивизиону задаётся при создании турнира и здесь не меняется.' }}</li>
                    </ul>
                </div>
                @endif

                {{-- ===== БЛОК 1: Основные настройки ===== --}}
                <div class="ramka"  style="z-index: 6">
                    <h2 class="-mt-05">{{ __('events.main_settings') }}</h2>
                    <div class="row">

                        <div class="col-md-6">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.event_title') }}</label>
                                <input
                                    name="title"
                                    value="{{ old('title', (string)$event->title) }}"
                                    placeholder="{{ __('events.event_title') }}"
                                    required
                                >
                                @error('title')
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.direction') }}</label>
                                <select name="direction" id="direction_edit">
                                    <option value="classic" @selected(old('direction', $event->direction) === 'classic')>{{ __('events.card_dir_classic') }}</option>
                                    <option value="beach" @selected(old('direction', $event->direction) === 'beach')>{{ __('events.card_dir_beach') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.event_type') }}</label>
                                <select name="format">
                                    @foreach([
                                        'game' => __('events.fmt_game'),
                                        'training' => __('events.fmt_training'),
                                        'training_game' => __('events.fmt_training_game'),
                                        'coach_student' => __('events.fmt_coach_student'),
                                        'tournament' => __('events.fmt_tournament'),
                                        'camp' => __('events.fmt_camp_caps'),
                                    ] as $k => $label)
                                        <option value="{{ $k }}" @selected(old('format', $event->format) === $k)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.starts_utc') }}</label>
                                <input
                                    name="starts_at"
                                    type="datetime-local"
                                    value="{{ old('starts_at', $event->starts_at ? $event->starts_at->copy()->setTimezone($event->timezone ?? 'UTC')->format('Y-m-d\TH:i') : '') }}"
                                    required
                                >
                                @error('starts_at')
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.duration_label_short') }}</label>
                                <div class="row row2">
                                    <div class="col-4">
                                        <label>{{ __('events.duration_h') }}</label>
                                        <select name="duration_hours">
                                            @for($h = 0; $h <= 23; $h++)
                                                <option value="{{ $h }}" @selected((old('duration_hours', $event->duration_sec ? (int)floor($event->duration_sec / 3600) : 0)) == $h)>{{ $h }}</option>
                                            @endfor
                                        </select>
                                            
                                    </div>
                                    <div class="col-4">
                                        <label>{{ __('events.duration_m') }}</label>
                                        <select name="duration_minutes">
                                            @foreach([0,15,30,45] as $m)
                                                <option value="{{ $m }}" @selected((old('duration_minutes', $event->duration_sec ? (int)(($event->duration_sec % 3600) / 60) : 0)) == $m)>{{ $m }}</option>
                                            @endforeach
                                        </select>
                                            
                                    </div>
                                </div>
                                <input type="hidden" name="duration_sec" id="duration_sec_edit"
                                    value="{{ old('duration_sec', $event->duration_sec ?? 0) }}">
                            </div>
                        </div>

                        <input type="hidden" name="timezone" id="mgmt_timezone_hidden"
                            value="{{ old('timezone', (string)$event->timezone) }}">

                    </div>
                </div>

                {{-- ===== БЛОК 2: Локация ===== --}}
                <div class="ramka" style="z-index: 5">
                    <h2 class="-mt-05">{{ __('events.location_section') }}</h2>
                    <div class="row">

                        {{-- Город --}}
                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.city_label') }}</label>
                                <div id="edit-city-ac-wrap" style="position:relative"
                                    data-search-url="{{ route('cities.search') }}"
                                    data-locations-url="{{ route('ajax.locations.byCity') }}"
                                    data-city-meta-url="{{ route('ajax.cities.meta') }}"
                                >
                                    @php
                                        $cityLabel = '';
                                        if ($currentCity) {
                                            $parts = array_filter([$currentCity->country_code ?? null, $currentCity->region ?? null]);
                                            $cityLabel = $currentCity->name . ($parts ? ' (' . implode(', ', $parts) . ')' : '');
                                        }
                                    @endphp
                                    <input type="text" id="edit_city_q" autocomplete="off"
                                        class="form-control"
                                        value="{{ $cityLabel }}"
                                        placeholder="{{ __('events.city_search_ph') }}">
                                    <div id="edit_city_dd" class="form-select-dropdown trainer_dd"></div>
                                </div>
                                <input type="hidden" id="edit_city_id" value="{{ $currentCity?->id ?? '' }}">
                                <div id="edit_tz_label" class="f-16 mt-1" style="opacity:.65">
                                    @if($event->timezone){{ $event->timezone }}@endif
                                </div>
                            </div>
                        </div>

                        {{-- Локация --}}
                        <div class="col-md-8">
                            <div class="card" style="overflow:visible">
                                <div class="d-flex between">
                                    <label>{{ __('events.location_label') }}</label>
                                    @if($isAdmin)
                                        <a href="{{ route('admin.locations.create') }}"
                                           class="f-16 cd b-600">{{ __('events.location_create_btn') }}</a>
                                    @endif
                                </div>

                                <select name="location_id" id="location_id_edit" class="w-full rounded-lg border-gray-200" required>
                                    <option value="">{{ __('events.tournament_choose') }}</option>
                                    @foreach(($locations ?? []) as $loc)
                                        @php
                                            $thumb = $loc->getFirstMediaUrl('photos', 'thumb');
                                            if (empty($thumb)) $thumb = $loc->getFirstMediaUrl('photos');
                                        @endphp
                                        <option
                                            value="{{ (int)$loc->id }}"
                                            @selected((int)old('location_id', (int)$event->location_id) === (int)$loc->id)
                                            data-name="{{ e((string)$loc->name) }}"
                                            data-city="{{ e((string)($loc->city?->name ?? '')) }}"
                                            data-address="{{ e((string)($loc->address ?? '')) }}"
                                            data-lat="{{ $loc->lat ?? '' }}"
                                            data-lng="{{ $loc->lng ?? '' }}"
                                            data-thumb="{{ e((string)$thumb) }}"
                                        >
                                            {{ $loc->name }}@if(!empty($loc->address)) — {{ $loc->address }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('location_id')
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                                @if(!$isAdmin)
                                <ul class="list f-16 mt-1">
                                    <li>{{ __('events.location_admin_only_hint') }}</li>
                                </ul>
                                @endif

                                {{-- preview --}}
                                <div id="location_preview_edit" class="mt-2 hidden">
                                    <div class="row fvc">
                                        <div class="col-3 location_preview">
                                            <img id="lpe_img" src="" alt="" class="border hidden">
                                            <div id="lpe_noimg" class="icon-nophoto"></div>
                                        </div>
                                        <div class="col-5">
                                            <p class="cd b-600" id="lpe_name"></p>
                                            <p class="mt-1 f-16" id="lpe_meta"></p>
                                        </div>
                                        <div class="col-4">
                                            <div class="border" id="lpe_map_wrap" style="display:none;">
                                                <iframe id="lpe_map" src="" class="w-100" style="height:120px;" loading="lazy"></iframe>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ===== БЛОК 3: Игровые настройки ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('events.game_settings_section') }}</h2>
                    <div class="row">

                    @if(($event->format ?? 'game') === 'tournament')
                        @php
                            $ts = $event->tournamentSetting;
                            $isBeach = ($event->direction ?? 'classic') === 'beach';
                            $tournamentSchemes = $isBeach
                                ? ['2x2' => '2×2', '3x3' => '3×3', '4x4' => '4×4']
                                : ['4x4' => '4×4', '4x2' => '4×2', '5x1' => '5×1'];
                        @endphp

                        <div class="col-md-3">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.game_subtype') }}</label>
                                <select name="tournament_game_scheme">
                                    @foreach($tournamentSchemes as $k => $l)
                                        <option value="{{ $k }}" @selected(old('tournament_game_scheme', $ts?->game_scheme) === $k)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.team_n') }}</label>
                                <input type="number" name="teams_count" min="2" max="200"
                                    value="{{ old('teams_count', $ts?->teams_count ?? 4) }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.tournament_team_size_label') ?? 'Состав команды' }}</label>
                                <input type="number" name="tournament_team_size_min" min="1" max="20"
                                    value="{{ old('tournament_team_size_min', $ts?->team_size_min ?? ($isBeach ? 2 : 6)) }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.tournament_reserve_label') ?? 'Запасных' }}</label>
                                <input type="number" name="tournament_reserve_players_max" min="0" max="20"
                                    value="{{ old('tournament_reserve_players_max', $ts?->reserve_players_max ?? 0) }}">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.gender_label') }}</label>
                                <select name="game_gender_policy">
                                    @foreach([
                                        'mixed_open' => __('events.gender_mixed_open'),
                                        'mixed_5050' => __('events.gender_5050'),
                                        'only_male' => __('events.gender_only_male'),
                                        'only_female' => __('events.gender_only_female'),
                                        'mixed_limited' => __('events.gender_mixed_limited'),
                                    ] as $k => $l)
                                        <option value="{{ $k }}"
                                            @selected(old('game_gender_policy', $event->gameSettings?->gender_policy) === $k)>
                                            {{ $l }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.tournament_application_mode_label') ?? 'Режим заявок' }}</label>
                                <select name="tournament_application_mode">
                                    <option value="manual" @selected(old('tournament_application_mode', $ts?->application_mode ?? 'manual') === 'manual')>{{ __('events.tournament_app_mode_manual') ?? 'Ручное одобрение' }}</option>
                                    <option value="auto" @selected(old('tournament_application_mode', $ts?->application_mode) === 'auto')>{{ __('events.tournament_app_mode_auto') ?? 'Автоматическое' }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.tournament_notifs') }}</label>
                                <label class="checkbox-item">
                                    <input type="hidden" name="tournament_captain_confirms_members" value="0">
                                    <input type="checkbox" name="tournament_captain_confirms_members" value="1"
                                        @checked(old('tournament_captain_confirms_members', $ts?->captain_confirms_members ?? true))>
                                    <div class="custom-checkbox"></div>
                                    <span>{{ __('events.tournament_captain_confirms') }}</span>
                                </label>
                                <label class="checkbox-item">
                                    <input type="hidden" name="tournament_auto_submit_when_ready" value="0">
                                    <input type="checkbox" name="tournament_auto_submit_when_ready" value="1"
                                        @checked(old('tournament_auto_submit_when_ready', $ts?->auto_submit_when_ready ?? false))>
                                    <div class="custom-checkbox"></div>
                                    <span>{{ __('events.tournament_auto_submit') }}</span>
                                </label>
                                <label class="checkbox-item">
                                    <input type="hidden" name="tournament_allow_incomplete_application" value="0">
                                    <input type="checkbox" name="tournament_allow_incomplete_application" value="1"
                                        @checked(old('tournament_allow_incomplete_application', $ts?->allow_incomplete_application ?? false))>
                                    <div class="custom-checkbox"></div>
                                    <span>{{ __('events.tournament_allow_incomplete') }}</span>
                                </label>
                            </div>
                        </div>
                    @else
                        @php
                            $isBeachGame = ($event->direction ?? 'classic') === 'beach';
                            $gameSubtypes = $isBeachGame
                                ? ['2x2' => '2×2', '3x3' => '3×3', '4x4' => '4×4']
                                : ['4x4' => '4×4', '4x2' => '4×2', '5x1' => '5×1'];
                        @endphp
                        <div class="col-md-3">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.game_subtype') }}</label>
                                <select name="game_subtype">
                                    @foreach($gameSubtypes as $k => $l)
                                        <option value="{{ $k }}"
                                            @selected(old('game_subtype', $event->gameSettings?->subtype) === $k)>
                                            {{ $l }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.team_n') }}</label>
                                <input type="number" name="teams_count" min="2" max="200"
                                    value="{{ old('teams_count', $event->gameSettings?->teams_count ?? 2) }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.min_players') }}</label>
                                <input type="number" name="game_min_players" min="0" max="99"
                                    value="{{ old('game_min_players', $event->gameSettings?->min_players ?? 0) }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.max_players') }}</label>
                                <input type="number" name="game_max_players" min="0" max="99"
                                    value="{{ old('game_max_players', $event->gameSettings?->max_players ?? 0) }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.gender_label') }}</label>
                                <select name="game_gender_policy">
                                    @foreach([
                                        'mixed_open' => __('events.gender_mixed_open'),
                                        'mixed_5050' => __('events.gender_5050'),
                                        'only_male' => __('events.gender_only_male'),
                                        'only_female' => __('events.gender_only_female'),
                                        'mixed_limited' => __('events.gender_mixed_limited'),
                                    ] as $k => $l)
                                        <option value="{{ $k }}"
                                            @selected(old('game_gender_policy', $event->gameSettings?->gender_policy) === $k)>
                                            {{ $l }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                        <div class="col-md-6">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.gender_limited_reg_label') }}</label>
                                <input type="number"
                                    name="game_gender_limited_reg_starts_days_before"
                                    min="0" max="365"
                                    value="{{ old('game_gender_limited_reg_starts_days_before', $event->gameSettings?->gender_limited_reg_starts_days_before) }}"
                                    placeholder="{{ __('events.gender_limited_reg_ph') }}">
                                <ul class="list f-14 mt-1">
                                    <li>{{ __('events.gender_limited_reg_hint') }}</li>
                                </ul>
                            </div>
                        </div>

@if(($event->direction ?? 'classic') === 'classic')
                        <div class="col-md-6">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.level_classic_short') }}</label>
                                <div class="row row2">
                                    <div class="col-6">
                                        <label>{{ __('events.level_from') }}</label>
                                        <input type="number" name="classic_level_min" min="1" max="7"
                                            value="{{ old('classic_level_min', $event->classic_level_min) }}"
                                            placeholder="—">
                                    </div>
                                    <div class="col-6">
                                        <label>{{ __('events.level_to') }}</label>
                                        <input type="number" name="classic_level_max" min="1" max="7"
                                            value="{{ old('classic_level_max', $event->classic_level_max) }}"
                                            placeholder="—">
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(($event->direction ?? 'classic') === 'beach')
                        <div class="col-md-6">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.level_beach_short') }}</label>
                                <div class="row row2">
                                    <div class="col-6">
                                        <label>{{ __('events.level_from') }}</label>
                                        <input type="number" name="beach_level_min" min="1" max="7"
                                            value="{{ old('beach_level_min', $event->beach_level_min) }}"
                                            placeholder="—">
                                    </div>
                                    <div class="col-6">
                                        <label>{{ __('events.level_to') }}</label>
                                        <input type="number" name="beach_level_max" min="1" max="7"
                                            value="{{ old('beach_level_max', $event->beach_level_max) }}"
                                            placeholder="—">
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="col-md-6">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.age_policy_label') }}</label>
                                @php $agePolicy = old('age_policy', $event->age_policy ?? 'adult'); @endphp
                                <label class="radio-item">
                                    <input type="radio" name="age_policy" value="adult" @checked($agePolicy === 'adult')>
                                    <div class="custom-radio"></div>
                                    <span>{{ __('events.age_policy_adult') }}</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="age_policy" value="child" @checked($agePolicy === 'child')>
                                    <div class="custom-radio"></div>
                                    <span>{{ __('events.age_policy_child') }}</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="age_policy" value="any" @checked($agePolicy === 'any')>
                                    <div class="custom-radio"></div>
                                    <span>{{ __('events.age_policy_any') }}</span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ===== БЛОК 4: Регистрация ===== --}}
                <div class="ramka"  style="z-index: 4">
                    <h2 class="-mt-05">{{ __('events.reg_section') }}</h2>
                    <div class="row">

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.reg_via_service') }}</label>
                                @php $allowRegVal = old('allow_registration', (int)((bool)$event->allow_registration)); @endphp
                                <label class="radio-item">
                                    <input type="radio" name="allow_registration" value="1" @checked((string)$allowRegVal === '1')>
                                    <div class="custom-radio"></div>
                                    <span>{{ __('events.yes') }}</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="allow_registration" value="0" @checked((string)$allowRegVal === '0')>
                                    <div class="custom-radio"></div>
                                    <span>{{ __('events.no') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.reg_starts_days') }}</label>
                                <div class="d-flex" style="gap:.5rem;align-items:center">
                                    <select name="reg_starts_days_before" id="mgmt_reg_starts_d" style="width:auto">
                                        @for($d = 0; $d <= 90; $d++)
                                            <option value="{{ $d }}" @selected((old('reg_starts_days_before', $regStartsDaysSaved)) == $d)>{{ $d }} {{ __('events.dur_d_short') }}</option>
                                        @endfor
                                    </select>
                                    <select name="reg_starts_hours_before" id="mgmt_reg_starts_h" style="width:auto">
                                        @for($h = 0; $h <= 23; $h++)
                                            <option value="{{ $h }}" @selected((old('reg_starts_hours_before', $regStartsHoursSaved)) == $h)>{{ $h }} {{ __('events.dur_h_short') }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.reg_ends_until_start') }}</label>
                                <input type="hidden" name="reg_ends_minutes_before" id="mgmt_reg_ends_min" value="{{ $regEndsMinCurrent }}">
                                <div class="d-flex" style="gap:.5rem;align-items:center">
                                    <select id="mgmt_reg_ends_h" style="width:auto">
                                        @for ($h = 0; $h <= 24; $h++)
                                            <option value="{{ $h }}" @selected($regEndsHours == $h)>{{ $h }} {{ __('events.dur_h_short') }}</option>
                                        @endfor
                                    </select>
                                    <select id="mgmt_reg_ends_m" style="width:auto">
                                        @foreach([0,10,15,20,30,40,50] as $m)
                                            <option value="{{ $m }}" @selected($regEndsMins == $m)>{{ $m }} {{ __('events.dur_m_short') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <ul class="list f-16 mt-1">
                                    <li>{{ __('events.reg_ends_default') }}</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.cancel_lock_until_start') }}</label>
                                <input type="hidden" name="cancel_lock_minutes_before" id="mgmt_cancel_min" value="{{ $cancelMinCurrent }}">
                                <div class="d-flex" style="gap:.5rem;align-items:center">
                                    <select id="mgmt_cancel_h" style="width:auto">
                                        @for ($h = 0; $h <= 24; $h++)
                                            <option value="{{ $h }}" @selected($cancelHours == $h)>{{ $h }} {{ __('events.dur_h_short') }}</option>
                                        @endfor
                                    </select>
                                    <select id="mgmt_cancel_m" style="width:auto">
                                        @foreach([0,10,15,20,30,40,50] as $m)
                                            <option value="{{ $m }}" @selected($cancelMins == $m)>{{ $m }} {{ __('events.dur_m_short') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <ul class="list f-16 mt-1">
                                    <li>{{ __('events.cancel_lock_default') }}</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.cancel_lock_waitlist_label') }}</label>
                                <input type="hidden" name="cancel_lock_waitlist_minutes_before" id="mgmt_cancel_waitlist_min" value="{{ $cancelWaitlistMinCurrent }}">
                                <div class="d-flex" style="gap:.5rem;align-items:center">
                                    <select id="mgmt_cancel_waitlist_h" style="width:auto">
                                        <option value="0" @selected($cancelWaitlistHours == 0)>0 {{ __('events.dur_h_short') }}</option>
                                        @for ($h = 1; $h <= 24; $h++)
                                            <option value="{{ $h }}" @selected($cancelWaitlistHours == $h)>{{ $h }} {{ __('events.dur_h_short') }}</option>
                                        @endfor
                                    </select>
                                    <select id="mgmt_cancel_waitlist_m" style="width:auto">
                                        @foreach([0,10,15,20,30,40,50] as $m)
                                            <option value="{{ $m }}" @selected($cancelWaitlistMins == $m)>{{ $m }} {{ __('events.dur_m_short') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <ul class="list f-16 mt-1">
                                    <li>{{ __('events.cancel_lock_waitlist_hint') }}</li>
                                    <li>{{ __('events.cancel_lock_waitlist_zero_hint') }}</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.personal_data_section') }}</label>
                                <label class="checkbox-item">
                                    <input type="hidden" name="requires_personal_data" value="0">
                                    <input type="checkbox" name="requires_personal_data" value="1"
                                        @checked(old('requires_personal_data', $event->requires_personal_data ?? false))>
                                    <div class="custom-checkbox"></div>
                                    <span>{{ __('events.personal_data_request') }}</span>
                                </label>
                                <ul class="list f-16 mt-1">
                                    <li>{{ __('events.personal_data_hint_2') }}</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ===== БЛОК 5: Доступность ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('events.access_pay_section') }}</h2>
                    <div class="row">

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label class="checkbox-item">
                                    <input type="hidden" name="is_private" value="0">
                                    <input type="checkbox" name="is_private" value="1"
                                        @checked(old('is_private', $event->is_private ?? false))>
                                    <div class="custom-checkbox"></div>
                                    <span>{{ __('events.private_short') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label class="checkbox-item">
                                    <input type="hidden" name="is_paid" value="0">
                                    <input type="checkbox" name="is_paid" value="1" id="is_paid_edit"
                                        @checked(old('is_paid', $event->is_paid ?? false))>
                                    <div class="custom-checkbox"></div>
                                    <span>{{ __('events.paid_label') }}</span>
                                </label>

                                <div class="row row2 mt-1" id="price_wrap_edit">
                                    <div class="col-7">
                                        <label>{{ __('events.price_label') }}</label>
                                        <input type="number" name="price_amount" min="10" max="500000" step="0.01"
                                            value="{{ old('price_amount', $event->price_minor ? $event->price_minor / 100 : '') }}"
                                            placeholder="{{ __('events.price_ph_500') }}">
                                        @error('price_amount')
                                            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-5">
                                        <label>{{ __('events.currency_label') }}</label>
                                        <select name="price_currency">
                                            @foreach(['RUB' => '₽ RUB', 'USD' => '$ USD', 'EUR' => '€ EUR', 'KZT' => '₸ KZT'] as $code => $label)
                                                <option value="{{ $code }}"
                                                    @selected(old('price_currency', $event->price_currency ?? 'RUB') === $code)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>

                                </div>
                                
                                <div class="mt-2">
                                    <label>{{ __('events.pay_method_label') }}</label>
                                    <select name="payment_method">
                                        @foreach([
                                            'cash' => __('events.pay_method_cash'),
                                            'tbank_link' => __('events.pay_method_tbank'),
                                            'sber_link' => __('events.pay_method_sber'),
                                            'yoomoney' => __('events.pay_method_yookassa')
                                        ] as $method => $label)
                                            <option value="{{ $method }}" 
                                                @selected(old('payment_method', $event->payment_method ?? 'cash') === $method)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mt-2" id="payment-link-block" style="display:none">
                                    <label>{{ __('events.pay_link_label2') }}</label>
                                    <input type="text" name="payment_link" class="form-control"
                                        placeholder="https://..."
                                        value="{{ old('payment_link', $event->payment_link ?? '') }}">
                                </div>

                                {{-- Режим оплаты турнира --}}
                                @if($event->format === 'tournament')
                                <div class="mt-2" id="tournament_payment_mode_edit_wrap">
                                    <label>{{ __('events.tournament_pay_mode') }}</label>
                                    @php
                                        $tpmEdit = old('tournament_payment_mode', $event->tournament_settings->payment_mode ?? 'team');
                                    @endphp
                                    <select name="tournament_payment_mode" id="tournament_payment_mode_edit">
                                        <option value="team" @selected($tpmEdit === 'team')>{{ __('events.tournament_pay_team') }}</option>
                                        <option value="per_player" @selected($tpmEdit === 'per_player')>{{ __('events.tournament_pay_per') }}</option>
                                    </select>
                                    <ul class="list f-14 mt-1">
                                        <li id="hint_team_edit" style="{{ $tpmEdit === 'team' ? '' : 'display:none' }}">{{ __('events.tournament_pay_team_short') }}</li>
                                        <li id="hint_pp_edit" style="{{ $tpmEdit === 'per_player' ? '' : 'display:none' }}">{{ __('events.tournament_pay_per_short') }}</li>
                                    </ul>
                                </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.show_participants_label') }}</label>
                                @php $showParts = old('show_participants', $event->show_participants ?? true); @endphp
                                <label class="radio-item">
                                    <input type="radio" name="show_participants" value="1" @checked($showParts)>
                                    <div class="custom-radio"></div>
                                    <span>{{ __('events.yes') }}</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="show_participants" value="0" @checked(!$showParts)>
                                    <div class="custom-radio"></div>
                                    <span>{{ __('events.no') }}</span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ===== БЛОК 6: Уведомления ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('events.notify_section') }}</h2>
                    <div class="row">

                        <div class="col-md-6">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.remind_label') }}</label>
                                <label class="checkbox-item">
                                    <input type="hidden" name="remind_registration_enabled" value="0">
                                    <input type="checkbox" name="remind_registration_enabled" value="1"
                                        @checked(old('remind_registration_enabled', $event->remind_registration_enabled ?? true))>
                                    <div class="custom-checkbox"></div>
                                    <span>{{ __('events.remind_enabled') }}</span>
                                </label>
                                <div class="mt-2">
                                    <label>{{ __('events.remind_when') }}</label>
                                    <div class="row row2">
                                        <div class="col-6">
                                            <label>{{ __('events.duration_h') }}</label>
                                            <select id="remind_hours_edit">
                                                @for($h = 0; $h <= 12; $h++)
                                                    <option value="{{ $h }}" @selected((int) floor($remMin / 60) == $h)>{{ $h }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label>{{ __('events.remind_minutes') }}</label>
                                            <select id="remind_minutes_edit">
                                                @foreach([0,5,10,15,20,25,30,35,40,45,50,55,60] as $m)
                                                    <option value="{{ $m }}" @selected(($remMin % 60) == $m)>{{ $m }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <input type="hidden" name="remind_registration_minutes_before"
                                        id="remind_hidden_edit" value="{{ $remMin }}">
                                    <ul class="list f-16 mt-1">
                                        <li id="remind_fire_at_hint_edit" class="cd b-600"></li>
                                        <li>{{ __('events.remind_example') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- ===== Каналы анонсов ===== --}}
                        @php
                        $selChannels = old('channels', $selectedChannelIds ?? []);
                        if (!is_array($selChannels)) { $selChannels = []; }
                        $cs = $channelSettings ?? ['silent' => false, 'update_message' => true, 'include_image' => true, 'include_registered_list' => true];
                        $chSilent     = (bool) old('channel_silent', $cs['silent']);
                        $chUpdateMsg  = (bool) old('channel_update_message', $cs['update_message']);
                        $chIncImg     = (bool) old('channel_include_image', $cs['include_image']);
                        $chIncReg     = (bool) old('channel_include_registered', $cs['include_registered_list']);
                        @endphp
                        <div class="col-md-6">
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.channels_label') }}</label>

                                <ul class="list f-16 mb-2">
                                    <li>{{ __('events.channels_hint_1') }}</li>
                                    <li>{{ __('events.channels_hint_2') }}</li>
                                </ul>

                                @if(($userChannels ?? collect())->isEmpty())
                                <div class="f-16">
                                    {{ __('events.channels_none_pre') }}
                                    <a href="{{ route('profile.notification_channels') }}" class="link">
                                        {{ __('events.channels_none_link') }}
                                    </a>
                                </div>
                                @else
                                <div class="mt-2">
                                    @foreach($userChannels as $channel)
                                    <label class="checkbox-item">
                                        <input type="checkbox"
                                            name="channels[]"
                                            value="{{ $channel->id }}"
                                            @checked(in_array((string) $channel->id, array_map('strval', $selChannels), true))>
                                        <div class="custom-checkbox"></div>
                                        <span>
                                            {{ strtoupper($channel->platform) }} — {{ $channel->title ?: __('events.channels_no_title') }}
                                            <span class="text-muted">({{ $channel->chat_id }})</span>
                                        </span>
                                    </label>
                                    @endforeach
                                </div>

                                <div class="mt-2">
                                    <label class="checkbox-item">
                                        <input type="hidden" name="channel_silent" value="0">
                                        <input type="checkbox" name="channel_silent" value="1" @checked($chSilent)>
                                        <div class="custom-checkbox"></div>
                                        <span>{{ __('events.channels_silent') }}</span>
                                    </label>

                                    <label class="checkbox-item">
                                        <input type="hidden" name="channel_update_message" value="0">
                                        <input type="checkbox" name="channel_update_message" value="1" @checked($chUpdateMsg)>
                                        <div class="custom-checkbox"></div>
                                        <span>{{ __('events.channels_update_msg') }}</span>
                                    </label>

                                    <label class="checkbox-item">
                                        <input type="hidden" name="channel_include_image" value="0">
                                        <input type="checkbox" name="channel_include_image" value="1" @checked($chIncImg)>
                                        <div class="custom-checkbox"></div>
                                        <span>{{ __('events.channels_with_image') }}</span>
                                    </label>

                                    <label class="checkbox-item">
                                        <input type="hidden" name="channel_include_registered" value="0">
                                        <input type="checkbox" name="channel_include_registered" value="1" @checked($chIncReg)>
                                        <div class="custom-checkbox"></div>
                                        <span>{{ __('events.channels_with_players') }}</span>
                                    </label>
                                </div>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>

                @if(!in_array($event->registration_mode ?? 'single', ['team', 'team_classic', 'team_beach'], true) && ($event->format ?? 'game') !== 'tournament')
                {{-- ===== БЛОК 7: Помощник записи 🤖 ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('events.bot_title_short') }} <span id="bot_icon_edit" style="color:{{ ($event->bot_assistant_enabled ?? false) ? '#10b981' : '#9ca3af' }}">🤖</span></h2>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card" style="overflow:visible">
                                <label class="checkbox-item">
                                    <input type="hidden" name="bot_assistant_enabled" value="0">
                                    <input type="checkbox" name="bot_assistant_enabled" value="1"
                                        id="bot_assistant_enabled_edit"
                                        @checked(old('bot_assistant_enabled', $event->bot_assistant_enabled ?? false))>
                                    <div class="custom-checkbox"></div>
                                    <span>{{ __('events.bot_enable') }}</span>
                                </label>
                                <ul class="list f-16 mt-1">
                                    <li>{{ __('events.bot_squeeze_hint') }}</li>
                                    <li>{{ __('events.bot_visibility_hint') }}</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6" id="bot_settings_edit"
                             @if(!old('bot_assistant_enabled', $event->bot_assistant_enabled ?? false)) style="display:none" @endif>
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.bot_threshold_short') }}
                                    <strong id="bot_threshold_val_edit" class="cd">
                                        {{ old('bot_assistant_threshold', $event->bot_assistant_threshold ?? 10) }}%
                                    </strong>
                                </label>
                                <input type="range" name="bot_assistant_threshold"
                                    min="5" max="30" step="5"
                                    value="{{ old('bot_assistant_threshold', $event->bot_assistant_threshold ?? 10) }}"
                                    oninput="document.getElementById('bot_threshold_val_edit').textContent = this.value + '%'">
                                <ul class="list f-16 mt-1"><li>{{ __('events.bot_threshold_range_short') }}</li></ul>
                            </div>
                        </div>

                        <div class="col-md-6" id="bot_fill_edit"
                             @if(!old('bot_assistant_enabled', $event->bot_assistant_enabled ?? false)) style="display:none" @endif>
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.bot_fill_short') }}
                                    <strong id="bot_fill_val_edit" class="cd">
                                        {{ old('bot_assistant_max_fill_pct', $event->bot_assistant_max_fill_pct ?? 40) }}%
                                    </strong>
                                </label>
                                <input type="range" name="bot_assistant_max_fill_pct"
                                    min="10" max="60" step="10"
                                    value="{{ old('bot_assistant_max_fill_pct', $event->bot_assistant_max_fill_pct ?? 40) }}"
                                    oninput="document.getElementById('bot_fill_val_edit').textContent = this.value + '%'">
                                <ul class="list f-16 mt-1"><li>{{ __('events.bot_fill_range_short') }}</li></ul>
                            </div>
                        </div>
                    </div>
                </div>

                @endif

                {{-- ===== БЛОК 8: Описание ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('events.photo_only_title') }}</h2>
                    <div class="row">
                        <div class="col-md-12">
                            @php
                                $userEventPhotos = auth()->user()->getMedia('event_photos')->sortByDesc('created_at');
                                $currentEventPhotos = $event->event_photos ?? [];
                            @endphp
                            @if($userEventPhotos->count() > 0)
                            <div class="card" style="overflow:visible">
                                <label>{{ __('events.photos_label') }}</label>
                                <div class="event-photos-selector-edit"
                                     data-selected='{{ json_encode(old('event_photos_edit') ? json_decode(old('event_photos_edit'), true) : $currentEventPhotos) }}'>
                                    <div class="swiper eventPhotosSwiperEdit">
                                        <div class="swiper-wrapper">
                                            @foreach($userEventPhotos as $photo)
                                            <div class="swiper-slide">
                                                <div class="hover-image mb-1">
                                                    <img src="{{ $photo->getUrl('event_thumb') }}" alt="event photo" loading="lazy"/>
                                                </div>
                                                <div class="mt-1 d-flex between fvc">
                                                    <label class="checkbox-item mb-0">
                                                        <input type="checkbox" class="photo-select-edit" value="{{ $photo->id }}">
                                                        <div class="custom-checkbox"></div>
                                                        <span>{{ __('events.photo_select') }}</span>
                                                    </label>
                                                    <div class="photo-order-badge-edit f-16 b-600 cd"></div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                        <div class="swiper-pagination"></div>
                                    </div>
                                    <ul class="list f-16 mt-1">
                                        <li>{{ __('events.photo_select_hint_1') }}</li>
                                        <li>{{ __('events.photo_select_hint_2_pre') }} <a target="_blank" href="{{ route('user.photos') }}">{{ __('events.photo_select_hint_2_link') }}</a></li>
                                    </ul>
                                    <input type="hidden" name="event_photos" id="event_photos_input_edit" value="">
                                </div>
                            </div>
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                new Swiper('.eventPhotosSwiperEdit', {
                                    slidesPerView: 1,
                                    spaceBetween: 15,
                                    pagination: { el: '.swiper-pagination', clickable: true },
                                    breakpoints: { 640: { slidesPerView: 1 }, 768: { slidesPerView: 1 }, 1024: { slidesPerView: 1 } }
                                });

                                var container = document.querySelector('.event-photos-selector-edit');
                                var savedPhotos = JSON.parse(container.dataset.selected || '[]');
                                var selectedPhotos = savedPhotos.slice();

                                function updateUI() {
                                    document.querySelectorAll('.photo-select-edit').forEach(function(checkbox) {
                                        var id = parseInt(checkbox.value);
                                        var isSelected = selectedPhotos.includes(id);
                                        checkbox.checked = isSelected;
                                        var badge = checkbox.closest('.swiper-slide').querySelector('.photo-order-badge-edit');
                                        if (isSelected) {
                                            var order = selectedPhotos.indexOf(id) + 1;
                                            badge.textContent = order === 1 ? @json(__('events.photo_main')) : @json(__('events.photo_pos_n', ['n' => ''])) + order;
                                        } else {
                                            badge.textContent = '';
                                        }
                                    });
                                    document.getElementById('event_photos_input_edit').value = JSON.stringify(selectedPhotos);
                                }

                                document.querySelectorAll('.photo-select-edit').forEach(function(checkbox) {
                                    checkbox.addEventListener('change', function() {
                                        var id = parseInt(this.value);
                                        if (this.checked) {
                                            selectedPhotos.push(id);
                                        } else {
                                            var index = selectedPhotos.indexOf(id);
                                            if (index !== -1) selectedPhotos.splice(index, 1);
                                        }
                                        updateUI();
                                    });
                                });

                                updateUI();
                            });
                            </script>
                            @else
                            <div class="alert alert-info">
                                <p>{{ __('events.photo_empty_p1') }}</p>
                                <p>{{ __('events.photo_select_hint_2_pre') }} <a target="_blank" href="{{ route('user.photos') }}">{{ __('events.photo_select_hint_2_link') }}</a></p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="ramka">
                    <h2 class="-mt-05">{{ __('events.desc_only_title') }}</h2>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card" style="overflow:visible">
                                <input id="description_html_edit" type="hidden" name="description_html"
                                    value="{{ old('description_html', $event->description_html ?? '') }}">
                                <trix-editor input="description_html_edit" class="trix-content"></trix-editor>
                                @error('description_html')
                                    <div class="text-red-600 text-sm mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- КНОПКИ --}}
                <div class="ramka text-center">
                    <a href="{{ route('events.create.event_management') }}" class="btn btn-secondary mr-2">{{ __('events.btn_cancel_back') }}</a>
                    <button type="submit" class="btn">{{ __('events.btn_save_changes') }}</button>
                </div>

            </form>
        </div>
    </div>

    <x-slot name="script">
        <script src="/assets/fas.js"></script>
        <script src="/assets/org.js"></script>

        <script>
        document.addEventListener('DOMContentLoaded', function () {

            // --- Длительность ---
            const dHours   = document.querySelector('[name="duration_hours"]');
            const dMinutes = document.querySelector('[name="duration_minutes"]');
            const dSec     = document.getElementById('duration_sec_edit');
            function syncDuration() {
                const h = Math.max(0, parseInt(dHours?.value || 0, 10));
                const m = Math.max(0, Math.min(59, parseInt(dMinutes?.value || 0, 10)));
                if (dSec) dSec.value = h * 3600 + m * 60;
            }
            dHours?.addEventListener('input', syncDuration);
            dMinutes?.addEventListener('input', syncDuration);
            syncDuration();

            // --- Напоминание ---
            const rH = document.getElementById('remind_hours_edit');
            const rM = document.getElementById('remind_minutes_edit');
            const rHid = document.getElementById('remind_hidden_edit');
            const rHint = document.getElementById('remind_fire_at_hint_edit');

            // Конвертирует datetime-local строку + timezone в UTC Date.
            // startsVal = "2026-05-13T19:30" (локальное время мероприятия), tzName = "Asia/Novosibirsk"
            function localEventTimeToUTC(startsVal, tzName) {
                if (!startsVal || !tzName) return null;
                try {
                    // Временно трактуем как UTC, чтобы узнать смещение timezone
                    const naiveUTC = new Date(startsVal + ':00Z');
                    const parts = new Intl.DateTimeFormat('en-CA', {
                        timeZone: tzName, hour12: false,
                        year: 'numeric', month: '2-digit', day: '2-digit',
                        hour: '2-digit', minute: '2-digit'
                    }).formatToParts(naiveUTC);
                    const get = t => parts.find(p => p.type === t).value;
                    const tzMs = new Date(get('year')+'-'+get('month')+'-'+get('day')+'T'+get('hour')+':'+get('minute')+':00Z').getTime();
                    const offsetMs = tzMs - naiveUTC.getTime(); // смещение tz в мс
                    return new Date(naiveUTC.getTime() - offsetMs); // корректный UTC
                } catch(e) { return null; }
            }

            function syncRemind() {
                const h = Math.max(0, parseInt(rH?.value || 0, 10));
                const m = Math.max(0, Math.min(59, parseInt(rM?.value || 0, 10)));
                if (rHid) rHid.value = h * 60 + m;

                if (rHint) {
                    const startsVal = document.querySelector('input[name="starts_at"]')?.value || '';
                    const tz = document.querySelector('input[name="timezone"]')?.value || '';
                    if (startsVal && tz) {
                        const startsUTC = localEventTimeToUTC(startsVal, tz);
                        if (startsUTC) {
                            const fireUTC = new Date(startsUTC.getTime() - (h * 60 + m) * 60000);
                            try {
                                const fmt = new Intl.DateTimeFormat('ru-RU', {
                                    timeZone: tz, day: '2-digit', month: '2-digit',
                                    hour: '2-digit', minute: '2-digit', hour12: false
                                });
                                rHint.textContent = '→ Напоминание придёт ~' + fmt.format(fireUTC) + ' (' + tz + ')';
                            } catch(e) { rHint.textContent = ''; }
                        } else { rHint.textContent = ''; }
                    } else { rHint.textContent = ''; }
                }
            }
            rH?.addEventListener('input', syncRemind);
            rM?.addEventListener('input', syncRemind);
            document.querySelector('input[name="starts_at"]')?.addEventListener('input', syncRemind);
            syncRemind();

            // --- Окно регистрации (часы+минуты → hidden total минут) ---
            function syncRegHM(hSel, mSel, hidden) {
                if (!hSel || !mSel || !hidden) return;
                var total = parseInt(hSel.value) * 60 + parseInt(mSel.value);
                if (total < 1) total = 1;
                hidden.value = total;
            }
            function syncRegHMAllowZero(hSel, mSel, hidden) {
                if (!hSel || !mSel || !hidden) return;
                hidden.value = parseInt(hSel.value) * 60 + parseInt(mSel.value);
            }
            var regEndsH   = document.getElementById('mgmt_reg_ends_h');
            var regEndsM   = document.getElementById('mgmt_reg_ends_m');
            var regEndsHid = document.getElementById('mgmt_reg_ends_min');
            var cancelH    = document.getElementById('mgmt_cancel_h');
            var cancelM    = document.getElementById('mgmt_cancel_m');
            var cancelHid  = document.getElementById('mgmt_cancel_min');
            var cancelWH   = document.getElementById('mgmt_cancel_waitlist_h');
            var cancelWM   = document.getElementById('mgmt_cancel_waitlist_m');
            var cancelWHid = document.getElementById('mgmt_cancel_waitlist_min');
            regEndsH?.addEventListener('change', function() { syncRegHM(regEndsH, regEndsM, regEndsHid); });
            regEndsM?.addEventListener('change', function() { syncRegHM(regEndsH, regEndsM, regEndsHid); });
            cancelH?.addEventListener('change',  function() { syncRegHM(cancelH,  cancelM,  cancelHid); });
            cancelM?.addEventListener('change',  function() { syncRegHM(cancelH,  cancelM,  cancelHid); });
            cancelWH?.addEventListener('change', function() { syncRegHMAllowZero(cancelWH, cancelWM, cancelWHid); });
            cancelWM?.addEventListener('change', function() { syncRegHMAllowZero(cancelWH, cancelWM, cancelWHid); });
            syncRegHM(regEndsH, regEndsM, regEndsHid);
            syncRegHM(cancelH,  cancelM,  cancelHid);
            syncRegHMAllowZero(cancelWH, cancelWM, cancelWHid);

            // --- Бот-ассистент toggle ---
            const botChk = document.getElementById('bot_assistant_enabled_edit');
            const botSettings = document.getElementById('bot_settings_edit');
            const botFill     = document.getElementById('bot_fill_edit');
            const botIcon     = document.getElementById('bot_icon_edit');
            function syncBot() {
                const show = botChk?.checked;
                if (botSettings) botSettings.style.display = show ? '' : 'none';
                if (botFill) botFill.style.display = show ? '' : 'none';
                if (botIcon) botIcon.style.color = show ? '#10b981' : '#9ca3af';
            }
            botChk?.addEventListener('change', syncBot);

            // --- Location preview ---
            const sel = document.getElementById('location_id_edit');
            const wrap = document.getElementById('location_preview_edit');
            const img = document.getElementById('lpe_img');
            const noimg = document.getElementById('lpe_noimg');
            const nameEl = document.getElementById('lpe_name');
            const metaEl = document.getElementById('lpe_meta');
            const mapWrap = document.getElementById('lpe_map_wrap');
            const mapEl = document.getElementById('lpe_map');

            function updatePreview() {
                const opt = sel?.options[sel.selectedIndex];
                if (!opt?.value) { wrap?.classList.add('hidden'); return; }
                wrap?.classList.remove('hidden');
                if (nameEl) nameEl.textContent = opt.getAttribute('data-name') || '';
                const city = opt.getAttribute('data-city') || '';
                const addr = opt.getAttribute('data-address') || '';
                if (metaEl) metaEl.textContent = [city, addr].filter(Boolean).join(' · ');
                const thumb = opt.getAttribute('data-thumb') || '';
                if (thumb && img && noimg) {
                    img.src = thumb; img.classList.remove('hidden'); noimg.classList.add('hidden');
                } else if (img && noimg) {
                    img.src = ''; img.classList.add('hidden'); noimg.classList.remove('hidden');
                }
                const lat = (opt.getAttribute('data-lat') || '').trim();
                const lng = (opt.getAttribute('data-lng') || '').trim();
                if (mapWrap && mapEl && lat && lng && !isNaN(lat) && !isNaN(lng)) {
                    mapWrap.style.display = '';
                    mapEl.src = `https://yandex.ru/map-widget/v1/?ll=${lng},${lat}&z=15&l=map&pt=${lng},${lat},pm2rdm`;
                } else if (mapWrap) {
                    mapWrap.style.display = 'none';
                }
            }
            sel?.addEventListener('change', updatePreview);
            updatePreview();

            // --- City autocomplete + dynamic locations ---
            (function() {
                var cityWrap   = document.getElementById('edit-city-ac-wrap');
                var cityInput  = document.getElementById('edit_city_q');
                var cityIdEl   = document.getElementById('edit_city_id');
                var tzHidden   = document.getElementById('mgmt_timezone_hidden');
                var tzLabel    = document.getElementById('edit_tz_label');
                var locSel     = document.getElementById('location_id_edit');
                var timer      = null;

                if (!cityWrap || !cityInput) return;

                var dd      = document.getElementById('edit_city_dd');
                var locUrl  = cityWrap.getAttribute('data-locations-url') || '';
                var metaUrl = cityWrap.getAttribute('data-city-meta-url') || '';
                var srchUrl = cityWrap.getAttribute('data-search-url') || '';
                var savedLocationId = {{ (int)old('location_id', (int)$event->location_id) }};

                function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
                function showDd() { dd && dd.classList.add('form-select-dropdown--active'); }
                function hideDd() { dd && dd.classList.remove('form-select-dropdown--active'); }

                function fetchJson(url, cb) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', url);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.onload = function() { try { cb(JSON.parse(xhr.responseText)); } catch(e) { cb(null); } };
                    xhr.onerror = function() { cb(null); };
                    xhr.send();
                }

                function fillLocations(items, preselect) {
                    if (!locSel) return;
                    var current = preselect != null ? preselect : parseInt(locSel.value || 0);
                    locSel.innerHTML = '<option value="">{{ __("events.tournament_choose") }}</option>';
                    (items || []).forEach(function(loc) {
                        var opt = document.createElement('option');
                        opt.value = loc.id;
                        opt.textContent = loc.name + (loc.address ? ' — ' + loc.address : '');
                        opt.setAttribute('data-name', loc.name || '');
                        opt.setAttribute('data-city', '');
                        opt.setAttribute('data-address', loc.address || '');
                        opt.setAttribute('data-lat', loc.lat || '');
                        opt.setAttribute('data-lng', loc.lng || '');
                        opt.setAttribute('data-thumb', loc.thumb || '');
                        if (loc.id === current) opt.selected = true;
                        locSel.appendChild(opt);
                    });
                    if (window.jQuery && typeof window.initCustomSelects === 'function') {
                        try {
                            var $sel = window.jQuery(locSel);
                            while ($sel.prev('.form-select-wrapper').length) $sel.prev('.form-select-wrapper').remove();
                            $sel.removeData('custom-initialized');
                            window.initCustomSelects();
                        } catch(e) {}
                    }
                    if (typeof updatePreview === 'function') updatePreview();
                }

                function loadLocations(cityId, preselect) {
                    if (!locUrl || !cityId) return;
                    fetchJson(locUrl + '?city_id=' + encodeURIComponent(cityId), function(data) {
                        fillLocations(data && data.ok ? (data.items || []) : [], preselect);
                    });
                }

                function loadCityMeta(cityId) {
                    if (!metaUrl || !cityId) return;
                    fetchJson(metaUrl + '?city_id=' + encodeURIComponent(cityId), function(data) {
                        if (!data || !data.ok) return;
                        if (tzHidden && data.timezone) tzHidden.value = data.timezone;
                        if (tzLabel) tzLabel.textContent = data.timezone || '';
                        // syncRemind находится в DOMContentLoaded, не глобальна — hint обновится при следующем изменении remind полей
                    });
                }

                function applyCity(id, label) {
                    if (cityIdEl) cityIdEl.value = id ? String(id) : '';
                    if (cityInput) cityInput.value = label || '';
                    hideDd();
                    if (id) {
                        loadLocations(id, null);
                        loadCityMeta(id);
                    }
                }

                function renderResults(items) {
                    if (!dd) return;
                    dd.innerHTML = '';
                    if (!items.length) {
                        dd.innerHTML = '<div class="city-message">Ничего не найдено</div>';
                        showDd(); return;
                    }
                    items.forEach(function(item) {
                        var div = document.createElement('div');
                        div.className = 'trainer-item form-select-option';
                        var sub = [];
                        if (item.country_code) sub.push(item.country_code);
                        if (item.region) sub.push(item.region);
                        if (item.timezone) sub.push(item.timezone);
                        div.innerHTML = '<div class="text-sm" style="font-weight:600">' + esc(item.name) + '</div>'
                            + (sub.length ? '<div class="f-14" style="opacity:.6">' + esc(sub.join(' · ')) + '</div>' : '');
                        div.addEventListener('click', function() {
                            var label = item.name + (sub.length ? ' (' + [item.country_code, item.region].filter(Boolean).join(', ') + ')' : '');
                            applyCity(item.id, label);
                        });
                        dd.appendChild(div);
                    });
                    showDd();
                }

                cityInput.addEventListener('input', function() {
                    clearTimeout(timer);
                    if (cityIdEl) cityIdEl.value = '';
                    var q = cityInput.value.trim();
                    if (q.length < 2) { hideDd(); return; }
                    if (dd) { dd.innerHTML = '<div class="city-message">Поиск…</div>'; showDd(); }
                    timer = setTimeout(function() {
                        if (!srchUrl) return;
                        var url = srchUrl + '?q=' + encodeURIComponent(q) + '&limit=20';
                        fetchJson(url, function(data) {
                            var items = Array.isArray(data) ? data : (data && data.items ? data.items : []);
                            renderResults(items);
                        });
                    }, 250);
                });

                document.addEventListener('click', function(e) {
                    var wrap = document.getElementById('edit-city-ac-wrap');
                    if (wrap && !wrap.contains(e.target)) hideDd();
                });

                cityInput.addEventListener('keydown', function(e) { if (e.key === 'Escape') hideDd(); });

                // При загрузке — если есть текущий город, локации уже отрендерены сервером (статика).
                // Ничего дополнительно не нужно.
            })();

        });

            // === Payment link toggle ===
            const payMethodSel = document.querySelector('select[name="payment_method"]');
            const payLinkBlock = document.getElementById('payment-link-block');
            function syncPayLink() {
                if (!payMethodSel || !payLinkBlock) return;
                const needsLink = ['tbank_link','sber_link'].includes(payMethodSel.value);
                payLinkBlock.style.display = needsLink ? '' : 'none';
            }
            syncPayLink();
            payMethodSel?.addEventListener('change', syncPayLink);

        
    // Переключение хинтов режима оплаты турнира
    (function() {
        var sel = document.getElementById('tournament_payment_mode_edit');
        var hTeam = document.getElementById('hint_team_edit');
        var hPp = document.getElementById('hint_pp_edit');
        var isPaid = document.getElementById('is_paid_edit');
        var wrap = document.getElementById('tournament_payment_mode_edit_wrap');

        function syncTpmEdit() {
            if (!sel) return;
            if (hTeam) hTeam.style.display = sel.value === 'team' ? '' : 'none';
            if (hPp) hPp.style.display = sel.value === 'per_player' ? '' : 'none';
            if (wrap && isPaid) wrap.style.display = isPaid.checked ? '' : 'none';
        }

        if (sel) sel.addEventListener('change', syncTpmEdit);
        if (isPaid) isPaid.addEventListener('change', syncTpmEdit);
        syncTpmEdit();
    })();
</script>
    </x-slot>

</x-voll-layout>