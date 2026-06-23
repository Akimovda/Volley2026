{{-- resources/views/profile/athlete.blade.php --}}
<x-voll-layout body_class="profile-page">

    <x-slot name="style">
    <style>
    :root {
        --act-hr-z1: #2967BA;
        --act-hr-z2: #22c55e;
        --act-hr-z3: #eab308;
        --act-hr-z4: #f97316;
        --act-hr-z5: #ef4444;
        --act-hr-z1-tint: rgba(41,103,186,.09);
        --act-hr-z2-tint: rgba(34,197,94,.09);
        --act-hr-z3-tint: rgba(234,179,8,.09);
        --act-hr-z4-tint: rgba(249,115,22,.09);
        --act-hr-z5-tint: rgba(239,68,68,.09);
    }
    body.dark {
        --act-hr-z1-tint: rgba(41,103,186,.18);
        --act-hr-z2-tint: rgba(34,197,94,.18);
        --act-hr-z3-tint: rgba(234,179,8,.18);
        --act-hr-z4-tint: rgba(249,115,22,.18);
        --act-hr-z5-tint: rgba(239,68,68,.18);
    }
    /* Сегментированная шкала */
    .hr-zone-bar { display:flex; gap:3px; height:10px; border-radius:6px; overflow:hidden; }
    .hr-zone-bar__seg { flex:1; border-radius:3px; }
    .hr-zone-bar__seg--z1 { background:var(--act-hr-z1); }
    .hr-zone-bar__seg--z2 { background:var(--act-hr-z2); }
    .hr-zone-bar__seg--z3 { background:var(--act-hr-z3); }
    .hr-zone-bar__seg--z4 { background:var(--act-hr-z4); }
    .hr-zone-bar__seg--z5 { background:var(--act-hr-z5); }
    /* Строки зон */
    .hr-zone-list { display:flex; flex-direction:column; gap:5px; }
    .hr-zone-row {
        display:flex; align-items:center; gap:12px;
        padding:10px 12px; border-radius:8px; min-height:44px;
    }
    .hr-zone-row--z1 { background:var(--act-hr-z1-tint); }
    .hr-zone-row--z2 { background:var(--act-hr-z2-tint); }
    .hr-zone-row--z3 { background:var(--act-hr-z3-tint); }
    .hr-zone-row--z4 { background:var(--act-hr-z4-tint); }
    .hr-zone-row--z5 { background:var(--act-hr-z5-tint); }
    .hr-zone-row__marker {
        width:4px; height:34px; border-radius:2px; flex-shrink:0;
    }
    .hr-zone-row--z1 .hr-zone-row__marker { background:var(--act-hr-z1); }
    .hr-zone-row--z2 .hr-zone-row__marker { background:var(--act-hr-z2); }
    .hr-zone-row--z3 .hr-zone-row__marker { background:var(--act-hr-z3); }
    .hr-zone-row--z4 .hr-zone-row__marker { background:var(--act-hr-z4); }
    .hr-zone-row--z5 .hr-zone-row__marker { background:var(--act-hr-z5); }
    .hr-zone-row__info { flex:1; min-width:0; }
    .hr-zone-row__name { font-size:1.4rem; line-height:1.3; }
    .hr-zone-row__bpm  { font-size:1.25rem; opacity:.7; margin-top:1px; }
    </style>
    </x-slot>

    <x-slot name="title">{{ __('activity.activity_sensors_settings') }}</x-slot>
    <x-slot name="h1">{{ __('activity.activity_sensors_settings') }}</x-slot>
    <x-slot name="h2">{{ __('activity.page_subtitle') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">{{ __('profile.menu_my_profile') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('activity.activity_sensors_settings') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka">
                        @include('profile._menu', [
                            'menuUser'   => $user,
                            'activeMenu' => 'athlete',
                        ])
                    </div>
                </div>
            </div>
            <div class="col-lg-8 col-xl-9 order-1">

                @if(session('status'))
                    <div class="alert alert-success mb-2">{{ session('status') }}</div>
                @endif

                <div class="ramka">
                    <h2 class="-mt-05">{{ __('activity.settings_heading') }}</h2>

                    <form method="POST" action="{{ route('profile.athlete.update') }}" class="form">
                        @csrf

                        <div class="row">

                            {{-- Пульс в покое --}}
                            <div class="col-sm-6">
                                <div class="card">
                                    <label>
                                        <div>{{ __('activity.resting_hr_label') }}</div>
                                    </label>
                                    <input type="number"
                                           name="resting_hr"
                                           class="{{ $errors->has('resting_hr') ? 'input-error' : '' }}"
                                           value="{{ old('resting_hr', $profile?->resting_hr) }}"
                                           min="30" max="100"
                                           placeholder="{{ __('activity.resting_hr_placeholder') }}">
                                    <ul class="list f-16 mt-1">
                                        @error('resting_hr')<li class="red b-600">{{ $message }}</li>@enderror
                                        <li>{{ __('activity.resting_hr_hint') }}</li>
                                    </ul>
                                </div>
                            </div>

                            {{-- Максимальный пульс --}}
                            <div class="col-sm-6">
                                <div class="card">
                                    <label>
                                        <div>{{ __('activity.max_hr_label') }}</div>
                                    </label>
                                    <input type="number"
                                           name="max_hr"
                                           class="{{ $errors->has('max_hr') ? 'input-error' : '' }}"
                                           value="{{ old('max_hr', $profile?->max_hr) }}"
                                           min="100" max="250"
                                           placeholder="{{ __('activity.max_hr_placeholder') }}">
                                    <ul class="list f-16 mt-1">
                                        @error('max_hr')<li class="red b-600">{{ $message }}</li>@enderror
                                        @if($suggestedMaxHr)
                                            <li>{{ __('activity.max_hr_age_hint', ['bpm' => $suggestedMaxHr]) }}</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>

                            {{-- Вес (опционально) --}}
                            <div class="col-sm-6">
                                <div class="card">
                                    <label>
                                        <div>{{ __('activity.weight_label') }}</div>
                                    </label>
                                    <input type="number"
                                           name="weight_kg"
                                           class="{{ $errors->has('weight_kg') ? 'input-error' : '' }}"
                                           value="{{ old('weight_kg', $profile?->weight_kg) }}"
                                           min="30" max="300" step="0.1"
                                           placeholder="{{ __('activity.weight_placeholder') }}">
                                    <ul class="list f-16 mt-1">
                                        @error('weight_kg')<li class="red b-600">{{ $message }}</li>@enderror
                                        <li>{{ __('activity.weight_hint') }}</li>
                                    </ul>
                                </div>
                            </div>

                            {{-- Доскок классика --}}
                            <div class="col-sm-6">
                                <div class="card">
                                    <label>
                                        <div>{{ __('activity.reach_classic') }}</div>
                                    </label>
                                    <input type="number"
                                           name="reach_classic_cm"
                                           class="{{ $errors->has('reach_classic_cm') ? 'input-error' : '' }}"
                                           value="{{ old('reach_classic_cm', $profile?->reach_classic_cm) }}"
                                           min="100" max="350"
                                           placeholder="250">
                                    <ul class="list f-16 mt-1">
                                        @error('reach_classic_cm')<li class="red b-600">{{ $message }}</li>@enderror
                                    </ul>
                                </div>
                            </div>

                            {{-- Доскок пляж --}}
                            <div class="col-sm-6">
                                <div class="card">
                                    <label>
                                        <div>{{ __('activity.reach_beach') }}</div>
                                    </label>
                                    <input type="number"
                                           name="reach_beach_cm"
                                           class="{{ $errors->has('reach_beach_cm') ? 'input-error' : '' }}"
                                           value="{{ old('reach_beach_cm', $profile?->reach_beach_cm) }}"
                                           min="100" max="350"
                                           placeholder="245">
                                    <ul class="list f-16 mt-1">
                                        @error('reach_beach_cm')<li class="red b-600">{{ $message }}</li>@enderror
                                    </ul>
                                </div>
                            </div>

                            {{-- Подсказка доскока --}}
                            <div class="col-12">
                                <div class="alert alert-info f-14">
                                    {{ __('activity.reach_hint') }}
                                </div>
                            </div>

                        </div>

                        <button type="submit" class="btn btn-primary mt-1">{{ __('activity.save_btn') }}</button>
                    </form>
                </div>

                {{-- Мои устройства (app-only, показывается через JS) --}}
                @php
                $_activityGate = config('activity.recording_open')
                    || $user->isAdmin()
                    || in_array($user->id, config('activity.recording_allowlist', []), true);
                @endphp
                @if($_activityGate)
                <div class="ramka mt-2" id="ble-devices-section" style="display:none">
                    <h2 class="-mt-05">{{ __('activity.my_devices') }}</h2>

                    {{-- Подсказка: два типа датчиков --}}
                    <div class="alert alert-info f-14 mb-15" style="padding-top:.75rem;padding-bottom:.75rem;line-height:1.5">
                        <div>{{ __('activity.device_help_ble') }}</div>
                        <div class="mt-05">⌚ {{ __('activity.device_help_watch') }}</div>
                    </div>

                    {{-- Блок согласия --}}
                    <div id="ble-consent-block-settings" style="{{ $hasHealthConsent ? 'display:none' : '' }}">
                        <div class="alert alert-info">
                            <strong>{{ __('activity.consent_title') }}</strong>
                        </div>
                        <div class="form mt-1 mb-1">
                            <label class="checkbox-item" style="align-items:flex-start">
                                <input type="checkbox" id="ble-consent-checkbox-settings">
                                <div class="custom-checkbox" style="margin-top:2px"></div>
                                <span class="f-14" style="line-height:1.5">
                                    {{ __('activity.consent_checkbox') }}
                                    (<a href="{{ route('personal_data_agreement') }}" target="_blank">{{ __('activity.consent_link') }}</a>)
                                </span>
                            </label>
                        </div>
                        <div id="ble-consent-error-settings" class="alert alert-danger" style="display:none">
                            {{ __('activity.consent_required') }}
                        </div>
                    </div>

                    {{-- Список привязанных устройств --}}
                    <div id="ble-device-list">
                        @forelse($devices as $device)
                        @php $devAccuracy = config('activity.device_accuracy.' . $device->protocol, config('activity.default_accuracy', 'none')); @endphp
                        <div class="card mb-1" data-device-id="{{ $device->id }}">
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="flex:1">
                                    <div class="b-600">{{ $device->name }}</div>
                                    @if($device->last_connected_at)
                                    <div class="f-13 cd3">{{ __('activity.last_connected_at') }}: {{ $device->last_connected_at->diffForHumans() }}</div>
                                    @endif
                                    <div class="f-13 cd3 mt-05">{{ __('activity.device_acc_' . $devAccuracy) }}</div>
                                </div>
                                <button class="btn btn-sm btn-outline-danger ble-device-delete"
                                        data-id="{{ $device->id }}" type="button">{{ __('activity.remove_device') }}</button>
                            </div>
                        </div>
                        @empty
                        <div class="f-14 cd3 mb-1" id="ble-no-devices-msg">{{ __('activity.no_devices') }}</div>
                        @endforelse
                    </div>

                    <div id="ble-add-device-status" class="alert mb-1" style="display:none"></div>

                    <button id="ble-btn-add-device" class="btn w-100" type="button"
                            {{ $hasHealthConsent ? '' : 'disabled style=opacity:.5' }}>
                        {{ __('activity.connect_device') }}
                    </button>

                    <div class="mt-1 f-14">
                        <a href="{{ route('activity.record') }}">{{ __('activity.record_training') }}</a>
                    </div>
                </div>
                @endif

                {{-- Зоны ЧСС --}}
                <div class="ramka mt-2">
                    <h3 class="-mt-05">{{ __('activity.zones_heading') }}</h3>

                    @if($usingDefaultHr)
                    <div class="alert alert-info f-14 mb-2">{{ __('activity.zones_no_data_hint') }}</div>
                    @else
                    <p class="f-13 cd3 mb-2">{{ __('activity.zones_description') }}</p>
                    @endif

                    {{-- Сегментированная шкала --}}
                    <div class="hr-zone-bar mb-2">
                        @foreach(['z1','z2','z3','z4','z5'] as $z)
                        <div class="hr-zone-bar__seg hr-zone-bar__seg--{{ $z }}"
                             title="{{ __('activity.' . $z . '_name') }}"></div>
                        @endforeach
                    </div>

                    {{-- Строки зон --}}
                    <div class="hr-zone-list">
                        @foreach(['z1','z2','z3','z4','z5'] as $z)
                        <div class="hr-zone-row hr-zone-row--{{ $z }}">
                            <div class="hr-zone-row__marker"></div>
                            <div class="hr-zone-row__info">
                                <div class="hr-zone-row__name">
                                    <span class="b-700">{{ strtoupper($z) }}</span>
                                    <span class="cd2"> · {{ __('activity.' . $z . '_name') }}</span>
                                </div>
                                <div class="hr-zone-row__bpm">
                                    {{ $zoneThresholds[$z]['low'] }}–{{ $zoneThresholds[$z]['high'] }} {{ __('activity.live_bpm') }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <p class="f-12 cd3 mt-15 mb-0">{{ __('activity.zones_karvonen_note') }}</p>
                </div>

            </div>
        </div>
    </div>

</x-voll-layout>

@if(config('activity.recording_open') || $user->isAdmin())
@vite(['resources/js/ble-activity.js'])
<script>
window.addEventListener('load', function () {
    if (typeof window.initBleDeviceManager === 'function') {
        window.initBleDeviceManager({
            hasHealthConsent: {{ $hasHealthConsent ? 'true' : 'false' }},
            connectingText:   @json(__('activity.adding_device')),
            removeText:       @json(__('activity.remove_device')),
            noDevicesText:    @json(__('activity.no_devices')),
            deviceAddedText:  @json(__('activity.device_added')),
            deviceAccText:    @json(__('activity.device_acc_none')),
        });
    }
});
</script>
@endif
