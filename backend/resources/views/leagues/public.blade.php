<x-voll-layout body_class="leagues-page">
	<x-slot name="title">Лиги</x-slot>
	<x-slot name="h1">Лиги</x-slot>

	<x-slot name="canonical">{{ route('leagues.public') }}</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.public') }}" itemprop="item">
				<span itemprop="name">Лиги</span>
			</a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>

	<x-slot name="t_description">
		Турнирные лиги с сезонами, рейтингами и системой промоушена.
	</x-slot>

	<div class="container">
		<div class="ramka">
			@if($leagues->isEmpty())
				<div class="alert alert-info">
					Пока нет активных лиг.
				</div>
			@else
				<div class="row">
					@foreach($leagues as $league)
						<div class="col-md-6 col-lg-4">
							<div class="card">
								@if($league->logo_url)
								<div class="mb-1">
									<a href="{{ route('leagues.show.slug', $league->slug) }}">
										<img src="{{ $league->logo_url }}" alt="{{ $league->name }}" style="width:60px;height:60px;border-radius:10px;object-fit:cover">
									</a>
								</div>
								@endif
								<div class="b-600 mb-1">
									<a href="{{ route('leagues.show.slug', $league->slug) }}" class="blink">{{ $league->name }}</a>
								</div>

								<div class="f-16 mb-1">
									{{ $league->direction === 'beach' ? 'Пляжный' : 'Классический' }}
								</div>

								@if($league->description)
									<div class="f-16 mb-1 cd">{{ Str::limit($league->description, 100) }}</div>
								@endif

								<div class="f-16 mb-1 cd">
									Организатор: {{ trim(($league->organizer->first_name ?? '') . ' ' . ($league->organizer->last_name ?? '')) ?: $league->organizer->name }}
								</div>

								<div class="f-16 mb-1 cd">
									Активных сезонов: {{ $league->seasons->count() }}
								</div>

								<div class="mt-auto" style="margin-top:auto">
									<a href="{{ route('leagues.show.slug', $league->slug) }}" class="btn btn-secondary f-13" style="padding:6px 14px">Подробнее</a>
								</div>
							</div>
						</div>
					@endforeach
				</div>
			@endif
		</div>
	</div>
</x-voll-layout>
