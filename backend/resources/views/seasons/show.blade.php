<x-voll-layout body_class="seasons-page">
	<x-slot name="title">{{ $season->name }}</x-slot>
	<x-slot name="h1">{{ $season->name }}</x-slot>
	
	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('seasons.index') }}" itemprop="item">
				<span itemprop="name">Мои сезоны</span>
			</a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ $season->name }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>
	<x-slot name="h2">
		Организатор: {{ trim(($season->organizer->first_name ?? '') . ' ' . ($season->organizer->last_name ?? '')) ?: $season->organizer->name }}
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
			.ts { display:inline-block; padding: 0.5rem 1rem; border-radius:1rem; font-size: 1.5rem; font-weight: 600 }
			.tm { font-size:13px; color:#6b7280; margin-top:2px; }
			.tm a { color:#6b7280; }
			.tm a:hover { color:#4f46e5; }
			.tn { font-weight:600; }
			.wr { font-weight:600; }
			.wp { color:#16a34a; }
			.wn { color:#dc2626; }
			@media (max-width:640px) {
			.hm { display:none; }
			}
		</style>
	</x-slot>
	
    <x-slot name="t_description">
		<div class="d-flex">
			{!! $season->direction === 'beach' ? '<span class="emo" style="flex: 0 0 4rem">🏖</span> Пляжный' : '🏐 Классический' !!}
		</div>			
		<div class="d-flex">				
			@if($season->starts_at)	
			<span class="emo" style="flex: 0 0 4rem">📅</span> {{ $season->starts_at->format('d.m.Y') }}@if($season->ends_at) — {{ $season->ends_at->format('d.m.Y') }}@endif
			@endif
		</div>
	</x-slot>
	
    <x-slot name="d_description">
		<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
			<span class="d-inline-block pt-1 pb-1 alert alert-{{
			substr($season->status, 0, 1) === 'a' ? 'success' : 
			(substr($season->status, 0, 1) === 'c' ? 'danger' : 'warning')
			}}">
				{{ $season->status === 'active' ? 'Активен' : ($season->status === 'completed' ? 'Завершён' : 'Черновик') }}
			</span>
		</div>		
	</x-slot>	
	
	<div class="container">
		
		
		
		{{-- Расписание туров --}}
		@if(isset($occurrences) && $occurrences->count())
		<div class="ramka">	
			<h2 class="-mt-05">Расписание туров</h2>
			<div class="table-scrollable mb-0">
				<div class="table-drag-indicator"></div>
				<table class="table">
					<thead>
						<tr>
							<th style="width:40px">Тур</th>
							<th>Дата</th>
							<th class="text-center">Время</th>
							<th style="width:10rem">Статус</th>
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
							<td class="b-600 text-center">{{ $loop->iteration }}</td>
							<td>
								<a href="{{ route('events.show', $sourceEvent) }}" class="blink" style="font-weight:500">
									{{ $dt->translatedFormat('d M, l') }}
								</a>
							</td>
							<td class="text-center">{{ $dt->format('H:i') }}</td>
							<td>
								@if($hasStages)
								<span class="ts alert-success">✓ Сыгран</span>
								@elseif($isToday)
								<span class="ts alert-info">Сегодня</span>
								@elseif($isPast)
								<span class="ts alert-warning">—</span>
								@else
								<span class="ts alert-info">Предстоит</span>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
		@endif
		
		{{-- Лиги --}}
		@foreach($season->leagues as $league)
		<div class="ramka">
			<div class="d-flex between">
				<h2 class="-mt-05">{{ $league->name }}</h2>
				<span class="b-600 cd">
					— {{ $league->activeTeams->count() }} команд
					@if($league->reserveTeams->count())
					/ {{ $league->reserveTeams->count() }} в резерве
					@endif
				</span>				
			</div>
			
			@if($league->activeTeams->isNotEmpty())
			<div class="table-scrollable mb-0">
				<div class="table-drag-indicator"></div>
				<table class="table">
					<thead>
						<tr>
							<th style="width:30px">#</th>
							<th>Команда</th>
							<th>Игроки</th>
						</tr>
					</thead>
					<tbody>
						@foreach($league->activeTeams as $i => $lt)
						<tr>
							<td>{{ $i + 1 }}</td>
							
							@if($lt->team)
							<td><strong>{{ $lt->team->name }}</strong></td>
							<td>
								
								@foreach($lt->team->members as $mi => $m)
								@if($m->user)
								<a class="blink" href="{{ route('users.show', $m->user) }}">{{ $m->user->last_name }} {{ $m->user->first_name }}</a>{{ $mi < $lt->team->members->count() - 1 ? ' / ' : '' }}
									@endif
									@endforeach
									
									@elseif($lt->user)
									<a class="blink" href="{{ route('users.show', $lt->user) }}">{{ $lt->user->first_name }} {{ $lt->user->last_name }}</a>
								</td>		
								@endif
								
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				@else
				<div class="alert alert-info">Нет команд</div>
				@endif
			</div>
			@endforeach
			
			{{-- Рейтинг игроков --}}
			@if($season->stats->isNotEmpty())
			<div class="ramka">	
				<h2 class="-mt-05">Рейтинг игроков</h2>
				<div class="table-scrollable mb-0">
					<div class="table-drag-indicator"></div>
					<table class="table">
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
								<td>{{ $i + 1 }}</td>
								<td><a href="{{ route('users.show', $stat->user_id) }}" class="blink">{{ $stat->user->first_name ?? '' }} {{ $stat->user->last_name ?? '' }}</a></td>
								<td class="text-center">{{ $stat->matches_played }}</td>
								<td class="text-center">{{ $stat->matches_won }}</td>
								<td class="text-center"><span class="wr">{{ number_format($stat->match_win_rate, 1) }}%</span></td>
								<td class="text-center">{{ $stat->sets_won }}:{{ $stat->sets_lost }}</td>
								<td class="text-center">
									@php $pd = $stat->points_scored - $stat->points_conceded; @endphp
									<span style="padding:0.2rem 1rem; border-radius: 0.6rem;" class="{{ $pd >= 0 ? 'alert-success' : 'alert-danger' }}">{{ $pd >= 0 ? '+' : '' }}{{ $pd }}</span>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
			@endif
			
		</div>
	</div>
	
</x-voll-layout>
