<x-voll-layout body_class="page-419" :isErrorPage="true">
    <x-slot name="title">Сессия истекла</x-slot>
    
    <x-slot name="h1">Упс, сессия истекла!</x-slot>  
    <x-slot name="h2">Ошибка 419</x-slot>
    <x-slot name="t_description">
        Вы слишком долго думали и страница «уснула». Просто обновите её и попробуйте снова!
    </x-slot> 

    <x-slot name="d_description">
        <div class="d-flex flex-wrap gap-1 m-center">
            <div class="mt-2" data-aos-delay="250" data-aos="fade-up">
                <a href="javascript:location.reload()" class="btn">🔄 Обновить страницу</a>
            </div>
            <div class="mt-2" data-aos-delay="350" data-aos="fade-up">
                <a href="javascript:history.back()" class="btn btn-secondary">Назад</a>
            </div>
        </div>
    </x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="#" itemprop="item"><span itemprop="name">Ошибка 419</span></a>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>
    
    <div class="pt-3 pb-3" style="text-align:center">
        <svg viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg" style="max-width:360px;margin:0 auto">
            <!-- Будильник -->
            <circle cx="200" cy="140" r="70" fill="#fff" stroke="#333" stroke-width="3"/>
            <circle cx="200" cy="140" r="62" fill="none" stroke="#e5e7eb" stroke-width="2"/>
            <!-- Циферблат -->
            <line x1="200" y1="140" x2="200" y2="95" stroke="#333" stroke-width="3" stroke-linecap="round"/>
            <line x1="200" y1="140" x2="230" y2="140" stroke="#E7612F" stroke-width="2" stroke-linecap="round"/>
            <!-- Метки часов -->
            <circle cx="200" cy="82" r="3" fill="#333"/>
            <circle cx="258" cy="140" r="3" fill="#333"/>
            <circle cx="200" cy="198" r="3" fill="#333"/>
            <circle cx="142" cy="140" r="3" fill="#333"/>
            <!-- Ножки -->
            <line x1="150" y1="200" x2="135" y2="230" stroke="#333" stroke-width="4" stroke-linecap="round"/>
            <line x1="250" y1="200" x2="265" y2="230" stroke="#333" stroke-width="4" stroke-linecap="round"/>
            <!-- Колокольчики -->
            <circle cx="155" cy="80" r="18" fill="#E7612F" opacity=".8"/>
            <circle cx="245" cy="80" r="18" fill="#E7612F" opacity=".8"/>
            <line x1="155" y1="62" x2="245" y2="62" stroke="#333" stroke-width="3" stroke-linecap="round"/>
            <!-- ZZZ -->
            <text x="290" y="100" font-size="24" font-weight="800" fill="#9ca3af" opacity=".6">Z</text>
            <text x="310" y="80" font-size="18" font-weight="800" fill="#9ca3af" opacity=".4">Z</text>
            <text x="325" y="65" font-size="14" font-weight="800" fill="#9ca3af" opacity=".3">Z</text>
            <!-- Волейбольный мяч спит -->
            <circle cx="100" cy="230" r="22" fill="#fff" stroke="#333" stroke-width="2"/>
            <path d="M 78 230 Q 89 218 100 230 Q 111 242 122 230" fill="none" stroke="#E7612F" stroke-width="1.5"/>
            <!-- Закрытые глазки -->
            <line x1="90" y1="226" x2="96" y2="226" stroke="#333" stroke-width="2" stroke-linecap="round"/>
            <line x1="104" y1="226" x2="110" y2="226" stroke="#333" stroke-width="2" stroke-linecap="round"/>
            <!-- Улыбка -->
            <path d="M 94 234 Q 100 238 106 234" fill="none" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
    </div>

</x-voll-layout>
