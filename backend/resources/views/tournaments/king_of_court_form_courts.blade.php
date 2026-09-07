<x-voll-layout>
<x-slot name="title">{{ __('tournaments.koc_form_courts_title') }} — {{ $event->title }}</x-slot>
<x-slot name="h1">{{ __('tournaments.koc_form_courts_title') }}</x-slot>

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

		@if($teams->isEmpty())
		<div class="alert alert-info">{{ __('tournaments.koc_assign_no_teams') }}</div>
		@elseif(!$range)
		<div class="alert alert-warning">{{ __('tournaments.koc_form_courts_hint_unavailable', ['n' => $teams->count(), 'min' => $minTeams, 'max' => $maxTeams]) }}</div>
		@else
		<p class="f-16 mb-2">{{ __('tournaments.koc_form_courts_hint_range', ['n' => $teams->count(), 'min' => $range[0], 'max' => $range[1]]) }}</p>

		<form method="POST" action="{{ route('tournament.kingOfCourt.formCourtsStore', $event) }}" class="form" id="koc-form-courts-form">
			@csrf
			<input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">

			{{-- Общие настройки раунда — одни на ВСЕ корты батча (и переедут в
			     финал при formFinal(), см. TournamentKingService::formFinal()). --}}
			<div class="row">
				<div class="col-md-6">
					<div class="card">
						<label>{{ __('tournaments.koc_lbl_round_duration') }}</label>
						<input type="number" name="round_duration_min" min="1" max="60" value="15">
					</div>
				</div>
				<div class="col-md-6">
					<div class="card">
						<label>{{ __('tournaments.koc_lbl_final_target') }}</label>
						<input type="number" name="final_target_points" min="1" max="99" value="15">
					</div>
				</div>
			</div>

			<div class="d-flex gap-1 mb-2 mt-2">
				<label class="d-flex fvc" style="gap:6px">
					<input type="radio" name="mode" value="random" checked class="koc-mode-radio">
					{{ __('tournaments.setup_stage_seed_random') }}
				</label>
				<label class="d-flex fvc" style="gap:6px">
					<input type="radio" name="mode" value="manual" class="koc-mode-radio">
					{{ __('tournaments.koc_lbl_manual_mode') }}
				</label>
			</div>

			{{-- Случайный режим --}}
			<div id="koc-random-block" class="row">
				<div class="col-md-6">
					<div class="card">
						<label>{{ __('tournaments.koc_lbl_groups_count') }}</label>
						<select name="groups_count">
							@for($g = $range[0]; $g <= $range[1]; $g++)
							<option value="{{ $g }}">{{ $g }}</option>
							@endfor
						</select>
					</div>
				</div>
				<div class="col-md-6">
					<div class="card">
						<label>{{ __('tournaments.setup_stage_seed') }}</label>
						<select name="draw_mode">
							<option value="random">{{ __('tournaments.setup_stage_seed_random') }}</option>
							<option value="seeded">{{ __('tournaments.setup_stage_seed_seeded') }}</option>
						</select>
					</div>
				</div>
			</div>

			{{-- Ручной режим --}}
			<div id="koc-manual-block" style="display:none">
				<p class="f-13 mb-1" style="opacity:.7">{{ __('tournaments.koc_manual_hint') }}</p>
				<div class="table-scrollable">
					<table class="table f-14">
						<thead>
							<tr>
								<th class="p-1">{{ __('tournaments.setup_col_team') }}</th>
								<th class="p-1" style="width:140px">{{ __('tournaments.setup_kb_col_group_label') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($teams as $team)
							<tr>
								<td class="p-1">{{ $team->name }}</td>
								<td class="p-1"><input type="text" name="assign[{{ $team->id }}]" placeholder="1, 2, A..."></td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>

			<div class="text-center mt-2">
				<button type="submit" class="btn btn-primary">{{ __('tournaments.koc_btn_form_courts') }}</button>
			</div>
		</form>
		@endif

	</div>
</div>

<script>
(function () {
	var radios = document.querySelectorAll('.koc-mode-radio');
	var randomBlock = document.getElementById('koc-random-block');
	var manualBlock = document.getElementById('koc-manual-block');
	if (!radios.length) return;

	function update() {
		var mode = document.querySelector('.koc-mode-radio:checked').value;
		randomBlock.style.display = mode === 'random' ? '' : 'none';
		manualBlock.style.display = mode === 'manual' ? '' : 'none';
	}
	radios.forEach(function (r) { r.addEventListener('change', update); });
	update();
})();
</script>
</x-voll-layout>
