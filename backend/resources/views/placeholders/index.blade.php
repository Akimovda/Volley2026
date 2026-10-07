<x-voll-layout body_class="placeholders-page">
	<x-slot name="title">{{ __('placeholders.title') }}</x-slot>
	<x-slot name="h1">{{ __('placeholders.title') }}</x-slot>

	<div class="container">
		@if(session('status'))<div class="alert alert-success mb-2">{{ session('status') }}</div>@endif
		@if(session('error'))<div class="alert alert-danger mb-2">{{ session('error') }}</div>@endif

		<div class="ramka mb-2">
			<p class="mb-0">{{ __('placeholders.intro') }}</p>
		</div>

		<div class="ramka mb-2">
			<h2 class="-mt-05">{{ __('placeholders.create_h') }}</h2>
			<form method="POST" action="{{ route('placeholders.store') }}" class="form">
				@csrf
				<div class="row">
					<div class="col-md-6 mb-2">
						<label>{{ __('placeholders.last_name') }}</label>
						<input type="text" name="last_name" value="{{ old('last_name') }}" required>
						@error('last_name')<div class="text-danger f-14">{{ $message }}</div>@enderror
					</div>
					<div class="col-md-6 mb-2">
						<label>{{ __('placeholders.first_name') }}</label>
						<input type="text" name="first_name" value="{{ old('first_name') }}" required>
						@error('first_name')<div class="text-danger f-14">{{ $message }}</div>@enderror
					</div>
					<div class="col-md-6 mb-2">
						<label>{{ __('placeholders.gender') }}</label>
						<select name="gender" required>
							<option value="m" @selected(old('gender') === 'm')>{{ __('placeholders.gender_m') }}</option>
							<option value="f" @selected(old('gender') === 'f')>{{ __('placeholders.gender_f') }}</option>
						</select>
					</div>
					<div class="col-md-6 mb-2">
						<label>{{ __('placeholders.phone') }}</label>
						<input type="tel" name="phone" value="{{ old('phone') }}" placeholder="+7">
						<div class="f-13" style="opacity:.7;">{{ __('placeholders.phone_hint') }}</div>
						@error('phone')<div class="text-danger f-14">{{ $message }}</div>@enderror
					</div>
				</div>
				<button type="submit" class="btn btn-small">{{ __('placeholders.btn_create') }}</button>
			</form>
		</div>

		<div class="ramka">
			<h2 class="-mt-05">{{ __('placeholders.list_h') }}</h2>
			@forelse($items as $u)
				<div class="card mb-2" style="height:auto;">
					<div class="d-flex between gap-1 fvc card-actions-row">
						<div class="card-actions-info">
							<b>{{ trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) ?: '#' . $u->id }}</b>
							<span class="f-13" style="opacity:.7;">· 👻 {{ __('placeholders.badge') }} · ID {{ $u->id }}</span>
							@if($isAdmin && $u->created_by_user_id)
								<div class="f-13" style="opacity:.7;">{{ __('placeholders.creator') }}: #{{ $u->created_by_user_id }}</div>
							@endif
							@if($u->phone)<div class="f-14">{{ $u->phone }}</div>@endif
						</div>
						<div class="d-flex flex-column gap-1 card-actions-buttons card-actions-buttons--stack">
							<a href="{{ route('profile.complete', ['user_id' => $u->id]) }}" class="btn btn-outline-primary btn-sm">{{ __('placeholders.btn_edit') }}</a>
							<a href="{{ route('placeholders.merge', $u) }}" class="btn btn-outline-primary btn-sm">{{ __('placeholders.btn_merge') }}</a>
							@if(!($history[$u->id] ?? false))
								<form method="POST" action="{{ route('placeholders.destroy', $u) }}" onsubmit="return confirm(@js(__('placeholders.confirm_delete')))">
									@csrf @method('DELETE')
									<button type="submit" class="btn btn-outline-danger btn-sm">{{ __('placeholders.btn_delete') }}</button>
								</form>
							@else
								<span class="f-13" style="opacity:.7;max-width:22rem;">{{ __('placeholders.has_history') }}</span>
							@endif
						</div>
					</div>
				</div>
			@empty
				<p class="mb-0">{{ __('placeholders.empty') }}</p>
			@endforelse
		</div>
	</div>
</x-voll-layout>
