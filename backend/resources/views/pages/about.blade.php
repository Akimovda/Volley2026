{{-- resources/views/pages/about.blade.php --}}
<x-voll-layout body_class="about">
	<x-slot name="title">
		О сервисе
	</x-slot>
	
    <x-slot name="description">
		О проекте, команде и целях сервиса
	</x-slot>
	
    <x-slot name="t_description">
		О проекте, команде и целях сервиса
	</x-slot>
	
    <x-slot name="canonical">
        {{ route('about') }}
	</x-slot>
	
    <x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('about') }}" itemprop="item"><span itemprop="name">О сервисе</span></a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>	
    <x-slot name="h1">
        О сервисе
	</x-slot>	
	
    <div class="container">
        <div class="ramka">
			<p>Информация о сервисе...</p>
		</div>
	</div>
</x-voll-layout>