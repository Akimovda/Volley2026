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
	.upcoming-special-head .day-section-title { margin-bottom:0; }
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
			<button type="button" data-dir="-1" aria-label="{{ __('events.upcoming_prev') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
			<button type="button" data-dir="1" aria-label="{{ __('events.upcoming_next') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
		</div>
	</div>
	<div class="row mb-0 upcoming-special-list">
		@foreach($__occs as $occ)
		@include('events._card', array_merge($__card, ['occ' => $occ, 'cardLinkOnly' => $cardLinkOnly ?? false]))
		@endforeach
	</div>
</section>
@once
<script>
(function () {
	function init() {
		document.querySelectorAll('[data-upcoming-special]').forEach(function (box) {
			// картинки карточек: на главной нет ленивой подгрузки ленты — подставляем сразу (их ≤ 10)
			box.querySelectorAll('img[data-src]').forEach(function (img) {
				img.src = img.getAttribute('data-src'); img.removeAttribute('data-src');
			});
			var list = box.querySelector('.upcoming-special-list');
			var prev = box.querySelector('[data-dir="-1"]'), next = box.querySelector('[data-dir="1"]');
			if (!list || !prev || !next) return;
			function sync() {
				prev.disabled = list.scrollLeft <= 2;
				next.disabled = list.scrollLeft + list.clientWidth >= list.scrollWidth - 2;
			}
			function step(dir) { list.scrollBy({ left: dir * Math.max(list.clientWidth * 0.8, 240), behavior: 'smooth' }); }
			prev.addEventListener('click', function () { step(-1); });
			next.addEventListener('click', function () { step(1); });
			list.addEventListener('scroll', sync, { passive: true });
			window.addEventListener('resize', sync);
			sync();
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
@endonce
@endif
