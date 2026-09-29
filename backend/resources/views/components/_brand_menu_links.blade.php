{{-- Свои ссылки меню бренда (Настройка App → Меню). $place: site | user --}}
@php
    $__mb = app(\App\Models\Brand::class);
    $__en = app()->getLocale() === 'en';
@endphp
@foreach($__mb->menuLinks($place) as $__l)
    <a href="{{ $__l['url'] }}" class="menu-item"@if(!empty($__l['new_tab'])) target="_blank" rel="noopener noreferrer"@endif>
        <span class="menu-text">{{ ($__en && !empty($__l['title_en'])) ? $__l['title_en'] : $__l['title_ru'] }}</span>
    </a>
@endforeach
