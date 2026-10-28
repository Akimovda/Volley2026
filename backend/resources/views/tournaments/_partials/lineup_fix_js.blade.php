{{-- «Исправить расстановку» (крупная половина площадки одной команды: поворот ↺/↻ и обмен двух игроков; сохранение — lineup-события
     rotate/swap одним запросом type=fix) и поведение подсказки по либеро. Данные: RallyLive.data().fx (MatchLineupView::forRally()['mini']). --}}
@php
	$fxT = [];
	foreach (['bx_fix_title', 'bx_fix_sub', 'bx_fix_back', 'bx_fix_fwd', 'bx_fix_swap_hint', 'bx_fix_swap_second', 'bx_fix_ok', 'bx_fix_save', 'bx_fix_reset', 'bx_fix_libero_no'] as $k) {
		$fxT[$k] = __('tournaments.' . $k);
	}
	$fxT['net'] = __('tournaments.lineup_net');
	$fxT['zone'] = __('tournaments.lineup_zone_n', ['n' => ':n']);
@endphp
<script>
(function () {
	var T = @json($fxT);
	var BALL = @json(view('tournaments._partials.ball_icon')->render());
	var ov = document.createElement('div');
	ov.className = 'rw-overlay lf-ov';
	ov.innerHTML = '<div class="rw-box lf-box"></div>';
	document.body.appendChild(ov);
	var box = ov.querySelector('.lf-box');
	var S = null;

	function zone(z, d, n) { return ((((z - 1 - d) % n) + n) % n) + 1; }
	function esc(v) { return String(v == null ? '' : v).replace(/[&<>"]/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]; }); }
	function close() { ov.classList.remove('rw-open'); S = null; }
	function rowsFor(n) { return n === 6 ? [[4, 3, 2], [5, 6, 1]] : [[3, 2], [4, 1]]; }
	function cellById(id) { for (var i = 0; i < S.tm.cells.length; i++) { if (S.tm.cells[i].id === id) { return S.tm.cells[i]; } } return null; }
	function changed() { return S.delta !== 0 || S.swaps.length > 0; }

	function open(side, opts) {
		var fx = RallyLive.data().fx;
		if (!fx || !fx.teams || !fx.teams[side]) { return; }
		var tm = fx.teams[side], base = {};
		tm.cells.forEach(function (c) { base[c.id] = c.zone; });
		S = {side: side, fx: fx, tm: tm, n: fx.size, base: base, pos: JSON.parse(JSON.stringify(base)), delta: 0, swaps: [], sel: null, banner: opts && opts.banner, busy: false, note: ''};
		render();
		ov.classList.add('rw-open');
	}

	function render() {
		var tm = S.tm, n = S.n, byZone = {};
		tm.cells.forEach(function (c) { byZone[S.pos[c.id]] = c; });
		var h = '';
		if (S.banner) { h += '<div class="lf-banner">' + esc(S.banner) + '</div>'; }
		h += '<div class="rw-title">' + esc(T.bx_fix_title.replace(':team', tm.name)) + '</div>';
		h += '<div class="rw-sub">' + esc(T.bx_fix_sub) + '</div>';
		h += '<div class="lf-court" style="background:' + esc(tm.color) + ';color:' + esc(tm.text) + '"><div class="mc-net"><span>' + esc(T.net) + '</span></div>';
		rowsFor(n).forEach(function (row) {
			h += '<div class="mc-row">';
			row.forEach(function (z) {
				var c = byZone[z], srv = tm.serving && z === 1 && c && !c.libero;
				h += '<div class="mc-cell lf-cell' + (c && S.sel === c.id ? ' lf-sel' : '') + '" data-uid="' + (c ? c.id : '') + '"><span class="mc-z">' + esc(T.zone.replace(':n', z)) + '</span>';
				if (c) {
					h += '<div class="mc-av' + (c.libero ? ' mc-av--lib' : '') + (srv ? ' mc-av--srv' : '') + '">' +
						(c.photo ? '<img src="' + esc(c.photo) + '" alt="">' : '<span class="mc-ini">' + esc(c.initials) + '</span>') +
						(c.number != null ? '<span class="mc-num">' + esc(c.number) + '</span>' : '') + '</div>' +
						'<div class="mc-nm">' + esc(c.surname) + '</div><div class="mc-pos' + (c.libero ? ' mc-pos--lib' : '') + '">' + esc(c.pos_abbr) + '</div>';
				}
				h += '</div>';
			});
			h += '</div>';
		});
		h += '</div>';
		h += '<div class="lf-hint">' + esc(S.note || (S.sel != null ? T.bx_fix_swap_second : T.bx_fix_swap_hint)) + '</div>';
		h += '<div class="lf-rot"><button type="button" class="btn btn-secondary js-lf-back">' + esc(T.bx_fix_back) + '</button><button type="button" class="btn btn-secondary js-lf-fwd">' + esc(T.bx_fix_fwd) + '</button></div>';
		if (changed()) {
			h += '<button type="button" class="btn btn-primary w-100 js-lf-save" style="margin-top:10px">' + esc(T.bx_fix_save) + '</button>' +
				'<div style="text-align:center;margin-top:6px"><a href="#" class="js-lf-reset" style="font-size:13px">' + esc(T.bx_fix_reset) + '</a></div>';
		} else {
			h += '<button type="button" class="btn w-100 js-lf-ok" style="margin-top:10px;background:#16a34a;color:#fff;border-color:#16a34a">' + esc(T.bx_fix_ok) + '</button>';
		}
		h += '<div class="rw-note lf-err" style="color:#dc2626;font-size:13px;margin-top:6px;min-height:1em"></div>';
		box.innerHTML = h;
	}

	function rotate(dir) {
		S.delta += dir; S.sel = null; S.note = '';
		for (var id in S.pos) { if (S.pos.hasOwnProperty(id)) { S.pos[id] = zone(S.pos[id], dir, S.n); } }
		render();
	}
	function pick(id) {
		var c = cellById(id);
		if (!c) { return; }
		if (c.libero) { S.note = T.bx_fix_libero_no; S.sel = null; render(); return; }
		S.note = '';
		if (S.sel == null) { S.sel = id; render(); return; }
		if (S.sel === id) { S.sel = null; render(); return; }
		var a = S.sel, t = S.pos[a]; S.pos[a] = S.pos[id]; S.pos[id] = t;
		S.swaps.push([a, id]); S.sel = null; render();
	}
	function save() {
		if (S.busy) { return; }
		var n = S.n, d = ((S.delta % n) + n) % n;
		if (d > n / 2) { d -= n; }
		var swaps = S.swaps.slice();
		if (d === 0 && !swaps.length) { close(); return; }
		S.busy = true;
		box.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
		var C = RallyLive.data().lu;
		RallyLive.send(C.eventUrl, {set_number: C.setNumber, team_id: S.tm.team_id, type: 'fix', delta: d, swaps: swaps, client_request_id: RallyLive.uuid()}, {json: true, retry: true},
			function (j) { close(); RallyLive.swap(j.html); },
			function (m) { S.busy = false; box.querySelectorAll('button').forEach(function (b) { b.disabled = false; }); var e = box.querySelector('.lf-err'); if (e) { e.textContent = m; } });
	}

	box.addEventListener('click', function (e) {
		var t = e.target;
		if (!S || !t.closest) { return; }
		if (t.closest('.js-lf-back')) { rotate(-1); return; }
		if (t.closest('.js-lf-fwd')) { rotate(1); return; }
		if (t.closest('.js-lf-save')) { save(); return; }
		if (t.closest('.js-lf-ok')) { close(); return; }
		if (t.closest('.js-lf-reset')) { e.preventDefault(); S.pos = JSON.parse(JSON.stringify(S.base)); S.delta = 0; S.swaps = []; S.sel = null; S.note = ''; render(); return; }
		var cell = t.closest('.lf-cell');
		if (cell && cell.dataset.uid) { pick(parseInt(cell.dataset.uid, 10)); }
	});
	ov.addEventListener('click', function (e) { if (e.target === ov && S && !S.busy) { close(); } });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && S && !S.busy) { close(); } });
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.js-lu-fix') : null;
		if (b && !b.disabled) { open(b.dataset.side); }
	});
	window.LuFix = {open: open};

	/* ---- подсказка по либеро ---- */
	window.__luLater = window.__luLater || {};
	function applyHints() {
		var live = document.getElementById('rw-live'), any = false;
		document.querySelectorAll('.lu-hint').forEach(function (h) {
			if (window.__luLater[h.dataset.key]) { h.style.display = 'none'; } else { any = true; }
		});
		if (live) { live.classList.toggle('rw-muted', any); }   // кнопки очков приглушены, но нажимаются
	}
	document.addEventListener('click', function (e) {
		var later = e.target.closest ? e.target.closest('.js-lu-hint-later') : null;
		if (later) { window.__luLater[later.dataset.key] = true; applyHints(); return; }
		var back = e.target.closest ? e.target.closest('.js-lu-hint-back') : null;
		if (back && !back.disabled) {
			back.disabled = true;
			var C = RallyLive.data().lu;
			RallyLive.send(C.eventUrl, {set_number: C.setNumber, team_id: back.dataset.team, type: 'libero_out', client_request_id: RallyLive.uuid()}, {json: true, retry: true},
				function (j) { RallyLive.swap(j.html); },
				function (m) { back.disabled = false; RallyLive.toast(m); });
		}
	});
	document.addEventListener('rally:swapped', applyHints);
	applyHints();
})();
</script>
