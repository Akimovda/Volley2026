{{-- resources/views/auth/max.blade.php — вход / привязка через MAX по ссылке-приглашению к боту --}}
<x-voll-layout body_class="auth-page auth-login">
    <x-slot name="title">{{ $intent === 'link' ? __('auth.max_link_title') : __('auth.max_title') }}</x-slot>
    <x-slot name="description">{{ __('auth.max_description') }}</x-slot>
    <x-slot name="h1">{{ $intent === 'link' ? __('auth.max_link_title') : __('auth.max_title') }}</x-slot>
    <x-slot name="h2">{{ __('auth.max_h2') }}</x-slot>
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('auth.max_title') }}</span>
            <meta itemprop="position" content="1">
        </li>
    </x-slot>
    <x-slot name="t_description">{{ __('auth.max_description') }}</x-slot>

    <div class="container">
        <div class="ramka" style="max-width:62rem">
            <ol class="list" style="list-style:decimal;padding-left:2rem">
                <li class="mb-1">{{ __('auth.max_step_open') }}</li>
                <li class="mb-1">{{ __('auth.max_step_start') }}</li>
                <li class="mb-1">{{ __('auth.max_step_code') }}</li>
                <li class="mb-1">{{ __('auth.max_step_back') }}</li>
            </ol>

            <div class="mt-2 mb-2" style="display:flex;flex-wrap:wrap;justify-content:center;gap:1.2rem">
                <a href="{{ $link }}" target="_blank" rel="noopener" class="btn" id="max-open-btn">{{ __('auth.max_open_btn') }}</a>
                <button type="button" class="btn btn-secondary" id="max-copy-btn">{{ __('auth.max_copy_btn') }}</button>
            </div>

            <div class="text-center mb-2">
                <div class="f-14 text-muted">{{ __('auth.max_code_label') }}</div>
                <div class="b-700" style="font-size:5rem;letter-spacing:1rem;line-height:1.1" id="max-code" aria-live="polite">{{ $code }}</div>
            </div>

            <div class="alert alert-info text-center" id="max-status" role="status">{{ __('auth.max_status_pending') }}</div>
            <p class="f-14 text-muted text-center" id="max-hint">{{ __('auth.max_hint', ['min' => $ttlMin]) }}</p>
            <div class="text-center d-none" id="max-restart">
                <a href="{{ route('auth.max.page') }}" class="btn btn-secondary">{{ __('auth.max_restart_btn') }}</a>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var statusUrl = @json($statusUrl);
        var link      = @json($link);
        var texts = {
            pending:       @json(__('auth.max_status_pending')),
            awaiting_code: @json(__('auth.max_status_awaiting_code')),
            done:          @json(__('auth.max_status_done')),
            expired:       @json(__('auth.max_status_expired')),
            denied:        @json(__('auth.max_status_denied')),
            error:         @json(__('auth.max_status_error'))
        };
        var box = document.getElementById('max-status'), restart = document.getElementById('max-restart');
        var stopped = false, failures = 0;

        function set(state, msg) {
            box.textContent = msg || texts[state] || '';
            box.className = 'alert text-center ' + (state === 'done' ? 'alert-success' : (state === 'expired' || state === 'denied' || state === 'error' ? 'alert-danger' : 'alert-info'));
        }
        function stop(state, msg) { stopped = true; set(state, msg); restart.classList.remove('d-none'); }

        function poll() {
            if (stopped) return;
            fetch(statusUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    failures = 0;
                    if (j.state === 'done') { stopped = true; set('done'); window.location.href = j.redirect || '/events'; return; }
                    if (j.state === 'expired' || j.state === 'denied') { stop(j.state); return; }
                    if (j.state === 'error') { stop('error', j.message); return; }
                    set(j.state);
                    setTimeout(poll, 2000);
                })
                .catch(function () { if (++failures > 15) { stop('error'); } else { setTimeout(poll, 3000); } });
        }
        setTimeout(poll, 1500);

        document.getElementById('max-copy-btn').addEventListener('click', function () {
            var btn = this, old = btn.textContent;
            var done = function () { btn.textContent = @json(__('auth.max_copied')); setTimeout(function () { btn.textContent = old; }, 1800); };
            if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(link).then(done, function () { window.prompt('', link); }); }
            else { window.prompt('', link); }
        });
    })();
    </script>
</x-voll-layout>
