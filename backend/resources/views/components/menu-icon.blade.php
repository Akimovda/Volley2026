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
    'volleyball' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 3c3 3 3 15 0 18"></path><path d="M4.2 7.8c4 2 11.6 2 15.6 0"></path><path d="M4.2 16.2c4-2 11.6-2 15.6 0"></path></svg>',
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
    'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
];
$svg = $icons[$name] ?? '';
@endphp
@if($svg)
<span {{ $attributes->merge(['class' => "menu-icon menu-icon-{$name}"]) }} aria-hidden="true">{!! $svg !!}</span>
@endif
