{{-- Экран поочкового ввода (без layout): общий для организатора (score_rally) и ведущего по токен-ссылке (scorer.match).
     Нужны: $event,$match,$stage,$board,$setNumber,$matchReady,$lineup,$journal,$urls,$unattributed,$finalScore --}}
@php
	$finTxt = [
		'confirm' => __('broadcast.scorer_finalize_confirm'), 'unattr' => __('broadcast.scorer_finalize_unattr'),
		'yes' => __('broadcast.scorer_finalize_yes'), 'no' => __('broadcast.scorer_finalize_no'),
		'startConfirm' => __('broadcast.match_start_confirm'), 'startYes' => __('broadcast.match_start_yes'), 'startNo' => __('broadcast.match_start_no'),
		'unstartConfirm' => __('broadcast.match_unstart_confirm'),
	];
	$luOn = !empty($lineup['enabled']);
	$rwText = [
		'ace' => __('tournaments.rally_action_ace'), 'kill' => __('tournaments.rally_action_kill'), 'block' => __('tournaments.rally_action_block'),
		'opp_serve_error' => __('tournaments.rally_action_opp_serve_error'), 'opp_attack_error' => __('tournaments.rally_action_opp_attack_error'),
		'opp_block_error' => __('tournaments.rally_action_opp_block_error'), 'opp_reception_error' => __('tournaments.rally_action_opp_reception_error'),
		'unattributed' => __('tournaments.rally_action_unattributed'),
		'how' => __('tournaments.rally_wiz_how'), 'errorType' => __('tournaments.rally_wiz_error_type'),
		'whoScored' => __('tournaments.rally_wiz_who_scored'), 'oppWho' => __('tournaments.rally_wiz_opp_who'),
		'dig' => __('tournaments.rally_wiz_dig'), 'assist' => __('tournaments.rally_wiz_assist'),
		'skip' => __('tournaments.rally_wiz_skip'), 'back' => __('tournaments.rally_wiz_back'), 'cancel' => __('tournaments.rally_wiz_cancel'),
		'noPlayer' => __('tournaments.rally_no_player_option'),
	];
@endphp

<div class="container">

	@if(session('error'))
	<div class="alert alert-error">{{ session('error') }}</div>
	@endif
	@if(session('success'))
	<div class="alert alert-success">{{ session('success') }}</div>
	@endif

	<div class="ramka">

		<div id="rw-live" data-fragment-url="{{ $urls['fragment'] }}" data-set="{{ $setNumber }}">
@include('tournaments._partials.rally_live')
		</div>

	</div>
</div>

{{-- Мобильная эргономика: html{font-size} на этом сайте схлопывается до 50% на ≤480px
     (см. style.css), из-за чего .form select реально рендерится ~14.4px — ниже порога 16px,
     вызывающего авто-zoom при фокусе в iOS Safari. Страница используется с телефона у площадки —
     фикс только для этой страницы, без изменения общего .form select/.btn-small. --}}
<style>
.tournament-rally-page .form select {
	font-size: 16px;
}
.tournament-rally-page .btn-small {
	font-size: 13px;
}
</style>

<form id="rw-form" method="POST" action="{{ $urls['point'] }}" style="display:none">
	@csrf
	<input type="hidden" name="set_number" value="{{ $setNumber }}">
	<input type="hidden" name="team_id">
	<input type="hidden" name="action_type">
	<input type="hidden" name="player_id">
	<input type="hidden" name="dig_user_id">
	<input type="hidden" name="assist_user_id">
</form>

