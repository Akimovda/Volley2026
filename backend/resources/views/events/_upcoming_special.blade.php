{{-- Блок «Скоро: кемпы, мастер-классы, турниры» — те же карточки, что в ленте дат (events/_card.blade.php), горизонтальная прокрутка.
     @include('events._upcoming_special', ['upcoming' => ['occurrences' => ..., 'card' => [...]], 'cardLinkOnly' => bool])
     cardLinkOnly — на главной (нет JS ленты: окно выбора позиции) кнопка записи заменяется ссылкой «Подробнее». --}}
@php
	$__occs = $upcoming['occurrences'] ?? collect();
@endphp
@if(count($__occs) > 0)
@php
	// контекст карточки (как в events/index.blade.php)
	$userTz        = \App\Support\DateTime::effectiveUserTz(auth()->user());
	$userHasCityTz = !is_null(auth()->user()?->city?->timezone);
	$nowUtc        = \Illuminate\Support\Carbon::now('UTC');
	$fmtDate = function ($occ) use ($userTz, $userHasCityTz) {
		$eventTz = $occ->timezone ?: ($occ->event?->timezone ?: 'UTC');
		$effectiveTz = $userHasCityTz ? $userTz : $eventTz;
		$sUser = $occ->starts_at ? \Illuminate\Support\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($effectiveTz) : null;
		if (!$sUser) {
			return ['date' => '—', 'time' => '—', 'tz' => $effectiveTz, 'tzLabel' => $effectiveTz, 'eventTz' => $eventTz];
		}
		return ['date' => $sUser->format('d.m.Y'), 'time' => $sUser->format('H:i'), 'tz' => $effectiveTz,
			'tzLabel' => $sUser->format('T') . ' (UTC' . $sUser->format('P') . ')', 'eventTz' => $eventTz];
	};
	$trainersById   = [];
	$usersById      = [];
	$trainerColumn  = null;
	$trainerIconUrl = asset('icons/trainer.png');
	$__card = (array) ($upcoming['card'] ?? []);
