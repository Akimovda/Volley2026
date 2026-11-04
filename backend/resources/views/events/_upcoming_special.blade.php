{{-- Блок «Скоро: кемпы, мастер-классы, турниры». @include('events._upcoming_special', ['items' => $upcomingSpecial]) --}}
@if(!empty($items) && count($items) > 0)
@once
<style>
	.upcoming-special-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
	.upcoming-special-head h2 { margin:0; }
	.upcoming-special-arrows { display:flex; gap:.6rem; flex:0 0 auto; }
	.upcoming-special-arrows button { width:3.6rem; height:3.6rem; border-radius:50%; border:1px solid rgba(128,128,128,.35); background:transparent; color:inherit; cursor:pointer; display:flex; align-items:center; justify-content:center; padding:0; }
	.upcoming-special-arrows button:disabled { opacity:.35; cursor:default; }
	.upcoming-special-list { display:flex; gap:1.2rem; overflow-x:auto; scroll-snap-type:x proximity; -webkit-overflow-scrolling:touch; padding-bottom:.8rem; scrollbar-width:thin; }
	.upcoming-card { flex:0 0 26rem; scroll-snap-align:start; display:flex; flex-direction:column; border:1px solid rgba(128,128,128,.3); border-radius:1.2rem; overflow:hidden; text-decoration:none; color:inherit; background:inherit; }
	.upcoming-card:hover { border-color:rgba(128,128,128,.6); }
	.upcoming-card-img { position:relative; aspect-ratio:16/10; background:rgba(128,128,128,.15); }
	.upcoming-card-img img { width:100%; height:100%; object-fit:cover; display:block; }
	.upcoming-card-kind { position:absolute; left:.8rem; top:.8rem; padding:.2rem .9rem; border-radius:99px; font-size:1.2rem; font-weight:600; background:rgba(0,0,0,.65); color:#fff; }
	.upcoming-card-body { padding:1.1rem 1.2rem 1.3rem; display:flex; flex-direction:column; gap:.4rem; flex:1; }
	.upcoming-card-title { font-size:1.6rem; font-weight:600; line-height:1.25; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
	.upcoming-card-meta { font-size:1.3rem; opacity:.75; }
	.upcoming-card-status { margin-top:auto; padding-top:.6rem; font-size:1.3rem; font-weight:600; }
	.upcoming-card-status.is-open { color:#2a8a3b; }
	.upcoming-card-status.is-opens { color:#b7791f; }
	.upcoming-card-status.is-full { color:#d33; }
	@media (max-width: 575px) { .upcoming-card { flex-basis:22.5rem; } }
</style>
@endonce
<div class="ramka upcoming-special" data-upcoming-special>
	<div class="upcoming-special-head">
		<h2 class="title-h">{{ __('events.upcoming_title') }}</h2>
		<div class="upcoming-special-arrows">
			<button type="button" data-dir="-1" aria-label="{{ __('events.upcoming_prev') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
			<button type="button" data-dir="1" aria-label="{{ __('events.upcoming_next') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
		</div>
	</div>
	<div class="upcoming-special-list">
		@foreach($items as $it)
		@php
			$ev   = $it['event'];
			$s    = $it['startsLocal'];
			$e    = $it['endsLocal'];
			$loc  = app()->getLocale();
			$when = $e
				? ($s->format('Y-m') === $e->format('Y-m')
					? $s->format('j') . '–' . $e->locale($loc)->translatedFormat('j F')
					: $s->locale($loc)->translatedFormat('j F') . ' – ' . $e->locale($loc)->translatedFormat('j F'))
				: $s->locale($loc)->translatedFormat('j F, D') . ' · ' . $s->format('H:i');
			$city = $ev->location?->city?->name;
			$url  = route('events.show', ['event' => $ev->id, 'occurrence' => $it['occ']->id]);
		@endphp
		<a class="upcoming-card" href="{{ $url }}">
			<div class="upcoming-card-img">
				<img src="{{ $it['image'] }}" alt="{{ $ev->title }}" loading="lazy">
				<span class="upcoming-card-kind">{{ __('events.upcoming_kind_' . $it['kind']) }}</span>
			</div>
			<div class="upcoming-card-body">
				<div class="upcoming-card-title">{{ $ev->title }}</div>
				<div class="upcoming-card-meta">{{ $when }}</div>
				@if($city)<div class="upcoming-card-meta">📍 {{ $city }}</div>@endif
				<div class="upcoming-card-status is-{{ $it['status'] }}">
					@if($it['status'] === 'opens')
						{{ __('events.msg_reg_opens') }} {{ \App\Support\RelativeDay::dateAtTime($it['opensAt']) }}
					@elseif($it['status'] === 'full')
						{{ __('events.upcoming_full') }}
					@else
						{{ __('events.upcoming_reg_open') }}@if($it['remaining'] !== null) · {{ __('events.upcoming_seats_left', ['n' => $it['remaining']]) }}@endif
					@endif
				</div>
			</div>
		</a>
		@endforeach
	</div>
</div>
@once
<script>
(function () {
	document.querySelectorAll('[data-upcoming-special]').forEach(function (box) {
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
})();
</script>
@endonce
@endif
