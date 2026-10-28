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
		'atkPower' => __('tournaments.rally_attack_power'), 'atkCut' => __('tournaments.rally_attack_cut'), 'atkPoke' => __('tournaments.rally_attack_poke'),
		'atkRoll' => __('tournaments.rally_attack_roll'), 'atkTip' => __('tournaments.rally_attack_tip'),
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
/* 5 подтипов атаки: 3 + 2 (нижние по центру), все кнопки одной ширины — без горизонтального скролла на 390 px */
.rw-atk{display:grid;grid-template-columns:repeat(6,1fr);gap:8px}
.rw-atk .btn{grid-column:span 2;font-size:14px;padding:10px 4px;min-height:54px;white-space:normal;line-height:1.15;text-align:center}
.rw-atk .btn:nth-child(4){grid-column:2 / span 2}
.rw-atk .btn:nth-child(5){grid-column:4 / span 2}
.rw-box .rw-atk .btn.on{background:#2967BA!important;color:#fff!important;border-color:#2967BA!important}
.rw-foot{display:flex;gap:8px;margin-top:14px}
.rw-foot .btn{flex:1;font-size:14px}
/* кнопки «Подаёт»: невыбранная — белая с контуром цвета команды (в тёмной теме прозрачная), выбранная — заливка (inline) */
#rw-live .rw-serve-btn{box-shadow:none}
#rw-live .rw-serve-btn:not(.on){background:#fff;color:#2c2c2c}
body.dark #rw-live .rw-serve-btn:not(.on){background:transparent;color:#cacaca}
/* белые кнопки выбора: контур и лёгкая тень, чтобы не сливались с белым фоном окна */
.rw-box .btn-secondary{background:#fff;border:1px solid #B5C3D9;box-shadow:0 1px 2px rgba(20,48,107,.12)}
.rw-box .btn-secondary:active,.rw-box .btn-secondary:focus-visible{border-color:#2967BA}
body.dark .rw-box .btn-secondary{background:transparent;border-color:rgba(181,195,217,.45);box-shadow:none}
.rw-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(20px);background:#222333;color:#fff;padding:10px 16px;border-radius:12px;font-size:14px;z-index:10002;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;max-width:90vw;text-align:center}
.rw-toast.rw-toast-on{opacity:1;transform:translateX(-50%) translateY(0)}
.rw-sidesw{position:fixed;left:50%;top:70px;transform:translateX(-50%);background:#2967BA;color:#fff;padding:12px 20px;border-radius:14px;font-size:17px;font-weight:700;z-index:10003;opacity:0;pointer-events:none;transition:opacity .2s;box-shadow:0 4px 14px rgba(20,48,107,.35);text-align:center;max-width:90vw}
.rw-sidesw.on{opacity:1}


/* ===== Экран стартовой расстановки: карточки игроков ===== */
.ls-card{cursor:pointer;min-height:150px;padding:8px 4px 6px;justify-content:flex-start;gap:1px}
.ls-av{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:19px;overflow:hidden;flex:none;border:2px solid rgba(255,255,255,.7);box-shadow:0 0 0 1px rgba(0,0,0,.12)}
.ls-av img{width:100%;height:100%;object-fit:cover}
.ls-av--lib{border-color:#C4B5FD}
.ls-card .ls-zone-pos{display:inline-block;white-space:nowrap;line-height:1;font-size:11px;font-weight:800;text-align:center;margin-top:3px;padding:3px 8px;border-radius:8px;background:rgba(0,0,0,.14);opacity:1}
.ls-zone-first{font-size:12px;opacity:.8;line-height:1.1;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ls-num{width:52px;margin-top:4px;text-align:center;font-weight:800;font-size:16px;border:2px solid rgba(0,0,0,.2);border-radius:8px;padding:2px 4px;background:#fff;color:#111}
.ls-num--ok{border-color:#16a34a}
.ls-num--err{border-color:#dc2626}
.ls-libero-row{display:flex;align-items:center;gap:10px;margin-top:10px;padding:8px;border-radius:12px;background:rgba(124,58,237,.10);border:1px solid rgba(124,58,237,.35)}
.ls-libero-txt{flex:1;min-width:0;display:flex;flex-direction:column}
.ls-libero-txt b{font-size:14px}
.ls-libero-txt span{font-size:12px;opacity:.8;color:#7C3AED}
/* ===== Мини-площадка (учёт состава): физические зоны, подающий, анимация перехода ===== */
.mc-wrap{margin-left:auto;margin-right:auto;max-width:420px}
.mc{position:relative;border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.2);cursor:zoom-in;user-select:none;-webkit-tap-highlight-color:transparent}
.mc-half{padding:8px 6px 6px}
.mc-net{height:8px;background:repeating-linear-gradient(90deg,#fff 0 6px,#cbd5e1 6px 12px);display:flex;align-items:center;justify-content:center;position:relative}
.mc-net span{position:absolute;font-size:9px;letter-spacing:.14em;text-transform:uppercase;background:rgba(15,23,42,.7);color:#fff;padding:0 6px;border-radius:6px;top:-5px;line-height:1.5}
.mc-title{font-weight:800;font-size:13px;text-align:center;margin:2px 0 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mc-ball{opacity:0;margin-right:4px;transition:opacity .2s;display:inline-flex;vertical-align:-.15em}
.ball-ic{width:1.05em;height:1.05em;display:inline-block;fill:none !important}
.mc-server-ic{display:inline-flex;vertical-align:-.15em;margin-right:4px}
.mc-server--warn{color:#dc2626;font-weight:800}
.ls-bi{display:inline-flex;vertical-align:-.15em}
.mc-ball.on{opacity:1}
.mc-row{display:flex;justify-content:space-around;gap:4px;margin:2px 0}
.mc-cell{position:relative;flex:1 1 0;min-width:0;text-align:center;padding:2px 0 1px;border-radius:10px;background:rgba(255,255,255,.10);will-change:transform}
.mc-z{position:absolute;left:4px;top:2px;font-size:9px;font-weight:700;opacity:.7}
.mc-av{position:relative;width:40px;height:40px;margin:0 auto;border-radius:50%;overflow:visible;display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,.55)}
.mc-av img{width:100%;height:100%;border-radius:50%;object-fit:cover;display:block}
.mc-ini{font-weight:800;font-size:14px;letter-spacing:.02em}
.mc-av--lib{border-color:#7C3AED;border-width:3px;background:#7C3AED !important}
.mc-av--srv{box-shadow:0 0 0 3px #EAB308;border-color:#EAB308}
.mc-num{position:absolute;right:-5px;top:-5px;min-width:17px;height:17px;border-radius:9px;background:#111827;color:#fff;font-size:10px;font-weight:800;line-height:17px;padding:0 3px;box-sizing:border-box;border:1px solid #fff}
.mc-nm{font-size:11px;font-weight:700;line-height:1.15;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding:0 2px}
.mc-pos{font-size:10px;font-weight:800;opacity:.85;line-height:1.1}
.mc-pos--lib{color:#DDD6FE;opacity:1}
.mc-server{text-align:center;font-size:13px;font-weight:700;margin-top:6px}
.mc-plate{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) scale(.9);background:rgba(15,23,42,.88);color:#fff;padding:8px 16px;border-radius:12px;font-weight:800;font-size:15px;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;z-index:5;white-space:nowrap}
.mc-plate.on{opacity:1;transform:translate(-50%,-50%) scale(1)}
.mc-zoom{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:10001;display:flex;align-items:center;justify-content:center;padding:10px}
.mc-zoom .mc{width:100%;max-width:440px;cursor:zoom-out}
.mc-zoom .mc-av{width:56px;height:56px}
.mc-zoom .mc-ini{font-size:19px}
.mc-zoom .mc-nm{font-size:13px}
.mc-zoom .mc-pos{font-size:12px}
.mc-zoom .mc-num{min-width:20px;height:20px;line-height:20px;font-size:12px;border-radius:10px;right:-6px;top:-6px}
.mc-zoom .mc-title{font-size:15px}
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

	/* B5: «Смена сторон» / «Технический тайм-аут» — тост 5 с + вибрация, ввод не блокирует. Один раз на ключ счёта
	   (откат очка через границу и повторный заход на неё не показываются). Первое состояние страницы — только запоминаем. */
	var ssSeen = {}, ssTimer = null;
	function sideSignal(first) {
		var ss = data().ss;
		if (!ss) { return; }
		var fresh = !ssSeen[ss.key];
		ssSeen[ss.key] = true;
		if (first || !fresh) { return; }
		var parts = [];
		if (ss.switch) { parts.push(SS_TXT.sw); }
		if (ss.timeout) { parts.push(SS_TXT.to); }
		var box = document.getElementById('rw-sidesw');
		if (!box) { box = document.createElement('div'); box.id = 'rw-sidesw'; box.className = 'rw-sidesw'; document.body.appendChild(box); }
		box.textContent = parts.join(' · ');
		box.classList.add('on');
		clearTimeout(ssTimer);
		ssTimer = setTimeout(function () { box.classList.remove('on'); }, 5000);
		try { if (navigator.vibrate) { navigator.vibrate([200, 100, 200]); } } catch (e) {}
	}
	var SS_TXT = {sw: @json(__('tournaments.rally_side_switch')), to: @json(__('tournaments.rally_tech_timeout'))};

	/* Мини-площадка: снимок позиций кружков до подмены фрагмента → «FLIP»-анимация перехода (~0,5 с) + плашка */
	var MC_TXT = @json(__('tournaments.mc_transition'));
	function mcSnapshot() {
		var m = {};
		document.querySelectorAll('#mc .mc-cell[data-uid]').forEach(function (c) { m[c.getAttribute('data-uid')] = c.getBoundingClientRect(); });
		return m;
	}
	function mcAnimate(snap, prev) {
		var cur = data().mc;
		if (!prev || !cur || prev.set !== cur.set) { return; }
		var moved = false;
		document.querySelectorAll('#mc .mc-cell[data-uid]').forEach(function (c) {
			var o = snap[c.getAttribute('data-uid')];
			if (!o) { return; }
			var n = c.getBoundingClientRect(), dx = o.left - n.left, dy = o.top - n.top;
			if (Math.abs(dx) + Math.abs(dy) < 2) { return; }
			moved = true;
			c.style.transition = 'none';
			c.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
			c.style.zIndex = '3';
			requestAnimationFrame(function () { requestAnimationFrame(function () {
				c.style.transition = 'transform .5s ease';
				c.style.transform = '';
				setTimeout(function () { c.style.transition = ''; c.style.zIndex = ''; }, 560);
			}); });
		});
		var sum = function (r) { return (r.home || 0) + (r.away || 0); };
		if (moved && sum(cur.rot) > sum(prev.rot) && cur.rotated_side) {
			var plate = document.getElementById('mc-plate');
			if (plate) {
				plate.textContent = MC_TXT.replace(':team', cur.names[cur.rotated_side] || '');
				plate.classList.add('on');
				setTimeout(function () { plate.classList.remove('on'); }, 1400);
			}
		}
	}

	function swap(html) {
		if (!live || !html) { return; }
		var mcPrev = data().mc, mcSnap = mcSnapshot();
		live.innerHTML = html;
		try { mcAnimate(mcSnap, mcPrev); } catch (e) {}
		var jn = document.getElementById('jn-list');
		if (jn) { jn.scrollTop = jn.scrollHeight; }
		sideSignal(false);
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

	/* тап по мини-площадке → крупный вид (кружки 56 px) */
	document.addEventListener('click', function (e) {
		var m = e.target.closest ? e.target.closest('#mc') : null;
		if (!m) { return; }
		var z = document.createElement('div');
		z.className = 'mc-zoom';
		var big = m.cloneNode(true);
		big.removeAttribute('id');
		big.querySelectorAll('[id]').forEach(function (x) { x.removeAttribute('id'); });
		z.appendChild(big);
		z.addEventListener('click', function () { z.remove(); });
		document.body.appendChild(z);
	});
	sideSignal(true);   // первое состояние страницы: ключ только запоминаем
	return {send: send, swap: swap, refresh: refresh, busy: busy, toast: toast, uuid: uuid, data: data, token: token, netErr: NET_ERR};
})();
</script>

<script>
(function () {
	var DATA = RallyLive.data().rw;
	var T = @json($rwText);
	var SELF = ['ace', 'kill', 'block'];
	var ATK = [['power', T.atkPower], ['cut', T.atkCut], ['poke', T.atkPoke], ['roll', T.atkRoll], ['tip', T.atkTip]];   // подтипы атаки (ключи = match_rally_events.attack_type)
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
		if (st.action === 'kill' && st.attackType) body.attack_type = st.attackType;   // подтип атаки (необязательно)
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
				// Атака — партнёр записывается в assist_user_id (пас); обман/скидка (attack_type = 'tip') — партнёра НЕ пишем.
				// Блок — в dig/assist ничего не пишем. dig_user_id в пляжке автоматически не заполняется никогда.
				if (st.action === 'kill' && st.attackType !== 'tip') {
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
					var dd = DATA[st.side];
					if (st.kind === 'own' && a === 'ace' && dd && dd.serverId) { st.player = String(dd.serverId); submit(); return; }   // эйс = подающий: игрока не спрашиваем
					// ошибка подачи соперника: ошибся его подающий (если известен по составу) — игрока не спрашиваем
					if (st.kind === 'opp' && a === 'opp_serve_error') {
						var od = DATA[st.side === 'home' ? 'away' : 'home'];
						if (od && od.serverId) { st.player = String(od.serverId); submit(); return; }
					}
					if (a === 'unattributed') { submit(); } else { go('player'); }
				}, list.length % 2 === 1 && a === list[list.length - 1]));
			});
		} else if (st.step === 'player') {
			var pl = st.kind === 'own' ? d.players : d.oppPlayers;
			elTitle.textContent = st.kind === 'own' ? T.whoScored : T.oppWho;
			if (st.kind === 'own' && st.action === 'kill') {
				// «Удар · Накат · Скидка» над списком игроков: по умолчанию не выбрано, повторное нажатие снимает выбор; без новых шагов
				var atk = document.createElement('div');
				atk.className = 'rw-atk rw-wide';
				ATK.forEach(function (a) {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'btn btn-secondary' + (st.attackType === a[0] ? ' on' : '');
					b.textContent = a[1];
					function paint(x, on) {
						// inline !important: общие стили .btn-secondary темы иначе перебивают заливку
						['background', 'color', 'border-color'].forEach(function (pr, k) { if (on) { x.style.setProperty(pr, ['#2967BA', '#fff', '#2967BA'][k], 'important'); } else { x.style.removeProperty(pr); } });
					}
					if (st.attackType === a[0]) { paint(b, true); }
					b.addEventListener('click', function () {
						st.attackType = st.attackType === a[0] ? '' : a[0];
						atk.querySelectorAll('button').forEach(function (x, i) { var on = st.attackType === ATK[i][0]; x.classList.toggle('on', on); paint(x, on); });
					});
					atk.appendChild(b);
				});
				elGrid.appendChild(atk);
			}
			pl.forEach(function (p) {
				elGrid.appendChild(btn(p.name + (p.role ? ' · ' + p.role : ''), 'btn-primary', function () { st.player = String(p.id); nextAfterPlayer(); }));
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
