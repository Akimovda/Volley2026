@php
    $menuUser = $menuUser ?? auth()->user();
    $isEditingOther = $isEditingOther ?? false;
    $activeMenu = $activeMenu ?? '';
    $isOrgOrAdmin = $menuUser->isOrganizer() || $menuUser->isAdmin();
@endphp

                    <div class="menu-move">
                        <button class="menu-move-btn menu-move-left">◀</button>
                        <button class="menu-move-btn menu-move-right">▶</button>
                    </div>


<div class="profile-avatar mb-1 f-0 text-center">
    <img src="{{ $menuUser->profile_photo_url }}" alt="avatar" class="avatar"/>
</div>
<div class="text-center mb-1">
    <div class="b-600 cd mt-2">{{ $menuUser->name }}</div>
</div>	
{{-- Чужая страница (редактирование) --}}
@if($isEditingOther)
<nav class="menu-nav sidebar-menu">
    <a href="{{ route('users.show', ['user' => $menuUser->id]) }}" class="menu-item">
        <span class="menu-text">{{ __('profile.menu_public_profile_other') }}</span>
    </a>
    <a href="{{ url('/profile/complete?user_id=' . $menuUser->id) }}" class="menu-item">
        <span class="menu-text">{{ __('profile.menu_edit_user') }}</span>
    </a>
    <a href="{{ url('/user/photos?user_id=' . $menuUser->id) }}"
       class="menu-item {{ $activeMenu === 'photos' ? 'active' : '' }}">
        @if($activeMenu === 'photos')
            <strong class="cd menu-text">{{ __('profile.menu_edit_user_photos') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_edit_user_photos') }}</span>
        @endif
    </a>
</nav>

{{-- Организатор или Админ — табы --}}
@elseif($isOrgOrAdmin)
<nav class="menu-nav sidebar-menu">
    <div class="tabs-content">
        <div class="tabs w-100">
            <div class="tab active" data-tab="player-menu">{{ __('profile.menu_tab_player') }}</div>
            <div class="tab" data-tab="org-menu">{{ __('profile.menu_tab_organizer') }}</div>
            <div class="tab-highlight"></div>
        </div>
        <div class="tab-panes">

            {{-- Таб: Игрок --}}
            <div class="tab-pane active" id="player-menu">
                <a href="{{ route('users.show', ['user' => $menuUser->id]) }}"
                   class="menu-item {{ $activeMenu === 'public_profile' ? 'active' : '' }}">
                    @if($activeMenu === 'public_profile')
                        <strong class="cd menu-text">{{ __('profile.menu_public_profile') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_public_profile') }}</span>
                    @endif
                </a>
                <a href="{{ route('profile.show') }}"
                   class="menu-item {{ $activeMenu === 'profile' ? 'active' : '' }}">
                    @if($activeMenu === 'profile')
                        <strong class="cd menu-text">{{ __('profile.menu_my_profile') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_my_profile') }}</span>
                    @endif
                </a>
                <a href="{{ url('/profile/complete') }}"
                   class="menu-item {{ $activeMenu === 'profile_edit' ? 'active' : '' }}">
                    @if($activeMenu === 'profile_edit')
                        <strong class="cd menu-text">{{ __('profile.menu_edit_profile') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_edit_profile') }}</span>
                    @endif
                </a>
                <a href="{{ route('user.photos') }}"
                   class="menu-item {{ $activeMenu === 'photos' ? 'active' : '' }}">
                    @if($activeMenu === 'photos')
                        <strong class="cd menu-text">{{ __('profile.menu_my_photos') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_my_photos') }}</span>
                    @endif
                </a>
                <a href="{{ route('notifications.index') }}"
                   class="menu-item {{ $activeMenu === 'notifications' ? 'active' : '' }}">
                    @if($activeMenu === 'notifications')
                        <strong class="cd menu-text">{{ __('profile.menu_notifications') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_notifications') }}</span>
                    @endif
                </a>
                <a href="{{ route('subscriptions.my') }}"
                   class="menu-item {{ $activeMenu === 'subscriptions' ? 'active' : '' }}">
                    @if($activeMenu === 'subscriptions')
                        <strong class="cd menu-text">{{ __('profile.menu_my_subs') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_my_subs') }}</span>
                    @endif
                </a>
                <a href="{{ route('coupons.my') }}"
                   class="menu-item {{ $activeMenu === 'coupons' ? 'active' : '' }}">
                    @if($activeMenu === 'coupons')
                        <strong class="cd menu-text">{{ __('profile.menu_my_coupons') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_my_coupons') }}</span>
                    @endif
                </a>
                <a href="{{ route('player.my-bookings') }}"
                   class="menu-item {{ $activeMenu === 'my_bookings' ? 'active' : '' }}">
                    @if($activeMenu === 'my_bookings')
                        <strong class="cd menu-text">{{ __('club.my_bookings') }}</strong>
                    @else
                        <span class="menu-text">{{ __('club.my_bookings') }}</span>
                    @endif
                </a>
                @if($menuUser->courtBookings()->exists())
                <a href="{{ route('player.my-court-bookings') }}"
                   class="menu-item {{ $activeMenu === 'my_court_bookings' ? 'active' : '' }}">
                    @if($activeMenu === 'my_court_bookings')
                        <strong class="cd menu-text">{{ __('club.my_court_bookings') }}</strong>
                    @else
                        <span class="menu-text">{{ __('club.my_court_bookings') }}</span>
                    @endif
                </a>
                @endif
                <a href="{{ route('player.dashboard') }}"
                   class="menu-item {{ $activeMenu === 'player_dashboard' ? 'active' : '' }}">
                    @if($activeMenu === 'player_dashboard')
                        <strong class="cd menu-text">{{ __('profile.menu_my_stats') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_my_stats') }}</span>
                    @endif
                </a>
                @if(config('activity.recording_open') || $menuUser->isAdmin())
                <a href="{{ route('activity.index') }}"
                   class="menu-item {{ $activeMenu === 'activity' ? 'active' : '' }}">
                    @if($activeMenu === 'activity')
                        <strong class="cd menu-text">{{ __('activity.my_activity') }}</strong>
                    @else
                        <span class="menu-text">{{ __('activity.my_activity') }}</span>
                    @endif
                </a>
                @endif
                <a href="{{ route('friends.index') }}"
                   class="menu-item {{ $activeMenu === 'friends' ? 'active' : '' }}">
                    @if($activeMenu === 'friends')
                        <strong class="cd menu-text">{{ __('profile.menu_my_friends') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_my_friends') }}</span>
                    @endif
                </a>
                @if($menuUser->isPremium())
                <a href="{{ route('profile.visitors') }}"
                   class="menu-item {{ $activeMenu === 'visitors' ? 'active' : '' }}">
                    @if($activeMenu === 'visitors')
                        <strong class="cd menu-text">{{ __('profile.menu_my_visitors') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_my_visitors') }}</span>
                    @endif
                </a>
                @else
                <a href="{{ route('premium.index') }}" class="menu-item" style="opacity:.6;">
                    <span class="menu-text">{{ __('profile.menu_my_visitors_premium') }}</span>
                </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
                    @csrf
                    <button type="submit" class="menu-item">{{ __('profile.menu_logout') }}</button>
                </form>
            </div>

            {{-- Таб: Организатор --}}
            <div class="tab-pane" id="org-menu">
                {{-- 1. Панель организатора --}}
                <a href="{{ route('org.dashboard') }}"
                   class="menu-item {{ $activeMenu === 'org_dashboard' ? 'active' : '' }}">
                    @if($activeMenu === 'org_dashboard')
                        <strong class="cd menu-text">{{ __('profile.menu_org_dashboard') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_org_dashboard') }}</span>
                    @endif
                </a>
                {{-- 2. Панель арендатора — только для арендодателей кортов --}}
                @if($menuUser->is_club_manager && $menuUser->ownedLocations()->exists())
                <a href="{{ route('club.analytics.index') }}"
                   class="menu-item {{ $activeMenu === 'club_analytics' ? 'active' : '' }}">
                    @if($activeMenu === 'club_analytics')
                        <strong class="cd menu-text">{{ __('club.analytics') }}</strong>
                    @else
                        <span class="menu-text">{{ __('club.analytics') }}</span>
                    @endif
                </a>
                @endif
                {{-- 3. Управление мероприятиями --}}
                <a href="{{ route('events.create.event_management') }}"
                   class="menu-item {{ $activeMenu === 'event_management' ? 'active' : '' }}">
                    @if($activeMenu === 'event_management')
                        <strong class="cd menu-text">{{ __('ui.org_events_management') }}</strong>
                    @else
                        <span class="menu-text">{{ __('ui.org_events_management') }}</span>
                    @endif
                </a>
                {{-- 4. Управление регистрациями --}}
                <a href="{{ route('events.registrations.manage') }}"
                   class="menu-item {{ $activeMenu === 'regs_manage' ? 'active' : '' }}">
                    @if($activeMenu === 'regs_manage')
                        <strong class="cd menu-text">{{ __('profile.menu_org_regs_manage') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_org_regs_manage') }}</span>
                    @endif
                </a>
                {{-- 5. Мои мероприятия --}}
                <a href="{{ route('organizer.my-events') }}"
                   class="menu-item {{ $activeMenu === 'organizer_my_events' ? 'active' : '' }}">
                    @if($activeMenu === 'organizer_my_events')
                        <strong class="cd menu-text">{{ __('profile.menu_my_events') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_my_events') }}</span>
                    @endif
                </a>
                {{-- 6. Брони кортов — только для арендодателей кортов --}}
                @if($menuUser->is_club_manager && $menuUser->ownedLocations()->exists())
                <a href="{{ route('club.bookings.index') }}"
                   class="menu-item {{ $activeMenu === 'club_bookings' ? 'active' : '' }}">
                    @if($activeMenu === 'club_bookings')
                        <strong class="cd menu-text">🎪 {{ __('club.bookings_title') }}</strong>
                    @else
                        <span class="menu-text">🎪 {{ __('club.bookings_title') }}</span>
                    @endif
                </a>
                @endif
                {{-- 7. Создать мероприятие --}}
                <a href="{{ route('events.create') }}"
                   class="menu-item {{ $activeMenu === 'event_create' ? 'active' : '' }}">
                    @if($activeMenu === 'event_create')
                        <strong class="cd menu-text">📆 {{ __('profile.menu_org_create_event') }}</strong>
                    @else
                        <span class="menu-text">📆 {{ __('profile.menu_org_create_event') }}</span>
                    @endif
                </a>
                {{-- 8. Абонементы --}}
                <a href="{{ route('subscription_templates.index') }}"
                   class="menu-item {{ $activeMenu === 'sub_templates' ? 'active' : '' }}">
                    @if($activeMenu === 'sub_templates')
                        <strong class="cd menu-text">🪪 {{ __('ui.org_subscriptions') }}</strong>
                    @else
                        <span class="menu-text">🪪 {{ __('ui.org_subscriptions') }}</span>
                    @endif
                </a>
                {{-- 9. Купоны --}}
                <a href="{{ route('coupon_templates.index') }}"
                   class="menu-item {{ $activeMenu === 'coupon_templates' ? 'active' : '' }}">
                    @if($activeMenu === 'coupon_templates')
                        <strong class="cd menu-text">🎟  {{ __('ui.org_coupons') }}</strong>
                    @else
                        <span class="menu-text">🎟  {{ __('ui.org_coupons') }}</span>
                    @endif
                </a>
                {{-- 10. Мои лиги и сезоны --}}
                <a href="{{ route('leagues.index') }}"
                   class="menu-item {{ $activeMenu === 'org_leagues' ? 'active' : '' }}">
                    @if($activeMenu === 'org_leagues')
                        <strong class="cd menu-text">{{ __('ui.org_my_leagues') }}</strong>
                    @else
                        <span class="menu-text">{{ __('ui.org_my_leagues') }}</span>
                    @endif
                </a>
                {{-- 11. Каналы уведомлений --}}
                <a href="{{ route('profile.notification_channels') }}"
                   class="menu-item {{ $activeMenu === 'org_notif_channels' ? 'active' : '' }}">
                    @if($activeMenu === 'org_notif_channels')
                        <strong class="cd menu-text">📣 {{ __('ui.org_notif_channels') }}</strong>
                    @else
                        <span class="menu-text">📣 {{ __('ui.org_notif_channels') }}</span>
                    @endif
                </a>
                {{-- 12. Виджет на сайт --}}
                <a href="{{ route('profile.widget') }}"
                   class="menu-item {{ $activeMenu === 'org_widget' ? 'active' : '' }}">
                    @if($activeMenu === 'org_widget')
                        <strong class="cd menu-text">{{ __('ui.org_widget') }}</strong>
                    @else
                        <span class="menu-text">{{ __('ui.org_widget') }}</span>
                    @endif
                </a>
                {{-- 13. Организатор Pro --}}
                <a href="{{ route('organizer_pro.index') }}"
                   class="menu-item {{ $activeMenu === 'org_pro' ? 'active' : '' }}">
                    @if($activeMenu === 'org_pro')
                        <strong class="cd menu-text">{{ __('ui.org_pro') }}</strong>
                    @else
                        <span class="menu-text">{{ __('ui.org_pro') }}</span>
                    @endif
                </a>

                {{-- Доп. пункты профиля-организатора, не входящие в единый порядок орг. меню (см. CLAUDE.md) --}}
                <a href="{{ route('subscriptions.index') }}"
                   class="menu-item {{ $activeMenu === 'org_subscriptions' ? 'active' : '' }}">
                    @if($activeMenu === 'org_subscriptions')
                        <strong class="cd menu-text">{{ __('profile.menu_org_subs') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_org_subs') }}</span>
                    @endif
                </a>
                <a href="{{ route('staff.index') }}"
                   class="menu-item {{ $activeMenu === 'staff' ? 'active' : '' }}">
                    @if($activeMenu === 'staff')
                        <strong class="cd menu-text">{{ __('profile.menu_org_staff') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_org_staff') }}</span>
                    @endif
                </a>
                <a href="{{ route('staff.logs') }}"
                   class="menu-item {{ $activeMenu === 'staff_logs' ? 'active' : '' }}">
                    @if($activeMenu === 'staff_logs')
                        <strong class="cd menu-text">{{ __('profile.menu_org_staff_logs') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_org_staff_logs') }}</span>
                    @endif
                </a>

                @php
                    $mySchool = \App\Models\VolleyballSchool::where('organizer_id', $menuUser->id)->first();
                @endphp
                @if($mySchool)
                <a href="{{ route('volleyball_school.edit') }}"
                   class="menu-item {{ $activeMenu === 'school' ? 'active' : '' }}">
                    @if($activeMenu === 'school')
                        <strong class="cd menu-text">{{ __('profile.menu_school_edit') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_school_edit') }}</span>
                    @endif
                </a>
                <a href="{{ route('volleyball_school.show', $mySchool->slug) }}"
                   class="menu-item">
                    <span class="menu-text">{{ __('profile.menu_school_show') }}</span>
                </a>
                @else
                <a href="{{ route('volleyball_school.create') }}"
                   class="menu-item {{ $activeMenu === 'school' ? 'active' : '' }}">
                    @if($activeMenu === 'school')
                        <strong class="cd menu-text">{{ __('profile.menu_school_create') }}</strong>
                    @else
                        <span class="menu-text">{{ __('profile.menu_school_create') }}</span>
                    @endif
                </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
                    @csrf
                    <button type="submit" class="menu-item">{{ __('profile.menu_logout') }}</button>
                </form>
            </div>

        </div>
    </div>
</nav>

{{-- Обычный пользователь --}}
@else
<nav class="menu-nav sidebar-menu">
    <a href="{{ route('users.show', ['user' => $menuUser->id]) }}"
       class="menu-item {{ $activeMenu === 'public_profile' ? 'active' : '' }}">
        @if($activeMenu === 'public_profile')
            <strong class="cd menu-text">{{ __('profile.menu_public_profile') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_public_profile') }}</span>
        @endif
    </a>
    <a href="{{ route('profile.show') }}"
       class="menu-item {{ $activeMenu === 'profile' ? 'active' : '' }}">
        @if($activeMenu === 'profile')
            <strong class="cd menu-text">{{ __('profile.menu_my_profile') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_my_profile') }}</span>
        @endif
    </a>
    <a href="{{ url('/profile/complete') }}"
       class="menu-item {{ $activeMenu === 'profile_edit' ? 'active' : '' }}">
        @if($activeMenu === 'profile_edit')
            <strong class="cd menu-text">{{ __('profile.menu_edit_profile') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_edit_profile') }}</span>
        @endif
    </a>
    <a href="{{ route('user.photos') }}"
       class="menu-item {{ $activeMenu === 'photos' ? 'active' : '' }}">
        @if($activeMenu === 'photos')
            <strong class="cd menu-text">{{ __('profile.menu_my_photos') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_my_photos') }}</span>
        @endif
    </a>
    <a href="{{ route('notifications.index') }}"
       class="menu-item {{ $activeMenu === 'notifications' ? 'active' : '' }}">
        @if($activeMenu === 'notifications')
            <strong class="cd menu-text">{{ __('profile.menu_notifications') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_notifications') }}</span>
        @endif
    </a>
    <a href="{{ route('subscriptions.my') }}"
       class="menu-item {{ $activeMenu === 'subscriptions' ? 'active' : '' }}">
        @if($activeMenu === 'subscriptions')
            <strong class="cd menu-text">{{ __('profile.menu_my_subs') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_my_subs') }}</span>
        @endif
    </a>
    <a href="{{ route('coupons.my') }}"
       class="menu-item {{ $activeMenu === 'coupons' ? 'active' : '' }}">
        @if($activeMenu === 'coupons')
            <strong class="cd menu-text">{{ __('profile.menu_my_coupons') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_my_coupons') }}</span>
        @endif
    </a>
    <a href="{{ route('player.my-bookings') }}"
       class="menu-item {{ $activeMenu === 'my_bookings' ? 'active' : '' }}">
        @if($activeMenu === 'my_bookings')
            <strong class="cd menu-text">{{ __('club.my_bookings') }}</strong>
        @else
            <span class="menu-text">{{ __('club.my_bookings') }}</span>
        @endif
    </a>
    @if($menuUser->courtBookings()->exists())
    <a href="{{ route('player.my-court-bookings') }}"
       class="menu-item {{ $activeMenu === 'my_court_bookings' ? 'active' : '' }}">
        @if($activeMenu === 'my_court_bookings')
            <strong class="cd menu-text">{{ __('club.my_court_bookings') }}</strong>
        @else
            <span class="menu-text">{{ __('club.my_court_bookings') }}</span>
        @endif
    </a>
    @endif
    <a href="{{ route('player.dashboard') }}"
       class="menu-item {{ $activeMenu === 'player_dashboard' ? 'active' : '' }}">
        @if($activeMenu === 'player_dashboard')
            <strong class="cd menu-text">{{ __('profile.menu_my_stats') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_my_stats') }}</span>
        @endif
    </a>
    @if(config('activity.recording_open') || $menuUser->isAdmin())
    <a href="{{ route('activity.index') }}"
       class="menu-item {{ $activeMenu === 'activity' ? 'active' : '' }}">
        @if($activeMenu === 'activity')
            <strong class="cd menu-text">{{ __('activity.my_activity') }}</strong>
        @else
            <span class="menu-text">{{ __('activity.my_activity') }}</span>
        @endif
    </a>
    @endif
    <a href="{{ route('friends.index') }}"
       class="menu-item {{ $activeMenu === 'friends' ? 'active' : '' }}">
        @if($activeMenu === 'friends')
            <strong class="cd menu-text">{{ __('profile.menu_my_friends') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_my_friends') }}</span>
        @endif
    </a>
    @if($menuUser->isPremium())
    <a href="{{ route('profile.visitors') }}"
       class="menu-item {{ $activeMenu === 'visitors' ? 'active' : '' }}">
        @if($activeMenu === 'visitors')
            <strong class="cd menu-text">{{ __('profile.menu_my_visitors') }}</strong>
        @else
            <span class="menu-text">{{ __('profile.menu_my_visitors') }}</span>
        @endif
    </a>
    @else
    <a href="{{ route('premium.index') }}" class="menu-item" style="opacity:.6;">
        <span class="menu-text">{{ __('profile.menu_my_visitors_premium') }}</span>
    </a>
    @endif
    <a href="{{ route('profile.show') }}#delete-account"
       class="menu-item {{ $activeMenu === 'delete_account' ? 'active' : '' }}"
       style="color:#e53e3e">
        <span class="menu-text">{{ __('profile.menu_delete_account') }}</span>
    </a>
    <form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
        @csrf
        <button type="submit" class="menu-item">{{ __('profile.menu_logout') }}</button>
    </form>
</nav>
@endif
