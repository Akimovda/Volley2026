    /* ---------- табло «Мировой Тур» (theme.board = wt) ---------- */
    function renderWt(p) {
        var th = p.theme || {}, n = normTheme(th);
        els.wt.className = 'p ' + n.wt_position + ' sk-' + n.skin + (els.wt.classList.contains('on') ? ' on' : '');
        var cs = p.set || {n: 1, home: 0, away: 0};
        if (p.mode === 'set_summary' && p.summary && p.summary.set_number) {
            cs = (p.sets || []).filter(function (s) { return s.n === p.summary.set_number; })[0] || cs;
        }
        // эмблема в центре фиксированная (VolleyClub); у этого табло настраивается только положение (сверху/снизу по центру)
        var emb = '<div class="wt-emb"><img src="' + esc(TB_LOGO) + '" alt=""></div>';
        function side(s) {
            var t = team(p, s), srv = p.serving === (s === 'home' ? 'A' : 'B');
            var nm = String(t.short_name || t.name || '').toUpperCase();
            var name = '<span class="wt-name' + (nm.length > 4 ? ' long' : '') + '">' + esc(nm) + '</span>';
            return '<div class="wt-side ' + s + '"><i class="wt-stripe" style="background:' + color(t.color) + '"></i><span class="wt-ball' + (srv ? ' on' : '') + '">' + BALL + '</span>' + name + '</div>';
        }
        function score(s) {
            var sets = '<div class="wt-sets"><small>' + esc(S.wt_sets || 'SETS') + '</small><b>' + ((p.wins && p.wins[s]) || 0) + '</b></div>';
            var pts = '<div class="wt-pts"><span data-side="' + s + '">' + cs[s] + '</span></div>';
            return '<div class="wt-score ' + s + '">' + (s === 'home' ? sets + pts : pts + sets) + '</div>';
        }
        els.wt.innerHTML = '<div class="wt-bar">' + side('home') + score('home') + emb + score('away') + side('away') + '</div>';
        ['home', 'away'].forEach(function (s) {
            var k = 'wt:' + (p.match ? p.match.id : 0) + ':' + cs.n + ':' + s;
            var el = els.wt.querySelector('[data-side="' + s + '"]');
            if (el && prevPts[k] !== undefined && prevPts[k] !== cs[s]) { el.classList.add('bump'); }
            prevPts[k] = cs[s];
        });
    }
