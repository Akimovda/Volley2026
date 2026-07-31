{{-- resources/views/components/menu-icon.blade.php --}}
{{-- Единая точка иконок для пунктов меню (раньше — эмодзи прямо в тексте,
     непоследовательно рендерятся на разных платформах/шрифтах). Один SVG-набор,
     используется и в шапке (voll-layout.blade.php), и в сайдбаре
     (profile/_menu.blade.php) — <x-menu-icon name="court" />. --}}
@props(['name'])
@php
$icons = [
    // Брони кортов
    'court' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"></rect><line x1="12" y1="5" x2="12" y2="19"></line><line x1="3" y1="12" x2="21" y2="12"></line></svg>',
    // Создать мероприятие
    'calendar-plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><line x1="12" y1="14" x2="12" y2="18"></line><line x1="10" y1="16" x2="14" y2="16"></line></svg>',
    // Абонементы
    'id-card' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><circle cx="8" cy="12" r="2"></circle><line x1="13" y1="10" x2="19" y2="10"></line><line x1="13" y1="14" x2="19" y2="14"></line></svg>',
    // Купоны
    'ticket' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"></path><line x1="10" y1="6.5" x2="10" y2="8.5"></line><line x1="10" y1="11.5" x2="10" y2="13.5"></line><line x1="10" y1="15.5" x2="10" y2="17.5"></line></svg>',
    // Каналы уведомлений
    'megaphone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10v4a1 1 0 0 0 1 1h2l10 5V4L6 9H4a1 1 0 0 0-1 1z"></path><path d="M18 9a4 4 0 0 1 0 6"></path></svg>',
    // Мои лиги и сезоны
    'trophy' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4h8v5a4 4 0 0 1-8 0z"></path><path d="M8 5H4v2a3 3 0 0 0 3 3"></path><path d="M16 5h4v2a3 3 0 0 1-3 3"></path><line x1="12" y1="13" x2="12" y2="17"></line><line x1="9" y1="20" x2="15" y2="20"></line><line x1="12" y1="17" x2="12" y2="20"></line></svg>',
    // Виджет на сайт
    'globe' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><line x1="3" y1="12" x2="21" y2="12"></line><path d="M12 3a15 15 0 0 1 0 18"></path><path d="M12 3a15 15 0 0 0 0 18"></path></svg>',
    // Организатор Pro
    'star' => '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z"></path></svg>',
    // Адрес/локация (замена 📍 в карточках) — тот же пин, что в топбаре /events
    'pin' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s7-7.58 7-12A7 7 0 1 0 5 10c0 4.42 7 12 7 12z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>',
    // Организатор (замена 🎪 в карточках) — силуэт человека + бейдж-звезда
    'organizer' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"></circle><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 5 2.7"></path><g transform="translate(13,11) scale(0.42)" fill="currentColor" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z"></path></g></svg>',
];
$svg = $icons[$name] ?? '';
@endphp
@if($svg)
<span {{ $attributes->merge(['class' => "menu-icon menu-icon-{$name}"]) }} aria-hidden="true">{!! $svg !!}</span>
@endif
