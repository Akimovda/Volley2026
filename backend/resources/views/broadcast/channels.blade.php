{{-- «Мои каналы трансляции». Данные — BroadcastChannelController::index; ключ в HTML не попадает (показ — POST /reveal). --}}
@php
    $platformNames = [];
    foreach (['vk', 'rutube', 'youtube', 'telegram', 'custom'] as $pk) { $platformNames[$pk] = __('stream.dest_p_' . $pk); }
    $cfg = [
        'csrf' => csrf_token(),
        'base' => url('/broadcast/channels'),
        'yt' => \App\Models\BroadcastDestination::YOUTUBE_SERVER,
        's' => [
            'show' => __('stream.dest_show'), 'hide' => __('stream.dest_hide'), 'error' => __('stream.error'),
            'deleteQ' => __('stream.ch_delete_q'), 'yes' => __('stream.yes'), 'cancel' => __('stream.cancel'),
            'add' => __('stream.ch_add'), 'save' => __('stream.ch_save'),
        ],
    ];
@endphp
<x-voll-layout body_class="stream-channels-page">
    <x-slot name="title">{{ __('stream.ch_h') }}</x-slot>
    <x-slot name="h1">📡 {{ __('stream.ch_h') }} <x-beta-badge /></x-slot>

    <x-slot name="style">
        <style>
            .bc-field { margin-bottom:1.3rem; }
            .bc-field label.bc-l { display:block; font-weight:600; font-size:1.4rem; margin-bottom:.4rem; }
            .bc-hint { font-size:1.25rem; opacity:.65; }
            .bc-tag { font-size:1.2rem; padding:.2rem .8rem; border-radius:1rem; background:rgba(128,128,128,.2); margin-left:.6rem; }
        </style>
    </x-slot>

    <div class="container form">
        <div class="alert alert-info mb-2">{{ __('stream.ch_hint') }}</div>
        <div class="ramka">
            @forelse($channels as $c)
            <div class="ch-row" data-ch="{{ $c['id'] }}" data-platform="{{ $c['platform'] }}" data-label="{{ $c['label'] }}" data-server="{{ $c['server_url'] }}" data-courts="{{ $c['courts'] }}" style="margin-bottom:1.4rem">
                <b>{{ $platformNames[$c['platform']] ?? $c['platform'] }}@if($c['label']) · {{ $c['label'] }}@endif</b>
                @if($c['is_default'])<span class="bc-tag">{{ __('stream.dest_default') }}</span>@endif
                <div class="bc-hint">{{ $c['server_url'] }} · {{ __('stream.ch_courts', ['n' => $c['courts']]) }}</div>
                <div class="bc-hint ch-key">••••••••</div>
                <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.4rem">
                    <button type="button" class="btn btn-small btn-secondary ch-show">{{ __('stream.dest_show') }}</button>
                    <button type="button" class="btn btn-small btn-secondary ch-edit">{{ __('stream.ch_edit') }}</button>
                    @unless($c['is_default'])<button type="button" class="btn btn-small btn-secondary ch-def">{{ __('stream.dest_default') }}</button>@endunless
                    <button type="button" class="btn btn-small btn-outline ch-del">{{ __('stream.dest_delete') }}</button>
                </div>
            </div>
            @empty
            <p>{{ __('stream.ch_none') }}</p>
            @endforelse
        </div>

        <div class="ramka" style="margin-top:2rem">
            <form id="ch-form" onsubmit="return false" autocomplete="off">
                <input type="hidden" id="ch-id" value="">
                <div class="bc-hint" style="margin-bottom:1rem">{{ __('stream.ch_recommend') }}</div>
                <div class="bc-field">
                    <label class="bc-l" for="ch-platform">{{ __('stream.dest_platform') }}</label>
                    <select id="ch-platform" name="ch_platform">
                        @foreach($platformNames as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="bc-field">
                    <label class="bc-l" for="ch-server">{{ __('stream.dest_server') }}</label>
                    <input type="text" id="ch-server" name="ch_server" maxlength="300" placeholder="rtmp://">
                </div>
                <div class="bc-field">
                    <label class="bc-l" for="ch-key">{{ __('stream.dest_key') }}</label>
                    <input type="password" id="ch-key" name="ch_key" maxlength="300" autocomplete="new-password" placeholder="{{ __('stream.dest_key_ph') }}">
                </div>
                <div class="bc-field">
                    <label class="bc-l" for="ch-label">{{ __('stream.dest_label') }}</label>
                    <input type="text" id="ch-label" name="ch_label" maxlength="100">
                </div>
                <div class="bc-field">
                    <label class="checkbox-item">
                        <input type="checkbox" id="ch-default" name="ch_default" value="1">
                        <div class="custom-checkbox"></div>
                        <span>{{ __('stream.dest_default') }}</span>
                    </label>
                </div>
                <button type="button" class="btn btn-small" id="ch-submit">{{ __('stream.ch_add') }}</button>
            </form>

            <h3 style="margin-top:2rem">{{ __('stream.dest_where_h') }}</h3>
            @foreach(['vk','rutube','youtube','telegram','custom'] as $pk)
            <details class="bc-where" style="margin-top:.6rem">
                <summary>{{ $platformNames[$pk] }}</summary>
                <div class="bc-hint" style="opacity:.85;margin-top:.5rem">{!! __('stream.dest_where_' . $pk) !!}</div>
            </details>
            @endforeach
        </div>
    </div>

    <x-slot name="script">
        <script>
        (function () {
            var CFG = @json($cfg), $ = jQuery, S = CFG.s;
            function call(method, url, data, ok, bad) {
                $.ajax({
                    url: url, type: 'POST', dataType: 'json', data: $.extend({ _method: method }, data || {}),
                    headers: { 'X-CSRF-TOKEN': CFG.csrf, 'Accept': 'application/json' }, xhrFields: { withCredentials: true },
                    success: function (r) { if (ok) { ok(r); } },
                    error: function (xhr) { if (bad) { bad(xhr); } else { alert((xhr.responseJSON && (xhr.responseJSON.message)) || S.error); } }
                });
            }
            function confirmThen(text, fn) {
                if (!window.swal) { if (confirm(text)) { fn(); } return; }
                swal({ text: text, icon: 'warning', buttons: { cancel: { text: S.cancel, visible: true }, confirm: { text: S.yes } } }).then(function (v) { if (v) { fn(); } });
            }
            function reload() { location.reload(); }
            $('#ch-platform').on('change', function () {
                var srv = $('#ch-server');
                if ($(this).val() === 'youtube') { if (!srv.val()) { srv.val(CFG.yt); } } else if (srv.val() === CFG.yt) { srv.val(''); }
            });
            $('#ch-submit').on('click', function () {
                var id = $('#ch-id').val();
                var data = { platform: $('#ch-platform').val(), server_url: $.trim($('#ch-server').val()), stream_key: $.trim($('#ch-key').val()),
                    label: $.trim($('#ch-label').val()), is_default: $('#ch-default').is(':checked') ? 1 : 0 };
                call(id ? 'PATCH' : 'POST', CFG.base + (id ? '/' + id : ''), data, reload);
            });
            $('.ch-edit').on('click', function () {
                var row = $(this).closest('.ch-row');
                $('#ch-id').val(row.data('ch')); $('#ch-platform').val(row.data('platform')); $('#ch-server').val(row.data('server'));
                $('#ch-label').val(row.data('label')); $('#ch-key').val('').attr('placeholder', @json(__('stream.ch_key_keep')));
                $('#ch-submit').text(S.save);
                $('html,body').animate({ scrollTop: $('#ch-form').offset().top - 120 }, 200);
            });
            $('.ch-show').on('click', function () {
                var btn = $(this), row = btn.closest('.ch-row'), el = row.find('.ch-key');
                if (btn.data('shown')) { el.text('••••••••'); btn.data('shown', 0).text(S.show); return; }
                call('POST', CFG.base + '/' + row.data('ch') + '/reveal', {}, function (r) { el.text(r.stream_key); btn.data('shown', 1).text(S.hide); });
            });
            $('.ch-def').on('click', function () { call('POST', CFG.base + '/' + $(this).closest('.ch-row').data('ch') + '/default', {}, reload); });
            $('.ch-del').on('click', function () {
                var id = $(this).closest('.ch-row').data('ch');
                confirmThen(S.deleteQ, function () {
                    call('DELETE', CFG.base + '/' + id, {}, reload, function (xhr) {
                        var j = xhr.responseJSON || {};
                        if (xhr.status === 409 && j.code === 'in_use') {
                            confirmThen(j.message, function () { call('DELETE', CFG.base + '/' + id, { confirm: 1 }, reload); });
                        } else { alert(j.message || S.error); }
                    });
                });
            });
        })();
        </script>
    </x-slot>
</x-voll-layout>
