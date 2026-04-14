{{-- resources/views/pages/rules.blade.php --}}
<x-voll-layout body_class="rules">
	<x-slot name="title">
		Правила сервиса
	</x-slot>
	
    <x-slot name="description">
		Основные правила пользования сервисом
	</x-slot>
	
    <x-slot name="t_description">
		Основные правила пользования сервисом
	</x-slot>
	
    <x-slot name="canonical">
        {{ route('rules') }}
	</x-slot>
	
    <x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('rules') }}" itemprop="item"><span itemprop="name">Правила сервиса</span></a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>	
    <x-slot name="h1">
        Правила сервиса
	</x-slot>	
	
    <div class="container">
        <div class="ramka">
			<p>Содержание правил сервиса...</p>
		</div>
	</div>
</x-voll-layout>