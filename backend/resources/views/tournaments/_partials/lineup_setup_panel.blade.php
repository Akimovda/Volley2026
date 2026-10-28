{{-- Экран расстановки партии. Данные — $lineup['setup'] (JS: lineup_rally_js). --}}
@if($lineup['can_edit_start'])
<div class="mb-2" style="text-align:center">
	<button type="button" class="btn btn-secondary btn-small" id="ls-toggle">{{ __('tournaments.lineup_edit_btn') }}</button>
</div>
@endif
<div class="card mb-2" id="ls-panel" style="{{ $lineup['needs_start'] ? '' : 'display:none' }}">
	<div class="b-700 f-16 mb-1" id="ls-title">{{ __('tournaments.lineup_setup_title', ['n' => $setNumber]) }}</div>
	<div class="f-13 mb-2" style="opacity:.7">{{ __('tournaments.lineup_setup_hint') }}</div>

	<div class="ls-tabs mb-1">
		@foreach($sides as $sk => $sd)
		<button type="button" class="btn btn-small ls-tab" data-side="{{ $sk }}"><span class="ls-tab-name">{{ $sd['team']->name ?? 'TBD' }}</span><span class="ls-tab-state"></span></button>
		@endforeach
	</div>

	<div class="f-13 b-600 mb-05">{{ __('tournaments.lineup_first_serve') }}</div>
	<div class="ls-tabs mb-2">
		@foreach($sides as $sk => $sd)
		<button type="button" class="btn btn-small ls-serve" data-side="{{ $sk }}"><span class="ls-bi">@include('tournaments._partials.ball_icon')</span> {{ $sd['team']->name ?? 'TBD' }}</button>
		@endforeach
	</div>

	<div class="ls-court-wrap">
		<div class="ls-net">{{ __('tournaments.lineup_net') }}</div>
		<div class="ls-court" id="ls-court"></div>
		<div class="ls-lines f-13"><span>{{ __('tournaments.lineup_front_line') }}: 4-3-2</span><span>{{ __('tournaments.lineup_back_line') }}: 5-6-1</span></div>
	</div>

	@if($lineup['profile']['has_libero'])
	<div class="mt-2">
		<div class="f-13 b-600 mb-05">{{ __('tournaments.lineup_libero_label') }}</div>
		<button type="button" class="btn btn-secondary" id="ls-libero" style="width:100%"></button>
	</div>
	@endif

	<div id="ls-libero-row"></div>
	<div class="f-13 mt-1" style="opacity:.7">{{ __('tournaments.lineup_jersey_hint') }}</div>

	<div id="ls-pool" class="f-13 mt-2" style="display:none"></div>
	<div id="ls-error" class="alert alert-error f-13 mt-2" style="display:none"></div>
	<div id="ls-missing" class="f-13 mt-2" style="opacity:.75"></div>
	<button type="button" class="btn btn-primary mt-1" id="ls-start" style="width:100%" disabled>{{ $lineup['has_start'] ? __('tournaments.lineup_save_btn') : __('tournaments.lineup_start_btn') }}</button>
</div>
