<x-voll-layout body_class="scorer-page tournament-rally-page">
<x-slot name="title">{{ __('broadcast.scorer_title') }} — {{ $court->name }}</x-slot>
<x-slot name="h1">{{ __('broadcast.scorer_title') }}</x-slot>

@php
	$fmtTime = function ($m) use ($event) {
		if (!$m->scheduled_at) return '';
		$tz = $event->timezone ?: config('app.timezone');
		return $m->scheduled_at->copy()->setTimezone($tz)->format('H:i');
	};
@endphp

<div class="container">
	<div class="ramka">

		@if(session('scorer_error'))
		<div class="alert alert-error">{{ session('scorer_error') }}</div>
		@endif

		@include('scorer._header')

		@if($askName)
		<div class="card p-3">
			<div class="b-700 f-16 mb-1">{{ __('broadcast.scorer_name_title') }}</div>
			<div class="f-13 mb-1" style="opacity:.7">{{ __('broadcast.scorer_name_hint') }}</div>
			<form method="POST" action="{{ route('scorer.name', $link->token) }}" class="form">
				@csrf
				<input type="text" name="name" value="{{ old('name', $name) }}" minlength="2" maxlength="40" required autocomplete="name"
					placeholder="{{ __('broadcast.scorer_name_placeholder') }}" style="width:100%">
				<button type="submit" class="btn btn-primary mt-1" style="width:100%">{{ __('broadcast.scorer_name_save') }}</button>
			</form>
		</div>
		@else

		@if($done)
		<div class="alert alert-success mb-2">
			✅ {{ __('broadcast.scorer_done', ['n' => $done->match_number]) }}:
			<b>{{ $done->teamHome?->name }} {{ implode(', ', array_map(fn ($h, $a) => $h . ':' . $a, (array) $done->score_home, (array) $done->score_away)) }} {{ $done->teamAway?->name }}</b>
			@if($current)<br>{{ __('broadcast.scorer_next_below') }}@endif
		</div>
		@endif

		@if($current)
		<div class="card p-3 mb-2" style="height:auto;border:2px solid #2967BA">
			<div class="f-13 mb-05" style="opacity:.7">
				{{ $current->isLive() ? '🔴 ' . __('broadcast.scorer_live') : __('broadcast.scorer_next') }}
				· #{{ $current->match_number }} · {{ $current->stage?->name }} {{ $fmtTime($current) ? '· ' . $fmtTime($current) : '' }}
			</div>
			<div class="b-700 f-18 mb-1">{{ $current->teamHome?->name }} — {{ $current->teamAway?->name }}</div>
			@if($current->is_tiebreaker)
			<div class="alert alert-info f-13">{{ __('broadcast.scorer_err_tiebreak') }}</div>
			@else
			<a href="{{ route('scorer.match', [$link->token, $current]) }}" class="btn btn-primary" style="width:100%;text-align:center">
				{{ $current->isLive() ? __('broadcast.scorer_continue') : __('broadcast.scorer_start') }}
			</a>
			@endif
		</div>
		@elseif(!$done)
		<div class="card p-3 text-center mb-2" style="height:auto">{{ __('broadcast.scorer_no_matches') }}</div>
		@endif

		@if($upcoming->isNotEmpty())
		<div class="b-700 f-16 mb-1 mt-2">{{ __('broadcast.scorer_upcoming') }}</div>
		@foreach($upcoming as $m)
		<div class="card p-2 mb-1" style="height:auto">
			<div class="f-12" style="opacity:.65">
				{{ $m->isLive() ? '🔴 ' : '' }}#{{ $m->match_number }} · {{ $m->stage?->name }} {{ $fmtTime($m) ? '· ' . $fmtTime($m) : '' }}
			</div>
			<div class="b-600 f-15 mb-05">{{ $m->teamHome?->name }} — {{ $m->teamAway?->name }}</div>
			@if($m->is_tiebreaker)
			<div class="f-12" style="opacity:.7">🔒 {{ __('broadcast.scorer_err_tiebreak') }}</div>
			@else
			<a href="{{ route('scorer.match', [$link->token, $m]) }}" class="btn btn-secondary btn-small">{{ __('broadcast.scorer_switch') }}</a>
			@endif
		</div>
		@endforeach
		@endif

		@endif
	</div>
</div>
</x-voll-layout>
