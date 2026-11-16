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
    // Дата (замена 📅 на странице мероприятия)
    'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
    // Время (замена ⏰ на странице мероприятия)
    'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 16 14"></polyline></svg>',
    // Длительность (замена ⏱️ на странице мероприятия)
    'stopwatch' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="2" x2="14" y2="2"></line><line x1="12" y1="2" x2="12" y2="6"></line><circle cx="12" cy="14" r="8"></circle><polyline points="12 10 12 14 15 16"></polyline></svg>',
    // Телефон (замена 📞 на странице мероприятия)
    'phone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>',
    // Приватное мероприятие (замена 🙈 перед названием на карточке) — перечёркнутый глаз
    'eye-off' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>',
    // Волейбольный мяч (замена 🏐 — ссылка на школу на странице мероприятия)
    'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="20" y2="20"></line><rect x="5" y="12" width="3" height="8" rx="0.5"></rect><rect x="10.5" y="5" width="3" height="15" rx="0.5"></rect><rect x="16" y="9" width="3" height="11" rx="0.5"></rect></svg>',
    'volleyball' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.5 1L12.25 1L13.55 1.1L14.6 1.3L15.75 1.65L16.9 2.15L17.75 2.65L19.3 3.75L20.15 4.6L20.7 5.3L21.35 6.25L21.9 7.2L22.3 8.15L22.65 9.2L22.9 10.4L23 11.35L23 12.5L22.9 13.55L22.7 14.6L22.45 15.4L22.1 16.35L21.7 17.2L21.1 18.2L20.5 19L19.7 19.85L19 20.5L18 21.2L17.2 21.7L16.25 22.15L15.45 22.45L14.15 22.8L13.1 22.95L12 23L10.9 22.95L9.85 22.8L8.7 22.5L7.65 22.1L6.8 21.7L5.8 21.1L5 20.5L4.15 19.7L3.45 18.95L2.7 17.9L2.2 17L1.7 15.85L1.35 14.8L1.1 13.6L1 12.65L1 11.45L1.1 10.4L1.3 9.4L1.65 8.3L2.05 7.3L2.5 6.45L3.15 5.5L3.8 4.65L4.65 3.8L5.45 3.15L6.25 2.65L7.2 2.1L8.25 1.65L9.4 1.3L10.45 1.1ZM12.35 12.8L13.35 12.85L14.6 13.1L15.85 13.55L17 14.15L16.2 14.95L15.25 15.7L14.35 16.4L13.3 17.05L11.55 17.95L9.6 18.6L7.6 19.05L5.9 19.2L5 18.35L4.2 17.4L4.25 17.35L5.55 17.15L6.5 16.9L7.75 16.45L8.75 15.95L9.75 15.35L10.7 14.6L11.75 13.55ZM18.25 15.05L18.35 15.1L19.35 16.1L19.9 16.8L20 17.05L19.3 18L18.35 19L17.55 19.65L16.5 20.35L15.4 20.85L14.35 21.2L13.35 21.35L12 21.45L11.05 21.45L10.05 21.25L8.85 20.95L8 20.55L9.3 20.3L10.7 19.9L12.2 19.3L13.45 18.7L14.85 17.9L16 17.1L17.2 16.1ZM11.35 7.15L12.25 7.2L13.65 7.4L15 7.75L16.2 8.15L17.45 8.7L18.4 9.25L19.55 10.05L20.35 10.75L21.45 11.85L21.4 12.95L21.3 13.7L21.15 14.55L20.8 15.45L20 14.5L19 13.65L18 12.95L16.9 12.3L15.9 11.9L14.65 11.5L13.55 11.3L12.45 11.25L12 10.35L11.6 9.2L11.4 8.25ZM10.6 2.65L10.8 2.65L10.25 4.1L9.9 5.7L9.8 6.85L9.85 7.9L10 9.05L10.25 9.95L10.65 11.05L11.05 11.95L10.55 12.55L9.7 13.4L9 14L8.05 14.6L7.75 13.8L7.4 12.6L7.15 11.5L7 10.45L6.95 9.45L6.95 8.1L7.05 7.05L7.25 5.95L7.55 4.75L8 3.45L9.45 2.85ZM12.5 2.6L12.8 2.55L13.7 2.7L15.15 3.05L16.5 3.65L17.75 4.5L18.9 5.55L19.7 6.45L20.55 7.9L21.05 9.2L21 9.25L19.8 8.35L18.6 7.6L17.55 7.05L16.25 6.5L15.1 6.15L13.85 5.85L12.45 5.65L11.5 5.6L11.75 4.4ZM5.85 4.8L5.95 4.8L5.65 6.25L5.45 7.9L5.45 10.05L5.55 11.4L5.8 12.65L6.15 13.95L6.65 15.2L6.15 15.4L5.1 15.65L3.4 15.85L3.15 15.4L2.85 14.55L2.6 13L2.55 11.6L2.75 10.05L3.15 8.6L3.85 7.15L4.7 6Z"/></svg>',
    // Тренер (замена 👨‍🏫 на странице мероприятия)
    'coach' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z"></path><path d="M6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"></path></svg>',
    // Направление (замена ⚔️ в «Сводке события» на странице мероприятия)
    'direction' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>',
    // Игроки/состав (замена 👥 в «Сводке события» на странице мероприятия)
    'players' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
    // Уровень (замена 📈 в «Сводке события» на странице мероприятия)
    'level' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>',
    // Оплата (замена 💵 в «Сводке события» на странице мероприятия)
    'money' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="3"></circle></svg>',
    // Ограничения (замена 🚧 в «Сводке события» на странице мероприятия)
    'warning' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
    // Поделиться (замена 🤝 на кнопке "Поделиться" на странице мероприятия)
    'share' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>',
    // Лайк мероприятия (плашка на карточке events/_card.blade.php) — плотный
    // силуэт огня с внутренним язычком (fill-rule=evenodd), ближе к форме
    // из report/fire *.svg, чем тонкий контурный вариант.
    'flame' => '<svg viewBox="0 0 384 512" fill="currentColor" stroke="none"><path fill-rule="evenodd" d="M216 23.86c0-23.8-30.65-32.77-44.15-13.04C48 219.24 22.14 292.14 22.14 328.03A192.19 192.19 0 0 0 216 512c103.94 0 192-88.31 192-192.19 0-89.68-53.35-124.85-64.7-79.6-6.63 26.86-33.72 34.42-53.36 4.6C285.61 233.28 271.7 158.32 216 23.86zM155.9 400.5c-24.09-9.24-42.4-31.14-45.34-56.75a49.34 49.34 0 0 1 3.85-27.14c3.85-8.74 15.09-9.24 20.59-1.5a63.68 63.68 0 0 0 39.94 23.61c1.5.2 2.7-.5 3.15-1.7 3.85-11.29-.85-30.15-4.55-42.5-2-6.79-14.05-32.09 6.4-24.34C226.32 288.44 246 348.34 246 378.44a68.94 68.94 0 0 1-90.1 22.06z"></path></svg>',
    // Галочка/подтверждение (кнопка перехода к отметке оплаты — "Контроль наличных")
    'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
    // Лупа (кнопка поиска в фильтрах таблиц — история платежей и т.п.)
    'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
    // Корзина (кнопка "Сбросить фильтр" — контроль наличных и т.п.)
    'trash' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>',
];
$svg = $icons[$name] ?? '';
@endphp
@if($svg)
<span {{ $attributes->merge(['class' => "menu-icon menu-icon-{$name}"]) }} aria-hidden="true">{!! $svg !!}</span>
@endif
