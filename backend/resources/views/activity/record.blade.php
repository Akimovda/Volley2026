<x-voll-layout body_class="activity-record-page">
    <x-slot name="title">{{ __('activity.record_page_title') }} — VolleyPlay</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.athlete') }}" itemprop="item"><span itemprop="name">{{ __('activity.page_title') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('activity.record_page_title') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">
        <div class="row">
            <div class="col-lg-6 col-lg-offset-3">

                {{-- Баннер для браузера (скрыт по умолчанию, JS показывает если не Capacitor) --}}
                <div id="ble-not-app" class="alert alert-info mb-2" style="display:none">
                    {{ __('activity.available_in_app_only') }}
                </div>

                <div class="ramka">
                    <h1 class="-mt-05">{{ __('activity.record_page_title') }}</h1>

                    {{-- Блок согласия на обработку данных о здоровье --}}
                    <div id="ble-consent-block" style="{{ $hasHealthConsent ? 'display:none' : '' }}">
                        <div class="alert alert-info">
                            <strong>{{ __('activity.consent_title') }}</strong>
                        </div>
                        <div class="form mt-1 mb-1">
                            <label class="checkbox-item" style="align-items:flex-start">
                                <input type="checkbox" id="ble-consent-checkbox">
                                <div class="custom-checkbox" style="margin-top:2px"></div>
                                <span class="f-14" style="line-height:1.5">
                                    {{ __('activity.consent_checkbox') }}
                                    (<a href="{{ route('personal_data_agreement') }}" target="_blank">{{ __('activity.consent_link') }}</a>)
                                </span>
                            </label>
                        </div>
                        <div id="ble-consent-error" class="alert alert-danger" style="display:none">
                            {{ __('activity.consent_required') }}
                        </div>
                    </div>

                    {{-- Основной блок управления --}}
                    <div id="ble-controls">

                        {{-- Выбор мероприятия (если не передан через ?occurrence) --}}
                        @if(!$occurrenceId && $occurrences->isNotEmpty())
                        <div class="form mb-2">
                            <label class="f-14" style="opacity:.7">{{ __('activity.select_occurrence') }}</label>
                            <select id="ble-occurrence-select" name="occurrence_id" class="form-control mt-05">
                                <option value="">{{ __('activity.no_occurrence') }}</option>
                                @foreach($occurrences as $occ)
                                <option value="{{ $occ['id'] }}">{{ $occ['starts_at'] }} — {{ $occ['title'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        @elseif($occurrenceId)
                        <input type="hidden" id="ble-occurrence-select" value="{{ $occurrenceId }}">
                        @endif

                        {{-- Подсказка когда нет привязанных устройств --}}
                        <div id="ble-no-device-hint" class="alert alert-info mb-1" style="display:none">
                            {!! __('activity.connect_in_settings_hint') !!}
                            <a href="{{ route('profile.athlete') }}">{{ __('activity.my_devices') }}</a>
                        </div>

                        {{-- ФАЗА: idle --}}
                        <div id="ble-phase-idle">
                            <div id="ble-connect-error" class="alert alert-danger mb-1" style="display:none"></div>
                            <button id="ble-btn-connect" class="btn w-100">{{ __('activity.connect_sensor') }}</button>
                        </div>

                        {{-- ФАЗА: connecting --}}
                        <div id="ble-phase-connecting" style="display:none">
                            <div class="alert alert-info">{{ __('activity.connecting') }}</div>
                        </div>

                        {{-- ФАЗА: connected --}}
                        <div id="ble-phase-connected" style="display:none">
                            <div class="alert alert-success">
                                {{ __('activity.connected') }}: <strong id="ble-device-name"></strong>
                            </div>
                            <button id="ble-btn-start" class="btn w-100 mt-1">{{ __('activity.start') }}</button>
                        </div>

                        {{-- ФАЗА: recording --}}
                        <div id="ble-phase-recording" style="display:none">
                            <div style="text-align:center;padding:16px 0">
                                <div id="ble-zone-bar" style="height:6px;border-radius:3px;background:#ccc;margin-bottom:12px;transition:background .4s"></div>
                                <div style="font-size:4rem;font-weight:700;line-height:1" id="ble-bpm">–</div>
                                <div style="font-size:1rem;opacity:.6;margin-top:4px">{{ __('activity.live_bpm') }}</div>
                                <div style="font-size:1.1rem;margin-top:8px;font-weight:600" id="ble-zone">–</div>
                                <div style="font-size:.9rem;opacity:.55;margin-top:6px" id="ble-timer">00:00</div>
                            </div>
                            <button id="ble-btn-stop" class="btn btn-danger w-100">{{ __('activity.stop') }}</button>
                        </div>

                        {{-- ФАЗА: reconnecting --}}
                        <div id="ble-phase-reconnecting" style="display:none">
                            <div class="alert alert-warning">{{ __('activity.reconnecting') }}</div>
                            <div style="text-align:center;padding:8px 0">
                                <div style="font-size:3rem;font-weight:700;opacity:.4" id="ble-bpm">–</div>
                            </div>
                            <button id="ble-btn-stop" class="btn btn-danger w-100">{{ __('activity.stop') }}</button>
                        </div>

                        {{-- ФАЗА: stopping --}}
                        <div id="ble-phase-stopping" style="display:none">
                            <div class="alert alert-info">{{ __('activity.saving') }}</div>
                        </div>

                        {{-- ФАЗА: done — итоги --}}
                        <div id="ble-phase-done" style="display:none">
                            <h2 class="-mt-05">{{ __('activity.session_summary') }}</h2>
                            <table style="width:100%;border-collapse:collapse" class="f-15">
                                <tr>
                                    <td style="padding:6px 0;opacity:.7">{{ __('activity.avg_hr') }}</td>
                                    <td style="text-align:right;font-weight:600" id="ble-sum-avg">–</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;opacity:.7">{{ __('activity.max_hr') }}</td>
                                    <td style="text-align:right;font-weight:600" id="ble-sum-max">–</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;opacity:.7">{{ __('activity.min_hr') }}</td>
                                    <td style="text-align:right;font-weight:600" id="ble-sum-min">–</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;opacity:.7">{{ __('activity.duration') }}</td>
                                    <td style="text-align:right;font-weight:600" id="ble-sum-duration">–</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;opacity:.7">{{ __('activity.load_score') }}</td>
                                    <td style="text-align:right;font-weight:600" id="ble-sum-load">–</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;opacity:.7">{{ __('activity.samples_count') }}</td>
                                    <td style="text-align:right;font-weight:600" id="ble-sum-samples">–</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;opacity:.7">{{ __('activity.calories') }}</td>
                                    <td style="text-align:right;font-weight:600" id="ble-sum-calories">–</td>
                                </tr>
                            </table>
                            <div class="mt-1" id="ble-sum-zones"></div>
                            <button id="ble-btn-done" class="btn w-100 mt-2">{{ __('activity.done_btn') }}</button>
                        </div>

                    </div>{{-- /#ble-controls --}}
                </div>

                {{-- Зоны ЧСС для справки --}}
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('activity.zones_heading') }}</h2>
                    @php
                    $zoneNames = [
                        'z1' => __('activity.z1_name'),
                        'z2' => __('activity.z2_name'),
                        'z3' => __('activity.z3_name'),
                        'z4' => __('activity.z4_name'),
                        'z5' => __('activity.z5_name'),
                    ];
                    $zoneColors = ['z1'=>'#4caf50','z2'=>'#8bc34a','z3'=>'#ffc107','z4'=>'#ff9800','z5'=>'#f44336'];
                    @endphp
                    @foreach($zones as $key => $z)
                    <div style="display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid rgba(0,0,0,.06)">
                        <div style="width:10px;height:10px;border-radius:50%;background:{{ $zoneColors[$key] }};flex-shrink:0"></div>
                        <div style="flex:1">
                            <span style="font-weight:600">{{ strtoupper($key) }}</span>
                            <span style="opacity:.7;font-size:.9em"> — {{ $zoneNames[$key] }}</span>
                        </div>
                        <div class="f-14" style="opacity:.8">{{ $z['low'] }}–{{ $z['high'] }} уд/мин</div>
                    </div>
                    @endforeach
                    <div class="f-13 mt-1" style="opacity:.55">
                        {{ __('activity.zones_karvonen_note') }}<br>
                        ЧСС макс: {{ $maxHr }}, покой: {{ $restingHr }} уд/мин
                    </div>
                </div>

            </div>
        </div>
    </div>

</x-voll-layout>

@php
$zoneNamesJs = [
    'z1' => __('activity.z1_name'),
    'z2' => __('activity.z2_name'),
    'z3' => __('activity.z3_name'),
    'z4' => __('activity.z4_name'),
    'z5' => __('activity.z5_name'),
];
@endphp
<script>
window.__activityConfig = {
    zones:              @json($zones),
    zoneNames:          @json($zoneNamesJs),
    maxHr:              {{ $maxHr }},
    restingHr:          {{ $restingHr }},
    occurrenceId:       {{ $occurrenceId ?? 'null' }},
    errorNoSession:     @json(__('activity.error_no_session')),
    hasHealthConsent:   {{ $hasHealthConsent ? 'true' : 'false' }},
    weightForCalories:  @json(__('activity.weight_for_calories')),
    setWeightUrl:       @json(route('profile.athlete')),
    pairedDevices:      @json($pairedDevices),
};
</script>
@vite(['resources/js/ble-activity.js'])
<script>
window.addEventListener('load', function () {
    if (typeof window.initBleActivity === 'function') {
        window.initBleActivity(window.__activityConfig);
    }
});
</script>
