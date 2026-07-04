@if($team ?? null)
	@if($team->team_kind === 'classic_team')
<a href="{{ route('tournament.public.team', [$team->event_id, $team->id]) }}" class="blink">{{ $team->name }}</a>
	@else
{{ $team->name }}
	@endif
@else
{{ $fallback ?? '—' }}
@endif
