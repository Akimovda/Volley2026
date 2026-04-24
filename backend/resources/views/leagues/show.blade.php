<x-voll-layout body_class="leagues-page">
	<x-slot name="title">{{ $league->name }}</x-slot>
	<x-slot name="h1">{{ $league->name }}</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.public') }}" itemprop="item">
				<span itemprop="name">Лиги</span>
			</a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ $league->name }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>



	<x-slot name="t_description">
		{{ $league->direction === 'beach' ? 'Пляжный' : 'Классический' }}
		@if($league->description)
			· {{ $league->description }}
		@endif
	</x-slot>

	<div class="container">

		<div class="row row2">
		<div class="col-lg-4 col-xl-3 order-1 order-lg-1">
		<div class="sticky">
		<div class="ramka">
			<div class="text-center">
				@if($league->logo_url)
				<div class="profile-avatar mb-3">
					<img src="{{ $league->logo_url }}" alt="logo">
				</div>
				@endif
			</div>

			<div class="row row2">
				<div class="col-sm-6 col-md-6 col-lg-12">
					<h2 class="-mt-05">Контакты</h2>
					@if($league->phone)
					<div class="provider-card__header icon-light">
						<span class="provider-card__icon icon-tel"></span>
						<span class="provider-card__title"><a href="tel:{{ $league->phone }}">{{ preg_replace('/(\+7)(\d{3})(\d{3})(\d{2})(\d{2})/', '$1 ($2) $3-$4-$5', $league->phone) }}</a></span>
					</div>
					@endif
					@if($league->website)
					<div class="provider-card__header icon-light">
						<span class="provider-card__icon icon-site"></span>
						<span class="provider-card__title"><a href="{{ $league->website }}" target="_blank" rel="nofollow">{{ preg_replace('#^https?://(www\.)?|/.*$#', '', $league->website) }}</a></span>
					</div>
					@endif
				</div>

				<div class="col-sm-6 col-md-6 col-lg-12 text-center">
					<div class="social-btns">
						@if($league->vk)
						<a href="{{ $league->vk }}" target="_blank">
							<span class="provider-card__header">
								<span class="provider-card__icon icon-vk"></span>
							</span>
						</a>
						@endif
						@if($league->telegram)
						<a href="{{ $league->telegram }}" target="_blank">
							<span class="provider-card__header">
								<span class="provider-card__icon icon-tg"></span>
							</span>
						</a>
						@endif
						@if($league->max_messenger)
						<a href="{{ $league->max_messenger }}" target="_blank">
							<span class="provider-card__header">
								<span class="provider-card__icon icon-max"></span>
							</span>
						</a>
						@endif
						@if(!$league->phone && !$league->website && !$league->vk && !$league->telegram && !$league->max_messenger)
						<div class="alert alert-error">Не указаны</div>
						@endif
					</div>
				</div>

				@if($league->organizer)
				<div class="col-12">
					<h2 class="mt-1">Организатор</h2>
					<div class="provider-card__header">
						<span class="provider-card__icon"><img src="{{ $league->organizer->profile_photo_url }}" alt="{{ $league->organizer->first_name }}" style="border-radius:50%;object-fit:cover"></span>
						<span class="provider-card__title"><a href="{{ route('users.show', $league->organizer->id) }}">{{ trim($league->organizer->first_name . ' ' . $league->organizer->last_name) }}</a></span>
					</div>
				</div>
				@endif
			</div>
		</div>
		</div>
		</div>

		<div class="col-lg-8 col-xl-9 order-2 order-lg-2">

		{{-- Сезоны --}}
		@if($league->seasons->isNotEmpty())
			@foreach($league->seasons as $season)
			<div class="ramka">
				<div class="d-flex between fvc mb-1">
					<h2 class="-mt-05 mb-0">
						<a href="{{ route('seasons.show.slug', [$season->league?->slug ?? 'league', $season->slug]) }}" class="blink">{{ $season->name }}</a>
					</h2>
					@php
						$statusColors = [
							'active' => ['bg' => 'rgba(16,185,129,.15)', 'color' => '#10b981', 'label' => 'Активен'],
							'completed' => ['bg' => 'rgba(128,128,128,.15)', 'color' => '#6b7280', 'label' => 'Завершён'],
							'draft' => ['bg' => 'rgba(231,97,47,.15)', 'color' => '#E7612F', 'label' => 'Черновик'],
						];
						$st = $statusColors[$season->status] ?? $statusColors['draft'];
					@endphp
					<span class="f-13 b-600" style="background:{{ $st['bg'] }}; padding: 0.4rem 1rem; border-radius:1rem;color:{{ $st['color'] }}">
						{{ $st['label'] }}
					</span>
				</div>

				<div class="f-16 mb-1 cd">
					{{ $season->starts_at?->format('d.m.Y') ?? '—' }} — {{ $season->ends_at?->format('d.m.Y') ?? '...' }}
					· Туров: {{ $season->seasonEvents->count() }}
				</div>

				{{-- Рейтинг топ-5 --}}
				@php $seasonStats = $season->stats->sortByDesc('match_win_rate')->take(5); @endphp
				@if($seasonStats->isNotEmpty())
					<div class="table-scrollable mb-0">
						<div class="table-drag-indicator"></div>
						<table class="table">
							<thead>
								<tr>
									<th style="width:30px">#</th>
									<th>Игрок</th>
									<th class="text-center">Матчей</th>
									<th class="text-center">Побед</th>
									<th class="text-center">WinRate</th>
								</tr>
							</thead>
							<tbody>
								@foreach($seasonStats as $i => $stat)
								<tr>
									<td>{{ $i + 1 }}</td>
									<td>
										<a href="{{ route('users.show', $stat->user_id) }}" class="blink">
											{{ $stat->user->first_name ?? '' }} {{ $stat->user->last_name ?? '' }}
										</a>
									</td>
									<td class="text-center">{{ $stat->matches_played }}</td>
									<td class="text-center">{{ $stat->matches_won }}</td>
									<td class="text-center"><strong>{{ number_format($stat->match_win_rate, 1) }}%</strong></td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
					<div class="mt-1">
						<a href="{{ route('seasons.show.slug', [$season->league?->slug ?? 'league', $season->slug]) }}" class="blink f-16">Полный рейтинг и подробности →</a>
					</div>
				@endif
			</div>
			@endforeach
		@else
			<div class="ramka">
				<div class="alert alert-info">В этой лиге пока нет сезонов.</div>
			</div>
		@endif

		</div>{{-- /col-lg-8 --}}
		</div>{{-- /row --}}
	</div>
</x-voll-layout>
