
<x-voll-layout body_class="page-404" :isErrorPage="true">
    {{-- =========================
	ЗАГОЛОВКИ И МЕТАДАННЫЕ
    ========================== --}}
    <x-slot name="title">
        Страница не найдена
	</x-slot>
    
    <x-slot name="h1">
		Страница не найдена
	</x-slot>  
    <x-slot name="h2">
		Ошибка 404
	</x-slot>
	<x-slot name="t_description">
		К сожалению, запрашиваемая страница не существует.
	</x-slot> 
	
	<x-slot name="d_description">
	 <div class="d-flex flex-wrap gap-1 m-center">
		<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
			<a href="{{ url('/') }}" class="btn">На главную</a>
		</div>
		<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
			<a href="javascript:history.back()" class="btn btn-secondary">Назад</a>
		</div>
	</div>	
	</x-slot>	
	
    {{-- Крошки --}}
    <x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="#" itemprop="item"><span itemprop="name">Ошибка 404</span></a>
			<meta itemprop="position" content="2">
		</li>			
	</x-slot>
    
    {{-- =========================
	ОСНОВНОЙ КОНТЕНТ
    ========================== --}}
<div class="pt-3 pb-3"></div>
	
</x-voll-layout>	