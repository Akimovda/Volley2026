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
		
		@if($leagues->isEmpty())
		<div class="ramka">
			<div class="alert alert-info">
				{{ __('seasons.leagues_public_empty') }}
			</div>
		</div>
		@else
		<div class="row">
			@foreach($leagues as $league)
			<div class="col-md-6 col-lg-4">
				<div class="event-card card-ramka">
					
					
					<div class="event-card-body">
						<div class="mb-1 -mt-05">
							<a href="{{ route('leagues.show.slug', $league->slug) }}" class="blink cd b-600 card-title ">
								{{ $league->name }}
							</a>
						</div>								
						
						
						<div class="border f-0 mb-1 card-img-top">
							<a href="{{ route('leagues.show.slug', $league->slug) }}">
								
								@if($league->logo_url)
								<img src="{{ $league->logo_url }}" alt="{{ $league->name }}">
								@else	
								<img src="/img/pixel.png" alt="no-image">
								
								@endif
								
								<div class="event-direction {{ $league->direction === 'beach' ? 'beach-direction' : 'classic-direction' }}">
									{{ $league->direction === 'beach' ? __('seasons.leagues_dir_beach_short') : __('seasons.leagues_dir_classic_short') }}					
								</div>	
								
							</a>
						</div>						
						
						
						@if($league->description)
						<div class="mb-1">{{ Str::limit($league->description, 100) }}</div>
						@endif
						
					</div>
					
					<div>	
						
						<div class="d-flex mb-05">
							<div class="emo f-16">🎪</div>
							<div class="f-16">{{ __('seasons.leagues_organizer_label') }} : <strong class="cd">{{ trim(($league->organizer->first_name ?? '') . ' ' . ($league->organizer->last_name ?? '')) ?: $league->organizer->name }}</strong></div>
						</div>					
						
						<div class="d-flex">
							<div class="emo f-16">⚡</div>
							<div class="f-16">{{ __('seasons.leagues_active_seasons') }} :  <strong class="cd"> {{ $league->seasons->count() }}</strong></div>
						</div>					
						
						
					</div>
				</div>
			</div>
			@endforeach
		</div>
		@endif
	</div>
</x-voll-layout>
