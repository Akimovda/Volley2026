<x-voll-layout body_class="leagues-page">
	<x-slot name="title">{{ __('seasons.leagues_show_breadcrumb') }}</x-slot>
	<x-slot name="h1">{{ __('seasons.leagues_show_breadcrumb') }}</x-slot>

	<x-slot name="canonical">{{ route('leagues.public') }}</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.public') }}" itemprop="item">
				<span itemprop="name">{{ __('seasons.leagues_show_breadcrumb') }}</span>
			</a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>

	<x-slot name="t_description">
		{{ __('seasons.leagues_public_description') }}
	</x-slot>

	<div class="container">
		<div class="ramka">
			@if($leagues->isEmpty())
				<div class="alert alert-info">
					{{ __('seasons.leagues_public_empty') }}
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
									{{ $league->direction === 'beach' ? __('seasons.leagues_dir_beach_short') : __('seasons.leagues_dir_classic_short') }}
								</div>

								@if($league->description)
									<div class="f-16 mb-1 cd">{{ Str::limit($league->description, 100) }}</div>
								@endif

								<div class="f-16 mb-1 cd">
									{{ __('seasons.leagues_organizer_label') }} {{ trim(($league->organizer->first_name ?? '') . ' ' . ($league->organizer->last_name ?? '')) ?: $league->organizer->name }}
								</div>

								<div class="f-16 mb-1 cd">
									{{ __('seasons.leagues_active_seasons') }} {{ $league->seasons->count() }}
								</div>

								<div class="mt-auto" style="margin-top:auto">
									<a href="{{ route('leagues.show.slug', $league->slug) }}" class="btn btn-secondary f-13" style="padding:6px 14px">{{ __('seasons.btn_details') }}</a>
								</div>
							</div>
						</div>
					@endforeach
				</div>
			@endif
		</div>
	</div>
</x-voll-layout>
