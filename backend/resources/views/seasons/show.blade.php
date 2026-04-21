<x-voll-layout body_class="seasons-page">
<x-slot name="title">{{ $season->name }}</x-slot>
<x-slot name="h1">{{ $season->name }}</x-slot>

<x-slot name="breadcrumbs">
	<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		<span itemprop="name">Сезоны</span>
		<meta itemprop="position" content="2">
	</li>
	<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		<span itemprop="name">{{ $season->name }}</span>
		<meta itemprop="position" content="3">
	</li>
</x-slot>

<x-slot name="style">
<style>
	.si { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; font-size:14px; align-items:center; }
	.sb { display:inline-block; padding:3px 12px; border-radius:12px; font-size:12px; font-weight:600; }
	.sb-a { background:#dcfce7; color:#166534; }
	.sb-c { background:#e5e7eb; color:#374151; }
	.sb-d { background:#fef3c7; color:#92400e; }
	.lc { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; margin-bottom:20px; }
	.lch { padding:14px 16px; background:#f9fafb; border-bottom:1px solid #e5e7eb; }
	.lch h3 { margin:0; font-size:17px; }
	.st { width:100%; border-collapse:collapse; }
	.st th { text-align:left; padding:8px 12px; font-size:12px; color:#6b7280; border-bottom:2px solid #e5e7eb; }
	.st td { padding:10px 12px; border-bottom:1px solid #f3f4f6; font-size:14px; vertical-align:middle; }
	.st tr:last-child td { border-bottom:none; }
	.ts { display:inline-block; padding:2px 10px; border-radius:10px; font-size:12px; font-weight:600; }
	.ts-p { background:#dcfce7; color:#166534; }
	.ts-u { background:#dbeafe; color:#1e40af; }
	.ts-t { background:#fef3c7; color:#92400e; }
	.tm { font-size:13px; color:#6b7280; margin-top:2px; }
	.tm a { color:#6b7280; }
	.tm a:hover { color:#4f46e5; }
	.tn { font-weight:600; }
	.wr { font-weight:700; }
	.wp { color:#16a34a; }
	.wn { color:#dc2626; }
	@media (max-width:640px) {
		.st th, .st td { padding:8px 6px; font-size:13px; }
		.hm { display:none; }
		.lch { padding:10px 12px; }
		.lch h3 { font-size:15px; }
	}
</style>
</x-slot>

<div class="container">
<div class="ramka">

	{{-- Инфо --}}
	<div class="si">
		<span>Организатор: <strong>{{ trim(($season->organizer->first_name ?? '') . ' ' . ($season->organizer->last_name ?? '')) ?: $season->organizer->name }}</strong></span>
		<span>{{ $season->direction === 'beach' ? '🏖 Пляжный' : '🏐 Классический' }}</span>
		@if($season->starts_at)
			<span>📅 {{ $season->starts_at->format('d.m.Y') }}@if($season->ends_at) — {{ $season->ends_at->format('d.m.Y') }}@endif</span>
		@endif
		<span class="sb sb-{{ substr($season->status, 0, 1) }}">
			{{ $season->status === 'active' ? 'Активен' : ($season->status === 'completed' ? 'Завершён' : 'Черновик') }}
		</span>
	</div>

	{{-- Расписание туров --}}
	@if(isset($occurrences) && $occurrences->count())
	<h3 style="font-size:17px;margin-bottom:12px">📋 Расписание туров</h3>
	<table class="st">
		<thead>
			<tr>
				<th style="width:40px">Тур</th>
				<th>Дата</th>
				<th class="hm">Время</th>
				<th style="width:100px">Статус</th>
			</tr>
		</thead>
		<tbody>
			@foreach($occurrences as $occ)
				@php
					$tz = $sourceEvent->timezone ?? 'Europe/Moscow';
					$dt = \Carbon\Carbon::parse($occ->starts_at)->setTimezone($tz);
					$isToday = $dt->isToday();
					$isPast = $dt->isPast();
					$hasStages = \App\Models\TournamentStage::where('event_id', $sourceEvent->id)
						->where('occurrence_id', $occ->id)
						->where('status', 'completed')
						->exists();
				@endphp
				<tr>
					<td style="font-weight:600">{{ $loop->iteration }}</td>
					<td>
						<a href="{{ route('events.show', $sourceEvent) }}" class="blink" style="font-weight:500">
							{{ $dt->translatedFormat('d M, l') }}
						</a>
					</td>
					<td class="hm">{{ $dt->format('H:i') }}</td>
					<td>
						@if($hasStages)
							<span class="ts ts-p">✓ Сыгран</span>
						@elseif($isToday)
							<span class="ts ts-t">Сегодня</span>
						@elseif($isPast)
							<span class="ts ts-p" style="opacity:.5">—</span>
						@else
							<span class="ts ts-u">Предстоит</span>
						@endif
					</td>
				</tr>
			@endforeach
		</tbody>
	</table>
	<div style="height:20px"></div>
	@endif

	{{-- Лиги --}}
	@foreach($season->leagues as $league)
	<div class="lc">
		<div class="lch">
			<h3>{{ $league->name }}
				<span style="font-weight:400;color:#6b7280;font-size:14px">
					— {{ $league->activeTeams->count() }} команд
					@if($league->reserveTeams->count())
						/ {{ $league->reserveTeams->count() }} в резерве
					@endif
				</span>
			</h3>
		</div>

		@if($league->activeTeams->isNotEmpty())
		<table class="st">
			<thead>
				<tr>
					<th style="width:30px">#</th>
					<th>Команда</th>
				</tr>
			</thead>
			<tbody>
				@foreach($league->activeTeams as $i => $lt)
				<tr>
					<td style="color:#9ca3af">{{ $i + 1 }}</td>
					<td>
						@if($lt->team)
							<span class="tn">{{ $lt->team->name }}</span>
							<span class="tm">(
								@foreach($lt->team->members as $mi => $m)
									@if($m->user)
										<a href="{{ route('users.show', $m->user) }}">{{ $m->user->last_name }} {{ $m->user->first_name }}</a>{{ $mi < $lt->team->members->count() - 1 ? ' / ' : '' }}
									@endif
								@endforeach
							)</span>
						@elseif($lt->user)
							<a href="{{ route('users.show', $lt->user) }}">{{ $lt->user->first_name }} {{ $lt->user->last_name }}</a>
						@endif
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@else
			<div style="padding:16px;color:#9ca3af">Нет команд</div>
		@endif
	</div>
	@endforeach

	{{-- Рейтинг игроков --}}
	@if($season->stats->isNotEmpty())
	<h3 style="font-size:17px;margin-bottom:12px">🏅 Рейтинг игроков</h3>
	<table class="st">
		<thead>
			<tr>
				<th style="width:30px">#</th>
				<th>Игрок</th>
				<th>Матчей</th>
				<th>Побед</th>
				<th>WinRate</th>
				<th class="hm">Сеты</th>
				<th class="hm">Очки ±</th>
			</tr>
		</thead>
		<tbody>
			@foreach($season->stats->take(20) as $i => $stat)
			<tr>
				<td style="color:#9ca3af">{{ $i + 1 }}</td>
				<td><a href="{{ route('users.show', $stat->user_id) }}" class="blink">{{ $stat->user->first_name ?? '' }} {{ $stat->user->last_name ?? '' }}</a></td>
				<td>{{ $stat->matches_played }}</td>
				<td>{{ $stat->matches_won }}</td>
				<td><span class="wr">{{ number_format($stat->match_win_rate, 1) }}%</span></td>
				<td class="hm">{{ $stat->sets_won }}:{{ $stat->sets_lost }}</td>
				<td class="hm">
					@php $pd = $stat->points_scored - $stat->points_conceded; @endphp
					<span class="{{ $pd >= 0 ? 'wp' : 'wn' }}">{{ $pd >= 0 ? '+' : '' }}{{ $pd }}</span>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif

</div>
</div>

</x-voll-layout>
