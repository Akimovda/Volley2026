<x-voll-layout body_class="tournament-rally-page scorer-page">
<x-slot name="title">{{ __('tournaments.rally_title') }} — {{ $event->title }}</x-slot>
<x-slot name="h1">{{ __('tournaments.rally_h1') }}</x-slot>

<div class="container pb-0">
	<div class="ramka">
		@include('scorer._header')
	</div>
</div>

@include('tournaments._partials.rally_screen')
</x-voll-layout>
