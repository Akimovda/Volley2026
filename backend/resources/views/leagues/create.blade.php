<x-voll-layout body_class="leagues-page">
	<x-slot name="title">{{ __('seasons.leagues_create_title') }}</x-slot>
	<x-slot name="h1">{{ __('seasons.leagues_create_h1') }}</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.index') }}" itemprop="item"><span itemprop="name">{{ __('seasons.leagues_idx_breadcrumb') }}</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ __('seasons.leagues_breadcrumb_create') }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>

	<x-slot name="t_description">
		{{ __('seasons.leagues_create_t_description') }}
	</x-slot>

	<div class="container form">
		<form action="{{ route('leagues.store') }}" method="POST" enctype="multipart/form-data">
			@csrf

			<div class="ramka">
				<h2 class="-mt-05">{{ __('seasons.leagues_section_main') }}</h2>
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<label>{{ __('seasons.leagues_label_name') }}</label>
							<input type="text" name="name" id="name"
								value="{{ old('name') }}"
								placeholder="{{ __('seasons.leagues_ph_name') }}"
								required>
							@error('name') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<label>{{ __('seasons.leagues_label_direction') }}</label>
							<select name="direction" id="direction">
								<option value="beach" {{ old('direction', 'beach') === 'beach' ? 'selected' : '' }}>{{ __('seasons.leagues_opt_beach') }}</option>
								<option value="classic" {{ old('direction') === 'classic' ? 'selected' : '' }}>{{ __('seasons.leagues_opt_classic') }}</option>
							</select>
						</div>
					</div>
				</div>

				@if(auth()->user()->isAdmin() && $organizers->isNotEmpty())
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<label>{{ __('seasons.leagues_label_organizer') }}</label>
							<select name="organizer_id">
								<option value="">{{ __('seasons.leagues_opt_self_organizer', ['name' => auth()->user()->first_name . ' ' . auth()->user()->last_name]) }}</option>
								@foreach($organizers as $org)
									<option value="{{ $org->id }}" {{ old('organizer_id') == $org->id ? 'selected' : '' }}>
										{{ $org->first_name }} {{ $org->last_name }} ({{ $org->role }})
									</option>
								@endforeach
							</select>
						</div>
					</div>
				</div>
				@endif
			</div>

			<div class="ramka">
				<h2 class="-mt-05">{{ __('seasons.leagues_section_description') }}</h2>
				<div class="card">
					<label>{{ __('seasons.leagues_label_description') }}</label>
					<textarea name="description" rows="3" placeholder="{{ __('seasons.leagues_ph_description') }}">{{ old('description') }}</textarea>
				</div>
			</div>

			<div class="ramka">
				<h2 class="-mt-05">{{ __('seasons.leagues_section_socials') }}</h2>
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<label>VK</label>
							<input type="text" name="vk" value="{{ old('vk') }}" placeholder="https://vk.com/your_group">
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<label>{{ __('seasons.leagues_label_tg') }}</label>
							<input type="text" name="telegram" value="{{ old('telegram') }}" placeholder="https://t.me/your_channel">
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<label>MAX</label>
							<input type="text" name="max_messenger" value="{{ old('max_messenger') }}" placeholder="{{ __('seasons.leagues_ph_max') }}">
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<label>{{ __('seasons.leagues_label_website') }}</label>
							<input type="text" name="website" value="{{ old('website') }}" placeholder="https://example.com">
							@error('website') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<label>{{ __('seasons.leagues_label_phone') }}</label>
							<input type="text" name="phone" value="{{ old('phone') }}" placeholder="+7 999 123-45-67">
						</div>
					</div>
				</div>
			</div>

			<div class="ramka">
				<h2 class="-mt-05">{{ __('seasons.leagues_section_logo') }}</h2>
				<div class="card">
					<label>{{ __('seasons.leagues_label_logo') }}</label>
					<input type="file" name="logo" accept="image/*">
					@error('logo') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror
					<div class="f-13 cd mt-1">{{ __('seasons.leagues_logo_hint') }}</div>
				</div>
			</div>

			<div class="ramka">
				<div class="text-center">
					<button type="submit" class="btn">{{ __('seasons.leagues_btn_create') }}</button>
				</div>
			</div>
		</form>
	</div>
</x-voll-layout>
