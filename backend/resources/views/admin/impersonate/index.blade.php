{{-- resources/views/admin/impersonate/index.blade.php --}}
<x-voll-layout body_class="admin-impersonate-page">

    <x-slot name="title">{{ __('admin.imp_title') }} — {{ __('admin.breadcrumb_dashboard') }}</x-slot>
    <x-slot name="h1">{{ __('admin.imp_title') }}</x-slot>
    <x-slot name="t_description">{{ __('admin.imp_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a itemprop="item" href="{{ route('admin.dashboard') }}">
                <span itemprop="name">{{ __('admin.breadcrumb_dashboard') }}</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Impersonation</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">

        @if(session('status'))
			<div class="ramka">
            <div class="alert alert-success">{{ session('status') }}</div>
			</div>
        @endif
        @if(session('error'))
			<div class="ramka">
            <div class="alert alert-danger">{{ session('error') }}</div>
			</div>
        @endif

        <div class="ramka">
            <h2 class="-mt-05">{{ __('admin.imp_search_label') }}</h2>
            <p>{{ __('admin.imp_search_hint') }}</p>

            {{-- Поиск --}}
            <div style="position:relative; max-width: 50rem;" class="form mt-2" id="imp-ac-wrap">
                <input type="text" id="imp-ac-input" autocomplete="off" class="form-control"
                       placeholder="{{ __('admin.imp_search_ph') }}">
                <div id="imp-ac-dd" class="form-select-dropdown trainer_dd"></div>
            </div>

            {{-- Результаты --}}
            <div id="imp-results"></div>
        </div>

    </div>

    <script>
    (function () {
        var input   = document.getElementById('imp-ac-input');
        var dd      = document.getElementById('imp-ac-dd');
        var results = document.getElementById('imp-results');
        var timer   = null;

        if (!input) return;

        function showDd() { dd.classList.add('form-select-dropdown--active'); }
        function hideDd() { dd.classList.remove('form-select-dropdown--active'); }
        function esc(s)   { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

        function renderUser(item) {
            var roleLabel = item.role === 'admin' ? ' [' + @json(__('admin.role_admin')) + ']' : (item.role === 'organizer' ? ' [' + @json(__('admin.role_organizer')) + ']' : '');
            var roleColor = item.role === 'admin' ? 'color:#c0392b' : (item.role === 'organizer' ? 'color:#2980b9' : '');
            var botBadge = item.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">🤖 бот</span>' : '';
            var html = '<div class="card mt-2 d-flex fvc" style="gap:12px;">' +
                '<div style="flex:1">' +
                    '<strong>' + esc(item.label || item.name) + '</strong>' +
                    '<strong class="f-16" style="' + roleColor + ';margin-left:6px;">' + esc(roleLabel) + '</strong>' + botBadge + '<br>' +
                '</div>';

            if (item.role !== 'admin') {
                html += '<form method="POST" action="/admin/impersonate/start/' + item.id + '" style="margin:0">' +
                    '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                    '<button type="submit" class="btn btn-primary btn-small btn-imp-confirm" ' +
                    'data-name="' + esc(item.label || item.name) + '">' + @json(__('admin.imp_btn_start')) + '</button>' +
                '</form>';
            } else {
                html += '<span class="text-muted f-16">' + @json(__('admin.imp_forbidden')) + '</span>';
            }

            html += '</div>';
            return html;
        }

        input.addEventListener('keyup', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) {
                hideDd();
                results.innerHTML = '';
                return;
            }
            dd.innerHTML = '<div class="city-message">' + @json(__('admin.users_search_searching')) + '</div>';
            showDd();
            timer = setTimeout(function () {
                jQuery.ajax({
                    url: '{{ route('admin.impersonate.search') }}?q=' + encodeURIComponent(q),
                    method: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        hideDd();
                        dd.innerHTML = '';
                        var items = data.items || [];
                        if (!items.length) {
                            results.innerHTML = '<p class="text-muted f-14">' + @json(__('admin.users_search_no_results')) + '.</p>';
                            return;
                        }
                        var html = '';
                        items.forEach(function (item) {
                            html += renderUser(item);
                        });
                        results.innerHTML = html;
                    },
                    error: function () {
                        hideDd();
                        results.innerHTML = '<p class="text-danger f-14">' + @json(__('admin.imp_load_error')) + '</p>';
                    }
                });
            }, 200);
        });

        document.addEventListener('click', function (e) {
            var wrap = document.getElementById('imp-ac-wrap');
            if (wrap && !wrap.contains(e.target)) hideDd();
        });

        input.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideDd(); });

        results.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-imp-confirm');
            if (!btn) return;
            e.preventDefault();
            var form = btn.closest('form');
            var name = btn.dataset.name || '';
            swal({
                title: @json(__('admin.imp_confirm_title', ['name' => '__NAME__'])).replace('__NAME__', name),
                icon: 'warning',
                buttons: {
                    cancel: { text: @json(__('admin.btn_cancel')), value: null, visible: true, closeModal: true },
                    confirm: { text: @json(__('admin.imp_confirm_yes')), value: true, visible: true, closeModal: true }
                }
            }).then(function (value) {
                if (value && form) form.submit();
            });
        });
    })();
    </script>

</x-voll-layout>
