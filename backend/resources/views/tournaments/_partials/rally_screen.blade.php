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
		'opp_rotation_error' => __('tournaments.rally_action_opp_rotation_error'), 'opp_position_error' => __('tournaments.rally_action_opp_position_error'),
		'bx_lineup_section' => __('tournaments.bx_lineup_section'), 'bx_server_other' => __('tournaments.bx_server_other'),
		'bx_srv_title' => __('tournaments.bx_srv_title'), 'bx_srv_text' => __('tournaments.bx_srv_text'),
		'bx_srv_keep' => __('tournaments.bx_srv_keep'), 'bx_srv_keep_sub' => __('tournaments.bx_srv_keep_sub'),
		'bx_srv_rot' => __('tournaments.bx_srv_rot'), 'bx_srv_rot_beach' => __('tournaments.bx_srv_rot_beach'), 'bx_fix_banner_beach' => __('tournaments.bx_fix_banner_beach'), 'opp_rotation_error_beach' => __('tournaments.rally_action_opp_rotation_error_beach'), 'bx_srv_rot_sub' => __('tournaments.bx_srv_rot_sub'),
		'bx_mm_title' => __('tournaments.bx_mm_title'), 'bx_mm_text' => __('tournaments.bx_mm_text'), 'bx_mm_record' => __('tournaments.bx_mm_record'),
		'bx_mm_record_sub' => __('tournaments.bx_mm_record_sub'), 'bx_mm_fix' => __('tournaments.bx_mm_fix'), 'bx_mm_hint' => __('tournaments.bx_mm_hint'),
		'bx_scores' => __('tournaments.bx_scores'),
		'bx_culprit' => __('tournaments.bx_culprit'), 'bx_fix_banner' => __('tournaments.bx_fix_banner'),
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
#rw-live .rw-serve-btn{box-shadow:none;white-space:normal;text-align:center;line-height:1.25;overflow-wrap:anywhere;word-break:normal;hyphens:none}
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



