<?php

/**
 * Каталог «токенов» темы white-label брендов (экран «Настройка App»).
 *
 * Каждый токен = ОДИН элемент + ОДНО свойство (или фиксированная связка), поэтому токены не
 * перебивают друг друга: для разных элементов правила независимы, для одного — токен один.
 * Значение пустое = токен не применяется (остаётся то, что даёт общая тема/style.css).
 *
 * rules: [[селектор, css-свойство], ...]; {M} — префикс режима: день → `body`, ночь → `body.dark`.
 * Правила выводятся ПОСЛЕ общей темы и с !important (специфичность style.css местами выше).
 * Новый элемент = новая строка здесь (+ ключи admin.tok_*); ни style.css, ни контроллер править не нужно.
 *
 * Классы-маркеры без правил в style.css (days-strip, card-title, menu-icon-*, icon-calendar/level/men)
 * сознательно не включены — стилизовать нечего; иконки колонок карточки красятся через event-col-icon.
 */
return [
    'groups' => [
        'feed_days' => [
            'title' => 'admin.tok_g_feed_days',
            'tokens' => [
                'day_chip_dow'        => ['admin.tok_day_chip_dow',        ['#6B7280', '#9CA3AF'], [['{M} .day-chip .dc-dow', 'color']]],
                'day_chip_date'       => ['admin.tok_day_chip_date',       ['#2C2C2C', '#CACACA'], [['{M} .day-chip .dc-date', 'color']]],
                'day_chip_weekend'    => ['admin.tok_day_chip_weekend',    ['#EF4444', '#EF4444'], [['{M} .day-chip.is-weekend .dc-dow', 'color'], ['{M} .day-section-title.is-weekend', 'color']]],
                'day_chip_active_bg'  => ['admin.tok_day_chip_active_bg',  ['#2967BA', '#E7612F'], [['{M} .day-chip.active', 'background'], ['{M} .day-chip.active', 'box-shadow:none;background-color']]],
                'day_chip_active_text' => ['admin.tok_day_chip_active_text', ['#FFFFFF', '#161721'], [['{M} .day-chip.active .dc-dow', 'color'], ['{M} .day-chip.active .dc-date', 'color']]],
                'day_dot'             => ['admin.tok_day_dot',             ['#10B981', '#10B981'], [['{M} .day-chip .dc-dot:not(.dc-dot--empty)', 'background']]],
                'day_section_title'   => ['admin.tok_day_section_title',   ['#2C2C2C', '#CACACA'], [['{M} .day-section-title:not(.is-weekend)', 'color']]],
                'dates_ramka_bg'      => ['admin.tok_dates_ramka_bg',      ['#FFFFFF', '#222333'], [['{M} .event-dates-ramka', 'background-image:none;background-color']]],
                'mob_sticky_bg'       => ['admin.tok_mob_sticky_bg',       ['#FFFFFF', '#222333'], [['{M} .mob-sticky', 'background-color']]],
            ],
        ],
        'topbar' => [
            'title' => 'admin.tok_g_topbar',
            'tokens' => [
                'topbar_btn_bg'       => ['admin.tok_topbar_btn_bg',       ['#FFFFFF', '#222333'], [['{M} .topbar-icon-btn', 'background']]],
                'topbar_btn_icon'     => ['admin.tok_topbar_btn_icon',     ['#2967BA', '#E7612F'], [['{M} .topbar-icon-btn', 'color']]],
                'topbar_btn_border'   => ['admin.tok_topbar_btn_border',   ['#2967BA', '#E7612F'], [['{M} .topbar-icon-btn', 'border-color']]],
                'topbar_btn_active_bg' => ['admin.tok_topbar_btn_active_bg', ['#2967BA', '#E7612F'], [['{M} .topbar-icon-btn.is-active', 'background'], ['{M} .topbar-icon-btn:hover', 'background']]],
                'select_bg'           => ['admin.tok_select_bg',           ['#FFFFFF', '#222333'], [['{M} .form-select-custom', 'background-color']]],
                'select_text'         => ['admin.tok_select_text',         ['#222333', '#CACACA'], [['{M} .form-select-custom', 'color']]],
                'select_border'       => ['admin.tok_select_border',       ['#CCCCCC', '#3A3B4D'], [['{M} .form-select-custom', 'border-color']]],
                'select_dropdown_bg'  => ['admin.tok_select_dropdown_bg',  ['#FFFFFF', '#222333'], [['{M} .form-select-dropdown', 'background']]],
                'select_dropdown_border' => ['admin.tok_select_dropdown_border', ['#CCCCCC', '#3A3B4D'], [['{M} .form-select-dropdown', 'border-color']]],
            ],
        ],
        'card' => [
            'title' => 'admin.tok_g_card',
            'tokens' => [
                'event_card_bg'       => ['admin.tok_event_card_bg',       ['#FFFFFF', '#222333'], [['{M} .event-card', 'background-image:none;background-color']]],
                'card_title'          => ['admin.tok_card_title',          ['#2C2C2C', '#FFFFFF'], [['{M} .event-card .card-title', 'color']]],
                'card_title_hover'    => ['admin.tok_card_title_hover',    ['#2967BA', '#E7612F'], [['{M} .event-card .card-title:hover', 'color']]],
                'event_address_text'  => ['admin.tok_event_address_text',  ['#2C2C2C', '#CACACA'], [['{M} .event-address', 'color']]],
                'event_pin'           => ['admin.tok_event_pin',           ['#2967BA', '#E7612F'], [['{M} .event-address .menu-icon', 'background-color'], ['{M} .event-address .menu-icon svg', 'fill']]],
                'col_icon_bg'         => ['admin.tok_col_icon_bg',         ['#2967BA', '#E7612F'], [['{M} .event-col-icon', 'background']]],
                'col_icon_fg'         => ['admin.tok_col_icon_fg',         ['#EDEFF2', '#292A37'], [['{M} .event-col-icon svg', 'fill']]],
                'col_data_text'       => ['admin.tok_col_data_text',       ['#2C2C2C', '#CACACA'], [['{M} .event-col-data', 'color']]],
                'organizer_icon'      => ['admin.tok_organizer_icon',      ['#2967BA', '#E7612F'], [['{M} .event-card .menu-icon svg', 'fill']]],
                'emo_text'            => ['admin.tok_emo_text',            ['#2967BA', '#E7612F'], [['{M} .event-card .emo', 'color']]],
            ],
        ],
        'badges' => [
            'title' => 'admin.tok_g_badges',
            'tokens' => [
                'badge_bg'            => ['admin.tok_badge_bg',            ['#E5E7EB', '#33344A'], [['{M} .badge.badge-sm:not(.status-open):not(.status-live):not(.status-finished):not(.badge-rated)', 'background']]],
                'badge_text'          => ['admin.tok_badge_text',          ['#2C2C2C', '#CACACA'], [['{M} .badge.badge-sm:not(.status-open):not(.status-live):not(.status-finished):not(.badge-rated)', 'color']]],
                'badge_open_bg'       => ['admin.tok_badge_open_bg',       ['#D1FAE5', '#16352B'], [['{M} .badge.badge-sm.status-open', 'background']]],
                'badge_open_text'     => ['admin.tok_badge_open_text',     ['#10B981', '#B6F23A'], [['{M} .badge.badge-sm.status-open', 'color']]],
                'badge_live_bg'       => ['admin.tok_badge_live_bg',       ['#FDE3D8', '#3A2218'], [['{M} .badge.badge-sm.status-live', 'background']]],
                'badge_live_text'     => ['admin.tok_badge_live_text',     ['#E7612F', '#E7612F'], [['{M} .badge.badge-sm.status-live', 'color']]],
                'badge_finished_bg'   => ['admin.tok_badge_finished_bg',   ['#E5E7EB', '#33344A'], [['{M} .badge.badge-sm.status-finished', 'background']]],
                'badge_finished_text' => ['admin.tok_badge_finished_text', ['#6B7280', '#9CA3AF'], [['{M} .badge.badge-sm.status-finished', 'color']]],
            ],
        ],
    ],

    /** Шрифты, доступные для выбора (файлы лежат в public/assets/fonts). */
    'fonts' => [
        'pragmatica-next' => [
            'label'  => 'Pragmatica Next XXL',
            'family' => 'Pragmatica Next',
            'files'  => [
                100 => '/assets/fonts/pragmatica-next_xxl-100.otf',
                400 => '/assets/fonts/pragmatica-next_xxl-400.otf',
                900 => '/assets/fonts/pragmatica-next_xxl-900.otf',
            ],
            // веса 100/400/900 в наличии; промежуточные браузер подберёт ближайшие
        ],
    ],
];
