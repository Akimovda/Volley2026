{{-- Экран поочкового ввода при включённом учёте состава: расстановка партии + попап замены/либеро.
     Данные приходят готовыми из MatchLineupView::forRally(); JS только рисует и шлёт JSON. --}}
@php
	$luPos = [];
	foreach (['setter', 'outside', 'opposite', 'middle', 'libero'] as $pc) {
		$luPos[$pc] = __('tournaments.pos_abbr_' . $pc);
	}
	$luPosFull = [];
	foreach (['setter', 'outside', 'opposite', 'middle', 'libero'] as $pc) {
		$luPosFull[$pc] = __('tournaments.pos_full_' . $pc);
	}
	$luT = [
		'jerseyPh' => __('tournaments.lineup_jersey_ph'), 'jerseySaved' => __('tournaments.lineup_jersey_saved'),
		'liberoRow' => __('tournaments.lineup_libero_row'),
		'net' => __('tournaments.lineup_net'),
		'whoOut' => __('tournaments.lineup_sub_who_out'), 'whoIn' => __('tournaments.lineup_sub_who_in'),
		'forLibero' => __('tournaments.lineup_sub_for_libero'), 'insteadLibero' => __('tournaments.lineup_sub_instead_of_libero'),
		'liberoFirst' => __('tournaments.lineup_sub_libero_first'), 'noBench' => __('tournaments.lineup_sub_no_bench'),
		'zone' => __('tournaments.lineup_zone_n', ['n' => ':n']),
		'pickPlayer' => __('tournaments.lineup_pick_player', ['zone' => ':n']),
		'pickPos' => __('tournaments.lineup_pick_position'), 'clearZone' => __('tournaments.lineup_clear_zone'),
		'liberoPick' => __('tournaments.lineup_libero_pick'), 'liberoNone' => __('tournaments.lineup_libero_none'),
		'liberoLabel' => __('tournaments.lineup_libero_label'),
		'unplaced' => __('tournaments.lineup_unplaced'),
		'missPrefix' => __('tournaments.lineup_missing_prefix'),
		'missZones' => __('tournaments.lineup_missing_zones', ['team' => ':team', 'n' => ':n']),
		'missServe' => __('tournaments.lineup_missing_serve'),
		'back' => __('tournaments.rally_wiz_back'), 'cancel' => __('tournaments.rally_wiz_cancel'),
		'net_err' => __('tournaments.lineup_err_network'),
		'saving' => __('tournaments.lineup_saving'), 'ok' => __('tournaments.lineup_ok'),
		'doneSub' => __('tournaments.lineup_done_sub'), 'doneLiberoIn' => __('tournaments.lineup_done_libero_in'),
		'doneLiberoOut' => __('tournaments.lineup_done_libero_out'),
		'tabOk' => __('tournaments.lineup_tab_ok'), 'tabMissOne' => __('tournaments.lineup_tab_missing_one'),
		'tabMissMany' => __('tournaments.lineup_tab_missing_many'),
		'setupTitle' => __('tournaments.lineup_setup_title', ['n' => $setNumber]),
	];
