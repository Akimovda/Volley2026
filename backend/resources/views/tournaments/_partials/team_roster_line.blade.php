@php
	$cls = $class ?? 'f-16'; $sty = $style ?? ''; $avatar = $showAvatar ?? false;
	// Уровень по направлению турнира — тот же паттерн, что и в team_name_link.blade.php
	// и на tournaments/public/team.blade.php.
	$isBeachPair = ($team ?? null) && $team->team_kind === 'beach_pair';
	$rosterLvlColor = function ($user) use ($isBeachPair) {
		if (!$user) return '#aaaaaa';
		$lvl = $isBeachPair
			? (int) ($user->beach_level ?? $user->classic_level ?? 0)
			: (int) ($user->classic_level ?? $user->beach_level ?? 0);
		return $lvl > 0 ? level_color($lvl) : '#aaaaaa';
	};
@endphp
{{-- $showAvatar — опциональный (default false), передаётся ТОЛЬКО с пульта
     (setup.blade.php); на публичных/TV/score страницах, где этот партиал тоже
     используется, поведение не меняется. --}}
@if($team ?? null)
	@if($team->team_kind === 'classic_team')
		@if($team->captain)
<div class="{{ $cls }}" style="{{ $sty }}">
	@if($avatar)
	{{-- Точка уровня — ОТДЕЛЬНЫЙ сосед аватара (не оверлей поверх него), тот же
	     паттерн, что в events/show/players.blade.php ("Записанные игроки"). --}}
	<img src="{{ $team->captain->profile_photo_url }}" class="ms-player-avatar-mini" alt="" style="vertical-align:middle;margin-right:.4rem">
	<span class="level-dot level-dot--sm" style="vertical-align:middle;margin-right:.4rem;background:{{ $rosterLvlColor($team->captain) }}"></span>
	@endif
	<a href="{{ route('users.show', $team->captain_user_id) }}" class="blink">{{ trim(($team->captain->last_name ?? '') . ' ' . ($team->captain->first_name ?? '')) ?: '?' }}</a>
</div>
		@endif
	@else
		@if($team->members->count())
{{-- Класс team-roster-line--avatars включает вертикальный список (по игроку на
     строке) — только когда показаны аватары (иначе на публичных/TV/score
     страницах, где $avatar=false, поведение как раньше — "Имя1 / Имя2"). --}}
<div class="{{ $cls }}{{ $avatar ? ' team-roster-line--avatars' : '' }}" style="{{ $sty }}">
	@foreach($team->members as $m)
	<span class="team-roster-member">
		@if($avatar)
		<img src="{{ $m->user->profile_photo_url }}" class="ms-player-avatar-mini" alt="" style="vertical-align:middle;margin-right:.3rem;flex-shrink:0">
		<span class="level-dot level-dot--sm" style="vertical-align:middle;margin-right:.3rem;flex-shrink:0;background:{{ $rosterLvlColor($m->user) }}"></span>
		@endif<a href="{{ route('users.show', $m->user_id) }}" class="blink">{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: '?' }}</a>
	</span>@if(!$loop->last)<span class="team-roster-sep"> / </span>@endif
	@endforeach
</div>
		@endif
	@endif
@endif
