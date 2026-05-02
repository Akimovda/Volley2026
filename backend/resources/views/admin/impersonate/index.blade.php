{{-- resources/views/admin/impersonate/index.blade.php --}}
<x-voll-layout body_class="admin-impersonate-page">

    <x-slot name="title">Войти от имени пользователя — Админ</x-slot>
    <x-slot name="h1">Войти от имени пользователя</x-slot>
    <x-slot name="t_description">Режим просмотра от имени пользователя</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a itemprop="item" href="{{ route('admin.dashboard') }}">
                <span itemprop="name">Админ-панель</span>
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
            <div class="alert alert-success mb-3">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger mb-3">{{ session('error') }}</div>
        @endif

        <div class="ramka mb-3">
            <h2 class="mb-2">Поиск пользователя</h2>
            <p class="text-muted mb-3 f-14">Введите имя или email. Опасные действия (платежи, отвязка аккаунтов, удаление) будут заблокированы.</p>

            {{-- Поиск --}}
            <div style="position:relative;max-width:480px;" class="mb-3" id="imp-ac-wrap">
                <input type="text" id="imp-ac-input" autocomplete="off" class="form-control"
                       placeholder="Введите имя или email пользователя…">
                <div id="imp-ac-dd" class="form-select-dropdown trainer_dd"></div>
            </div>

            {{-- Результаты --}}
            <div id="imp-results"></div>
        </div>

    </div>

    <x-slot name="scripts">
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
            var roleLabel = item.role === 'admin' ? ' [ADMIN]' : (item.role === 'organizer' ? ' [Орг]' : '');
            var roleColor = item.role === 'admin' ? 'color:#c0392b' : (item.role === 'organizer' ? 'color:#2980b9' : '');
            var html = '<div class="ramka mb-2 d-flex fvc" style="gap:12px;">' +
                '<div style="flex:1">' +
                    '<strong>' + esc(item.label || item.name) + '</strong>' +
                    '<span style="' + roleColor + ';margin-left:6px;font-size:12px;">' + esc(roleLabel) + '</span><br>' +
                    '<span class="text-muted f-13">' + esc(item.email || '') + '</span>' +
                '</div>';

            if (item.role !== 'admin') {
                html += '<form method="POST" action="/admin/impersonate/start/' + item.id + '" style="margin:0">' +
                    '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                    '<button type="submit" class="btn btn-primary btn-small" ' +
                    'onclick="return confirm(\'Войти от имени: ' + esc(item.label || item.name) + '?\')">Войти как</button>' +
                '</form>';
            } else {
                html += '<span class="text-muted f-13">Запрещено</span>';
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
            dd.innerHTML = '<div class="city-message">Поиск…</div>';
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
                            results.innerHTML = '<p class="text-muted f-14">Ничего не найдено.</p>';
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
                        results.innerHTML = '<p class="text-danger f-14">Ошибка загрузки.</p>';
                    }
                });
            }, 200);
        });

        document.addEventListener('click', function (e) {
            var wrap = document.getElementById('imp-ac-wrap');
            if (wrap && !wrap.contains(e.target)) hideDd();
        });

        input.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideDd(); });
    })();
    </script>
    </x-slot>

</x-voll-layout>