@endphp
<script>
(function () {
	var C = RallyLive.data().lu, T = @json($luT), POS = @json($luPos), POSFULL = @json($luPosFull);
	var SIDES = ['home', 'away'];

	/* запросы — общий рантайм (jQuery.ajax, повтор при сетевой ошибке тем же client_request_id) */
	function post(url, body, ok, fail) {
		RallyLive.send(url, body, {json: true, retry: !!body.client_request_id}, ok, fail);
	}
	function surname(n) { return String(n || '').split(' ')[0]; }
	function posLabel(p) { return p ? (POS[p] || p) : ''; }
	function zoneLabel(z) { return z ? T.zone.replace(':n', z) : ''; }
	function posFull(p) { return p ? (POSFULL[p] || POS[p] || p) : ''; }

	/* ---------- общий оверлей ---------- */
	var ov = document.createElement('div');
	ov.className = 'rw-overlay';
	ov.innerHTML = '<div class="rw-box"><div class="rw-title"></div><div class="rw-sub"></div><div class="rw-chips"></div><div class="rw-note"></div><div class="rw-grid"></div><div class="rw-foot"></div></div>';
	document.body.appendChild(ov);
	var oTitle = ov.querySelector('.rw-title'), oSub = ov.querySelector('.rw-sub'), oChips = ov.querySelector('.rw-chips'),
		oNote = ov.querySelector('.rw-note'), oGrid = ov.querySelector('.rw-grid'), oFoot = ov.querySelector('.rw-foot');
	function mk(label, cls, fn, wide, disabled) {
		var b = document.createElement('button');
		b.type = 'button'; b.className = 'btn ' + cls + (wide ? ' rw-wide' : '');
		b.textContent = label;
		if (disabled) { b.disabled = true; } else { b.addEventListener('click', fn); }
		return b;
	}
	/* кнопка игрока: 1-я строка — Фамилия Имя, 2-я — бейдж позиции + зона (мелко) */
	function mkP(name, posText, zoneText, cls, fn, wide) {
		var b = document.createElement('button');
		b.type = 'button'; b.className = 'btn rw-player ' + cls + (wide ? ' rw-wide' : '');
		var n = document.createElement('span'); n.className = 'rw-pname'; n.textContent = name; b.appendChild(n);
		if (posText || zoneText) {
			var m = document.createElement('span'); m.className = 'rw-psub';
			if (posText) { var g = document.createElement('span'); g.className = 'rw-pbadge'; g.textContent = posText; m.appendChild(g); }
			if (zoneText) { m.appendChild(document.createTextNode(zoneText)); }
			b.appendChild(m);
		}
		if (fn) { b.addEventListener('click', fn); } else { b.disabled = true; }
		return b;
	}
	function closeOv() { ov.classList.remove('rw-open'); }
	function resetOv(title, sub) {
		oTitle.textContent = title; oSub.textContent = sub || ''; oChips.innerHTML = ''; oNote.textContent = ''; oGrid.innerHTML = ''; oFoot.innerHTML = '';
	}
	function showOv() { ov.classList.add('rw-open'); }
	ov.addEventListener('click', function (e) { if (e.target === ov) closeOv(); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeOv(); });

	/* ---------- замена / либеро ---------- */
	var sub = null;
	var busy = false;
	function lockOv(on) {
		busy = on;
		ov.querySelectorAll('button').forEach(function (b) { b.disabled = on; });
	}
	function doneText() {
		var t = C.teams[sub.side], o = sub.out ? surname(sub.out.name) : '', i = sub.inName || '';
		return t.name + ' · ' + o + ' → ' + i;
	}
	function sendEvent(body, inName, doneKey) {
		if (busy) { return; }
		sub.inName = inName;
		sub.rid = sub.rid || RallyLive.uuid(); // один uuid на одно нажатие: повтор запроса не задвоит замену
		oNote.style.color = 'inherit'; oNote.textContent = T.saving;
		lockOv(true);
		body.set_number = C.setNumber; body.team_id = C.teams[sub.side].team_id; body.client_request_id = sub.rid;
		post(C.eventUrl, body, function (j) {
			var msg = '✓ ' + T[doneKey] + ' · ' + doneText();
			busy = false; closeOv();
			RallyLive.swap(j.html);
			RallyLive.toast(msg);
		}, function (m) { lockOv(false); oNote.style.color = '#dc2626'; oNote.textContent = m; });
	}
	function stepOut() {
		var t = C.teams[sub.side];
		resetOv(T.whoOut, t.name);
		t.court.forEach(function (p) {
			oGrid.appendChild(mkP(p.name, p.libero ? posLabel('libero') : posLabel(p.position), zoneLabel(p.zone), 'btn-primary', function () { sub.out = p; stepIn(); }));
		});
		t.held.forEach(function (h) {
			oGrid.appendChild(mkP(h.name, '', T.forLibero, 'btn-secondary', null));
		});
		oFoot.appendChild(mk(T.cancel, 'btn-secondary', closeOv));
	}
	function stepIn() {
		var t = C.teams[sub.side], out = sub.out;
		resetOv(T.whoIn, t.name + ' · ' + surname(out.name));
		if (out.libero) {
			var partner = t.held.filter(function (h) { return h.id === out.for_user_id; })[0];
			if (partner) {
				oGrid.appendChild(mkP(partner.name, '', T.insteadLibero, 'btn-primary', function () {
					sendEvent({type: 'libero_out'}, surname(partner.name), 'doneLiberoOut');
				}, true));
			}
		} else {
			var libero = null, others = [];
			t.bench.forEach(function (b) { if (b.is_libero) { libero = b; } else { others.push(b); } });
			if (libero && C.hasLibero && !t.libero_on_court) {
				var lb = mkP(libero.name, posLabel('libero'), T.liberoFirst, 'btn-primary rw-hl', function () {
					sendEvent({type: 'libero_in', out_user_id: out.id}, surname(libero.name), 'doneLiberoIn');
				}, true);
				oGrid.appendChild(lb);
			}
			others.forEach(function (b) {
				oGrid.appendChild(mkP(b.name, posLabel(b.position), '', 'btn-primary', function () {
					sendEvent({type: 'substitution', out_user_id: out.id, in_user_id: b.id}, surname(b.name), 'doneSub');
				}));
			});
			if (!oGrid.children.length) { oNote.textContent = T.noBench; }
		}
		oFoot.appendChild(mk(T.back, 'btn-secondary', stepOut));
		oFoot.appendChild(mk(T.cancel, 'btn-secondary', closeOv));
	}
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.js-lu-sub') : null;
		if (!b || b.disabled) { return; }
		sub = {side: b.dataset.side, out: null, rid: ''};
		stepOut(); showOv();
	});

	/* ---------- расстановка (пересоздаётся после каждой подмены блока) ---------- */
	function initPanel() {
		C = RallyLive.data().lu;
		var panel = document.getElementById('ls-panel');
		if (!panel || !C || !C.setup) { return; }
		var courtEl = document.getElementById('ls-court'), poolEl = document.getElementById('ls-pool'),
			missEl = document.getElementById('ls-missing'), errEl = document.getElementById('ls-error'),
			startBtn = document.getElementById('ls-start'), liberoBtn = document.getElementById('ls-libero'),
			toggle = document.getElementById('ls-toggle');
		var cur = 'home', first = C.setup.first_serve_side, S = {}, R = {};

		SIDES.forEach(function (side) {
			var st = {zones: {}, libero: C.setup.teams[side].libero || null, cand: []};
			R[side] = {};
			C.setup.teams[side].roster.forEach(function (p) { R[side][p.id] = p; });
			C.setup.teams[side].court.forEach(function (c) {
				if (c.zone) { st.zones[c.zone] = {uid: c.user_id, pos: c.position || null}; } else { st.cand.push(c.user_id); }
				if (c.position && R[side][c.user_id] && !R[side][c.user_id].position) { R[side][c.user_id].position = c.position; }
			});
			S[side] = st;
		});

		function nameOf(side, uid) { return R[side][uid] ? R[side][uid].name : '#' + uid; }
		/* кружок 56px: фото или инициалы на цвете команды (без внешних сервисов); либеро — фиолетовый */
		function avatar(p, side, lib) {
			var a = document.createElement('span');
			a.className = 'ls-av' + (lib ? ' ls-av--lib' : '');
			var team = C.teams[side] || {};
			a.style.background = lib ? '#7C3AED' : (team.color || '#2967BA');
			a.style.color = lib ? '#fff' : (team.text || '#fff');
			if (p.photo) { var im = document.createElement('img'); im.src = p.photo; im.alt = ''; a.style.background = 'transparent'; a.appendChild(im); }
			else { a.textContent = p.initials || '?'; }
			return a;
		}
		/* поле «№»: правка сразу сохраняется (event_jersey_numbers), расстановку и выбор не сбрасывает */
		function numInput(side, uid) {
			var i = document.createElement('input');
			i.type = 'text'; i.className = 'ls-num'; i.setAttribute('inputmode', 'numeric'); i.maxLength = 2;
			i.placeholder = T.jerseyPh; i.value = (R[side][uid] && R[side][uid].number != null) ? R[side][uid].number : '';
			i.addEventListener('click', function (e) { e.stopPropagation(); });
			i.addEventListener('input', function () { i.value = i.value.replace(/[^0-9]/g, ''); });
			i.addEventListener('change', function () {
				var v = i.value.trim();
				i.classList.remove('ls-num--ok'); i.classList.remove('ls-num--err');
				if (!C.jerseyUrl) { return; }
				RallyLive.send(C.jerseyUrl, {set_number: C.setNumber, user_id: uid, number: v}, {json: true, retry: true}, function () {
					if (R[side][uid]) { R[side][uid].number = v === '' ? null : parseInt(v, 10); }
					i.classList.add('ls-num--ok'); setTimeout(function () { i.classList.remove('ls-num--ok'); }, 1200);
				}, function (m) { i.classList.add('ls-num--err'); i.title = m; });
			});
			return i;
		}
		function placedIds(side) { return Object.keys(S[side].zones).map(function (z) { return S[side].zones[z].uid; }); }
		function validPos(p) { return p && C.positions.indexOf(p) !== -1 ? p : null; }
		function zoneOrder() { return C.size === 6 ? [4, 3, 2, 5, 6, 1] : Array.apply(null, Array(C.size)).map(function (_, i) { return i + 1; }); }

		var ACCENT = {home: ['#2967BA', '41,103,186'], away: ['#E7612F', '231,97,47']};
		function missingZones(side) {
			return zoneOrder().filter(function (z) { return !S[side].zones[z]; }).sort(function (a, b) { return a - b; });
		}
		function render() {
			var st = S[cur];
			panel.style.setProperty('--ls-accent', ACCENT[cur][0]);
			panel.style.setProperty('--ls-accent-rgb', ACCENT[cur][1]);
			var titleEl = document.getElementById('ls-title');
			if (titleEl) { titleEl.textContent = T.setupTitle + ' · ' + C.teams[cur].name; }
			panel.querySelectorAll('.ls-tab').forEach(function (b) {
				var sd = b.dataset.side, miss = missingZones(sd), stEl = b.querySelector('.ls-tab-state');
				b.classList.toggle('ls-on', sd === cur);
				b.classList.toggle('ls-done', miss.length === 0);
				b.style.setProperty('--ls-tab-accent', ACCENT[sd][0]);
				if (stEl) { stEl.textContent = miss.length === 0 ? '✓ ' + T.tabOk : (miss.length === 1 ? T.tabMissOne : T.tabMissMany).replace(':z', miss.join(', ')); }
			});
			panel.querySelectorAll('.ls-serve').forEach(function (b) { b.classList.toggle('ls-on', b.dataset.side === first); });
			courtEl.className = 'ls-court' + (C.size === 6 ? '' : ' ls-court--flat');
			courtEl.innerHTML = '';
			zoneOrder().forEach(function (z) {
				var cell = st.zones[z], b = document.createElement('div');
				b.setAttribute('role', 'button'); b.tabIndex = 0; b.className = 'ls-zone ls-card' + (cell ? ' ls-zone--filled' : '');
				var h = '<span class="ls-zone-n">' + z + '</span>';
				if (first === cur && z === 1) { h += '<span class="ls-ball">🏐</span>'; }
				b.innerHTML = h;
				if (cell) {
					var rp = R[cur][cell.uid] || {};
					b.appendChild(avatar(rp, cur));
					var n = document.createElement('span'); n.className = 'ls-zone-name'; n.textContent = rp.surname || surname(nameOf(cur, cell.uid));
					var fn = document.createElement('span'); fn.className = 'ls-zone-first'; fn.textContent = rp.first || '';
					var p = document.createElement('span'); p.className = 'ls-zone-pos'; p.textContent = posFull(cell.pos);
					b.appendChild(n); b.appendChild(fn); b.appendChild(p);
					b.appendChild(numInput(cur, cell.uid));
				}
				b.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('.ls-num')) { return; } openZone(z); });
				b.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target === b) { e.preventDefault(); openZone(z); } });
				courtEl.appendChild(b);
			});
			if (liberoBtn) { liberoBtn.textContent = T.liberoLabel + ': ' + (st.libero ? nameOf(cur, st.libero) : T.liberoNone); }
			var libRow = document.getElementById('ls-libero-row');
			if (libRow) {
				libRow.innerHTML = '';
				if (st.libero && R[cur][st.libero]) {
					var lr = R[cur][st.libero];
					libRow.className = 'ls-libero-row';
					libRow.appendChild(avatar(lr, cur, true));
					var lt = document.createElement('div'); lt.className = 'ls-libero-txt';
					lt.innerHTML = '<b></b><span></span>';
					lt.querySelector('b').textContent = (lr.surname || '') + (lr.first ? ' ' + lr.first : '');
					lt.querySelector('span').textContent = T.liberoRow;
					libRow.appendChild(lt);
					libRow.appendChild(numInput(cur, st.libero));
				} else { libRow.className = ''; }
			}
			var placed = placedIds(cur), un = st.cand.filter(function (id) { return placed.indexOf(id) === -1 && id !== st.libero; });
			poolEl.style.display = un.length ? '' : 'none';
			poolEl.innerHTML = '';
			if (un.length) {
				poolEl.appendChild(document.createTextNode(T.unplaced + ': '));
				un.forEach(function (id) { var s = document.createElement('span'); s.className = 'ls-pill'; s.textContent = nameOf(cur, id); poolEl.appendChild(s); });
			}
			var miss = [];
			SIDES.forEach(function (side) {
				var n = C.size - Object.keys(S[side].zones).length;
				if (n > 0) { miss.push(T.missZones.replace(':team', C.teams[side].name).replace(':n', n)); }
			});
			if (!first) { miss.push(T.missServe); }
			missEl.textContent = miss.length ? T.missPrefix + miss.join('; ') : '';
			startBtn.disabled = miss.length > 0;
		}

		function openZone(z) {
			var st = S[cur], occ = st.zones[z] || null, chosen = occ ? occ.pos : null, touched = false;
			function draw() {
				resetOv(T.pickPlayer.replace(':n', z), C.teams[cur].name);
				C.positions.forEach(function (p) {
					oChips.appendChild(mk(posLabel(p), 'btn-secondary' + (chosen === p ? ' rw-on' : ''), function () { chosen = p; touched = true; draw(); }));
				});
				var placed = placedIds(cur), seen = {}, list = [];
				function add(id) { if (!seen[id] && id !== st.libero) { seen[id] = 1; list.push(id); } }
				if (occ) { add(occ.uid); }
				st.cand.forEach(function (id) { if (placed.indexOf(id) === -1) { add(id); } });
				Object.keys(R[cur]).forEach(function (id) { id = parseInt(id, 10); if (placed.indexOf(id) === -1) { add(id); } });
				list.forEach(function (id) {
					var p = R[cur][id], dp = validPos(p.position);
					var b = mkP(p.name, posLabel(dp), '', 'btn-primary' + (occ && occ.uid === id ? ' rw-hl' : ''), function () {
						var pos = touched ? chosen : ((occ && occ.uid === id && occ.pos) || dp || chosen);
						if (!pos) { oNote.textContent = T.pickPos + '?'; return; }
						st.zones[z] = {uid: id, pos: pos};
						closeOv(); render();
					});
					oGrid.appendChild(b);
				});
				if (occ) { oFoot.appendChild(mk(T.clearZone, 'btn-secondary', function () { delete st.zones[z]; closeOv(); render(); })); }
				oFoot.appendChild(mk(T.cancel, 'btn-secondary', closeOv));
			}
			draw(); showOv();
		}

		if (liberoBtn) {
			liberoBtn.addEventListener('click', function () {
				var st = S[cur], placed = placedIds(cur);
				resetOv(T.liberoPick, C.teams[cur].name);
				Object.keys(R[cur]).forEach(function (id) {
					id = parseInt(id, 10);
					if (placed.indexOf(id) !== -1) { return; }
					var p = R[cur][id];
					oGrid.appendChild(mkP(p.name, p.position === 'libero' ? posLabel('libero') : '', '', 'btn-primary' + (st.libero === id ? ' rw-hl' : ''), function () {
						st.libero = id; closeOv(); render();
					}));
				});
				oGrid.appendChild(mk(T.liberoNone, 'btn-secondary', function () { st.libero = null; closeOv(); render(); }, true));
				oFoot.appendChild(mk(T.cancel, 'btn-secondary', closeOv));
				showOv();
			});
		}

		panel.querySelectorAll('.ls-tab').forEach(function (b) { b.addEventListener('click', function () { cur = this.dataset.side; render(); }); });
		panel.querySelectorAll('.ls-serve').forEach(function (b) { b.addEventListener('click', function () { first = this.dataset.side; render(); }); });
		if (toggle) { toggle.addEventListener('click', function () { panel.style.display = panel.style.display === 'none' ? '' : 'none'; }); }

		startBtn.addEventListener('click', function () {
			errEl.style.display = 'none';
			startBtn.disabled = true;
			var lineups = {};
			SIDES.forEach(function (side) {
				var st = S[side], court = [];
				Object.keys(st.zones).forEach(function (z) { court.push({user_id: st.zones[z].uid, position: st.zones[z].pos, zone: parseInt(z, 10)}); });
				lineups[C.teams[side].team_id] = {court: court, libero: C.hasLibero ? st.libero : null};
			});
			post(C.startUrl, {set_number: C.setNumber, first_serve_team_id: C.teams[first].team_id, lineups: lineups},
				function (j) { closeOv(); RallyLive.swap(j.html); },
				function (m) { errEl.textContent = m; errEl.style.display = ''; startBtn.disabled = false; });
		});

		render();
	}
	initPanel();
	document.addEventListener('rally:swapped', initPanel);
})();
</script>
