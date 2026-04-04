{{-- body_class - класс для body --}}
<x-voll-layout body_class="broadcasts-create-page">
    
    <x-slot name="title">
        Новая рассылка
    </x-slot>
    
    <x-slot name="description">
        Создание новой рассылки
    </x-slot>
    
    <x-slot name="canonical">
        {{ route('admin.broadcasts.create') }}
    </x-slot>
    
    <x-slot name="style">
        <style>
            /* Дополнительные стили при необходимости */
        </style>
    </x-slot>
    
    <x-slot name="h1">
        Новая рассылка
    </x-slot>
    
    
    <x-slot name="t_description">
        Заполните форму для создания новой рассылки
    </x-slot>
    
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админ-панель</span></a>
            <meta itemprop="position" content="2">
		</li>	
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.broadcasts.index') }}" itemprop="item">
                <span itemprop="name">Рассылки</span>
            </a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">Новая рассылка</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>
    
    
    <x-slot name="script">
        <script>
            // Дополнительные скрипты при необходимости
        </script>
    </x-slot>
    
    <div class="container">
                <form id="broadcast-form" method="POST" action="{{ route('admin.broadcasts.store') }}" class="form">
                    @csrf

                    @include('admin.broadcasts.partials.form', [
                        'broadcast' => $broadcast,
                        'channelOptions' => $channelOptions,
                        'statusOptions' => $statusOptions,
                        'channels' => [],
                        'filters' => [],
                    ])

                    <div class="ramka text-center">
                        <button type="submit" name="action" value="save" class="btn mr-1">
                            Сохранить
                        </button>

                        <button type="submit" name="action" value="save_and_launch" class="btn">
                            Сохранить и запустить
                        </button>
                    </div>
                </form>
        </div>

</x-voll-layout>