<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title></title>
<link rel="preload" href="/assets/fonts/commissioner-bold.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/commissioner-semibold.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/commissioner-regular.woff2" as="font" type="font/woff2" crossorigin>
<style>
@font-face{font-family:"Commissioner";font-weight:400;font-display:block;src:url(/assets/fonts/commissioner-regular.woff2) format("woff2")}
@font-face{font-family:"Commissioner";font-weight:500;font-display:block;src:url(/assets/fonts/commissioner-medium.woff2) format("woff2")}
@font-face{font-family:"Commissioner";font-weight:600;font-display:block;src:url(/assets/fonts/commissioner-semibold.woff2) format("woff2")}
@font-face{font-family:"Commissioner";font-weight:700;font-display:block;src:url(/assets/fonts/commissioner-bold.woff2) format("woff2")}
:root{--u0:min(calc(100vw/1920),calc(100vh/1080));--u:var(--u0);--edge:48;--panel:rgba(12,16,24,.85);--text:rgba(255,255,255,.95);--muted:rgba(255,255,255,.68)}
*{box-sizing:border-box}
html,body{margin:0;padding:0;overflow:hidden;background:transparent;width:100%;height:100%}
body.preview{background:#5b6068}
body{font-family:"Commissioner",system-ui,sans-serif;color:var(--text);-webkit-font-smoothing:antialiased}
#board,#sub,#setsum,#matchsum{--u:calc(var(--u0)*var(--k,.7))}
#stage{position:absolute;left:0;top:0;width:calc(var(--u)*1920);height:calc(var(--u)*1080)}
.p{position:absolute;opacity:0;transform:translateY(calc(var(--u)*14));transition:opacity .25s ease,transform .25s ease;pointer-events:none;will-change:opacity,transform}
.p.on{opacity:1;transform:none}
.card{background:rgba(12,16,24,var(--pa,.85));border-radius:calc(var(--u)*14);overflow:hidden}
.stripe{flex:none;width:calc(var(--u)*10);align-self:stretch}
.logo{flex:none;width:calc(var(--u)*56);height:calc(var(--u)*56);object-fit:cover;border-radius:50%;margin-left:calc(var(--u)*14)}
.hide{display:none!important}
.logo.only{width:calc(var(--u)*64);height:calc(var(--u)*64);margin:0 calc(var(--u)*14) 0 calc(var(--u)*18)}
.name.sp{padding:0 calc(var(--u)*6)}
/* названия скрыты: блоки сжимаются по содержимому */
#board.nn,#board.wide.nn{width:auto;min-width:0}
#board.nn .name{min-width:calc(var(--u)*60)}
#board.nn .name.sp{min-width:0}
#setsum.nn{width:calc(var(--u)*760);margin-left:calc(var(--u)*-380)}
#matchsum.nn{width:calc(var(--u)*760);margin-left:calc(var(--u)*-380)}
.sum-score .tn img.lg2{width:calc(var(--u)*64);height:calc(var(--u)*64);border-radius:50%;object-fit:cover;flex:none}

/* ---- метка платформы ---- */
#tag{left:50%;bottom:calc(var(--u)*14);width:calc(var(--u)*700);margin-left:calc(var(--u)*-350);text-align:center;font-size:calc(var(--u)*22);font-weight:500;color:#fff;opacity:0;text-shadow:0 0 calc(var(--u)*6) rgba(0,0,0,.75),0 calc(var(--u)*1) calc(var(--u)*2) rgba(0,0,0,.6);letter-spacing:.02em;transform:none;z-index:5}
#tag.on{opacity:.85}

/* ---- табло ---- */
#board{width:calc(var(--u)*640)}
#board.tl{left:calc(var(--u0)*var(--edge));top:calc(var(--u0)*var(--edge))}
#board.tr{right:calc(var(--u0)*var(--edge));top:calc(var(--u0)*var(--edge))}
#board.bl{left:calc(var(--u0)*var(--edge));bottom:calc(var(--u0)*var(--edge))}
#board.br{right:calc(var(--u0)*var(--edge));bottom:calc(var(--u0)*var(--edge))}
#board.wide{width:auto;min-width:calc(var(--u)*640)}
.row{display:flex;align-items:center;height:calc(var(--u)*88);border-top:1px solid rgba(255,255,255,.08)}
.row:first-child{border-top:0}
.name{flex:1;min-width:0;padding:0 calc(var(--u)*18);font-size:calc(var(--u)*38);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sv{flex:none;width:calc(var(--u)*26);height:calc(var(--u)*26);margin-right:calc(var(--u)*10);opacity:0;transition:opacity .2s}
.sv.on{opacity:1}
.wins{flex:none;min-width:calc(var(--u)*52);height:calc(var(--u)*52);line-height:calc(var(--u)*52);text-align:center;margin-right:calc(var(--u)*10);border-radius:calc(var(--u)*8);background:rgba(255,255,255,.12);font-size:calc(var(--u)*32);font-weight:700}
.col{flex:none;width:calc(var(--u)*58);text-align:center;font-size:calc(var(--u)*32);font-weight:500;color:var(--muted)}
.col.win{color:var(--text);font-weight:700}
.pts{flex:none;width:calc(var(--u)*104);text-align:center;font-size:calc(var(--u)*60);font-weight:700;font-variant-numeric:tabular-nums;background:rgba(255,255,255,.1);align-self:stretch;display:flex;align-items:center;justify-content:center}
.pts span{display:block;transition:transform .25s ease}
.pts span.bump{animation:bump .3s ease}
@keyframes bump{0%{transform:scale(1.3)}100%{transform:scale(1)}}
.foot{height:calc(var(--u)*44);line-height:calc(var(--u)*44);padding:0 calc(var(--u)*20);font-size:calc(var(--u)*24);font-weight:500;color:var(--muted);background:rgba(255,255,255,.06);display:flex;justify-content:space-between}

/* ---- замена ---- */
#sub{min-width:calc(var(--u)*520);max-width:calc(var(--u)*760);bottom:calc(var(--u0)*var(--edge))}
#sub.r{right:calc(var(--u0)*var(--edge))}
#sub.l{left:calc(var(--u0)*var(--edge))}
#sub .in{display:flex;align-items:center;padding:calc(var(--u)*16) calc(var(--u)*24) calc(var(--u)*16) 0}
#sub svg{flex:none;width:calc(var(--u)*40);height:calc(var(--u)*40);margin:0 calc(var(--u)*18)}
#sub .t1{font-size:calc(var(--u)*24);color:var(--muted);font-weight:500}
#sub .t2{font-size:calc(var(--u)*34);font-weight:600}

/* ---- итоги ---- */
#setsum{left:50%;bottom:calc(var(--u0)*var(--edge));width:calc(var(--u)*1100);margin-left:calc(var(--u)*-550)}
#matchsum{left:50%;top:50%;width:calc(var(--u)*1120);margin-left:calc(var(--u)*-560);margin-top:calc(var(--u)*-300)}
.sum-title{padding:calc(var(--u)*18) calc(var(--u)*30);font-size:calc(var(--u)*30);font-weight:600;color:var(--muted);text-align:center;background:rgba(255,255,255,.06);letter-spacing:.02em}
.sum-score{display:flex;align-items:center;justify-content:center;padding:calc(var(--u)*22) calc(var(--u)*30)}
.sum-score .tn{flex:1;font-size:calc(var(--u)*36);font-weight:600;display:flex;align-items:center;gap:calc(var(--u)*14);min-width:0}
.sum-score .tn.r{justify-content:flex-end;text-align:right}
.sum-score .tn b{display:inline-block;width:calc(var(--u)*10);height:calc(var(--u)*40);border-radius:calc(var(--u)*4);flex:none}
.sum-score .tn span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sum-score .big{flex:none;padding:0 calc(var(--u)*34);font-size:calc(var(--u)*96);font-weight:700;font-variant-numeric:tabular-nums}
.sum-sets{text-align:center;font-size:calc(var(--u)*30);font-weight:500;color:var(--muted);padding-bottom:calc(var(--u)*14)}
.sum-sets i{font-style:normal;margin:0 calc(var(--u)*16)}
.stat{display:flex;align-items:center;padding:calc(var(--u)*10) calc(var(--u)*30);border-top:1px solid rgba(255,255,255,.08);font-size:calc(var(--u)*34)}
.stat .v{width:calc(var(--u)*140);font-weight:700;font-variant-numeric:tabular-nums}
.stat .v.r{text-align:right}
.stat .l{flex:1;text-align:center;color:var(--muted);font-weight:500;font-size:calc(var(--u)*28)}
.sum-next{padding:calc(var(--u)*16) calc(var(--u)*30);text-align:center;font-size:calc(var(--u)*28);color:var(--muted);background:rgba(255,255,255,.06)}
.sum-next b{color:var(--text);font-weight:600}

/* ---- заставка ---- */
#splash{left:0;top:0;width:calc(var(--u)*1920);height:calc(var(--u)*1080);background:#10141c center/cover no-repeat;display:flex;align-items:center;justify-content:center;transform:none}
#splash::before{content:"";position:absolute;inset:0;background:rgba(8,10,16,.45)}
#splash .in{position:relative;text-align:center;max-width:calc(var(--u)*1500)}
#splash .lg{width:calc(var(--u)*170);height:calc(var(--u)*170);border-radius:50%;object-fit:cover;margin-bottom:calc(var(--u)*30);box-shadow:0 0 0 calc(var(--u)*6) rgba(255,255,255,.25)}
#splash .ev{font-size:calc(var(--u)*44);font-weight:600;color:var(--muted)}
#splash .vs{font-size:calc(var(--u)*92);font-weight:700;margin:calc(var(--u)*26) 0;line-height:1.1}
#splash .vs em{font-style:normal;color:var(--muted);margin:0 calc(var(--u)*24)}
#splash .tx{font-size:calc(var(--u)*38);color:var(--muted);margin-bottom:calc(var(--u)*20)}
#splash .cd{font-size:calc(var(--u)*52);font-weight:600;font-variant-numeric:tabular-nums}
#splash .cd b{font-size:calc(var(--u)*72);margin-left:calc(var(--u)*16)}
</style>
</head>
<body class="{{ $preview ? 'preview' : '' }}">
<div id="stage">
    <div id="board" class="p card tl"></div>
    <div id="sub" class="p card r"></div>
    <div id="setsum" class="p card"></div>
    <div id="matchsum" class="p card"></div>
    <div id="splash" class="p"></div>
    <div id="tag" class="p">{{ __('broadcast.platform_tag') }}</div>
</div>
<script src="/js/pusher.min.js"></script>
<script src="/js/echo.iife.js"></script>
<script>
window.__BC = @json($config);
</script>
<script>
(function () {
    'use strict';
    var C = window.__BC || {}, S = C.strings || {};
    var cur = null, offset = 0, wsOk = false, pollFrom = Date.now() + 5000;
    var lastFetch = 0, switched = null, subKey = null, subTimer = null;
    var els = {
        board: document.getElementById('board'), sub: document.getElementById('sub'),
        setsum: document.getElementById('setsum'), matchsum: document.getElementById('matchsum'),
        splash: document.getElementById('splash'), tag: document.getElementById('tag')
    };
    var prevPts = {};

    function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
    function fmt(s, n) { return String(s || '').replace(':n', n); }
    function srvNow() { return Date.now() + offset; }
    function safeUrl(u) { return (u && (/^https?:\/\//.test(u) || u.charAt(0) === '/')) ? u : ''; }
    function color(c) { return /^#[0-9a-fA-F]{6}$/.test(c || '') ? c : '#8892a0'; }
    function show(el, on) { el.classList.toggle('on', !!on); }

    var BALL = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="12" r="9.5" fill="#fff" stroke="none"/><path d="M12 2.5c-3 3-3 6.500 0 9.500s3 6.500 0 9.500M2.500 12c3-1 6.500-1 9.500 0s6.500 1 9.500 0" stroke="#10141c" stroke-width="1.500"/></svg>';
    var SWAP = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.200" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h14M14 4l4 4-4 4M20 16H6M10 20l-4-4 4-4"/></svg>';

    function labelOf(p, side) {
        // → {logo, text, only}: при скрытых названиях — логотип (если есть и показ логотипов включён), иначе короткое имя
        var t = team(p, side), th = p.theme || {};
        if (th.show_team_names === false) {
            var lg = th.show_logos !== false ? safeUrl(t.logo_url) : '';
            return {logo: lg, text: lg ? '' : (t.short_name || ''), only: !!lg};
        }
        return {logo: '', text: t.name || '', only: false};
    }
    var EDGES = {small: 24, medium: 48, large: 80};
    function applyTheme(th) {
        th = th || {};
        var k = Math.max(50, Math.min(150, parseInt(th.scale, 10) || 70)) / 100;
        var pa = Math.max(30, Math.min(100, parseInt(th.opacity, 10) || 85)) / 100;
        ['board', 'sub', 'setsum', 'matchsum'].forEach(function (n) { els[n].style.setProperty('--k', k); els[n].style.setProperty('--pa', pa); });
        document.getElementById('stage').style.setProperty('--edge', EDGES[th.edge_margin] || 48);
    }
    function team(p, side) { return (p.teams && p.teams[side]) || {name: '', color: null, logo_url: null}; }

    /* ---------- табло ---------- */
    function renderBoard(p) {
        var th = p.theme || {}, tpl = th.template || 'compact', corner = th.corner || 'top-left';
        var cls = {'top-left': 'tl', 'top-right': 'tr', 'bottom-left': 'bl', 'bottom-right': 'br'}[corner] || 'tl';
        els.board.className = 'p card ' + cls + (tpl === 'sets_table' ? ' wide' : '') + (th.show_team_names === false ? ' nn' : '') + (els.board.classList.contains('on') ? ' on' : '');
        var cs = p.set || {n: 1, home: 0, away: 0};
        if (p.mode === 'set_summary' && p.summary && p.summary.set_number) {
            // под плашкой итогов табло остаётся на только что сыгранной партии
            cs = (p.sets || []).filter(function (s) { return s.n === p.summary.set_number; })[0] || cs;
        }
        var done = (p.sets || []).filter(function (s) { return s.n < cs.n; });
        var html = '';
        ['home', 'away'].forEach(function (side) {
            var t = team(p, side), pts = cs[side];
            var lb = labelOf(p, side);
            var logo = lb.only ? '<img class="logo only" src="' + esc(lb.logo) + '" alt="">'
                : (th.show_team_names !== false && th.show_logos !== false && safeUrl(t.logo_url) ? '<img class="logo" src="' + esc(t.logo_url) + '" alt="">' : '');
            var extra = '';
            if (th.show_set_scores !== false) {
                if (tpl === 'sets_table') {
                    done.forEach(function (s) {
                        var other = side === 'home' ? 'away' : 'home';
                        extra += '<div class="col' + (s[side] > s[other] ? ' win' : '') + '">' + s[side] + '</div>';
                    });
                } else {
                    extra = '<div class="wins">' + ((p.wins && p.wins[side]) || 0) + '</div>';
                }
            }
            html += '<div class="row"><i class="stripe" style="background:' + color(t.color) + '"></i>' + logo +
                '<div class="name' + (lb.only ? ' sp' : '') + '">' + esc(lb.text) + '</div>' +
                '<div class="sv' + (p.serving === side ? ' on' : '') + '">' + BALL + '</div>' + extra +
                '<div class="pts"><span data-side="' + side + '">' + pts + '</span></div></div>';
        });
        html += '<div class="foot"><span>' + esc(fmt(S.set_n, cs.n)) + '</span></div>';
        els.board.innerHTML = html;
        ['home', 'away'].forEach(function (side) {
            var k = (p.match ? p.match.id : 0) + ':' + cs.n + ':' + side;
            var el = els.board.querySelector('[data-side="' + side + '"]');
            if (el && prevPts[k] !== undefined && prevPts[k] !== cs[side]) {
                el.classList.add('bump');
            }
            prevPts[k] = cs[side];
        });
    }

    /* ---------- замена ---------- */
    function renderSub(p) {
        var ls = p.last_sub;
        if (p.mode !== 'live' || !ls) { return; }
        var key = (p.match ? p.match.id : 0) + '|' + ls.at + '|' + ls.text;
        if (key === subKey) { return; }
        subKey = key;
        // старые замены (при загрузке страницы) не показываем (в примере — висит постоянно)
        if (!C.demo && ls.at && srvNow() - Date.parse(ls.at) > 15000) { return; }
        var th = p.theme || {}, left = (th.corner || 'top-left').indexOf('left') >= 0;
        var t = team(p, ls.team);
        els.sub.className = 'p card ' + (left ? 'r' : 'l');
        els.sub.innerHTML = '<div style="display:flex;align-items:center"><i class="stripe" style="background:' + color(t.color) + ';align-self:stretch"></i>' +
            '<div class="in">' + SWAP + '<div><div class="t1">' + esc(labelOf(p, ls.team).text || t.name) + '</div><div class="t2">' + esc(ls.text) + '</div></div></div></div>';
        void els.sub.offsetWidth;
        show(els.sub, true);
        clearTimeout(subTimer);
        if (!C.demo) { subTimer = setTimeout(function () { show(els.sub, false); }, 5000); }
    }

    /* ---------- итоги ---------- */
    function statRows(sm) {
        var h = sm.teams.home, a = sm.teams.away;
        var rows = [[S.stat_aces, h.aces, a.aces], [S.stat_attacks, h.kills, a.kills], [S.stat_blocks, h.blocks, a.blocks], [S.stat_opp_errors, a.errors, h.errors]];
        return rows.map(function (r) {
            return '<div class="stat"><div class="v">' + r[1] + '</div><div class="l">' + esc(r[0]) + '</div><div class="v r">' + r[2] + '</div></div>';
        }).join('');
    }
    function nameChip(p, side, right) {
        var t = team(p, side), lb = labelOf(p, side);
        var bar = '<b style="background:' + color(t.color) + '"></b>';
        var body = lb.only ? '<img class="lg2" src="' + esc(lb.logo) + '" alt="">' : '<span>' + esc(lb.text) + '</span>';
        return '<div class="tn' + (right ? ' r' : '') + '">' + (right ? body + bar : bar + body) + '</div>';
    }
    function renderSetSum(p) {
        var sm = p.summary; if (!sm || sm.scope !== 'set') { return; }
        els.setsum.classList.toggle('nn', (p.theme || {}).show_team_names === false);
        var set = (p.sets || []).filter(function (s) { return s.n === sm.set_number; })[0] || {home: 0, away: 0};
        els.setsum.innerHTML = '<div class="sum-title">' + esc(fmt(S.set_summary, sm.set_number)) + '</div>' +
            '<div class="sum-score">' + nameChip(p, 'home', false) + '<div class="big">' + set.home + ' : ' + set.away + '</div>' + nameChip(p, 'away', true) + '</div>' + statRows(sm);
    }
    function renderMatchSum(p) {
        var sm = p.summary; if (!sm || sm.scope !== 'match') { return; }
        els.matchsum.classList.toggle('nn', (p.theme || {}).show_team_names === false);
        var sets = (p.sets || []).map(function (s) { return '<i>' + s.home + ':' + s.away + '</i>'; }).join('');
        var nx = p.next_match && p.next_match.home ? '<div class="sum-next">' + esc(S.next) + ': <b>' + esc(p.next_match.home) + ' — ' + esc(p.next_match.away) + '</b></div>' : '';
        els.matchsum.innerHTML = '<div class="sum-title">' + esc(S.match_summary) + '</div>' +
            '<div class="sum-score">' + nameChip(p, 'home', false) + '<div class="big">' + ((p.wins && p.wins.home) || 0) + ' : ' + ((p.wins && p.wins.away) || 0) + '</div>' + nameChip(p, 'away', true) + '</div>' +
            '<div class="sum-sets">' + sets + '</div>' + statRows(sm) + nx;
    }

    /* ---------- заставка ---------- */
    function renderSplash(p) {
        var sp = p.splash || {}, nm = p.next_match, tm = p.teams;
        var bg = safeUrl(sp.bg_url);
        els.splash.style.backgroundImage = bg ? 'url("' + bg.replace(/"/g, '%22') + '")' : 'none';
        var html = '<div class="in">';
        var logo = p.event && safeUrl(p.event.logo_url);
        if (logo) { html += '<img class="lg" src="' + esc(logo) + '" alt="">'; }
        var ev = [p.event && p.event.title, p.court && p.court.name].filter(Boolean).join(' · ');
        if (ev) { html += '<div class="ev">' + esc(ev) + '</div>'; }
        if (nm && nm.home) { html += '<div class="vs">' + esc(nm.home) + '<em>—</em>' + esc(nm.away) + '</div>'; }
        if (sp.text) { html += '<div class="tx">' + esc(sp.text) + '</div>'; }
        html += '<div class="cd" id="cd"></div></div>';
        els.splash.innerHTML = html;
        tickCountdown();
    }
    function tickCountdown() {
        var el = document.getElementById('cd');
        if (!el || !cur || !cur.splash || !cur.splash.countdown_to) { if (el) { el.innerHTML = ''; } return; }
        var left = Math.floor((Date.parse(cur.splash.countdown_to) - srvNow()) / 1000);
        if (left < 0) { el.innerHTML = ''; return; }
        var h = Math.floor(left / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        el.innerHTML = esc(S.starts_in) + '<b>' + (h ? h + ':' : '') + pad(m) + ':' + pad(s) + '</b>';
    }

    /* ---------- применение состояния ---------- */
    function render(p) {
        var m = p.mode;
        if (p.theme) { applyTheme(p.theme); }
        show(els.tag, m !== 'off');
        show(els.board, m === 'live' || m === 'set_summary' ? (renderBoard(p), true) : false);
        if (m === 'live') { renderSub(p); } else { show(els.sub, false); }
        show(els.setsum, m === 'set_summary' ? (renderSetSum(p), true) : false);
        show(els.matchsum, m === 'match_summary' ? (renderMatchSum(p), true) : false);
        show(els.splash, m === 'splash' ? (renderSplash(p), true) : false);
    }

    // Ссылка недоступна / площадка скрыта: полностью прозрачный кадр, редкий опрос (оживёт, если вернут)
    var offMode = false;
    function goOff() {
        offMode = true; cur = null; switched = null;
        try { render({mode: 'off'}); } catch (e) {}
    }

    function apply(p) {
        try {
            if (!p || !p.mode) { return; }
            if (p.hidden) { goOff(); return; }
            offMode = false;
            if (cur) {
                var cm = cur.match ? cur.match.id : null, pm = p.match ? p.match.id : null;
                if (cm === pm && p.seq < cur.seq) { return; }
                if (cm === pm && p.seq === cur.seq && p.mode === cur.mode && p.now_ms < cur.now_ms) { return; }
            }
            if (p.now_ms) { offset = p.now_ms - Date.now(); }
            cur = p;
            switched = null;
            render(p);
        } catch (e) { /* на эфире ошибок не показываем */ }
    }

    /* ---------- получение состояния ---------- */
    function fetchState() {
        if (C.demo || !C.stateUrl) { return; }
        lastFetch = Date.now();
        try {
            var x = new XMLHttpRequest();
            x.open('GET', C.stateUrl + (C.stateUrl.indexOf('?') < 0 ? '?' : '&') + 't=' + Date.now());
            x.onload = function () { try { if (x.status === 200) { apply(JSON.parse(x.responseText)); } else if (x.status === 404) { goOff(); } } catch (e) {} };
            x.send();
        } catch (e) {}
    }

    function tick() {
        try {
            tickCountdown();
            if (cur && cur.mode_until) {
                var until = Date.parse(cur.mode_until);
                if (srvNow() >= until) {
                    if (switched !== cur.mode_until) {
                        // сами переключаемся в момент mode_until, затем подтверждаем GET-ом
                        switched = cur.mode_until;
                        var n = JSON.parse(JSON.stringify(cur));
                        if (n.mode === 'set_summary') { n.mode = 'live'; n.summary = null; }
                        else if (n.mode === 'match_summary') { n.mode = 'splash'; n.match = null; n.summary = null; n.teams = null; n.splash = n.splash || {}; n.splash.countdown_to = null; }
                        n.mode_until = cur.mode_until;
                        cur = n; render(n);
                    }
                    if (Date.now() - lastFetch > 700) { fetchState(); }
                }
            }
        } catch (e) {}
    }
    setInterval(tick, 200);

    // polling-fallback: WS не поднялся за 5 с или разорван
    setInterval(function () {
        if (offMode) { if (Date.now() - lastFetch >= 30000) { fetchState(); } return; }
        if (!wsOk && Date.now() >= pollFrom) { fetchState(); }
    }, 3000);

    function connect() {
        try {
            if (typeof Echo === 'undefined' || !C.key) { return; }
            var echo = new Echo({
                broadcaster: 'reverb', key: C.key, wsHost: window.location.hostname, wsPort: 80, wssPort: 443,
                forceTLS: window.location.protocol === 'https:', enabledTransports: ['ws', 'wss']
            });
            echo.channel(C.channel).listen('.state', function (p) { apply(p); });
            var conn = echo.connector.pusher.connection;
            conn.bind('state_change', function (st) {
                if (st.current === 'connected') { wsOk = true; fetchState(); }
                else { if (wsOk) { pollFrom = Date.now(); } wsOk = false; }
            });
            if (conn.state === 'connected') { wsOk = true; }
        } catch (e) {}
    }

    // Предпросмотр из настройки стрима: тема применяется сразу, без перезагрузки и без запроса к серверу
    window.addEventListener('message', function (e) {
        try {
            if (!C.preview || !e.data || e.data.type !== 'bc-theme' || !cur) { return; }
            var n = JSON.parse(JSON.stringify(cur));
            n.theme = Object.assign({}, n.theme || {}, e.data.theme || {});
            if (e.data.teams && n.teams) { ['home', 'away'].forEach(function (k) { if (e.data.teams[k]) { n.teams[k] = Object.assign({}, n.teams[k], e.data.teams[k]); } }); }
            subKey = null;
            cur = n; render(n);
        } catch (er) {}
    });

    apply(C.initial);
    if (C.demo) {
        // Пример: без канала и без опроса; таймер заставки идёт от «сейчас»
        try {
            offset = 0; cur.now_ms = Date.now();
            if (cur.splash && cur.splash.countdown_to) { cur.splash.countdown_to = new Date(Date.now() + 300000).toISOString(); }
            render(cur);
        } catch (er) {}
    } else {
        connect();
    }
})();
</script>
</body>
</html>
