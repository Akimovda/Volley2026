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
                'day_text'            => ['admin.tok_day_text',            ['#2C2C2C', '#CACACA'], [['{M} .day-chip .dc-date', 'color'], ['{M} .day-section-title:not(.is-weekend)', 'color']]],
                'day_chip_weekend'    => ['admin.tok_day_chip_weekend',    ['#EF4444', '#EF4444'], [['{M} .day-chip.is-weekend .dc-dow', 'color'], ['{M} .day-section-title.is-weekend', 'color']]],
                'day_chip_active_bg'  => ['admin.tok_day_chip_active_bg',  ['#2967BA', '#E7612F'], [['{M} .day-chip.active', 'background'], ['{M} .day-chip.active', 'box-shadow:none;background-color']]],
                'day_chip_active_text' => ['admin.tok_day_chip_active_text', ['#FFFFFF', '#161721'], [['{M} .day-chip.active .dc-dow', 'color'], ['{M} .day-chip.active .dc-date', 'color']]],
                'day_dot'             => ['admin.tok_day_dot',             ['#10B981', '#10B981'], [['{M} .day-chip .dc-dot:not(.dc-dot--empty)', 'background']]],
                'feed_panel_bg'       => ['admin.tok_feed_panel_bg',       ['#FFFFFF', '#222333'], [['{M} .event-dates-ramka', 'background-image:none;background-color'], ['{M} .mob-sticky', 'background-color']]],
            ],
        ],
        'topbar' => [
            'title' => 'admin.tok_g_topbar',
            'tokens' => [
                'topbar_btn_bg'       => ['admin.tok_topbar_btn_bg',       ['#FFFFFF', '#222333'], [['{M} .topbar-icon-btn', 'background']]],
                'topbar_btn_icon'     => ['admin.tok_topbar_btn_icon',     ['#2967BA', '#E7612F'], [['{M} .topbar-icon-btn', 'color'], ['{M} .topbar-icon-btn', 'border-color']]],
                'topbar_btn_active_bg' => ['admin.tok_topbar_btn_active_bg', ['#2967BA', '#E7612F'], [['{M} .topbar-icon-btn.is-active', 'background'], ['{M} .topbar-icon-btn:hover', 'background']]],
            ],
        ],
        'card' => [
            'title' => 'admin.tok_g_card',
            'tokens' => [
                'event_card_bg'       => ['admin.tok_event_card_bg',       ['#FFFFFF', '#222333'], [['{M} .event-card', 'background-image:none;background-color']]],
                'card_title'          => ['admin.tok_card_title',          ['#2C2C2C', '#FFFFFF'], [['{M} .event-card .card-title', 'color']]],
                'card_text'           => ['admin.tok_card_text',           ['#2C2C2C', '#CACACA'], [['{M} .event-address', 'color'], ['{M} .event-col-data', 'color']]],
                'card_icon'           => ['admin.tok_card_icon',           ['#2967BA', '#E7612F'], [['{M} .event-col-icon', 'background'], ['{M} .event-address .menu-icon', 'background-color'], ['{M} .event-address .menu-icon svg', 'fill'], ['{M} .event-card .menu-icon svg', 'fill'], ['{M} .event-card .emo', 'color'], ['{M} .event-card .card-title:hover', 'color']]],
                'col_icon_fg'         => ['admin.tok_col_icon_fg',         ['#EDEFF2', '#292A37'], [['{M} .event-col-icon svg', 'fill']]],
            ],
        ],
        'badges' => [
            'title' => 'admin.tok_g_badges',
            'tokens' => [
                'badge_bg'            => ['admin.tok_badge_bg',            ['#E5E7EB', '#33344A'], [['{M} .badge.badge-sm:not(.status-open):not(.status-live):not(.badge-rated)', 'background']]],
                'badge_text'          => ['admin.tok_badge_text',          ['#2C2C2C', '#CACACA'], [['{M} .badge.badge-sm:not(.status-open):not(.status-live):not(.badge-rated)', 'color']]],
                'badge_open_bg'       => ['admin.tok_badge_open_bg',       ['#D1FAE5', '#16352B'], [['{M} .badge.badge-sm.status-open', 'background']]],
                'badge_open_text'     => ['admin.tok_badge_open_text',     ['#10B981', '#B6F23A'], [['{M} .badge.badge-sm.status-open', 'color']]],
                'badge_live_bg'       => ['admin.tok_badge_live_bg',       ['#FDE3D8', '#3A2218'], [['{M} .badge.badge-sm.status-live', 'background']]],
                'badge_live_text'     => ['admin.tok_badge_live_text',     ['#E7612F', '#E7612F'], [['{M} .badge.badge-sm.status-live', 'color']]],
            ],
        ],
        'buttons' => [
            'title' => 'admin.tok_g_buttons',
            'tokens' => [
                'btn_bg' => ['admin.tok_btn_bg', ['#2967BA', '#E7612F'], [['{M} .btn:not(.btn-secondary):not(.btn-outline):not(.btn-outline-danger):not(.btn-outline-primary)', 'background']]],
                'btn_text' => ['admin.tok_btn_text', ['#FFFFFF', '#FFFFFF'], [['{M} .btn:not(.btn-secondary):not(.btn-outline):not(.btn-outline-danger):not(.btn-outline-primary)', 'color'], ['{M} .btn:not(.btn-secondary):not(.btn-outline):not(.btn-outline-danger):not(.btn-outline-primary):hover', 'color']]],
                'btn_secondary_bg' => ['admin.tok_btn_secondary_bg', ['#FFFFFF', '#222333'], [['{M} .btn-secondary', 'background']]],
                'btn_secondary_text' => ['admin.tok_btn_secondary_text', ['#333333', '#CACACA'], [['{M} .btn-secondary', 'color']]],
                'btn_outline_color' => ['admin.tok_btn_outline_color', ['#2967BA', '#FFB171'], [['{M} .btn-outline', 'border-color'], ['{M} .btn-outline', 'color'], ['{M} .btn-secondary svg', 'fill']]],
            ],
        ],
        'text' => [
            'title' => 'admin.tok_g_text',
            'tokens' => [
                'heading' => ['admin.tok_heading', ['#000000', '#FFFFFF'], [['{M} h2', 'color'], ['{M} h3', 'color'], ['{M} .title-h', 'color']]],
                'footer_text' => ['admin.tok_footer_text', ['#000000', '#B0B0C0'], [['{M} .footer', 'color'], ['{M} .footer a', 'color']]],
                'link_underline' => ['admin.tok_link_underline', ['#2967BA', '#E7612F'], [['{M} .blink:before', 'border-bottom-color'], ['{M} .footer a:hover', 'color']]],
            ],
        ],
        'forms' => [
            'title' => 'admin.tok_g_forms',
            'tokens' => [
                'input_bg' => ['admin.tok_input_bg', ['#FFFFFF', '#222333'], [['{M} .form input:not([type="radio"]):not([type="checkbox"])', 'background-color'], ['{M} .form textarea', 'background-color'], ['{M} .form select', 'background-color'], ['{M} .form-select-custom', 'background-color'], ['{M} .form-select-dropdown', 'background']]],
                'input_text' => ['admin.tok_input_text', ['#222333', '#CACACA'], [['{M} .form input:not([type="radio"]):not([type="checkbox"])', 'color'], ['{M} .form textarea', 'color'], ['{M} .form select', 'color'], ['{M} .form-select-custom', 'color']]],
                'input_border' => ['admin.tok_input_border', ['#CCCCCC', '#3A3B4D'], [['{M} .form input:not([type="radio"]):not([type="checkbox"])', 'border-color'], ['{M} .form textarea', 'border-color'], ['{M} .form select', 'border-color'], ['{M} .form-select-custom', 'border-color'], ['{M} .form-select-dropdown', 'border-color'], ['{M} .form .custom-checkbox', 'border-color'], ['{M} .form .custom-radio', 'border-color']]],
                'input_focus' => ['admin.tok_input_focus', ['#2967BA', '#E7612F'], [['{M} .form input:focus:not([type="radio"]):not([type="checkbox"])', 'border-color'], ['{M} .form textarea:focus', 'border-color'], ['{M} .form select:focus', 'border-color']]],
                'check_mark' => ['admin.tok_check_mark', ['#2967BA', '#E7612F'], [['{M} .form .custom-checkbox::after', 'background'], ['{M} .form .custom-radio::after', 'background']]],
            ],
        ],
        'tabs' => [
            'title' => 'admin.tok_g_tabs',
            'tokens' => [
                'tab_text' => ['admin.tok_tab_text', ['#333333', '#CACACA'], [['{M} .tab', 'color'], ['{M} .filter-tab:not(.active)', 'color']]],
                'tab_active_bg' => ['admin.tok_tab_active_bg', ['#2967BA', '#E7612F'], [['{M} .tab-highlight', 'background'], ['{M} .filter-tab.active', 'color'], ['{M} .filter-tab.active', 'border-bottom-color']]],
                'tab_active_text' => ['admin.tok_tab_active_text', ['#FFFFFF', '#FFFFFF'], [['{M} .tab.active', 'color']]],
            ],
        ],
        'tables' => [
            'title' => 'admin.tok_g_tables',
            'tokens' => [
                'table_head_bg' => ['admin.tok_table_head_bg', ['#F2F2F2', '#2C2D3F'], [['{M} .table thead', 'background']]],
                'table_head_text' => ['admin.tok_table_head_text', ['#000000', '#FFFFFF'], [['{M} .table thead', 'color']]],
                'table_row_alt' => ['admin.tok_table_row_alt', ['#FAFAFA', '#262738'], [['{M} .table tbody tr:nth-child(even)', 'background-color']]],
            ],
        ],
        'alerts' => [
            'title' => 'admin.tok_g_alerts',
            'tokens' => [
                'alert_success_text' => ['admin.tok_alert_success_text', ['#0F6B3F', '#8EE0B5'], [['{M} .alert-success', 'color']]],
                'alert_success_bar' => ['admin.tok_alert_success_bar', ['#10B981', '#10B981'], [['{M} .alert-success', 'border-color'], ['{M} .alert-success::before', 'background']]],
            ],
        ],
        'progress' => [
            'title' => 'admin.tok_g_progress',
            'tokens' => [
                'progress_track' => ['admin.tok_progress_track', ['#E5E7EB', '#33344A'], [['{M} .progress', 'background']]],
                'progress_ok' => ['admin.tok_progress_ok', ['#10B981', '#B6F23A'], [['{M} .progress .progress-bar.bg-success', 'background']]],
                'progress_full' => ['admin.tok_progress_full', ['#EF4444', '#EF4444'], [['{M} .progress .progress-bar.bg-danger', 'background']]],
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
