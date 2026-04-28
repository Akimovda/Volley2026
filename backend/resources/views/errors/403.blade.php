<x-voll-layout body_class="page-403" :isErrorPage="true">
    <x-slot name="title">
        Доступ запрещён
    </x-slot>
    
    <x-slot name="h1">
        Ой, похоже Вам сюда нельзя!
    </x-slot>  
    <x-slot name="h2">
        Ошибка 403
    </x-slot>
    <x-slot name="t_description">
        У вас нет доступа к этой странице. Возможно, она доступна только организаторам.
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

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="#" itemprop="item"><span itemprop="name">Ошибка 403</span></a>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>
    

</x-voll-layout>