<style>
.rw-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:10000;display:none;align-items:flex-end;justify-content:center}
.rw-overlay.rw-open{display:flex}
.rw-box{background:#fff;color:#2c2c2c;width:100%;max-width:520px;max-height:90vh;overflow:auto;border-radius:16px 16px 0 0;padding:16px}
body.dark .rw-box{background:#222333;color:#cacaca}
@media(min-width:600px){.rw-overlay{align-items:center}.rw-box{border-radius:16px}}
.rw-title{font-weight:700;font-size:17px;margin-bottom:4px}
.rw-sub{font-size:13px;opacity:.65;margin-bottom:12px;min-height:1em}
.rw-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.rw-grid .btn{width:100%;font-size:15px;padding:14px 8px;text-align:center;white-space:normal;line-height:1.2}
.rw-grid .rw-wide{grid-column:1/-1}
.rw-foot{display:flex;gap:8px;margin-top:14px}
.rw-foot .btn{flex:1;font-size:14px}
.rw-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(20px);background:#222333;color:#fff;padding:10px 16px;border-radius:12px;font-size:14px;z-index:10002;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;max-width:90vw;text-align:center}
.rw-toast.rw-toast-on{opacity:1;transform:translateX(-50%) translateY(0)}
</style>

<script>
/* Общий рантайм экрана поочкового ввода: AJAX (jQuery — fetch в WKWebView ненадёжен), подмена блока #rw-live
   серверным HTML-фрагментом, блокировка кнопок на время запроса, повтор при сетевой ошибке с тем же client_request_id. */
window.RallyLive = (function () {
	var NET_ERR = @json(__('tournaments.rally_net_err'));
	var live = document.getElementById('rw-live');
	var toastEl = null, toastTimer = null;

	function token() { var t = document.querySelector('#rw-form input[name=_token]'); return t ? t.value : ''; }
	function data() { var el = document.getElementById('rw-live-data'); try { return JSON.parse(el.textContent); } catch (e) { return {rw: {}, lu: null}; } }
	function uuid() {
		if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
		var b = new Uint8Array(16);
		if (window.crypto && crypto.getRandomValues) { crypto.getRandomValues(b); } else { for (var i = 0; i < 16; i++) { b[i] = Math.floor(Math.random() * 256); } }
		b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
		var h = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
		return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
	}
	function errText(xhr, net) {
		var j = xhr && xhr.responseJSON;
		if (net) { return NET_ERR; }
		return (j && (j.error || j.message)) || NET_ERR;
	}

	/* opts: {json: bool (тело JSON), retry: bool}. fail(message). Сетевая ошибка/5xx-шлюза → до 3 повторов тем же телом. */
	function send(url, body, opts, ok, fail) {
		opts = opts || {};
		var tries = 0;
		(function go() {
			jQuery.ajax({
				url: url, method: 'POST', dataType: 'json', timeout: 15000,
				xhrFields: {withCredentials: true},
				data: opts.json ? JSON.stringify(body) : body,
				contentType: opts.json ? 'application/json' : 'application/x-www-form-urlencoded; charset=UTF-8',
				headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest'}
			}).done(function (j) {
				if (j && j.success) { ok(j); } else { fail((j && (j.error || j.message)) || NET_ERR); }
			}).fail(function (xhr) {
				var net = xhr.status === 0 || xhr.status >= 502;
				if (net && opts.retry && tries < 3) { tries++; setTimeout(go, 600 * Math.pow(2, tries - 1)); return; }
				fail(errText(xhr, net), net);
			});
		})();
	}

	function swap(html) {
		if (!live || !html) { return; }
		live.innerHTML = html;
		var jn = document.getElementById('jn-list');
		if (jn) { jn.scrollTop = jn.scrollHeight; }
		document.dispatchEvent(new CustomEvent('rally:swapped'));
	}
	function refresh(done) {
		jQuery.ajax({url: live.getAttribute('data-fragment-url') + '?set=' + live.getAttribute('data-set'), dataType: 'json', xhrFields: {withCredentials: true}, headers: {'Accept': 'application/json'}})
			.done(function (j) { if (j && j.html) { swap(j.html); } })
			.always(function () { if (done) { done(); } });
	}

	/* блокировка кнопок экрана на время запроса */
	var locked = [];
	function busy(on) {
		if (!live) { return; }
		if (on) {
			locked = [];
			live.querySelectorAll('button').forEach(function (b) { if (!b.disabled) { b.disabled = true; locked.push(b); } });
		} else {
			locked.forEach(function (b) { b.disabled = false; });
			locked = [];
		}
	}

	function toast(msg) {
		if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'rw-toast'; document.body.appendChild(toastEl); }
		toastEl.textContent = msg; toastEl.classList.add('rw-toast-on');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.classList.remove('rw-toast-on'); }, 1800);
	}

	var FIN = @json($finTxt);
	function confirmDo(msg, onYes, yesText, noText) {
		if (window.swal) {
			swal({text: msg, icon: 'warning', buttons: {cancel: {text: noText || FIN.no, value: null, visible: true}, confirm: {text: yesText || FIN.yes, value: true}}})
				.then(function (v) { if (v) { onYes(); } });
		} else if (window.confirm(msg)) { onYes(); }
	}

	/* отмена последнего действия и «Записать счёт» — формы внутри #rw-live (делегирование) */
	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (!f || !f.classList) { return; }
		if (f.classList.contains('js-rw-undo')) {
			e.preventDefault();
			if (f.dataset.busy === '1') { return; }
			f.dataset.busy = '1';
			busy(true);
			var body = {}; new FormData(f).forEach(function (v, k) { body[k] = v; });
			send(f.action, body, {retry: false}, function (j) { busy(false); swap(j.html); }, function (m) {
				busy(false); f.dataset.busy = '';
				toast(m);
				refresh(); // расхождение (чужая отмена/очко) — показать актуальное состояние
			});
		} else if (f.classList.contains('js-rw-match-start') || f.classList.contains('js-rw-match-unstart')) {
			// «Матч начался» (с подтверждением; uuid на нажатие — повтор запроса безвреден) и «Отменить начало»
			e.preventDefault();
			if (f.dataset.busy === '1') { return; }
			var isStart = f.classList.contains('js-rw-match-start');
			var rid = f.dataset.rid || (f.dataset.rid = uuid());
			var go = function () {
				f.dataset.busy = '1';
				busy(true);
				send(f.action, {set_number: f.dataset.set, client_request_id: rid}, {retry: true}, function (j) {
					busy(false); swap(j.html);
				}, function (m) {
					busy(false); f.dataset.busy = '';
					toast(m);
					refresh();
				});
			};
			confirmDo(isStart ? FIN.startConfirm : FIN.unstartConfirm, go, isStart ? FIN.startYes : null, isStart ? FIN.startNo : null);
		} else if (f.classList.contains('js-rw-serve-mark')) {
			// Отметка «подаёт первым»: без подтверждения, uuid на нажатие; повтор запроса безвреден
			e.preventDefault();
			if (f.dataset.busy === '1') { return; }
			f.dataset.busy = '1';
			busy(true);
			send(f.action, {set_number: f.dataset.set, team_id: f.dataset.team, client_request_id: uuid()}, {retry: true}, function (j) {
				busy(false); swap(j.html);
			}, function (m) {
				busy(false); f.dataset.busy = '';
				toast(m);
				refresh();
			});
		} else if (f.classList.contains('js-rw-finalize-ajax')) {
			// Ведущий по токен-ссылке: запись итога с подтверждением, ответ JSON → экран площадки
			e.preventDefault();
			if (f.dataset.busy === '1') { return; }
			var un = parseInt(f.dataset.unattributed || '0', 10) || 0;
			var msg = FIN.confirm.replace(':score', f.dataset.score || '');
			if (un > 0) { msg += '\n\n' + FIN.unattr.replace(':n', String(un)); }
			confirmDo(msg, function () {
				f.dataset.busy = '1';
				busy(true);
				send(f.action, {}, {retry: false}, function (j) {
					window.location.href = j.redirect || window.location.href;
				}, function (m) {
					busy(false); f.dataset.busy = '';
					toast(m);
					refresh();
				});
			});
		} else if (f.classList.contains('js-rw-finalize')) {
			if (f.dataset.sent === '1') { e.preventDefault(); return; }
			f.dataset.sent = '1';
			var b = f.querySelector('button[type=submit]');
			if (b) { setTimeout(function () { b.disabled = true; }, 0); }
		}
	});
	// возврат из bfcache — формы снова рабочие
	window.addEventListener('pageshow', function (ev) { if (ev.persisted) { refresh(); } });

	return {send: send, swap: swap, refresh: refresh, busy: busy, toast: toast, uuid: uuid, data: data, token: token, netErr: NET_ERR};
})();
</script>

