    /* ---------- табло «Вкладки» (theme.template = tabs) ---------- */
    var TB_LOGO = '/assets/broadcast/overlay_logo_dark.svg';
    var TB_MAXSETS = {bo1: 1, bo3: 3, bo5: 5};

    function tbFit(root) {
        // авто-ужатие по ширине ячейки: до 60% от базового кегля, дальше — многоточие (CSS)
        var list = root.querySelectorAll('.tb-fit,.tb-t1');
        for (var i = 0; i < list.length; i++) {
            var el = list[i];
            el.style.fontSize = '';
            var base = parseFloat(getComputedStyle(el).fontSize) || 0;
            if (!base || el.scrollWidth <= el.clientWidth + 1) { continue; }
            var k = 1;
            while (k > 0.6 && el.scrollWidth > el.clientWidth + 1) {
                k = Math.max(0.6, k - 0.04);
                el.style.fontSize = (base * k) + 'px';
            }
            // Пара «Фамилия Имя» не поместилась даже ужатая — оставляем только фамилию (и снова ужимаем), дальше многоточие (CSS)
            var sur = el.getAttribute('data-sur');
            if (sur && el.scrollWidth > el.clientWidth + 1) {
                el.textContent = sur;
                el.style.fontSize = '';
                k = 1;
                while (k > 0.6 && el.scrollWidth > el.clientWidth + 1) {
                    k = Math.max(0.6, k - 0.04);
                    el.style.fontSize = (base * k) + 'px';
                }
            }
        }
    }

    function tbName(p, side, th) {
        var t = team(p, side), lb = labelOf(p, side);
        if (th.show_team_names === false) {
            return lb.only ? '' : '<div class="tb-fit">' + esc(lb.text) + '</div>';
        }
        var beach = p.event && p.event.direction === 'beach' && t.players && t.players.length;
        if (beach) {
            // пара: «Фамилия Имя» (если полные имена есть), иначе только фамилии, как раньше
            var full = t.pair && t.players_full && t.players_full.length === 2;
            return t.players.slice(0, 2).map(function (n, i) {
                return '<div class="tb-fit two"' + (full ? ' data-sur="' + esc(n) + '"' : '') + '>' + esc(full ? t.players_full[i] : n) + '</div>';
            }).join('');
        }
        return '<div class="tb-fit">' + esc(t.short_custom && t.short_name ? t.short_name : (t.name || '')) + '</div>';
    }

    function renderTabs(p) {
        var th = p.theme || {}, corner = th.corner || 'top-left';
        var cls = {'top-left': 'tl', 'top-right': 'tr', 'bottom-left': 'bl', 'bottom-right': 'br'}[corner] || 'tl';
        els.tabs.className = 'p ' + cls + ' sk-' + normTheme(th).skin + (els.tabs.classList.contains('on') ? ' on' : '');
        var cs = p.set || {n: 1, home: 0, away: 0};
        if (p.mode === 'set_summary' && p.summary && p.summary.set_number) {
            cs = (p.sets || []).filter(function (s) { return s.n === p.summary.set_number; })[0] || cs;
        }
        var done = (p.sets || []).filter(function (s) { return s.n < cs.n; });
        var fmt0 = p.match && p.match.format;
        var cells = th.show_set_scores === false ? 0 : Math.max((TB_MAXSETS[fmt0] || 3) - 1, done.length);
        var noNames = th.show_team_names === false;

        // вкладки
        var court = p.court && p.court.name ? String(p.court.name) : '';
        var ev = [p.event && p.event.title, p.match && p.match.stage_label].filter(Boolean).join(' · ');
        var tc = /^#[0-9a-fA-F]{6}$/.test(th.tabs_color || '') ? th.tabs_color : '';
        var logoOn = th.tabs_logo !== 'hidden';
        var tabs = '<div class="tb-tabs">' + (court ? '<div class="tb-t1' + (logoOn ? ' sq' : '') + '">' + esc(court) + '</div>' : '') +
            (ev ? '<div class="tb-t2"' + (tc ? ' style="background:' + tc + '"' : '') + '>' + esc(ev) + '</div>' : '') + '</div>';

        // логотип
        var logo = '';
        if (logoOn) {
            var org = th.tabs_logo === 'organizer' ? safeUrl(p.event && p.event.logo_url) : '';
            var src = org || TB_LOGO;
            logo = '<div class="tb-logo' + (org ? ' cv' : '') + '"><img src="' + esc(src) + '" alt=""></div>';
        }

        var u = 'var(--u)*';
        var cols = 'calc(' + u + (noNames ? 260 : 500) + ') calc(' + u + '124)';
        for (var c = 0; c < cells; c++) { cols += ' calc(' + u + '78)'; }
        cols += ' calc(' + u + '104)';

        var grid = '';
        ['home', 'away'].forEach(function (side, idx) {
            var t = team(p, side), other = side === 'home' ? 'away' : 'home', r2 = idx ? ' r2' : '';
            var lg = th.show_logos !== false ? safeUrl(t.logo_url) : '';
            var lead = lg ? '<img class="tb-lg" src="' + esc(lg) + '" alt="">' : '<i class="tb-bar" style="background:' + color(t.color) + '"></i>';
            grid += '<div class="tb-n' + r2 + '">' + lead + '<div class="tb-nm">' + tbName(p, side, th) + '</div>' +
                '<div class="tb-sv' + (p.serving === (side === 'home' ? 'A' : 'B') ? ' on' : '') + '"><i></i></div></div>';
            grid += '<div class="tb-p' + r2 + '"><span data-side="' + side + '">' + cs[side] + '</span></div>';
            for (var i = 0; i < cells; i++) {
                var s = done[i];
                grid += '<div class="tb-s' + r2 + (s && s[side] > s[other] ? ' win' : '') + '">' + (s ? s[side] : '–') + '</div>';
            }
            grid += '<div class="tb-w' + r2 + '">' + ((p.wins && p.wins[side]) || 0) + '</div>';
        });

        els.tabs.innerHTML = tabs + '<div class="tb-main">' + logo + '<div class="tb-grid" style="grid-template-columns:' + cols + '">' + grid + '</div></div>';
        tbFit(els.tabs);
        ['home', 'away'].forEach(function (side) {
            var k = (p.match ? p.match.id : 0) + ':' + cs.n + ':' + side;
            var el = els.tabs.querySelector('[data-side="' + side + '"]');
            if (el && prevPts[k] !== undefined && prevPts[k] !== cs[side]) { el.classList.add('bump'); }
            prevPts[k] = cs[side];
        });
    }
    if (document.fonts && document.fonts.load) {
        // шрифт грузится лениво: после загрузки пересчитываем ужатие
        document.fonts.load('600 40px Oswald', 'Аб').then(function () { if (cur && normTheme(cur.theme || {}).board === 'avp') { tbFit(els.tabs); } }, function () {});
    }
