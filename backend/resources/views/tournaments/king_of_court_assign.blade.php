<x-voll-layout>
<x-slot name="title">{{ __('tournaments.koc_assign_title') }} — {{ $event->title }}</x-slot>
<x-slot name="h1">{{ __('tournaments.koc_assign_title') }}</x-slot>

<x-slot name="breadcrumbs">
	<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		<a href="{{ route('tournament.setup', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
		<meta itemprop="position" content="2">
	</li>
</x-slot>

<div class="container">
	<div class="ramka">

		@if(session('error'))
		<div class="alert alert-error">{{ session('error') }}</div>
		@endif

		<p class="f-16 mb-2">{{ __('tournaments.koc_assign_hint', ['min' => $minTeams, 'max' => $maxTeams]) }}</p>

		@if($teams->isEmpty())
		<div class="alert alert-info">{{ __('tournaments.koc_assign_no_teams') }}</div>
		@else
		<form method="POST" action="{{ route('tournament.kingOfCourt.assign', $stage) }}" class="form" id="koc-assign-form">
			@csrf

			<div class="card mb-2">
				<div class="b-700 f-16 mb-1">{{ __('tournaments.koc_assign_pick_teams') }}</div>
				<div class="d-flex" style="gap:8px;flex-wrap:wrap">
					@foreach($teams as $team)
					<label class="checkbox-item d-flex fvc" style="gap:6px">
						<input type="checkbox" name="team_ids[]" value="{{ $team->id }}" class="koc-team-cb">
						<div class="custom-checkbox"></div>
						<span>{{ $team->name }}</span>
					</label>
					@endforeach
				</div>
				<div class="f-13 mt-1" id="koc-team-count" style="opacity:.7"></div>
			</div>

			<div class="row">
				<div class="col-md-6">
					<div class="card">
						<label>{{ __('tournaments.setup_stage_seed') }}</label>
						<select name="draw_mode">
							<option value="random">{{ __('tournaments.setup_stage_seed_random') }}</option>
							<option value="seeded">{{ __('tournaments.setup_stage_seed_seeded') }}</option>
						</select>
					</div>
				</div>
				<div class="col-md-6">
					<div class="card">
						<label>{{ __('tournaments.koc_lbl_round_duration') }}</label>
						<input type="number" name="round_duration_min" min="1" max="60" value="15">
					</div>
				</div>
			</div>

			<div class="text-center mt-2">
				<button type="submit" class="btn btn-primary" id="koc-assign-submit">{{ __('tournaments.koc_btn_assign') }}</button>
			</div>
		</form>
		@endif

	</div>
</div>

<script>
(function () {
	var form = document.getElementById('koc-assign-form');
	if (!form) return;
	var checkboxes = form.querySelectorAll('.koc-team-cb');
	var counter = document.getElementById('koc-team-count');
	var min = {{ (int) $minTeams }};
	var max = {{ (int) $maxTeams }};

	function update() {
		var n = form.querySelectorAll('.koc-team-cb:checked').length;
		counter.textContent = '{{ __('tournaments.koc_assign_selected') }}: ' + n + ' / ' + min + '-' + max;
	}
	checkboxes.forEach(function (cb) { cb.addEventListener('change', update); });
	update();

	form.addEventListener('submit', function (e) {
		var n = form.querySelectorAll('.koc-team-cb:checked').length;
		if (n < min || n > max) {
			e.preventDefault();
			window.swal ? swal('', '{{ __('tournaments.koc_assign_count_error') }}'.replace(':min', min).replace(':max', max), 'warning') : alert('{{ __('tournaments.koc_assign_count_error') }}'.replace(':min', min).replace(':max', max));
		}
	});
})();
</script>
</x-voll-layout>
