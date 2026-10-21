{{-- Экран поочкового ввода при включённом учёте состава: расстановка партии + попап замены/либеро.
     Данные приходят готовыми из MatchLineupView::forRally(); JS только рисует и шлёт JSON. --}}
@php
	$luTeams = [];
	foreach ($sides as $sk => $sd) {
		$luTeams[$sk] = $lineup['teams'][$sk] + ['name' => (string) ($sd['team']->name ?? 'TBD')];
	}
	$luCfg = [
		'setNumber' => $setNumber,
		'startUrl'  => route('tournament.matches.rally.lineup.start', $match),
		'eventUrl'  => route('tournament.matches.rally.lineup.event', $match),
		'size'      => $lineup['profile']['on_court_size'],
		'hasLibero' => $lineup['profile']['has_libero'],
		'positions' => $lineup['profile']['positions'],
		'hasStart'  => $lineup['has_start'],
		'teams'     => $luTeams,
		'setup'     => $lineup['setup'],
	];
	$luPos = [];
	foreach (['setter', 'outside', 'opposite', 'middle', 'libero'] as $pc) {
		$luPos[$pc] = __('tournaments.pos_abbr_' . $pc);
	}
	$luT = [
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
	];
@endphp
<script>
(function () {
	var C = @json($luCfg), T = @json($luT), POS = @json($luPos);
	var tokenEl = document.querySelector('#rw-form input[name=_token]');
	var token = tokenEl ? tokenEl.value : '';
	var SIDES = ['home', 'away'];

	function post(url, body, ok, fail) {
		fetch(url, {
			method: 'POST', credentials: 'same-origin',
			headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest'},
			body: JSON.stringify(body)
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) { return {ok: r.ok, j: j}; });
		}).then(function (x) {
			if (x.ok && x.j.success) { ok(x.j); } else { fail((x.j && (x.j.error || x.j.message)) || T.net_err); }
		}).catch(function () { fail(T.net_err); });
	}
	function surname(n) { return String(n || '').split(' ')[0]; }
	function posLabel(p) { return p ? (POS[p] || p) : ''; }
	function zoneLabel(z) { return z ? T.zone.replace(':n', z) : ''; }

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
	function closeOv() { ov.classList.remove('rw-open'); }
	function resetOv(title, sub) {
		oTitle.textContent = title; oSub.textContent = sub || ''; oChips.innerHTML = ''; oNote.textContent = ''; oGrid.innerHTML = ''; oFoot.innerHTML = '';
	}
	function showOv() { ov.classList.add('rw-open'); }
	ov.addEventListener('click', function (e) { if (e.target === ov) closeOv(); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeOv(); });

	/* ---------- замена / либеро ---------- */
	var sub = null;
	function sendEvent(body) {
		oNote.textContent = '';
		body.set_number = C.setNumber; body.team_id = C.teams[sub.side].team_id;
		post(C.eventUrl, body, function () { location.reload(); }, function (m) { oNote.textContent = m; });
	}
	function stepOut() {
		var t = C.teams[sub.side];
		resetOv(T.whoOut, t.name);
		t.court.forEach(function (p) {
			var label = p.name + ' · ' + (p.libero ? posLabel('libero') : posLabel(p.position)) + (p.zone ? ' · ' + zoneLabel(p.zone) : '');
			oGrid.appendChild(mk(label, 'btn-primary', function () { sub.out = p; stepIn(); }));
		});
		t.held.forEach(function (h) {
			oGrid.appendChild(mk(h.name + ' (' + T.forLibero + ')', 'btn-secondary', null, false, true));
		});
		oFoot.appendChild(mk(T.cancel, 'btn-secondary', closeOv));
	}
	function stepIn() {
		var t = C.teams[sub.side], out = sub.out;
		resetOv(T.whoIn, t.name + ' · ' + surname(out.name));
		if (out.libero) {
			var partner = t.held.filter(function (h) { return h.id === out.for_user_id; })[0];
			if (partner) {
				oGrid.appendChild(mk(partner.name + ' — ' + T.insteadLibero, 'btn-primary', function () {
					sendEvent({type: 'libero_out'});
				}, true));
			}
		} else {
			var libero = null, others = [];
			t.bench.forEach(function (b) { if (b.is_libero) { libero = b; } else { others.push(b); } });
			if (libero && C.hasLibero && !t.libero_on_court) {
				var lb = mk(T.liberoFirst + ': ' + libero.name, 'btn-primary rw-hl', function () {
					sendEvent({type: 'libero_in', out_user_id: out.id});
				}, true);
				oGrid.appendChild(lb);
			}
			others.forEach(function (b) {
				oGrid.appendChild(mk(b.name + (b.position ? ' · ' + posLabel(b.position) : ''), 'btn-primary', function () {
					sendEvent({type: 'substitution', out_user_id: out.id, in_user_id: b.id});
				}));
			});
			if (!oGrid.children.length) { oNote.textContent = T.noBench; }
		}
		oFoot.appendChild(mk(T.back, 'btn-secondary', stepOut));
		oFoot.appendChild(mk(T.cancel, 'btn-secondary', closeOv));
	}
	document.querySelectorAll('.js-lu-sub').forEach(function (b) {
		b.addEventListener('click', function () { sub = {side: this.dataset.side, out: null}; stepOut(); showOv(); });
	});

	/* ---------- расстановка ---------- */
	var panel = document.getElementById('ls-panel');
	if (!panel || !C.setup) { return; }
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
	function placedIds(side) { return Object.keys(S[side].zones).map(function (z) { return S[side].zones[z].uid; }); }
	function validPos(p) { return p && C.positions.indexOf(p) !== -1 ? p : null; }
	function zoneOrder() { return C.size === 6 ? [4, 3, 2, 5, 6, 1] : Array.apply(null, Array(C.size)).map(function (_, i) { return i + 1; }); }

	function render() {
		var st = S[cur];
		panel.querySelectorAll('.ls-tab').forEach(function (b) { b.classList.toggle('ls-on', b.dataset.side === cur); });
		panel.querySelectorAll('.ls-serve').forEach(function (b) { b.classList.toggle('ls-on', b.dataset.side === first); });
		courtEl.className = 'ls-court' + (C.size === 6 ? '' : ' ls-court--flat');
		courtEl.innerHTML = '';
		zoneOrder().forEach(function (z) {
			var cell = st.zones[z], b = document.createElement('button');
			b.type = 'button'; b.className = 'ls-zone' + (cell ? ' ls-zone--filled' : '');
			var h = '<span class="ls-zone-n">' + z + '</span>';
			if (first === cur && z === 1) { h += '<span class="ls-ball">🏐</span>'; }
			b.innerHTML = h;
			if (cell) {
				var n = document.createElement('span'); n.className = 'ls-zone-name'; n.textContent = surname(nameOf(cur, cell.uid));
				var p = document.createElement('span'); p.className = 'ls-zone-pos'; p.textContent = posLabel(cell.pos);
				b.appendChild(n); b.appendChild(p);
			}
			b.addEventListener('click', function () { openZone(z); });
			courtEl.appendChild(b);
		});
		if (liberoBtn) { liberoBtn.textContent = T.liberoLabel + ': ' + (st.libero ? nameOf(cur, st.libero) : T.liberoNone); }
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
				var b = mk(p.name + (dp ? ' · ' + posLabel(dp) : ''), 'btn-primary' + (occ && occ.uid === id ? ' rw-hl' : ''), function () {
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
				oGrid.appendChild(mk(p.name + (p.position === 'libero' ? ' · ' + posLabel('libero') : ''), 'btn-primary' + (st.libero === id ? ' rw-hl' : ''), function () {
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
			function () { location.reload(); },
			function (m) { errEl.textContent = m; errEl.style.display = ''; startBtn.disabled = false; });
	});

	render();
})();
</script>
