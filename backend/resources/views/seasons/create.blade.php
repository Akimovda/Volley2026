<x-voll-layout body_class="seasons-page">
	<x-slot name="title">{{ __('seasons.create_title_with', ['league' => $league->name]) }}</x-slot>
	<x-slot name="h1">{{ __('seasons.create_title') }}</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.index') }}" itemprop="item"><span itemprop="name">{{ __('seasons.breadcrumb_my_leagues') }}</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.edit', $league) }}" itemprop="item"><span itemprop="name">{{ $league->name }}</span></a>
			<meta itemprop="position" content="3">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ __('seasons.breadcrumb_new_season') }}</span>
			<meta itemprop="position" content="4">
		</li>
	</x-slot>

	<x-slot name="h2">{{ $league->name }} · {{ $league->direction === 'beach' ? __('seasons.dir_beach') : __('seasons.dir_classic') }}</x-slot>

	<x-slot name="t_description">
		{{ __('seasons.create_t_description') }}
	</x-slot>

	<div class="container form">
		<form action="{{ route('seasons.store', $league) }}" method="POST">
			@csrf
			<div class="ramka">
				<h2 class="-mt-05">{{ __('seasons.section_main') }}</h2>
				<div class="row">
					<div class="col-md-12">
						<div class="card">
							<label>{{ __('seasons.label_name') }}</label>
							<input type="text" name="name" id="name"
								value="{{ old('name') }}"
								placeholder="{{ __('seasons.ph_name') }}"
								required>
							@error('name') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror
						</div>
					</div>
				</div>
			</div>
			<div class="ramka">
				<h2 class="-mt-05">{{ __('seasons.section_dates') }}</h2>
				<div class="row">
					<div class="col-sm-6">
						<div class="card">
							<label>{{ __('seasons.label_start') }}</label>
							<input type="date" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d')) }}">
						</div>
					</div>
					<div class="col-sm-6">
						<div class="card">
							<label>{{ __('seasons.label_end') }}</label>
							<input type="date" name="ends_at" value="{{ old('ends_at') }}">
							<ul class="list f-16 mt-1">
								<li>{{ __('seasons.hint_no_end') }}</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
			<div class="ramka">
				<div class="text-center">
					<button type="submit" class="btn">{{ __('seasons.btn_create_season') }}</button>
				</div>
			</div>
		</form>
	</div>
</x-voll-layout>
