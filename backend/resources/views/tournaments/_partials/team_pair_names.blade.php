@php
	// Пляжная пара (ровно 2 игрока) — «Фамилия Имя» друг под другом вместо названия команды; иначе обычное название команды.
	$pairNames = ($team ?? null) ? app(\App\Services\TeamDisplayNames::class)->pair($team) : null;
@endphp
@if($pairNames)
	@foreach($pairNames as $pn)<div>{{ $pn['full'] }}</div>@endforeach
@else
	@include('tournaments._partials.team_name_link', ['team' => $team ?? null, 'fallback' => $fallback ?? '—'])
@endif
