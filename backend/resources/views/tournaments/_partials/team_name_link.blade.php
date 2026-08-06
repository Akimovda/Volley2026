@if($team ?? null)
	{{-- Название команды — без аватара/уровня: у команды нет "лица", это агрегат
	     игроков (аватар+уровень — только у отдельных игроков, team_roster_line.blade.php).
	     Ссылка на публичную страницу команды — для ОБОИХ team_kind: tournaments/public/team.blade.php
	     сама умеет рендерить и classic_team, и beach_pair ($isBeachPair внутри), раньше
	     beach_pair ошибочно оставался без ссылки. --}}
<a href="{{ route('tournament.public.team', [$team->event_id, $team->id]) }}" class="blink">{{ $team->name }}</a>
@else
{{ $fallback ?? '—' }}
@endif
