{{-- Данные: $gamesData = [ключ => [ ['title','date','url','matches','wins'], … ]]; ссылки .games-link с data-key/data-title --}}
<script>
(function() {
    var data = @json($gamesData ?? []);
    var heads = @json($gamesHeads ?? []);
    var emptyText = @json(__('players.games_modal_empty'));
    var rowText = @json(__('players.games_modal_row'));
    var openText = @json(__('players.games_modal_open'));
    var placeText = @json(__('players.games_modal_place'));
    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text != null) e.textContent = text;
        return e;
    }
    document.querySelectorAll('.games-link').forEach(function(link) {
        link.addEventListener('click', function(ev) {
            ev.preventDefault();
            var list = data[link.dataset.key] || [];
            var head = document.getElementById('games-modal-head');
            head.innerHTML = '';
            (heads[link.dataset.key] || [[link.dataset.title || '', null]]).forEach(function(h) {
                var row = el('div', 'games-head-row');
                if (h[1]) { var img = el('img', 'games-avatar'); img.src = h[1]; img.alt = ''; row.appendChild(img); }
                row.appendChild(el('span', 'b-600 f-16', h[0]));
                head.appendChild(row);
            });
            var box = document.getElementById('games-modal-list');
            box.innerHTML = '';
            if (!list.length) box.appendChild(el('div', 'f-14', emptyText));
            list.forEach(function(t) {
                var row = el('div', 'games-row');
                var medals = {1: '🥇', 2: '🥈', 3: '🥉'};
                var a = el('a', 'blink b-600', (medals[t.place] ? medals[t.place] + ' ' : '') + t.title);
                a.href = t.url;
                a.title = openText;
                row.appendChild(a);
                var sub = (t.date ? t.date + ' · ' : '') + rowText.replace(':m', t.matches).replace(':w', t.wins)
                    + (t.place && !medals[t.place] ? ' · ' + placeText.replace(':n', t.place) : '');
                row.appendChild(el('div', 'f-13 games-sub', sub));
                box.appendChild(row);
            });
            jQuery.fancybox.open({ src: '#games-modal', type: 'inline' });
        });
    });
})();
</script>
<style>
    #games-modal a { outline: none; }
    .games-head { margin-bottom: 1.2rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(128,128,128,.25); }
    .games-head-row { display: flex; align-items: center; gap: 1.2rem; padding: .4rem 0; }
    .games-avatar { width: 4rem; height: 4rem; max-width: none; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
    .games-row { padding: .9rem 0; border-bottom: 1px solid rgba(128,128,128,.15); }
    .games-row:last-child { border-bottom: 0; }
    .games-sub { opacity: .6; margin-top: .2rem; }
</style>
