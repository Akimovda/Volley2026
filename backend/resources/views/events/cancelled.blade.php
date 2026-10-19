<x-voll-layout body_class="event-cancelled-page">
<x-slot name="title">{{ __('events.cancelled_page_title') }} — {{ $event->title }}</x-slot>
<x-slot name="h1">{{ __('events.cancelled_page_title') }}</x-slot>
<x-slot name="breadcrumbs">
	<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		<a href="{{ route('events.index') }}" itemprop="item"><span itemprop="name">{{ __('events.cancelled_page_list') }}</span></a>
		<meta itemprop="position" content="2">
	</li>
</x-slot>

@php
	$cTz = $occurrence->timezone ?: ($event->timezone ?: 'Europe/Moscow');
	$cWhen = $occurrence->starts_at
		? \Illuminate\Support\Carbon::parse($occurrence->starts_at, 'UTC')->setTimezone($cTz)->format('d.m.Y H:i')
		: null;
@endphp

<div class="container">
	<div class="ramka">
		<div class="alert alert-danger mb-2">
			❌ {{ __('events.cancelled_page_text') }}
		</div>
		<div class="card mb-2" style="height:auto">
			<div class="b-700 f-18 mb-1">{{ $event->title }}</div>
			@if($cWhen)
			<div class="f-14">{{ $cWhen }}</div>
			@endif
		</div>
		<div class="d-flex gap-1" style="flex-wrap:wrap">
			@if($hasOther)
			<a href="{{ route('events.show', $event) }}" class="btn btn-primary">{{ __('events.cancelled_page_other') }}</a>
			@endif
			<a href="{{ route('events.index') }}" class="btn btn-secondary">{{ __('events.cancelled_page_list') }}</a>
		</div>
	</div>
</div>
</x-voll-layout>
