{{-- body_class - класс для body --}}
<x-voll-layout body_class="broadcasts-edit-page">
    
    <x-slot name="title">
        {{ __('admin.bc_edit_title') }}
    </x-slot>
    
    <x-slot name="description">
        {{ __('admin.bc_edit_title') }} {{ $broadcast->name }}
    </x-slot>
    
    <x-slot name="canonical">
        {{ route('admin.broadcasts.edit', $broadcast->id) }}
    </x-slot>
    
    <x-slot name="style">
        <style>
            /* Дополнительные стили при необходимости */
        </style>
    </x-slot>
    
    <x-slot name="h1">
        {{ __('admin.bc_edit_title') }}
    </x-slot>

    <x-slot name="t_description">
        <div class="f-22 b-600">
			ID: <span class="cd">{{ $broadcast->id }}</span>
		</div>		
        <div class="f-22 b-600">
			{{ __('admin.bc_label_name_label') }} <span class="cd">{{ $broadcast->name }}</span>
		</div>	
	</x-slot>


    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">{{ __('admin.breadcrumb_dashboard') }}</span></a>
            <meta itemprop="position" content="2">
		</li>		
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.broadcasts.index') }}" itemprop="item">
                <span itemprop="name">{{ __('admin.bc_breadcrumb') }}</span>
            </a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('admin.bc_breadcrumb_edit') }}</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>
    

    
    <x-slot name="script">
        <script>
            // Дополнительные скрипты при необходимости
        </script>
    </x-slot>
    
    <div class="container">
        <div class="row">
            <div class="col-12">
                @if(session('status'))
                    <div class="alert alert-success mb-4">
                        {{ session('status') }}
                    </div>
                @endif
                
                <form id="broadcast-form" method="POST" action="{{ route('admin.broadcasts.update', $broadcast->id) }}" class="form">
                    @csrf
                    @method('PATCH')

                    @include('admin.broadcasts.partials.form', [
                        'broadcast' => $broadcast,
                        'channelOptions' => $channelOptions,
                        'statusOptions' => $statusOptions,
                        'channels' => $channels ?? [],
                        'filters' => $filters ?? [],
                    ])

                    <div class="ramka text-center">
                        <button type="submit" name="action" value="save" class="btn mr-1">
                            Сохранить изменения
                        </button>

                        <button type="submit" name="action" value="save_and_launch" class="btn">
                            Сохранить и запустить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
</x-voll-layout>