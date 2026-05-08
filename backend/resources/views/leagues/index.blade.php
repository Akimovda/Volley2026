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
		<div class="ramka">
			@if($leagues->isEmpty())
				<div class="alert alert-info">
					<p><strong>{{ __('seasons.leagues_empty_lead') }}</strong></p>
					<p>{{ __('seasons.leagues_empty_text') }}</p>
				</div>
			@else
				<div class="row">
					@foreach($leagues as $league)
						<div class="col-md-6 col-lg-4">
							<div class="card">
								<div class="text-center mb-1">
								@if($league->logo_url)
									<div class="mb-1">
										<img src="{{ $league->logo_url }}" alt="{{ $league->name }}" style="width:60px;height:60px;border-radius:10px;object-fit:cover">
									</div>
								@endif
								<div class="b-600">{{ $league->name }}</div>
							</div>
								<div class="d-flex mb-1" style="justify-content:center">
									@php
										$statusColors = [
											'active' => ['bg' => 'rgba(16,185,129,.15)', 'color' => '#10b981', 'label' => __('seasons.leagues_status_active')],
											'archived' => ['bg' => 'rgba(128,128,128,.15)', 'color' => '#6b7280', 'label' => __('seasons.leagues_status_archived')],
										];
										$st = $statusColors[$league->status] ?? $statusColors['active'];
									@endphp
									<span class="f-13 b-600" style="background:{{ $st['bg'] }}; padding: 0.4rem 1rem; border-radius:1rem;color:{{ $st['color'] }}">
										{{ $st['label'] }}
									</span>
								</div>

								<div class="f-16 mb-1 text-center">
									{{ $league->direction === 'beach' ? __('seasons.leagues_dir_beach') : __('seasons.leagues_dir_classic') }}
								</div>

								@if($league->description)
									<div class="f-16 mb-1 cd">{{ Str::limit($league->description, 80) }}</div>
								@endif

								<div class="f-16 mb-1 cd">
									{{ __('seasons.leagues_seasons_label') }} {{ $league->seasons->count() }}
								</div>

								@if(auth()->user()->isAdmin() && $league->organizer)
									<div class="f-13 mb-1 cd">
										{{ __('seasons.leagues_organizer_label') }} <a href="{{ route('users.show', $league->organizer->id) }}" class="blink">{{ $league->organizer->first_name }} {{ $league->organizer->last_name }}</a>
									</div>
								@endif

								<div class="d-flex mt-auto" style="gap:8px;margin-top:auto;justify-content:center">
									<a href="{{ route('leagues.edit', $league) }}" class="btn btn-primary f-13" style="padding:6px 14px" title="{{ __('seasons.leagues_btn_edit_title') }}">⚙️</a>
									<a href="{{ route('leagues.show.slug', $league->slug) }}" class="btn btn-secondary f-13" style="padding:6px 14px" title="{{ __('seasons.leagues_btn_public_title') }}">🔗</a>
									@if(auth()->check() && auth()->user()->isAdmin())
									<form method="POST" action="{{ route('leagues.destroy', $league) }}">
										@csrf @method('DELETE')
										<button type="submit"
											class="btn-alert btn btn-danger f-13"
											style="padding:6px 14px"
											data-title="{{ __('seasons.leagues_confirm_delete') }}"
											data-text="{{ $league->name }}"
											data-confirm-text="{{ __('seasons.btn_delete') }}"
											data-cancel-text="{{ __('seasons.btn_cancel') }}"
											title="{{ __('seasons.leagues_btn_delete_title') }}">🗑</button>
									</form>
									@endif
								</div>
							</div>
						</div>
					@endforeach
				</div>
			@endif
		</div>
	</div>
</x-voll-layout>
