<x-voll-layout body_class="leagues-page">
	<x-slot name="title">{{ __('seasons.leagues_idx_title') }}</x-slot>
	<x-slot name="h1">{{ __('seasons.leagues_idx_h1') }}</x-slot>
	
	<x-slot name="canonical">{{ route('leagues.index') }}</x-slot>
	
	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.index') }}" itemprop="item">
				<span itemprop="name">{{ __('seasons.leagues_idx_breadcrumb') }}</span>
			</a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>
	
	<x-slot name="t_description">
		{{ __('seasons.leagues_idx_t_description') }}
	</x-slot>
	
	<x-slot name="d_description">
		<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
			<a href="{{ route('leagues.create') }}" class="btn btn-primary">{{ __('seasons.leagues_btn_create') }}</a>
		</div>
	</x-slot>
	
	<div class="container">
		@if($leagues->isEmpty())
		<div class="ramka">
			<div class="alert alert-info">
				<p><strong>{{ __('seasons.leagues_empty_lead') }}</strong></p>
				<p>{{ __('seasons.leagues_empty_text') }}</p>
			</div>
		</div>
		@else
		<div class="row">
			@foreach($leagues as $league)
			<div class="col-md-6 col-lg-4">
				<div class="ramka">
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
								
								
							@php
							$statusColors = [
							'active' => ['bg' => 'green', 'color' => '#10b981', 'label' => __('seasons.leagues_status_active')],
							'archived' => ['bg' => 'orange', 'color' => '#6b7280', 'label' => __('seasons.leagues_status_archived')],
							];
							$st = $statusColors[$league->status] ?? $statusColors['active'];
							@endphp
							<span  class="event-direction f-13 b-600" style="left: 0.8rem; right:auto; background:{{ $st['bg'] }}; line-height: 1; color:#fff; left: 1rem; right:auto; padding: 6px 12px; font-size: 11px; border-radius: 6px;">
								{{ $st['label'] }}
							</span>								
								
								
								<div class="event-direction {{ $league->direction === 'beach' ? 'beach-direction' : 'classic-direction' }}">
									{{ $league->direction === 'beach' ? __('seasons.leagues_dir_beach') : __('seasons.leagues_dir_classic') }}					
								</div>	
								
							</a>
						</div>	
						

						
						@if($league->description)
						<div class="mb-1">{{ Str::limit($league->description, 80) }}</div>
						@endif
					</div>
					<div>
						
						@if(auth()->user()->isAdmin() && $league->organizer)
						<div class="d-flex mb-05">
							<div class="emo f-16">🎪</div>
							<div class="f-16">{{ __('seasons.leagues_organizer_label') }} : <strong class="cd">{{ trim(($league->organizer->first_name ?? '') . ' ' . ($league->organizer->last_name ?? '')) ?: $league->organizer->name }}</strong></div>
						</div>					
						@endif
						<div class="d-flex">
							<div class="emo f-16">⚡</div>
							<div class="f-16">{{ __('seasons.leagues_seasons_label') }} :  <strong class="cd"> {{ $league->seasons->count() }}</strong></div>
						</div>							
						

						
						<div class="d-flex mt-1 gap-1 text-center">
							<a href="{{ route('leagues.edit', $league) }}" class="btn btn-svg btn-secondary icon-edit"  title="{{ __('seasons.leagues_btn_edit_title') }}"></a>
							@if(auth()->check() && auth()->user()->isAdmin())
							<form method="POST" action="{{ route('leagues.destroy', $league) }}">
								@csrf @method('DELETE')
								<button type="submit"
								class="btn-alert btn btn-danger btn-svg icon-delete"
								data-title="{{ __('seasons.leagues_confirm_delete') }}"
								data-text="{{ $league->name }}"
								data-confirm-text="{{ __('seasons.btn_delete') }}"
								data-cancel-text="{{ __('seasons.btn_cancel') }}"
								title="{{ __('seasons.leagues_btn_delete_title') }}"></button>
							</form>
							@endif
						</div>
					</div>
				</div>
			</div>
			@endforeach
		</div>
		@endif
	</div>
</x-voll-layout>
