@php
$pname = trim(($placeholder->last_name ?? '') . ' ' . ($placeholder->first_name ?? '')) ?: '#' . $placeholder->id;
$row = function ($u) use ($placeholder) {
	return trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) ?: ($u->name ?: '#' . $u->id);
};
@endphp
<x-voll-layout body_class="placeholders-page">
	<x-slot name="title">{{ __('placeholders.merge_title') }}</x-slot>
	<x-slot name="h1">{{ __('placeholders.merge_title') }}</x-slot>

	<div class="container">
		@if(session('error'))<div class="alert alert-danger mb-2">{{ session('error') }}</div>@endif

		<p class="mb-2"><a href="{{ route('placeholders.index') }}" class="blink">{{ __('placeholders.back') }}</a></p>

		<div class="ramka mb-2">
			<h2 class="-mt-05">{{ __('placeholders.flow_h') }}</h2>
			<div class="d-flex gap-1 fvc mb-2" style="flex-wrap:wrap;">
				<div class="card" style="height:auto;flex:1 1 20rem;border:0.15rem dashed #E7612F;">
					<div class="f-13" style="opacity:.7;">{{ __('placeholders.flow_from') }}</div>
					<div><b>👻 {{ $pname }}</b> <span class="f-13" style="opacity:.7;">ID {{ $placeholder->id }}@if($placeholder->phone) · {{ $placeholder->phone }}@endif</span></div>
					<div class="f-14 mt-1">
						{{ __('placeholders.ph_has') }}:
						{{ $phSummary['registrations'] }} {{ __('placeholders.cnt_regs') }}
						({{ __('placeholders.cnt_upcoming') }} {{ $phSummary['upcoming'] }}),
						{{ $phSummary['teams'] }} {{ __('placeholders.cnt_teams') }},
						{{ $phSummary['matches'] }} {{ __('placeholders.cnt_matches') }}
					</div>
				</div>
				<div class="b-600" style="flex:0 0 auto;">➡️</div>
				<div class="card" style="height:auto;flex:1 1 20rem;border:0.15rem solid #2967BA;">
					<div class="f-13" style="opacity:.7;">{{ __('placeholders.flow_to') }}</div>
					<div class="f-14">{{ __('placeholders.flow_arrow') }}</div>
				</div>
			</div>
			<ul class="f-14 mb-0">
				<li>{{ __('placeholders.flow_text') }}</li>
				<li>{{ __('placeholders.flow_moves') }}</li>
				<li>{{ __('placeholders.flow_fields') }}</li>
				<li>{{ __('placeholders.flow_conflict') }}</li>
				<li><b>{{ __('placeholders.flow_check') }}</b></li>
			</ul>
		</div>

		<div class="ramka mb-2">
			<h2 class="-mt-05">{{ __('placeholders.suggested_h') }}</h2>
			@include('placeholders._candidates', ['users' => $suggested, 'placeholder' => $placeholder, 'service' => $service, 'empty' => __('placeholders.suggested_none')])
		</div>

		<div class="ramka">
			<h2 class="-mt-05">{{ __('placeholders.search_h') }}</h2>
			<form method="GET" class="form d-flex gap-1 mb-2">
				<input type="text" name="q" value="{{ $q }}" placeholder="{{ __('placeholders.search_ph') }}">
				<button type="submit" class="btn btn-small">{{ __('placeholders.btn_search') }}</button>
			</form>
			@if($q !== '')
				@include('placeholders._candidates', ['users' => $found, 'placeholder' => $placeholder, 'service' => $service, 'service' => $service, 'empty' => '—'])
			@endif
		</div>
	</div>
</x-voll-layout>
