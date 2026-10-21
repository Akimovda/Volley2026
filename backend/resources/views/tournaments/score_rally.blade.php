<x-voll-layout body_class="tournament-rally-page">
<x-slot name="title">{{ __('tournaments.rally_title') }} — {{ $event->title }}</x-slot>
<x-slot name="h1">{{ __('tournaments.rally_h1') }}</x-slot>

<x-slot name="breadcrumbs">
	<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		<a href="{{ ($match->stage->type ?? null) === 'friendly' ? route('game.manage', $event) . '?occurrence=' . $match->stage->occurrence_id : route('tournament.setup', $event) . ($match->stage->occurrence_id ? '?occurrence_id=' . $match->stage->occurrence_id : '') }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
		<meta itemprop="position" content="2">
	</li>
	<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		<span itemprop="name">{{ __('tournaments.score_match_n', ['n' => $match->match_number]) }}</span>
		<meta itemprop="position" content="3">
	</li>
</x-slot>

@include('tournaments._partials.rally_screen')
</x-voll-layout>
