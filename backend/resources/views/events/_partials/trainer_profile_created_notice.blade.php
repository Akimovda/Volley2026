@if(session('trainer_profile_created'))
<div class="ramka">
	<div class="alert alert-success">
		{{ __('trainers.profile_auto_created') }}
		<a href="{{ url('/profile/complete?section=trainer#trainer') }}" class="b-600">{{ __('trainers.profile_auto_created_link') }}</a>
	</div>
</div>
@endif
