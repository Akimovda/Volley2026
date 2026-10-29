{{-- Порядок подачи в пляжке: тап по карточке игрока («Подаёт первым» / «Кто подаёт у <пара>?») и экран «Кто подаёт у <пара> на самом деле?» (SoFix).
     Данные: RallyLive.data().so (BeachServeOrderService::forRally). Подключается после player_card_js. --}}
@php
	$soT = [];
	foreach (['so_fix_title', 'so_fix_sub', 'so_fix_cancel', 'so_fix_same'] as $k) { $soT[$k] = __('tournaments.' . $k); }
@endphp
<script>
(function () {
	var T = @json($soT);
	function so() { return RallyLive.data().so; }
	function esc(v) { return String(v == null ? '' : v).replace(/[&<>"]/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]; }); }

	/* тап по карточке: пара подаёт первой / первый подающий второй пары */
	function pick(card) {
		var C = so(); if (!C || !C.firstUrl || card.dataset.busy) { return; }
		card.dataset.busy = '1';
		RallyLive.send(C.firstUrl, {set_number: C.setNumber, team_id: card.dataset.team, user_id: card.dataset.uid, client_request_id: RallyLive.uuid()}, {json: true, retry: true},
			function (j) { RallyLive.swap(j.html); },
			function (m) { delete card.dataset.busy; RallyLive.toast ? RallyLive.toast(m) : alert(m); });
	}
	document.addEventListener('click', function (e) {
		var c = e.target.closest && e.target.closest('[data-so-pick]');
		if (c && !e.target.closest('.js-so-num')) { pick(c); }
	});
	document.addEventListener('keydown', function (e) {
		var c = e.target.closest && e.target.closest('[data-so-pick]');
		if (c && (e.key === 'Enter' || e.key === ' ') && e.target === c) { e.preventDefault(); pick(c); }
	});
	/* поле «№» под карточкой стартового блока: сохраняется сразу (event_jersey_numbers) */
	function bindNums(root) {
		var C = so(); if (!C) { return; }
		(root || document).querySelectorAll('.js-so-num:not([data-b])').forEach(function (i) {
			i.dataset.b = '1';
			PlayerCardUi.bindNum(i, C.jerseyUrl, C.setNumber, i.dataset.uid, function () { RallyLive.refresh(); });
		});
	}
	bindNums();
	document.addEventListener('rally-live-swapped', function () { bindNums(); });
	document.addEventListener('focusin', function () { bindNums(); });

	/* «Кто подаёт у <пара> на самом деле?» — две карточки; отличие от очереди системы → событие serve_flip */
	var ov = document.createElement('div');
	ov.className = 'rw-overlay lf-ov';
	ov.innerHTML = '<div class="rw-box lf-box"></div>';
	document.body.appendChild(ov);
	var box = ov.querySelector('.lf-box'), S = null;
	function close() { ov.classList.remove('rw-open'); S = null; }
	function open(side, opts) {
		var C = so(); if (!C || !C.teams[side]) { return; }
		S = {side: side, banner: opts && opts.banner, busy: false};
		var t = C.teams[side], h = '';
		if (S.banner) { h += '<div class="lf-banner">' + esc(S.banner) + '</div>'; }
		h += '<div class="rw-title">' + esc(T.so_fix_title.replace(':team', t.name)) + '</div><div class="rw-sub">' + esc(T.so_fix_sub) + '</div><div class="so-pair-cards" style="margin:10px 0"></div>';
		h += '<div class="lf-err" style="color:#dc2626;min-height:1em"></div><button type="button" class="btn btn-secondary w-100 js-so-cancel">' + esc(T.so_fix_cancel) + '</button>';
		box.innerHTML = h;
		var row = box.querySelector('.so-pair-cards');
		t.players.forEach(function (p) {
			var d = document.createElement('div'); d.className = 'ls-zone ls-card so-card' + (t.server_id === p.id ? ' so-sel' : ''); d.setAttribute('role', 'button'); d.tabIndex = 0;
			var w = document.createElement('span'); w.className = 'so-av-wrap'; w.appendChild(PlayerCardUi.avatar(p, t.color, t.text, false));
			if (p.number != null) { var nb = document.createElement('span'); nb.className = 'rw-pc-num'; nb.textContent = p.number; w.appendChild(nb); }
			d.appendChild(w);
			[['ls-zone-name', p.surname], ['ls-zone-first', p.first], ['ls-zone-pos', p.pos]].forEach(function (x) { if (x[1]) { var s = document.createElement('span'); s.className = x[0]; s.textContent = x[1]; d.appendChild(s); } });
			d.addEventListener('click', function () { choose(p.id); });
			row.appendChild(d);
		});
		ov.classList.add('rw-open');
	}
	function choose(uid) {
		var C = so(); if (!S || S.busy) { return; }
		var t = C.teams[S.side];
		if (String(t.server_id) === String(uid)) { close(); return; }   // совпало с очередью — править нечего
		S.busy = true;
		RallyLive.send(C.eventUrl, {set_number: C.setNumber, team_id: t.team_id, type: 'serve_flip', client_request_id: RallyLive.uuid()}, {json: true, retry: true},
			function (j) { close(); RallyLive.swap(j.html); },
			function (m) { if (S) { S.busy = false; box.querySelector('.lf-err').textContent = m; } });
	}
	box.addEventListener('click', function (e) { if (e.target.closest('.js-so-cancel')) { close(); } });
	window.SoFix = {open: open};
})();
</script>
