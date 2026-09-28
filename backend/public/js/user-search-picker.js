// Поиск игрока(ов) по /api/users/search с чипами — тот же паттерн, что и поиск
// тренеров на странице создания мероприятия (events-create.js), но обобщённый
// и переиспользуемый. Используется там, где раньше был ручной ввод ID
// пользователя(ей) (напр. /coupons/templates, /subscriptions).
(function () {
    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function debounce(fn, wait) {
        var t;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }

    // opts: { inputId, dropdownId, chipsId, hiddenName, multi, searchUrl }
    window.initUserSearchPicker = function (opts) {
        var input = document.getElementById(opts.inputId);
        var dd = document.getElementById(opts.dropdownId);
        var chips = document.getElementById(opts.chipsId);
        if (!input || !dd || !chips) return;

        var hiddenName = opts.hiddenName;
        var multi = !!opts.multi;
        var searchUrl = opts.searchUrl || '/ajax/users/search';

        function currentIds() {
            var ins = chips.querySelectorAll('input[data-user-hidden]');
            var out = [];
            for (var i = 0; i < ins.length; i++) out.push(Number(ins[i].value));
            return out;
        }

        function showDd() { dd.classList.add('form-select-dropdown--active'); }
        function hideDd() { dd.classList.remove('form-select-dropdown--active'); }
        function clearDd() { dd.innerHTML = ''; }

        function addChip(id, label) {
            id = Number(id || 0);
            if (!id) return;
            if (!multi) {
                chips.innerHTML = '';
            } else if (currentIds().indexOf(id) !== -1) {
                return;
            }

            var span = document.createElement('span');
            span.className = 'd-flex mb-1 between f-16 fvc pl-1 pr-1';

            var t = document.createElement('span');
            t.textContent = label ? String(label) : ('#' + id);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-small btn-secondary';
            btn.textContent = '×';
            btn.addEventListener('click', function () { removeChip(id); });

            span.appendChild(t);
            span.appendChild(btn);

            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = hiddenName;
            hidden.value = String(id);
            hidden.setAttribute('data-user-hidden', String(id));

            chips.appendChild(span);
            chips.appendChild(hidden);
        }

        function removeChip(id) {
            id = Number(id || 0);
            var ins = chips.querySelectorAll('input[data-user-hidden]');
            for (var i = 0; i < ins.length; i++) {
                if (Number(ins[i].value) === id) {
                    var span = ins[i].previousElementSibling;
                    if (span) span.parentNode.removeChild(span);
                    ins[i].parentNode.removeChild(ins[i]);
                    break;
                }
            }
        }

        function fetchUsers(q, cb) {
            fetch(searchUrl + '?q=' + encodeURIComponent(q || ''), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(cb)
                .catch(function () { cb(null); });
        }

        function render(items) {
            var html = [];
            for (var i = 0; i < items.length; i++) {
                var it = items[i] || {};
                var label = it.label || it.name || ('#' + it.id);
                html.push('<div class="form-select-option" data-id="' + escHtml(it.id) + '" data-label="' + escHtml(label) + '">' + escHtml(label) + '</div>');
            }
            dd.innerHTML = html.join('');
            var optEls = dd.querySelectorAll('[data-id]');
            for (var j = 0; j < optEls.length; j++) {
                optEls[j].addEventListener('click', function () {
                    addChip(this.getAttribute('data-id'), this.getAttribute('data-label'));
                    input.value = '';
                    hideDd();
                });
            }
        }

        var run = debounce(function () {
            var q = (input.value || '').trim();
            if (q.length === 0) { clearDd(); hideDd(); return; }
            if (q.length < 2) { showDd(); dd.innerHTML = '<div class="city-message">Введите ещё символы…</div>'; return; }
            showDd();
            dd.innerHTML = '<div class="city-message">Поиск…</div>';
            fetchUsers(q, function (data) {
                if (!data) { dd.innerHTML = '<div class="city-message">Не удалось загрузить список.</div>'; return; }
                var items = Array.isArray(data) ? data : (data.items || []);
                if (!items.length) { dd.innerHTML = '<div class="city-message">Ничего не найдено.</div>'; return; }
                render(items.slice(0, 10));
            });
        }, 220);

        input.addEventListener('input', run);
        input.addEventListener('focus', function () { if ((input.value || '').trim().length >= 2) run(); });
        document.addEventListener('click', function (e) { if (e.target !== input && !dd.contains(e.target)) hideDd(); });
        input.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideDd(); });
    };
})();
