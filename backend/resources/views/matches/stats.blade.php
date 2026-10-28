{{-- resources/views/matches/stats.blade.php — расширенная статистика матча --}}
<x-voll-layout body_class="match-stats-page">

    <x-slot name="title">{{ __('match_stats.page_title') }} — {{ $stats['teams']['home']['name'] }} / {{ $stats['teams']['away']['name'] }}</x-slot>
    <x-slot name="h1">{{ __('match_stats.page_title') }}</x-slot>
    <x-slot name="h2">{{ $event->title }}</x-slot>
    <x-slot name="t_description">{{ $stats['teams']['home']['name'] }} — {{ $stats['teams']['away']['name'] }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ $resultsUrl }}" itemprop="item"><span itemprop="name">{{ __('match_stats.breadcrumb_results') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('match_stats.page_title') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <x-slot name="style">
        @include('matches.stats._css')
    </x-slot>

    <div class="container mx-page">
        <div id="mx-live" data-live="{{ $isLive ? 1 : 0 }}" data-highlight="{{ $highlight }}">
            <div class="ramka">
                @include('matches.stats._head', ['isLive' => $isLive])
                <div class="mx-actions">
                    @if($isCompleted)
                    <button type="button" class="btn btn-secondary btn-small" onclick="mxShare({{ $match->id }}, {{ json_encode($stats['teams']['home']['name'] . ' vs ' . $stats['teams']['away']['name']) }})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 8l5-5 5 5M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg>{{ __('match_stats.btn_share') }}</button>
                    <a href="{{ route('match.stats.pdf', $match) }}" data-file-download class="btn btn-secondary btn-small"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5M9 13h6M9 17h6"/></svg>{{ __('match_stats.btn_pdf') }}</a>
                    @else
                    <span class="mx-note" style="margin:0">{{ __('match_stats.live_note') }}</span>
                    @endif
                </div>
            </div>

            <div class="mx-tabs" role="tablist">
                <button type="button" class="mx-tab is-active" data-tab="overview" role="tab">{{ __('match_stats.tab_overview') }}</button>
                <button type="button" class="mx-tab" data-tab="players" role="tab">{{ __('match_stats.tab_players') }}</button>
                <button type="button" class="mx-tab" data-tab="protocol" role="tab">{{ __('match_stats.tab_protocol') }}</button>
                @if($stats['lineup'])
                <button type="button" class="mx-tab" data-tab="lineup" role="tab">{{ __('match_stats.tab_lineup') }}</button>
                @endif
                @if(!empty($stats['rotations']))
                <button type="button" class="mx-tab" data-tab="rotations" role="tab">{{ __('match_stats.tab_rotations') }}</button>
                @endif
            </div>

            <div class="mx-pane is-active" data-pane="overview">
                @include('matches.stats._overview', ['uid' => 'pg'])
            </div>
            <div class="mx-pane" data-pane="players">
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('match_stats.tab_players') }}</h2>
                    @include('matches.stats._players', ['highlight' => $highlight])
                </div>
            </div>
            <div class="mx-pane" data-pane="protocol">
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('match_stats.tab_protocol') }}</h2>
                    @include('matches.stats._protocol', ['canSeeRecorder' => $canSeeRecorder])
                </div>
            </div>
            @if($stats['lineup'])
            <div class="mx-pane" data-pane="lineup">
                @include('matches.stats._lineup')
            </div>
            @endif
            @if(!empty($stats['rotations']))
            <div class="mx-pane" data-pane="rotations">
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('match_stats.rot_title') }}</h2>
                    @include('matches.stats._rotations')
                </div>
            </div>
            @endif
        </div>
    </div>

    <x-slot name="script">
    <script>
    (function () {
        var state = { tab: 'overview', chartSet: null, scope: 'all', team: '', player: '', sortAsc: false };
        var tabs = ['overview', 'players', 'protocol', 'lineup', 'rotations'];

        function root() { return document.getElementById('mx-live'); }
        function q(sel, ctx) { return Array.prototype.slice.call((ctx || root()).querySelectorAll(sel)); }

        function apply() {
            var r = root(); if (!r) return;
            if (!q('[data-pane="' + state.tab + '"]').length) state.tab = 'overview';
            q('.mx-tab').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-tab') === state.tab); });
            q('.mx-pane').forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-pane') === state.tab); });

            var setBtns = q('[data-mx-seg="chart"] button');
            if (setBtns.length && !setBtns.some(function (b) { return b.getAttribute('data-set') === String(state.chartSet); })) state.chartSet = setBtns[0].getAttribute('data-set');
            setBtns.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-set') === String(state.chartSet)); });
            if (setBtns.length) q('.mx-chart-wrap').forEach(function (w) { w.classList.toggle('is-active', w.getAttribute('data-chart-set') === String(state.chartSet)); });

            var scopeBtns = q('[data-mx-seg="players"] button');
            if (scopeBtns.length && !scopeBtns.some(function (b) { return b.getAttribute('data-scope') === state.scope; })) state.scope = 'all';
            scopeBtns.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-scope') === state.scope); });
            q('[data-mx-ptable] tbody').forEach(function (tb) { tb.style.display = tb.getAttribute('data-scope') === state.scope ? '' : 'none'; });
            sortTables();

            q('[data-mx-filter="team"]').forEach(function (s) { s.value = state.team; });
            q('[data-mx-filter="player"]').forEach(function (s) { s.value = state.player; });
            filterProtocol();
            if (window.mxRotApply) { window.mxRotApply(); }
        }

        function sortTables() {
            q('[data-mx-ptable] tbody').forEach(function (tb) {
                var rows = q('tr[data-player]', tb);
                if (rows.length < 2) return;
                var dir = state.sortAsc ? 1 : -1;
                rows.map(function (tr, i) { return { tr: tr, i: i, v: +tr.querySelector('.mx-bal').getAttribute('data-bal') }; })
                    .sort(function (a, b) { return (a.v - b.v) * dir || a.i - b.i; })
                    .forEach(function (x) { tb.appendChild(x.tr); });
            });
            q('[data-mx-sort]').forEach(function (th) { th.textContent = th.textContent.replace(/[↑↓]/, '') .trim() + ' ' + (state.sortAsc ? '↑' : '↓'); });
        }

        function filterProtocol() {
            var shown = {}, any = false;
            q('.mx-ev[data-set]').forEach(function (el) {
                var ok = (!state.team || el.getAttribute('data-side') === state.team);
                if (ok && state.player) {
                    var ids = (el.getAttribute('data-players') || el.getAttribute('data-player') || '').split(',');
                    ok = ids.indexOf(state.player) !== -1;
                }
                el.classList.toggle('is-hidden', !ok);
                if (ok) { shown[el.getAttribute('data-set')] = true; any = true; }
            });
            q('.mx-set-h[data-set-h]').forEach(function (h) { h.classList.toggle('is-hidden', !shown[h.getAttribute('data-set-h')]); });
            q('[data-mx-proto-empty]').forEach(function (e) { e.style.display = any ? 'none' : ''; });
        }

        // Протокол: заголовок «Сет N · счёт» раскрывает/сворачивает розыгрыши сета (по умолчанию раскрыт только последний)
        function toggleSet(h) {
            var body = h.nextElementSibling; if (!body || !body.classList.contains('mx-set-body')) return;
            var open = body.classList.toggle('is-collapsed') === false;
            h.classList.toggle('is-open', open); h.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('mx-set-toggle')) { e.preventDefault(); toggleSet(e.target); }
        });

        function setTab(t) {
            state.tab = t;
            try { history.replaceState(null, '', '#' + t); } catch (e) {}
            apply();
        }

        document.addEventListener('click', function (e) {
            var t = e.target.closest ? e.target : null; if (!t || !root() || !root().contains(t)) return;
            var tab = t.closest('.mx-tab'); if (tab) { setTab(tab.getAttribute('data-tab')); return; }
            var cs = t.closest('[data-mx-seg="chart"] button'); if (cs) { state.chartSet = cs.getAttribute('data-set'); apply(); return; }
            var ps = t.closest('[data-mx-seg="players"] button'); if (ps) { state.scope = ps.getAttribute('data-scope'); apply(); return; }
            if (t.closest('[data-mx-sort]')) { state.sortAsc = !state.sortAsc; apply(); return; }
            var sh = t.closest('.mx-set-toggle'); if (sh) { toggleSet(sh); }
        });
        document.addEventListener('change', function (e) {
            var s = e.target.closest ? e.target.closest('[data-mx-filter]') : null; if (!s) return;
            state[s.getAttribute('data-mx-filter')] = s.value; apply();
        });

        window.mxShare = async function (matchId, title) {
            var url = '/tournament-matches/' + matchId + '/share-card';
            try {
                var resp = await fetch(url);
                var blob = await resp.blob();
                var file = new File([blob], 'match.png', { type: 'image/png' });
                if (navigator.canShare && navigator.canShare({ files: [file] })) { await navigator.share({ files: [file], title: title }); return; }
            } catch (err) { console.warn('[share]', err); }
            window.open(url, '_blank');
        };

        // старт: вкладка из #hash; ?player= → «Игроки» + подсветка
        var h = (location.hash || '').replace('#', '');
        if (tabs.indexOf(h) !== -1) state.tab = h;
        else if (root() && root().getAttribute('data-highlight')) state.tab = 'players';
        apply();
        var hl = root() && root().querySelector('tr.mx-row--hl');
        if (hl && state.tab === 'players') { setTimeout(function () { hl.scrollIntoView({ block: 'center' }); }, 150); }

        // live: раз в 15 с подтягиваем свежий HTML блока, состояние (вкладка/фильтры) сохраняется
        var box = root();
        if (box && box.getAttribute('data-live') === '1') {
            var busy = false;
            var timer = setInterval(function () {
                if (busy || document.hidden) return;
                busy = true;
                fetch(window.location.href, { cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.ok ? r.text() : null; })
                    .then(function (html) {
                        if (!html) return;
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var fresh = doc.getElementById('mx-live'), cur = root();
                        if (!fresh || !cur) return;
                        if (cur.innerHTML.trim() !== fresh.innerHTML.trim()) { cur.innerHTML = fresh.innerHTML; apply(); }
                        cur.setAttribute('data-live', fresh.getAttribute('data-live'));
                        if (fresh.getAttribute('data-live') !== '1') clearInterval(timer);
                    })
                    .catch(function () {})
                    .then(function () { busy = false; });
            }, 15000);
        }
    })();
    </script>
    </x-slot>
</x-voll-layout>