@endphp
@once
<style>
	.upcoming-special-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
	.upcoming-special-head .day-section-title { margin-bottom:0; min-width:0; flex:1 1 auto; white-space:normal; }
	.upcoming-special-arrows { margin-left:auto; }
	.upcoming-special-list .event-card { position:relative; }
	.upcoming-dismiss { position:absolute; top:.6rem; right:.6rem; z-index:5; width:3.2rem; height:3.2rem; border-radius:50%; border:0; background:rgba(0,0,0,.45); color:#fff; font-size:2.2rem; line-height:1; cursor:pointer; display:flex; align-items:center; justify-content:center; padding:0 0 .2rem; }
	.upcoming-dismiss:hover { background:rgba(0,0,0,.7); }
	.upcoming-toast { position:fixed; left:50%; bottom:2.4rem; transform:translateX(-50%); z-index:10050; max-width:calc(100vw - 3.2rem); background:#222333; color:#fff; border-radius:1.2rem; padding:1.2rem 1.8rem; font-size:1.4rem; box-shadow:0 .6rem 2rem rgba(0,0,0,.35); display:flex; gap:1.4rem; align-items:center; }
	.upcoming-toast a { color:#ffb171; font-weight:600; text-decoration:underline; cursor:pointer; white-space:nowrap; }
	.upcoming-special-arrows { display:flex; gap:.6rem; flex:0 0 auto; }
	.upcoming-special-arrows button { width:3.6rem; height:3.6rem; border-radius:50%; border:1px solid rgba(128,128,128,.35); background:transparent; color:inherit; cursor:pointer; display:flex; align-items:center; justify-content:center; padding:0; }
	.upcoming-special-arrows button:disabled { opacity:.35; cursor:default; }
	/* та же сетка, что у ленты дат (.row + .col-*): карточка лежит в колонке и получает ту же ширину; здесь ряд не переносится, а прокручивается */
	.upcoming-special-list { flex-wrap:nowrap; overflow-x:auto; scroll-snap-type:x proximity; -webkit-overflow-scrolling:touch; padding-bottom:.8rem; scrollbar-width:thin; }
	.upcoming-special-list > [class*="col-"] { scroll-snap-align:start; }
</style>
@endonce
<section class="day-section upcoming-special" data-upcoming-special>
	<div class="upcoming-special-head">
		<h3 class="day-section-title">{{ __('events.upcoming_title') }}</h3>
		<div class="upcoming-special-arrows">
			<button type="button" data-upcoming-hide aria-label="{{ __('events.upcoming_hide_block') }}" title="{{ __('events.upcoming_hide_block') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"></line></svg></button>
			<button type="button" data-dir="-1" aria-label="{{ __('events.upcoming_prev') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
			<button type="button" data-dir="1" aria-label="{{ __('events.upcoming_next') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
		</div>
	</div>
	<div class="row mb-0 upcoming-special-list">
		@foreach($__occs as $occ)
		@include('events._card', array_merge($__card, ['occ' => $occ, 'cardLinkOnly' => $cardLinkOnly ?? false, 'upcomingDismiss' => true]))
		@endforeach
	</div>
</section>
@once
<script>
(function () {
	var AUTH = @json(auth()->check());
	var URLS = { dismiss: @json(route('upcoming.dismiss')), undoBase: @json(url('/events/upcoming/dismiss')), hide: @json(route('upcoming.hide')), track: @json(route('upcoming.track')) };
	var T = { dismissed: @json(__('events.upcoming_dismissed')), undo: @json(__('events.upcoming_undo')), hidden: @json(__('events.upcoming_block_hidden')) };
	var LS_D = 'upcoming_dismissed', LS_H = 'upcoming_hidden_until', HIDE_MS = 30 * 86400000;

	function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
	function send(url, method, body) {
		try {
			return fetch(url, { method: method || 'POST', credentials: 'same-origin', keepalive: true,
				headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
				body: body ? JSON.stringify(body) : undefined }).catch(function () {});
		} catch (e) {}
	}
	function lsGet(k, d) { try { var v = JSON.parse(localStorage.getItem(k)); return v == null ? d : v; } catch (e) { return d; } }
	function lsSet(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }

	// пакетная отправка показов/переходов
	var queue = [], timer = null;
	function track(id, type) {
		queue.push({ event_id: parseInt(id, 10), type: type });
		if (!timer) timer = setTimeout(flush, 1500);
	}
	function flush() {
		timer = null;
		if (!queue.length) return;
		var batch = queue.splice(0, 40);
		send(URLS.track, 'POST', { events: batch });
		if (queue.length) timer = setTimeout(flush, 500);
	}
	window.addEventListener('pagehide', flush);

	var toastEl = null, toastTimer = null;
	function toast(text, undoLabel, onUndo) {
		if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'upcoming-toast'; document.body.appendChild(toastEl); }
		toastEl.innerHTML = '';
		var s = document.createElement('span'); s.textContent = text; toastEl.appendChild(s);
		if (onUndo) {
			var a = document.createElement('a'); a.textContent = undoLabel;
			a.addEventListener('click', function () { clearTimeout(toastTimer); toastEl.style.display = 'none'; onUndo(); });
			toastEl.appendChild(a);
		}
		toastEl.style.display = 'flex';
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.style.display = 'none'; }, 6000);
	}

	function init() {
		document.querySelectorAll('[data-upcoming-special]').forEach(function (box) {
			// картинки карточек: на главной нет ленивой подгрузки ленты — подставляем сразу (их ≤ 10)
			box.querySelectorAll('img[data-src]').forEach(function (img) {
				img.src = img.getAttribute('data-src'); img.removeAttribute('data-src');
			});
			var list = box.querySelector('.upcoming-special-list');
			var prev = box.querySelector('[data-dir="-1"]'), next = box.querySelector('[data-dir="1"]');
			var cards = function () { return Array.prototype.slice.call(box.querySelectorAll('[data-upcoming-event]')); };

			// гость: скрытое хранится в браузере
			if (!AUTH) {
				var until = lsGet(LS_H, 0);
				if (until && Date.now() < until) { box.style.display = 'none'; return; }
				var gone = lsGet(LS_D, []);
				cards().forEach(function (c) { if (gone.indexOf(parseInt(c.getAttribute('data-upcoming-event'), 10)) !== -1) c.style.display = 'none'; });
			}
			function refreshVisibility() {
				var any = cards().some(function (c) { return c.style.display !== 'none'; });
				box.style.display = any ? '' : 'none';
				sync();
			}

			function sync() {
				if (!list || !prev || !next) return;
				prev.disabled = list.scrollLeft <= 2;
				next.disabled = list.scrollLeft + list.clientWidth >= list.scrollWidth - 2;
			}
			function step(dir) { list.scrollBy({ left: dir * Math.max(list.clientWidth * 0.8, 240), behavior: 'smooth' }); }
			if (list && prev && next) {
				prev.addEventListener('click', function () { step(-1); });
				next.addEventListener('click', function () { step(1); });
				list.addEventListener('scroll', sync, { passive: true });
				window.addEventListener('resize', sync);
			}
			refreshVisibility();

			// крестик на карточке
			box.addEventListener('click', function (e) {
				var btn = e.target.closest('[data-upcoming-dismiss]');
				if (btn) {
					e.preventDefault(); e.stopPropagation();
					var col = btn.closest('[data-upcoming-event]'); if (!col) return;
					var id = parseInt(col.getAttribute('data-upcoming-event'), 10);
					col.style.display = 'none';
					if (AUTH) { send(URLS.dismiss, 'POST', { event_id: id }); }
					else { var g = lsGet(LS_D, []); if (g.indexOf(id) === -1) g.push(id); lsSet(LS_D, g); track(id, 'dismiss'); }
					refreshVisibility();
					toast(T.dismissed, T.undo, function () {
						col.style.display = '';
						if (AUTH) { send(URLS.undoBase + '/' + id, 'DELETE'); }
						else { lsSet(LS_D, lsGet(LS_D, []).filter(function (x) { return x !== id; })); }
						refreshVisibility();
					});
					return;
				}
				// переход по карточке — для статистики организатора
				var link = e.target.closest('a[href]');
				if (link) {
					var c2 = link.closest('[data-upcoming-event]');
					if (c2) { track(c2.getAttribute('data-upcoming-event'), 'click'); flush(); }
				}
			}, true);

			// «Скрыть блок» на 30 дней
			var hide = box.querySelector('[data-upcoming-hide]');
			if (hide) hide.addEventListener('click', function (e) {
				e.preventDefault();
				if (AUTH) send(URLS.hide, 'POST'); else lsSet(LS_H, Date.now() + HIDE_MS);
				box.style.display = 'none';
				toast(T.hidden);
			});

			// показы: карточка считается показанной, когда видна наполовину (один раз за загрузку страницы)
			if ('IntersectionObserver' in window) {
				var seen = {};
				var io = new IntersectionObserver(function (entries) {
					entries.forEach(function (en) {
						if (!en.isIntersecting) return;
						var id = en.target.getAttribute('data-upcoming-event');
						if (!seen[id]) { seen[id] = true; track(id, 'impression'); }
						io.unobserve(en.target);
					});
				}, { threshold: 0.5 });
				cards().forEach(function (c) { if (c.style.display !== 'none') io.observe(c); });
			}
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
@endonce
@endif
