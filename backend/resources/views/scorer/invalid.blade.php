<x-voll-layout body_class="scorer-page">
<x-slot name="title">{{ __('broadcast.scorer_invalid_title') }}</x-slot>
<x-slot name="h1">{{ __('broadcast.scorer_invalid_title') }}</x-slot>
<div class="container">
	<div class="ramka">
		<div class="card p-3 text-center">
			<div class="b-700 f-20 mb-1">⛔ {{ __('broadcast.scorer_invalid_title') }}</div>
			<div class="f-14" style="opacity:.8">
				{{ $reason === 'other_user' ? __('broadcast.scorer_other_user') : __('broadcast.scorer_invalid_text') }}
			</div>
		</div>
	</div>
</div>
</x-voll-layout>
