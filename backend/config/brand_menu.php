<?php

/*
 * Каталог пунктов меню, которые можно скрыть для white-label бренда
 * («Настройка App»). Ключ пункта = путь ссылки. ДОЛЖЕН совпадать с href
 * в resources/views/components/voll-layout.blade.php (скрытие идёт CSS-ом
 * по href, см. BrandThemeService::hiddenMenuCss()). Пункты админа сюда
 * намеренно не входят — чтобы не заблокировать себе доступ к настройкам.
 * Формат пункта: [путь, ключ перевода названия].
 */
return [
    'groups' => [
        'site' => [
            'title' => 'admin.app_menu_g_site',
            'items' => [
                ['/events', 'ui.nav_games_trainings'],
                ['/locations', 'ui.nav_locations'],
                ['/volleyball_school', 'ui.nav_schools'],
                ['/leagues/all', 'ui.nav_leagues'],
                ['/users', 'ui.nav_players'],
                ['/help', 'ui.nav_help'],
                ['/rules', 'ui.nav_rules'],
                ['/level_players', 'ui.nav_levels'],
                ['/players/rating', 'players.rating'],
                ['/players/teams', 'players.teams_title'],
                ['/about', 'ui.nav_about'],
            ],
        ],
        'user' => [
            'title' => 'admin.app_menu_g_user',
            'items' => [
                ['/notifications', 'ui.menu_notifications'],
                ['/my/bookings', 'club.my_bookings'],
                ['/subscriptions/my', 'profile.menu_my_subs'],
                ['/my/court-bookings', 'club.my_court_bookings'],
                ['/activity', 'activity.my_activity'],
                ['/user/profile', 'ui.menu_my_profile'],
                ['/profile/complete', 'ui.menu_edit_profile'],
                ['/user/photos', 'ui.menu_my_photos'],
                ['/wallet', 'ui.menu_my_wallet'],
                ['/profile/transactions', 'ui.menu_transactions'],
            ],
        ],
        'organizer' => [
            'title' => 'admin.app_menu_g_organizer',
            'items' => [
                ['/org/dashboard', 'ui.org_dashboard'],
                ['/club/analytics', 'club.analytics'],
                ['/events/create/event_management', 'ui.org_events_management'],
                ['/events/registrations/manage', 'ui.org_regs_manage'],
                ['/my/events', 'ui.menu_my_events'],
                ['/club/bookings', 'club.bookings_title'],
                ['/events/create', 'ui.org_create_event'],
                ['/subscriptions/templates', 'ui.org_subscriptions'],
                ['/subscriptions', 'ui.org_subscriptions_issued'],
                ['/coupons/templates', 'ui.org_coupons'],
                ['/coupons/org', 'ui.org_coupons_issued'],
                ['/leagues', 'ui.org_my_leagues'],
                ['/user/profile/notification-channels', 'ui.org_notif_channels'],
                ['/profile/widget', 'ui.org_widget'],
                ['/organizer-pro', 'ui.org_pro'],
            ],
        ],
    ],
    'max_links' => 15,
];
