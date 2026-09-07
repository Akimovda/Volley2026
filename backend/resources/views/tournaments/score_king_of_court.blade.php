<x-voll-layout body_class="tournament-koc-score-page">
<x-slot name="title">{{ __('tournaments.koc_score_title') }} — {{ $event->title }}</x-slot>
<x-slot name="h1">{{ __('tournaments.koc_score_title') }}</x-slot>

<x-slot name="breadcrumbs">
	<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		<a href="{{ route('tournament.setup', $event) }}#stage_{{ $stage->id }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
		<meta itemprop="position" content="2">
	</li>
</x-slot>

@php
	$kingTeam = $teamsById->get($state['king_team_id']);
	$challengerTeam = $teamsById->get($state['challenger_team_id']);
	$queueTeams = collect($state['queue'])->map(fn($id) => $teamsById->get($id));
	$kingPoints = (int) ($state['round_points'][$state['king_team_id']] ?? 0);
	$roundDurationMin = (int) $stage->cfg('round_duration_min', 15);
	$roundStartedAt = $stage->cfg('round_started_at');
@endphp

<div class="container">

	@if(session('error'))
	<div class="alert alert-error">{{ session('error') }}</div>
	@endif

	<div class="ramka">

		<div class="card p-2 mb-2" style="text-align:center">
			<span class="score-pill score-pill--blue">{{ __('tournaments.koc_lbl_round') }} {{ $state['round_number'] }}</span>
			<span class="score-pill score-pill--orange" id="koc-timer" data-started-at="{{ $roundStartedAt }}" data-duration-min="{{ $roundDurationMin }}">--:--</span>
		</div>

		{{-- King side --}}
		<form method="POST" action="{{ route('tournament.kingOfCourt.point', $stage) }}">
			@csrf
			<input type="hidden" name="event_type" value="king_point">
			<button type="submit" class="koc-side-btn koc-side-king w-100">
				<div class="f-13">👑 {{ __('tournaments.koc_lbl_king_side') }}</div>
				<div class="b-800 f-24">{{ $kingTeam?->name ?? '?' }}</div>
				<div class="f-18">{{ $kingPoints }} {{ __('tournaments.pub_pts_label') }}</div>
				<div class="f-12 mt-1" style="opacity:.85">{{ __('tournaments.koc_hint_king_tap') }}</div>
			</button>
		</form>

		{{-- Challenge side --}}
		<form method="POST" action="{{ route('tournament.kingOfCourt.point', $stage) }}" class="mt-2">
			@csrf
			<input type="hidden" name="event_type" value="takeover">
			<button type="submit" class="koc-side-btn koc-side-challenge w-100">
				<div class="f-13">🙋 {{ __('tournaments.koc_lbl_challenge_side') }}</div>
				<div class="b-800 f-24">{{ $challengerTeam?->name ?? '?' }}</div>
				<div class="f-12 mt-1" style="opacity:.85">{{ __('tournaments.koc_hint_challenge_tap') }}</div>
			</button>
		</form>

		<form method="POST" action="{{ route('tournament.kingOfCourt.point', $stage) }}" class="mt-1 text-center">
			@csrf
			<input type="hidden" name="event_type" value="fault">
			<button type="submit" class="btn btn-secondary btn-small">⚠ {{ __('tournaments.koc_btn_fault') }}</button>
		</form>

		{{-- Очередь --}}
		@if($queueTeams->isNotEmpty())
		<div class="card mt-2 p-2">
			<div class="f-13 mb-1" style="opacity:.7">{{ __('tournaments.koc_lbl_queue') }}</div>
			<div class="d-flex" style="gap:6px;flex-wrap:wrap">
				@foreach($queueTeams as $qt)
				<span class="badge badge-sm">{{ $qt?->name ?? '?' }}</span>
				@endforeach
			</div>
		</div>
		@endif

		<div class="d-flex text-center gap-1 mt-2" style="flex-wrap:wrap">
			<form method="POST" action="{{ route('tournament.kingOfCourt.undo', $stage) }}" style="flex:1">
				@csrf
				<button type="submit" class="btn btn-secondary w-100">↩ {{ __('tournaments.rally_btn_undo') }}</button>
			</form>
			<form method="POST" action="{{ route('tournament.kingOfCourt.endRound', $stage) }}" style="flex:1"
				data-alert="1"
				data-title="{{ __('tournaments.koc_end_round_confirm_title') }}"
				data-confirm-text="{{ __('tournaments.koc_btn_end_round') }}"
				data-cancel-text="{{ __('tournaments.btn_cancel') }}">
				@csrf
				<button type="submit" class="btn btn-primary w-100 js-koc-end-round">🏁 {{ __('tournaments.koc_btn_end_round') }}</button>
			</form>
		</div>

	</div>
</div>

<style>
.koc-side-btn {
	display: block;
	border: none;
	border-radius: 1.2rem;
	padding: 1.6rem;
	text-align: center;
	color: #fff;
	cursor: pointer;
}
.koc-side-king {
	background: linear-gradient(135deg, #FFB171, #E7612F);
}
.koc-side-challenge {
	background: linear-gradient(135deg, #6ea8f5, #2967BA);
}
</style>

<script>
(function () {
	var timerEl = document.getElementById('koc-timer');
	if (!timerEl) return;
	var startedAt = timerEl.dataset.startedAt ? new Date(timerEl.dataset.startedAt).getTime() : null;
	var durationMs = parseInt(timerEl.dataset.durationMin, 10) * 60 * 1000;
	if (!startedAt) return;

	function tick() {
		var remaining = startedAt + durationMs - Date.now();
		var sign = remaining < 0 ? '-' : '';
		remaining = Math.abs(remaining);
		var m = Math.floor(remaining / 60000);
		var s = Math.floor((remaining % 60000) / 1000);
		timerEl.textContent = sign + m + ':' + (s < 10 ? '0' : '') + s;
		if (remaining <= 0 && sign === '-') {
			timerEl.textContent = '{{ __('tournaments.koc_lbl_time_up') }}';
			timerEl.classList.add('score-pill--red');
		}
	}
	tick();
	setInterval(tick, 1000);
})();

// Кнопка "Завершить раунд" — подтверждение через swal (стиль проекта), без
// заведения ещё одного .btn-alert data-атрибута конфликтующего с undo/point.
document.querySelectorAll('.js-koc-end-round').forEach(function (btn) {
	btn.addEventListener('click', function (e) {
		if (btn.dataset.confirmed === '1') return;
		e.preventDefault();
		if (window.swal) {
			swal({
				title: '{{ __('tournaments.koc_end_round_confirm_title') }}',
				icon: 'warning',
				buttons: { cancel: '{{ __('tournaments.btn_cancel') }}', confirm: '{{ __('tournaments.koc_btn_end_round') }}' },
			}).then(function (ok) {
				if (ok) {
					btn.dataset.confirmed = '1';
					btn.click();
				}
			});
		} else {
			btn.dataset.confirmed = '1';
			btn.click();
		}
	});
});
</script>
</x-voll-layout>
