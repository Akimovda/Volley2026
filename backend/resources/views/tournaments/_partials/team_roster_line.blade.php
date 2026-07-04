@php $cls = $class ?? 'f-16'; $sty = $style ?? ''; @endphp
@if($team ?? null)
	@if($team->team_kind === 'classic_team')
		@if($team->captain)
<div class="{{ $cls }}" style="{{ $sty }}"><a href="{{ route('users.show', $team->captain_user_id) }}" class="blink">{{ trim(($team->captain->last_name ?? '') . ' ' . ($team->captain->first_name ?? '')) ?: '?' }}</a></div>
		@endif
	@else
		@if($team->members->count())
<div class="{{ $cls }}" style="{{ $sty }}">{{ $team->members->map(fn($m) => trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: '?')->implode(' / ') }}</div>
		@endif
	@endif
@endif