/* ===== Блок B: проверка подающего, исправление расстановки, подсказка по либеро ===== */
.rw-sec{font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:.06em;opacity:.7;margin-top:6px}
.rw-lu-btn{border-color:#7C3AED !important}
.rw-lite.on{background:#EAB308 !important;color:#111 !important;border-color:#EAB308 !important}
.rw-danger{border:2px solid #dc2626 !important;color:#dc2626 !important}
.rw-sub2{font-size:12px;opacity:.75;text-align:center;margin:-4px 0 6px}
.rw-pcs{display:flex;justify-content:center;gap:24px;margin:6px 0 10px}
.rw-pc{text-align:center;max-width:130px}
.rw-pc-av{position:relative;width:56px;height:56px;border-radius:50%;margin:0 auto;background:#64748B;color:#fff;font-weight:800;font-size:19px;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 1px rgba(0,0,0,.15)}
.rw-pc-av img{width:100%;height:100%;border-radius:50%;object-fit:cover}
.rw-pc-av--srv{box-shadow:0 0 0 4px #EAB308}
.rw-pc-num{position:absolute;right:-6px;top:-6px;min-width:20px;height:20px;border-radius:10px;background:#111827;color:#fff;font-size:12px;font-weight:800;line-height:20px;padding:0 4px;box-sizing:border-box;border:1px solid #fff}
.rw-pc-nm{font-size:12px;font-weight:700;margin-top:4px}
.lf-box{max-width:480px}
.lf-banner{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:10px;padding:8px 10px;font-weight:800;font-size:13px;margin-bottom:8px}
.lf-court{border-radius:14px;overflow:hidden;padding:0 6px 8px;margin:8px 0}
.lf-court .mc-net{margin:10px -6px 8px}
.lf-cell{padding-top:15px !important}
.lf-cell .mc-z{left:6px;top:3px;font-size:10px}
.lf-cell{cursor:pointer}
.lf-cell .mc-av{width:56px;height:56px}
.lf-cell .mc-ini{font-size:19px}
.lf-cell .mc-nm{font-size:13px}
.lf-cell .mc-z{font-size:10px}
.lf-cell.lf-sel{outline:3px solid #fff;box-shadow:0 0 0 5px #2967BA}
.lf-hint{text-align:center;font-size:13px;opacity:.8;margin:4px 0 8px;min-height:1.2em}
.lf-rot{display:flex;gap:8px}
.lf-rot .btn{flex:1}
#rw-live.rw-muted .js-rw-start{opacity:.55}
.lu-hint{border:2px solid #7C3AED;border-radius:14px;padding:10px;margin:0 0 10px;background:rgba(124,58,237,.07)}
.lu-hint-top{display:flex;gap:10px;align-items:center}
.lu-hint-av{position:relative;flex:none;width:44px;height:44px;border-radius:50%;background:#7C3AED;color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;border:2px solid #C4B5FD}
.lu-hint-av img{width:100%;height:100%;border-radius:50%;object-fit:cover}
.lu-hint-av i{position:absolute;right:-6px;top:-6px;min-width:17px;height:17px;border-radius:9px;background:#111827;color:#fff;font-size:10px;font-style:normal;font-weight:800;line-height:17px;padding:0 3px;box-sizing:border-box}
.lu-hint-ttl{font-weight:800;font-size:13px;color:#6D28D9}
.lu-hint-q{font-size:14px;font-weight:600}
.lu-hint-btns{display:flex;gap:8px;margin-top:8px}
.lu-hint-btns .btn{flex:1}
.lu-hint-note{font-size:11px;opacity:.65;text-align:center;margin-top:4px}
.rw-bi{display:inline-flex;vertical-align:-.15em}
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
.rw-foot{display:grid;grid-template-columns:minmax(max-content,1fr) minmax(max-content,1.3fr) minmax(max-content,1fr);gap:8px;text-align:center}
.rw-foot--2{grid-template-columns:minmax(max-content,1fr) minmax(max-content,1.3fr)}
.rw-foot form{margin:0}
.rw-foot .btn{height:52px;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:6px;white-space:nowrap;padding:0 8px;box-sizing:border-box;font-size:14px;letter-spacing:0;line-height:1}
.rw-foot .btn .ui-ico{flex:none}
.rw-foot .rw-foot-pdf{grid-column:1/-1;height:auto;min-height:40px;width:auto;justify-self:center;padding:0 16px}
@media (max-width:400px){.rw-foot{gap:6px}.rw-foot .btn{font-size:12px;gap:4px;padding:0 6px}.rw-foot .btn .ui-ico{width:15px;height:15px}}
@media (max-width:370px){.rw-foot .btn{font-size:11px;padding:0 5px}.rw-foot .btn .ui-ico{width:14px;height:14px}}
.js-rw-start{display:inline-flex;align-items:center;justify-content:center;gap:8px}
.ui-ico{vertical-align:-3px;flex:none;fill:none!important;stroke:currentColor!important}
.ui-ico *{fill:none!important}
.rw-lbl-short{display:none}
@media (max-width:480px){.rw-lbl-full{display:none}.rw-lbl-short{display:inline}}
.so-pairs{display:flex;flex-direction:column;gap:10px}
.so-pair{border:2px solid;border-radius:12px;overflow:hidden}
.so-pair-name{text-align:center;font-weight:800;padding:6px 8px;line-height:1.2}
.so-pair-cards{display:flex;gap:8px;padding:8px}
.so-first-panel .so-pair-cards,.lf-box .so-pair-cards{padding:0}
.so-card{flex:1 1 0;min-width:0;border:2px solid rgba(0,0,0,.15);border-radius:12px;display:flex;flex-direction:column;align-items:center;text-align:center;background:transparent;color:inherit}
.so-card.so-sel{border-color:#EAB308;box-shadow:0 0 0 3px rgba(234,179,8,.45)}
.so-av-wrap{position:relative;display:inline-block}
.so-av-wrap .rw-pc-num{right:-6px;top:auto;bottom:-4px}
.so-av28{width:28px;height:28px;font-size:11px;border-width:1px}
.so-now .b-700{font-size:17px}
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
		document.dispatchEvent(new Event('rally-live-swapped'));
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
	var OPP_LU = ['opp_rotation_error', 'opp_position_error'];   // ошибки расстановки (виновник необязателен)
	function luOn() { var l = RallyLive.data().lu; return !!(l && l.hasStart); }
	/* пляжка 2×2: порядок подачи по парам (BeachServeOrderService) */
	function soOn() { var s = RallyLive.data().so; return !!(s && s.servingSide); }
	function chkOn() { return luOn() || soOn(); }
	function fixOpen(side, opts) {
		if (soOn() && window.SoFix) { window.SoFix.open(side, opts); } else if (window.LuFix) { window.LuFix.open(side, opts); }
	}
	function otherSide(sd) { return sd === 'home' ? 'away' : 'home'; }
	function findP(list, id) { for (var i = 0; i < (list || []).length; i++) { if (String(list[i].id) === String(id)) { return list[i]; } } return null; }
	function lab(p) { var c = p.card || {}; return (c.number != null ? '№' + c.number + ' ' : '') + (c.surname || p.name || ''); }
	/* кружок игрока (фото/инициалы, номер, фамилия · амплуа); ring — жёлтое кольцо подающего */
	function pcard(p, ring) {
		var c = p.card || {}, w = document.createElement('div'), av = document.createElement('div');
		w.className = 'rw-pc'; av.className = 'rw-pc-av' + (ring ? ' rw-pc-av--srv' : '');
		if (c.photo) { var im = document.createElement('img'); im.src = c.photo; im.alt = ''; im.dataset.ini = c.initials || String(p.name || '?').charAt(0); av.appendChild(im); } else { av.textContent = c.initials || String(p.name || '?').charAt(0); }
		if (c.number != null) { var nb = document.createElement('span'); nb.className = 'rw-pc-num'; nb.textContent = c.number; av.appendChild(nb); }
		var nm = document.createElement('div'); nm.className = 'rw-pc-nm'; nm.textContent = (c.surname || p.name || '') + (c.pos ? ' · ' + c.pos : '');
		w.appendChild(av); w.appendChild(nm);
		return w;
	}
	/* проверка подающего: игрок эйса/ошибки подачи ≠ подающий по расстановке → вопрос вместо записи */
	function serverCheck(p) {
		if (!chkOn() || !st) { return null; }
		var ownAce = st.kind === 'own' && st.action === 'ace', oppSrv = st.kind === 'opp' && st.action === 'opp_serve_error';
		if (!ownAce && !oppSrv) { return null; }
		var srvSide = ownAce ? st.side : otherSide(st.side), sid = DATA[srvSide] && DATA[srvSide].serverId;
		if (!sid || String(sid) === String(p.id)) { return null; }
		var list = DATA[srvSide].players, srv = findP(list, sid), pick = findP(list, p.id);
		return (srv && pick) ? {srv: srv, pick: pick, srvSide: srvSide} : null;
	}
	function afterPick(p) {
		st.player = String(p.id);
		var chk = serverCheck(p);
		if (chk) { st.chk = chk; go('srvcheck'); return; }
		nextAfterPlayer();
	}
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
		// ошибка перехода → сразу экран «Исправить расстановку» для нарушившей команды
		var fixSide = st.fixSide || (st.action === 'opp_rotation_error' ? otherSide(st.side) : null), scorerName = DATA[st.side].teamName;
		RallyLive.send(form.action, body, {retry: true}, function (j) {
			sending = false; setSending(false);
			close();
			RallyLive.swap(j.html);
			if (fixSide && (window.LuFix || window.SoFix)) {
				var sc = (j.board && j.board.score) ? (j.board.score.home + ':' + j.board.score.away) : '';
				fixOpen(fixSide, {banner: (soOn() ? T.bx_fix_banner_beach : T.bx_fix_banner).replace(':team', scorerName).replace(':score', sc)});
			}
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
		// под заголовком — «Очко получает: <команда>» (команда жирным); выбранные действие/игроки — второй строкой
		var crumbs = [];
		if (st.action) crumbs.push(T[st.action]);
		if (st.player) crumbs.push(nameOf(st.kind === 'own' ? d.players : d.oppPlayers, st.player));
		if (st.dig) crumbs.push('⤵ ' + nameOf(players, st.dig));
		elSub.innerHTML = '';
		var scoreLine = document.createElement('div'), sp = T.bx_scores.split(':team'), teamB = document.createElement('b');
		teamB.textContent = d.teamName;
		scoreLine.appendChild(document.createTextNode(sp[0])); scoreLine.appendChild(teamB); scoreLine.appendChild(document.createTextNode(sp[1] || ''));
		elSub.appendChild(scoreLine);
		if (crumbs.length) { var cl = document.createElement('div'); cl.style.fontSize = '12px'; cl.style.opacity = '.8'; cl.textContent = crumbs.join(' · '); elSub.appendChild(cl); }

		if (st.step === 'action') {
			elTitle.textContent = st.kind === 'own' ? T.how : T.errorType;
			var list = st.kind === 'own' ? SELF : OPP.concat(['unattributed']);
			var auto = !st.forcePick;   // «Подавал другой игрок?» — вручную выбрать игрока вместо автозаписи на подающего
			list.forEach(function (a) {
				elGrid.appendChild(btn(T[a], st.kind === 'own' ? 'btn-primary' : 'btn-secondary', function () {
					st.action = a;
					var dd = DATA[st.side];
					if (auto && st.kind === 'own' && a === 'ace' && dd && dd.serverId) { st.player = String(dd.serverId); submit(); return; }   // эйс = подающий: игрока не спрашиваем
					// ошибка подачи соперника: ошибся его подающий (если известен по составу) — игрока не спрашиваем
					if (auto && st.kind === 'opp' && a === 'opp_serve_error') {
						var od = DATA[otherSide(st.side)];
						if (od && od.serverId) { st.player = String(od.serverId); submit(); return; }
						// по расстановке подаёт ЭТА (набравшая очко) команда, а ошибку подачи записывают сопернику → расхождение: предупреждаем
						if (dd && dd.serving && (luOn() || (soOn() && dd.serverId))) { go('srvmismatch'); return; }
					}
					if (a === 'unattributed') { submit(); } else { go('player'); }
				}, list.length % 2 === 1 && a === list[list.length - 1]));
			});
			if (st.kind === 'opp' && chkOn()) {
				var sec = document.createElement('div'); sec.className = 'rw-sec rw-wide'; sec.textContent = T.bx_lineup_section; elGrid.appendChild(sec);
				(soOn() ? ['opp_rotation_error'] : OPP_LU).forEach(function (a) {
					elGrid.appendChild(btn(soOn() && a === 'opp_rotation_error' ? T.opp_rotation_error_beach : T[a], 'btn-secondary rw-lu-btn', function () { st.action = a; go('player'); }));
				});
			}
			var srvSide0 = st.kind === 'own' ? st.side : otherSide(st.side);
			if (chkOn() && DATA[srvSide0] && DATA[srvSide0].serverId) {
				elGrid.appendChild(btn(T.bx_server_other, 'btn-secondary rw-lite' + (st.forcePick ? ' on' : ''), function () { st.forcePick = !st.forcePick; render(); }, true));
			}
		} else if (st.step === 'srvmismatch') {
			var sv = DATA[st.side], svP = sv.serverId ? findP(sv.players, sv.serverId) : null;
			elTitle.textContent = T.bx_mm_title;
			elSub.textContent = T.bx_mm_text.replace(':team', sv.teamName).replace(':who', svP ? lab(svP) : '—');
			if (svP) {
				var mp = document.createElement('div'); mp.className = 'rw-pcs rw-wide'; mp.appendChild(pcard(svP, true)); elGrid.appendChild(mp);
				var recB = btn(T.bx_mm_record.replace(':who', lab(svP)), 'btn-primary', function () {
					// ошибка подачи у подающей по расстановке команды: очко её сопернику, виновник — подающий
					var scorerSide = otherSide(st.side);
					st.player = String(svP.id); st.side = scorerSide; st.kind = 'opp'; st.action = 'opp_serve_error'; st.rid = '';
					submit();
				}, true);
				var recS = document.createElement('div'); recS.className = 'rw-sub2 rw-wide'; recS.textContent = T.bx_mm_record_sub.replace(':team', DATA[otherSide(st.side)].teamName);
				elGrid.appendChild(recB); elGrid.appendChild(recS);
			}
			var fixSide0 = st.side;
			elGrid.appendChild(btn(T.bx_mm_fix, 'btn-secondary', function () { close(); fixOpen(fixSide0); }, true));
			var hintEl = document.createElement('div'); hintEl.className = 'rw-sub2 rw-wide'; hintEl.textContent = T.bx_mm_hint; elGrid.appendChild(hintEl);
		} else if (st.step === 'srvcheck') {
			var ck = st.chk;
			elTitle.textContent = T.bx_srv_title;
			elSub.textContent = T.bx_srv_text.replace(':srv', lab(ck.srv)).replace(':pick', lab(ck.pick));
			var pcs = document.createElement('div'); pcs.className = 'rw-pcs rw-wide';
			pcs.appendChild(pcard(ck.srv, true)); pcs.appendChild(pcard(ck.pick, false));
			elGrid.appendChild(pcs);
			var keepB = btn(T.bx_srv_keep.replace(':action', T[st.action]).replace(':who', lab(ck.srv)), 'btn-primary', function () { st.player = String(ck.srv.id); submit(); }, true);
			var keepS = document.createElement('div'); keepS.className = 'rw-sub2 rw-wide'; keepS.textContent = T.bx_srv_keep_sub;
			var rotB = btn((soOn() ? T.bx_srv_rot_beach : T.bx_srv_rot).replace(':who', lab(ck.pick)), 'btn-secondary rw-danger', function () {
				// ошибка перехода: очко сопернику нарушившей (подающей) команды, затем экран «Исправить расстановку»
				var scorer = otherSide(ck.srvSide), pid = String(ck.pick.id);
				st.side = scorer; st.kind = 'opp'; st.action = 'opp_rotation_error'; st.player = pid; st.fixSide = ck.srvSide; st.rid = '';
				submit();
			}, true);
			var rotS = document.createElement('div'); rotS.className = 'rw-sub2 rw-wide'; rotS.textContent = T.bx_srv_rot_sub.replace(':team', DATA[otherSide(ck.srvSide)].teamName);
			elGrid.appendChild(keepB); elGrid.appendChild(keepS); elGrid.appendChild(rotB); elGrid.appendChild(rotS);
		} else if (st.step === 'player') {
			var pl = st.kind === 'own' ? d.players : d.oppPlayers;
			elTitle.textContent = OPP_LU.indexOf(st.action) !== -1 ? T.bx_culprit : (st.kind === 'own' ? T.whoScored : T.oppWho);
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
				elGrid.appendChild(btn(p.name + (p.role ? ' · ' + p.role : ''), 'btn-primary', function () { afterPick(p); }));
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

@include('tournaments._partials.photo_fallback_js')
@if($luOn)
@include('tournaments._partials.lineup_popup_js')
@include('tournaments._partials.player_card_js')
@include('tournaments._partials.lineup_rally_js')
@include('tournaments._partials.lineup_fix_js')
@endif
@if(!empty($serve['enabled']))
@include('tournaments._partials.player_card_js')
@include('tournaments._partials.beach_serve_js')
@endif
