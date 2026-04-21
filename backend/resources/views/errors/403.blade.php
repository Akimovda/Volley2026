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
    
    <div class="pt-3 pb-3" style="text-align:center">
        <svg viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg" style="max-width:360px;margin:0 auto">
            <!-- Забор -->
            <rect x="40" y="120" width="20" height="140" rx="3" fill="#d4a574"/>
            <rect x="100" y="120" width="20" height="140" rx="3" fill="#d4a574"/>
            <rect x="160" y="120" width="20" height="140" rx="3" fill="#d4a574"/>
            <rect x="220" y="120" width="20" height="140" rx="3" fill="#d4a574"/>
            <rect x="280" y="120" width="20" height="140" rx="3" fill="#d4a574"/>
            <rect x="340" y="120" width="20" height="140" rx="3" fill="#d4a574"/>
            <!-- Перекладины -->
            <rect x="30" y="150" width="340" height="12" rx="3" fill="#c49a6c"/>
            <rect x="30" y="210" width="340" height="12" rx="3" fill="#c49a6c"/>
            <!-- Табличка -->
            <rect x="120" y="130" width="160" height="70" rx="8" fill="#fff" stroke="#E7612F" stroke-width="3"/>
            <text x="200" y="158" text-anchor="middle" font-size="16" font-weight="800" fill="#E7612F">СТОП!</text>
            <text x="200" y="178" text-anchor="middle" font-size="11" fill="#6b7280">Только для</text>
            <text x="200" y="193" text-anchor="middle" font-size="11" fill="#6b7280">организаторов 🏐</text>
            <!-- Волейбольный мяч пытается перелезть -->
            <circle cx="330" cy="105" r="28" fill="#fff" stroke="#333" stroke-width="2"/>
            <path d="M 302 105 Q 316 90 330 105 Q 344 120 358 105" fill="none" stroke="#E7612F" stroke-width="2"/>
            <path d="M 330 77 Q 345 91 330 105 Q 315 91 330 77" fill="none" stroke="#E7612F" stroke-width="2"/>
            <!-- Глазки на мяче -->
            <circle cx="322" cy="98" r="3" fill="#333"/>
            <circle cx="338" cy="98" r="3" fill="#333"/>
            <!-- Капелька пота -->
            <ellipse cx="350" cy="88" rx="3" ry="5" fill="rgba(41,103,186,.3)"/>
            <!-- Ручки мяча (цепляется за забор) -->
            <line x1="310" y1="110" x2="300" y2="120" stroke="#333" stroke-width="2" stroke-linecap="round"/>
            <line x1="350" y1="110" x2="355" y2="120" stroke="#333" stroke-width="2" stroke-linecap="round"/>
            <!-- Трава -->
            <rect x="0" y="260" width="400" height="40" rx="0" fill="#86efac" opacity=".3"/>
        </svg>
    </div>

</x-voll-layout>
