{{-- «Трансляция» в приложении: выбор площадки и старт эфира. Данные — BroadcastCameraController::index; ключ в HTML не попадает. --}}
@php
    $platformNames = [];
    foreach (['vk', 'rutube', 'youtube', 'telegram', 'custom'] as $pk) { $platformNames[$pk] = __('stream.dest_p_' . $pk); }
    $cfg = [
        'csrf'  => csrf_token(),
        'start' => url('/broadcast/camera'),
        's'     => [
            'openInApp' => __('stream.cam_open_in_app'), 'alt' => __('stream.cam_alt'), 'overlay' => __('stream.cam_overlay_link'),
            'copy' => __('stream.cam_copy'), 'error' => __('stream.cam_error'), 'toSetup' => __('stream.cam_to_setup'),
        ],
    ];
@endphp
<x-voll-layout body_class="stream-camera-page">
    <x-slot name="title">{{ __('stream.cam_title') }}</x-slot>
    <x-slot name="h1">📡 {{ __('stream.cam_title') }}</x-slot>
    <x-slot name="h2">{{ __('stream.cam_hint') }}</x-slot>

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
            .cam-box input { width:100%; font-size:1.3rem; margin:.5rem 0; }
        </style>
    </x-slot>

    <div class="container form">
        @forelse($rows as $r)
        <div class="ramka cam-card" data-court="{{ $r['court_id'] }}">
            <div class="cam-event">{{ $r['event'] }}@if($r['date']) · {{ $r['date'] }}@endif @if($r['today'])<span class="cam-today">{{ __('stream.cam_today') }}</span>@endif</div>
            <div class="cam-court">{{ $r['court'] }}</div>
            <div class="cam-status {{ $r['status']['kind'] === 'live' ? 'live' : '' }}">{{ $r['status']['text'] }}</div>
            <div class="cam-dest">
                @if($r['dest'])
                {{ __('stream.cam_to', ['p' => ($platformNames[$r['dest']['platform']] ?? $r['dest']['platform']) . ($r['dest']['label'] ? ' · ' . $r['dest']['label'] : '')]) }}
                @else
                {{ __('stream.cam_no_dest') }} — <a class="blink b-600" href="{{ $r['setup_url'] }}">{{ __('stream.cam_setup') }}</a>
                @endif
            </div>
            <button type="button" class="btn cam-start">{{ __('stream.cam_start') }}</button>
            <div class="cam-box"></div>
        </div>
        @empty
        <div class="ramka"><p>{{ __('stream.cam_empty') }}</p></div>
        @endforelse
    </div>

    <x-slot name="script">
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
