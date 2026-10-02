{{-- resources/views/pages/level_players.blade.php --}}
<x-voll-layout body_class="level">
	<x-slot name="title">
		{{ __('pages.lp_title') }}
	</x-slot>

    <x-slot name="description">
		{{ __('pages.lp_description') }}
	</x-slot>

    <x-slot name="t_description">
		{!! __('pages.lp_t_description') !!}
	</x-slot>

    <x-slot name="canonical">
        {{ route('level_players') }}
	</x-slot>

    <x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('level_players') }}" itemprop="item"><span itemprop="name">{{ __('pages.lp_breadcrumb') }}</span></a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>
    <x-slot name="h1">
        {{ __('pages.lp_title') }}
	</x-slot>

	<x-slot name="image">
		<div class="top-section-img" data-aos="fade" data-aos-duration="1000">
			<div class="top-section-light-img">
				<img src="/img/level-light.png" alt="img">
			</div>
			<div class="top-section-dark-img">
				<img src="/img/level-dark.png" alt="img">
			</div>
		</div>
	</x-slot>

    @php
        // Питерская терминология уровней — по городу зрителя (гость → стандарт)
        $levelScope = $scope ?? level_terminology_scope_for_user(auth()->user());
    @endphp

    <div class="container">
        <div class="ramka">
			@include('pages._level_players_body', ['levelScope' => $levelScope])
		</div>
	</div>
</x-voll-layout>
