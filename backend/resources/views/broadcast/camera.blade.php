{{-- «Трансляция» в приложении: выбор площадки и старт эфира. Данные — BroadcastCameraController::index; ключ в HTML не попадает. --}}
@php
    $platformNames = [];
    foreach (['vk', 'rutube', 'youtube', 'telegram', 'custom'] as $pk) { $platformNames[$pk] = __('stream.dest_p_' . $pk); }
    $cfg = [
        'csrf'  => csrf_token(),
        'start' => url('/broadcast/camera'),
        's'     => [
            'openInApp' => __('stream.cam_open_in_app'), 'alt' => __('stream.cam_alt'), 'overlay' => __('stream.cam_overlay_link'),
            'copy' => __('stream.cam_copy'), 'keySaved' => __('stream.cam_key_saved'), 'keyError' => __('stream.cam_key_error'), 'noPluginApp' => __('stream.cam_no_plugin_app'), 'noPluginBrowser' => __('stream.cam_no_plugin_browser'), 'error' => __('stream.cam_error'), 'toSetup' => __('stream.cam_to_setup'),
        ],
    ];
@endphp
<x-voll-layout body_class="stream-camera-page">
    <x-slot name="title">{{ __('stream.cam_title') }}</x-slot>
    <x-slot name="h1">📡 {{ __('stream.cam_title') }} <x-beta-badge /></x-slot>

    <x-slot name="style">
        <style>
            .cam-card { margin-bottom: 1.6rem; }
            .cam-event { font-size:1.3rem; opacity:.7; }
            .cam-court { font-weight:700; font-size:1.9rem; margin:.2rem 0; }
            .cam-status { font-size:1.4rem; opacity:.8; }
            .cam-status.live { color:#d32f2f; font-weight:600; opacity:1; }
            .cam-dest { font-size:1.35rem; margin:.6rem 0 1rem; }
            .cam-today { font-size:1.2rem; padding:.2rem .8rem; border-radius:1rem; background:rgba(46,125,50,.18); margin-left:.6rem; }
            .cam-box { margin-top:1rem; font-size:1.4rem; line-height:1.4; display:none; }
            .cam-box.on { display:block; }
            .cam-fallback input { width:100%; font-size:1.3rem; margin:.5rem 0; }
            .cam-qr { margin-top:1rem; }
            .cam-qr svg { width:18rem; height:18rem; background:#fff; padding:1rem; border-radius:1rem; }
            .cam-vk { margin:.6rem 0 1rem; padding:1rem 1.2rem; border-radius:1rem; background:rgba(41,103,186,.12); font-size:1.3rem; line-height:1.4; }
            .cam-vk .cam-keyline { margin-top:.6rem; opacity:.8; }
            .cam-keyform { display:none; margin-top:.6rem; }
            .cam-keyform.on { display:block; }
            .cam-keymsg { margin-top:.4rem; }
            .cam-box input { width:100%; font-size:1.3rem; margin:.5rem 0; }
                    .cam-teams { display:flex; gap:1.6rem; flex-wrap:wrap; margin:.4rem 0 .6rem; }
            .cam-team { flex:1 1 14rem; min-width:0; }
            .cam-team-name { font-weight:600; font-size:1.4rem; }
            .cam-team-line { font-size:1.3rem; opacity:.75; line-height:1.35; }
        </style>
    </x-slot>

    <div class="container form">
        <x-beta-note />
        <div class="alert alert-info" style="margin-bottom:1.6rem">{{ __('stream.cam_hint') }}</div>
        @if(auth()->user()->isAdmin() || auth()->user()->role === 'organizer')
        <p><a class="blink b-600" href="{{ route('broadcast.channels') }}">{{ __('stream.ch_my') }}</a></p>
        @endif
        <div class="ramka" id="cam-noplugin" style="display:none"><p id="cam-noplugin-text"></p><p class="cam-event">{{ __('stream.cam_prism_hint') }}</p></div>
        @forelse($rows as $r)
        <div class="ramka cam-card" data-court="{{ $r['court_id'] }}">
            <div class="cam-event">{{ $r['event'] }}@if($r['date']) · {{ $r['date'] }}@endif @if($r['today'])<span class="cam-today">{{ __('stream.cam_today') }}</span>@endif</div>
            <div class="cam-court">{{ $r['court'] }}</div>
            <div class="cam-status {{ $r['status']['kind'] === 'live' ? 'live' : '' }}">{{ $r['status']['text'] }}</div>
            @if(collect($r['teams'])->contains(fn ($t) => !empty($t['lines'])))
            <div class="cam-teams">
                @foreach($r['teams'] as $t)
                <div class="cam-team"><div class="cam-team-name">{{ $t['name'] }}</div>@foreach($t['lines'] as $ln)<div class="cam-team-line">{{ $ln }}</div>@endforeach</div>
                @endforeach
            </div>
            @endif
            <div class="cam-dest">
                @if($r['dest'])
                {{ __('stream.cam_to', ['p' => ($platformNames[$r['dest']['platform']] ?? $r['dest']['platform']) . ($r['dest']['label'] ? ' · ' . $r['dest']['label'] : '')]) }}
                @elseif($r['need_channel'])
                {{ __('stream.cam_no_channel') }} — <a class="blink b-600" href="{{ $r['setup_url'] }}">{{ __('stream.cam_setup') }}</a>
                @else
                {{ __('stream.cam_no_dest') }} — <a class="blink b-600" href="{{ $r['setup_url'] }}">{{ __('stream.cam_setup') }}</a>
                @endif
            </div>
            @if($r['vk'])
            <div class="cam-vk">
                <div>{{ __('stream.cam_vk_hint') }}</div>
                @if($r['key_masked'])<div class="cam-keyline">{{ __('stream.cam_key_current') }}: <span class="cam-keymasked">{{ $r['key_masked'] }}</span></div>@endif
                @if($r['can_replace'])
                <button type="button" class="btn btn-small btn-outline cam-key-toggle" style="margin-top:.6rem">{{ __('stream.cam_key_replace') }}</button>
                <div class="cam-keyform">
                    <input type="password" class="cam-key-input" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="{{ __('stream.cam_key_new') }}">
                    <button type="button" class="btn btn-small cam-key-save">{{ __('stream.cam_key_save') }}</button>
                    <div class="cam-keymsg"></div>
                </div>
                @endif
            </div>
            @endif
            <button type="button" class="btn cam-start">{{ __('stream.cam_start') }}</button>
            <div class="cam-fallback" style="display:none" data-url="{{ $r['overlay_url'] }}">
                <div class="cam-event">{{ __('stream.cam_overlay_link') }}</div>
                <input type="text" readonly value="{{ $r['overlay_url'] }}">
                <button type="button" class="btn btn-small btn-secondary cam-copy">{{ __('stream.cam_copy') }}</button>
                <div class="cam-qr"></div>
            </div>
            <div class="cam-box"></div>
        </div>
        @empty
        <div class="ramka"><p>{{ __('stream.cam_empty') }}</p></div>
        @endforelse
    </div>

    <x-slot name="script">
        <script src="{{ asset('assets/qrcode.js') }}?v={{ filemtime(public_path('assets/qrcode.js')) }}"></script>
        <script>
        (function () {
            var CFG = @json($cfg), $ = jQuery, S = CFG.s;
            function box(card, build) { var b = card.find('.cam-box').empty().addClass('on'); build(b); }
            function overlayBlock(b, url) {
                var inp = $('<input type="text" readonly>').val(url);
                b.append($('<div>').text(S.overlay), inp,
                    $('<button type="button" class="btn btn-small btn-secondary">').text(S.copy).on('click', function () {
                        inp[0].select();
                        if (navigator.clipboard) { navigator.clipboard.writeText(url); } else { document.execCommand('copy'); }
                    }));
            }
            // Нет нативного плагина Stream (текущие версии из магазинов / обычный браузер): вместо старта — PRISM/OBS
            var hasPlugin = !!(window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Stream);
            if (!hasPlugin) {
                var inApp = document.documentElement.classList.contains('is-app') || !!window.Capacitor;
                $('#cam-noplugin-text').text(inApp ? S.noPluginApp : S.noPluginBrowser);
                $('#cam-noplugin').show();
                $('.cam-start').hide();
                $('.cam-fallback').each(function () {
                    var box = $(this).show();
                    try { var qr = qrcode(0, 'M'); qr.addData(String(box.data('url'))); qr.make(); box.find('.cam-qr').html(qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true })); } catch (e) {}
                });
                $(document).on('click', '.cam-copy', function () {
                    var inp = $(this).siblings('input')[0]; inp.select();
                    if (navigator.clipboard) { navigator.clipboard.writeText(inp.value); } else { document.execCommand('copy'); }
                });
            }
            $(document).on('click', '.cam-key-toggle', function () { $(this).siblings('.cam-keyform').toggleClass('on'); });
            $(document).on('click', '.cam-key-save', function () {
                var card = $(this).closest('[data-court]'), inp = card.find('.cam-key-input'), msg = card.find('.cam-keymsg'), key = $.trim(inp.val());
                if (!key) { return; }
                $.ajax({
                    url: CFG.start + '/' + card.data('court') + '/key', type: 'POST', dataType: 'json', data: { stream_key: key },
                    headers: { 'X-CSRF-TOKEN': CFG.csrf, 'Accept': 'application/json' }, xhrFields: { withCredentials: true },
                    success: function (r) {
                        inp.val(''); key = null;
                        card.find('.cam-keymasked').text(r.key_masked);
                        card.find('.cam-keyform').removeClass('on');
                        card.find('.cam-box').empty().addClass('on').append($('<p>').text(S.keySaved));
                    },
                    error: function (xhr) { msg.text((xhr.responseJSON && xhr.responseJSON.message) || S.keyError); }
                });
            });
            $(document).on('click', '.cam-start', function () {
                var card = $(this).closest('[data-court]'), id = card.data('court');
                // Ключ не логируем и в URL не кладём: только тело ответа → в плагин
                $.ajax({
                    url: CFG.start + '/' + id + '/start', type: 'POST', dataType: 'json', data: {},
                    headers: { 'X-CSRF-TOKEN': CFG.csrf, 'Accept': 'application/json' },
                    xhrFields: { withCredentials: true },
                    success: function (r) {
                        var plugin = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Stream;
                        if (plugin && plugin.open) {
                            plugin.open({ overlay_url: r.overlay_url, server_url: r.server_url, stream_key: r.stream_key, court_name: r.court_name, event_title: r.event_title });
                            r = null;
                            return;
                        }
                        box(card, function (b) {
                            b.append($('<p>').text(S.openInApp), $('<p>').text(S.alt));
                            overlayBlock(b, r.overlay_url);
                        });
                        r = null;
                    },
                    error: function (xhr) {
                        var j = xhr.responseJSON || {};
                        box(card, function (b) {
                            b.append($('<p>').text(j.message || S.error));
                            if (j.setup_url) { b.append($('<a class="blink b-600">').attr('href', j.setup_url).text(S.toSetup)); }
                        });
                    }
                });
            });
        })();
        </script>
    </x-slot>
</x-voll-layout>
