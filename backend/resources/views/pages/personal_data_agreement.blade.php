{{-- resources/views/pages/personal_data_agreement.blade.php --}}
<x-voll-layout body_class="politic">
	<x-slot name="title">
        {{ __('pages.pda_title') }}
	</x-slot>

    <x-slot name="description">
		{{ __('pages.pda_description') }}
	</x-slot>

    <x-slot name="t_description">
		{{ __('pages.pda_description') }}
	</x-slot>

    <x-slot name="canonical">
        {{ route('personal_data_agreement') }}
	</x-slot>

    <x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('personal_data_agreement') }}" itemprop="item"><span itemprop="name">{{ __('pages.pda_breadcrumb') }}</span></a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>
    <x-slot name="h1">
        {{ __('pages.pda_title') }}
	</x-slot>

    <div class="container">
        <div class="ramka">
			<div class="alert alert-info mb-3">
				{{ __('pages.pda_warning') }}
			</div>

			<div class="title-h h3">{{ __('pages.pda_h_1') }}</div>
			<p>{{ __('pages.pda_p_1') }}</p>

			<div class="title-h h3">{{ __('pages.pda_h_2') }}</div>
			<ul class="list">
				<li>{{ __('pages.pda_2_1') }}</li>
				<li>{{ __('pages.pda_2_2') }}</li>
				<li>{{ __('pages.pda_2_3') }}</li>
				<li>{{ __('pages.pda_2_4') }}</li>
				<li>{{ __('pages.pda_2_5') }}</li>
			</ul>

			<div class="title-h h3">{{ __('pages.pda_h_3') }}</div>
			<ul class="list">
				<li>{{ __('pages.pda_3_1') }}</li>
				<li>{{ __('pages.pda_3_2') }}</li>
				<li>{{ __('pages.pda_3_3') }}</li>
				<li>{{ __('pages.pda_3_4') }}</li>
				<li>{{ __('pages.pda_3_5') }}</li>
			</ul>

			<div class="title-h h3">{{ __('pages.pda_h_4') }}</div>
			<p>{{ __('pages.pda_p_4') }}</p>

			<div class="title-h h3">{{ __('pages.pda_h_5') }}</div>
			<p>{{ __('pages.pda_p_5') }}</p>

			<div class="title-h h3">{{ __('pages.pda_h_6') }}</div>
			<p>{{ __('pages.pda_p_6') }}</p>

			<div class="title-h h3">{{ __('pages.pda_h_7') }}</div>
			<p>{{ __('pages.pda_p_7') }}</p>

			<div class="title-h h3">{{ __('pages.pda_h_8') }}</div>
			<p>{{ __('pages.pda_p_8') }}</p>

		</div>
	</div>
</x-voll-layout>