<script>
(function () {
	var DATA = RallyLive.data().rw;
	var T = @json($rwText);
	var SELF = ['ace', 'kill', 'block'];
	var OPP = ['opp_serve_error', 'opp_attack_error', 'opp_block_error', 'opp_reception_error'];
	var OPP_REQ = ['opp_serve_error', 'opp_attack_error'];
	var form = document.getElementById('rw-form');
	var st = null, history = [], sending = false;

	var overlay = document.createElement('div');
	overlay.className = 'rw-overlay';
	overlay.innerHTML = '<div class="rw-box"><div class="rw-title"></div><div class="rw-sub"></div><div class="rw-grid"></div><div class="rw-note" style="color:#dc2626;font-size:13px;margin-top:8px;min-height:1em"></div><div class="rw-foot"></div></div>';
	document.body.appendChild(overlay);
	var elTitle = overlay.querySelector('.rw-title'), elSub = overlay.querySelector('.rw-sub'),
		elGrid = overlay.querySelector('.rw-grid'), elFoot = overlay.querySelector('.rw-foot'), elNote = overlay.querySelector('.rw-note');

	function btn(label, cls, onClick, wide) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'btn ' + cls + (wide ? ' rw-wide' : '');
		b.textContent = label;
		b.addEventListener('click', onClick);
		return b;
	}
	function nameOf(list, id) {
		for (var i = 0; i < list.length; i++) if (String(list[i].id) === String(id)) return list[i].name;
		return '';
	}
	function close() { if (sending) return; overlay.classList.remove('rw-open'); st = null; history = []; }

	function submit() {
		if (sending) return;
		sending = true;
		st.rid = st.rid || RallyLive.uuid(); // один uuid на одно нажатие: повтор запроса не задвоит очко
		var body = {
			set_number: form.elements.set_number.value,
			team_id: DATA[st.side].teamId,
			action_type: st.action || '',
			client_request_id: st.rid
		};
		if (st.player) body.player_id = st.player;
		if (st.dig) body.dig_user_id = st.dig;
		if (st.assist) body.assist_user_id = st.assist;
		setSending(true);
		RallyLive.send(form.action, body, {retry: true}, function (j) {
			sending = false; setSending(false);
			close();
			RallyLive.swap(j.html);
		}, function (m, net) {
			sending = false; setSending(false);
			elNote.textContent = m; // тот же st.rid — повторное нажатие не задвоит очко, если первый запрос дошёл
			if (!net) { RallyLive.refresh(); }
		});
	}
	function setSending(on) {
		overlay.querySelectorAll('button').forEach(function (b) { b.disabled = on; });
		if (on) elNote.textContent = '';
	}

	function go(step) { history.push(st.step); st.step = step; render(); }
	function back() { if (history.length) { st.step = history.pop(); render(); } else close(); }

	function nextAfterPlayer() {
		// ace — прямой выигрыш на подаче: приёма/передачи своей команды нет
		if (st.kind === 'own' && st.action !== 'ace') {
			var d = DATA[st.side];
			if (d.pairMode && d.players.length === 2) {
				// Пляжка 2×2: шаг «Кто принимал?» не показываем.
				// Атака — партнёр записывается в assist_user_id (пас); скидка (attack_type = 'dump') — партнёра НЕ пишем.
				// Блок — в dig/assist ничего не пишем. dig_user_id в пляжке автоматически не заполняется никогда.
				if (st.action === 'kill' && st.attackType !== 'dump') {
					var partner = d.players.filter(function (p) { return String(p.id) !== String(st.player); })[0];
					if (partner) { st.assist = String(partner.id); }
				}
				submit();
				return;
			}
			go('dig');
		} else { submit(); }
	}

	function render() {
		var d = DATA[st.side], players = d.players;
		elGrid.innerHTML = ''; elFoot.innerHTML = ''; elNote.textContent = '';
		var crumbs = [d.teamName];
		if (st.action) crumbs.push(T[st.action]);
		if (st.player) crumbs.push(nameOf(st.kind === 'own' ? d.players : d.oppPlayers, st.player));
		if (st.dig) crumbs.push('⤵ ' + nameOf(players, st.dig));
		elSub.textContent = crumbs.join(' · ');

		if (st.step === 'action') {
			elTitle.textContent = st.kind === 'own' ? T.how : T.errorType;
			var list = st.kind === 'own' ? SELF : OPP.concat(['unattributed']);
			list.forEach(function (a) {
				elGrid.appendChild(btn(T[a], st.kind === 'own' ? 'btn-primary' : 'btn-secondary', function () {
					st.action = a;
					if (a === 'unattributed') { submit(); } else { go('player'); }
				}, list.length % 2 === 1 && a === list[list.length - 1]));
			});
		} else if (st.step === 'player') {
			var pl = st.kind === 'own' ? d.players : d.oppPlayers;
			elTitle.textContent = st.kind === 'own' ? T.whoScored : T.oppWho;
			pl.forEach(function (p) {
				elGrid.appendChild(btn(p.name, 'btn-primary', function () { st.player = String(p.id); nextAfterPlayer(); }));
			});
			if (st.kind === 'opp' && OPP_REQ.indexOf(st.action) === -1) {
				elGrid.appendChild(btn(T.noPlayer, 'btn-secondary', function () { st.player = ''; submit(); }, true));
			}
		} else if (st.step === 'dig' || st.step === 'assist') {
			var isDig = st.step === 'dig';
			elTitle.textContent = isDig ? T.dig : T.assist;
			var left = players.filter(function (p) {
				return String(p.id) !== String(st.player) && (isDig || String(p.id) !== String(st.dig));
			});
			if (!left.length) { if (isDig) { st.step = 'assist'; return render(); } return submit(); }
			left.forEach(function (p) {
				elGrid.appendChild(btn(p.name, 'btn-primary', function () {
					if (isDig) { st.dig = String(p.id); go('assist'); } else { st.assist = String(p.id); submit(); }
				}));
			});
			elGrid.appendChild(btn(T.skip, 'btn-secondary', function () {
				if (isDig) { go('assist'); } else { submit(); }
			}, true));
		}

		elFoot.appendChild(btn(T.back, 'btn-secondary', back));
		elFoot.appendChild(btn(T.cancel, 'btn-secondary', close));
	}

	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.js-rw-start') : null;
		if (!b || b.disabled) return;
		DATA = RallyLive.data().rw;
		st = { side: b.dataset.side, kind: b.dataset.kind, step: 'action', action: '', player: '', dig: '', assist: '', rid: '' };
		history = [];
		overlay.classList.add('rw-open');
		render();
	});
	overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && st) close(); });
})();
</script>

@if($luOn)
@include('tournaments._partials.lineup_popup_js')
@include('tournaments._partials.lineup_rally_js')
@endif
