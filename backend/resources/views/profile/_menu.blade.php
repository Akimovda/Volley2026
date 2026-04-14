@php
    $menuUser = $menuUser ?? auth()->user();
    $isEditingOther = $isEditingOther ?? false;
    $activeMenu = $activeMenu ?? '';
    $isOrgOrAdmin = $menuUser->isOrganizer() || $menuUser->isAdmin();
@endphp

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
        <span class="menu-text">Публичный профиль пользователя</span>
    </a>
    <a href="{{ url('/profile/complete?user_id=' . $menuUser->id) }}" class="menu-item">
        <span class="menu-text">Редактировать пользователя</span>
    </a>
    <a href="{{ url('/user/photos?user_id=' . $menuUser->id) }}"
       class="menu-item {{ $activeMenu === 'photos' ? 'active' : '' }}">
        @if($activeMenu === 'photos')
            <strong class="cd menu-text">Редактировать фото пользователя</strong>
        @else
            <span class="menu-text">Редактировать фото пользователя</span>
        @endif
    </a>
</nav>

{{-- Организатор или Админ — табы --}}
@elseif($isOrgOrAdmin)
<nav class="menu-nav sidebar-menu">
    <div class="tabs-content">
        <div class="tabs w-100">
            <div class="tab active" data-tab="player-menu">Игрок</div>
            <div class="tab" data-tab="org-menu">Организатор</div>
            <div class="tab-highlight"></div>
        </div>
        <div class="tab-panes">

            {{-- Таб: Игрок --}}
            <div class="tab-pane active" id="player-menu">
                <a href="{{ route('users.show', ['user' => $menuUser->id]) }}"
                   class="menu-item {{ $activeMenu === 'public_profile' ? 'active' : '' }}">
                    @if($activeMenu === 'public_profile')
                        <strong class="cd menu-text">Публичный профиль</strong>
                    @else
                        <span class="menu-text">Публичный профиль</span>
                    @endif
                </a>
                <a href="{{ route('profile.show') }}"
                   class="menu-item {{ $activeMenu === 'profile' ? 'active' : '' }}">
                    @if($activeMenu === 'profile')
                        <strong class="cd menu-text">Мой профиль</strong>
                    @else
                        <span class="menu-text">Мой профиль</span>
                    @endif
                </a>
                <a href="{{ url('/profile/complete') }}"
                   class="menu-item {{ $activeMenu === 'profile_edit' ? 'active' : '' }}">
                    @if($activeMenu === 'profile_edit')
                        <strong class="cd menu-text">Редактировать профиль</strong>
                    @else
                        <span class="menu-text">Редактировать профиль</span>
                    @endif
                </a>
                <a href="{{ route('user.photos') }}"
                   class="menu-item {{ $activeMenu === 'photos' ? 'active' : '' }}">
                    @if($activeMenu === 'photos')
                        <strong class="cd menu-text">Мои фотографии</strong>
                    @else
                        <span class="menu-text">Мои фотографии</span>
                    @endif
                </a>
                <a href="{{ route('notifications.index') }}"
                   class="menu-item {{ $activeMenu === 'notifications' ? 'active' : '' }}">
                    @if($activeMenu === 'notifications')
                        <strong class="cd menu-text">Уведомления</strong>
                    @else
                        <span class="menu-text">Уведомления</span>
                    @endif
                </a>
                <a href="{{ route('subscriptions.my') }}"
                   class="menu-item {{ $activeMenu === 'subscriptions' ? 'active' : '' }}">
                    @if($activeMenu === 'subscriptions')
                        <strong class="cd menu-text">Мои абонементы</strong>
                    @else
                        <span class="menu-text">Мои абонементы</span>
                    @endif
                </a>
                <a href="{{ route('coupons.my') }}"
                   class="menu-item {{ $activeMenu === 'coupons' ? 'active' : '' }}">
                    @if($activeMenu === 'coupons')
                        <strong class="cd menu-text">Мои купоны</strong>
                    @else
                        <span class="menu-text">Мои купоны</span>
                    @endif
                </a>
                <a href="{{ route('player.dashboard') }}"
                   class="menu-item {{ $activeMenu === 'player_dashboard' ? 'active' : '' }}">
                    @if($activeMenu === 'player_dashboard')
                        <strong class="cd menu-text">Моя статистика</strong>
                    @else
                        <span class="menu-text">Моя статистика</span>
                    @endif
                </a>
                <a href="{{ route('friends.index') }}"
                   class="menu-item {{ $activeMenu === 'friends' ? 'active' : '' }}">
                    @if($activeMenu === 'friends')
                        <strong class="cd menu-text">Мои друзья</strong>
                    @else
                        <span class="menu-text">Мои друзья</span>
                    @endif
                </a>
                @if($menuUser->isPremium())
                <a href="{{ route('profile.visitors') }}"
                   class="menu-item {{ $activeMenu === 'visitors' ? 'active' : '' }}">
                    @if($activeMenu === 'visitors')
                        <strong class="cd menu-text">Мои гости</strong>
                    @else
                        <span class="menu-text">Мои гости</span>
                    @endif
                </a>
                @else
                <a href="{{ route('premium.index') }}" class="menu-item" style="opacity:.6;">
                    <span class="menu-text">Мои гости 👑</span>
                </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
                    @csrf
                    <button type="submit" class="menu-item">Выйти</button>
                </form>
            </div>

            {{-- Таб: Организатор --}}
            <div class="tab-pane" id="org-menu">
                <a href="{{ route('org.dashboard') }}"
                   class="menu-item {{ $activeMenu === 'org_dashboard' ? 'active' : '' }}">
                    @if($activeMenu === 'org_dashboard')
                        <strong class="cd menu-text">Панель организатора</strong>
                    @else
                        <span class="menu-text">Панель организатора</span>
                    @endif
                </a>
                <a href="{{ route('events.create.event_management') }}"
                   class="menu-item {{ $activeMenu === 'event_management' ? 'active' : '' }}">
                    @if($activeMenu === 'event_management')
                        <strong class="cd menu-text">Мои мероприятия</strong>
                    @else
                        <span class="menu-text">Мои мероприятия</span>
                    @endif
                </a>
                <a href="{{ route('events.create') }}"
                   class="menu-item {{ $activeMenu === 'event_create' ? 'active' : '' }}">
                    @if($activeMenu === 'event_create')
                        <strong class="cd menu-text">Создать мероприятие</strong>
                    @else
                        <span class="menu-text">Создать мероприятие</span>
                    @endif
                </a>
                <a href="{{ route('subscriptions.index') }}"
                   class="menu-item {{ $activeMenu === 'org_subscriptions' ? 'active' : '' }}">
                    @if($activeMenu === 'org_subscriptions')
                        <strong class="cd menu-text">Абонементы (орг.)</strong>
                    @else
                        <span class="menu-text">Абонементы (орг.)</span>
                    @endif
                </a>
                <a href="{{ route('subscription_templates.index') }}"
                   class="menu-item {{ $activeMenu === 'sub_templates' ? 'active' : '' }}">
                    @if($activeMenu === 'sub_templates')
                        <strong class="cd menu-text">Шаблоны абонементов</strong>
                    @else
                        <span class="menu-text">Шаблоны абонементов</span>
                    @endif
                </a>
                <a href="{{ route('coupon_templates.index') }}"
                   class="menu-item {{ $activeMenu === 'coupon_templates' ? 'active' : '' }}">
                    @if($activeMenu === 'coupon_templates')
                        <strong class="cd menu-text">Шаблоны купонов</strong>
                    @else
                        <span class="menu-text">Шаблоны купонов</span>
                    @endif
                </a>
                <a href="{{ route('staff.index') }}"
                   class="menu-item {{ $activeMenu === 'staff' ? 'active' : '' }}">
                    @if($activeMenu === 'staff')
                        <strong class="cd menu-text">Мои помощники</strong>
                    @else
                        <span class="menu-text">Мои помощники</span>
                    @endif
                </a>
                <a href="{{ route('staff.logs') }}"
                   class="menu-item {{ $activeMenu === 'staff_logs' ? 'active' : '' }}">
                    @if($activeMenu === 'staff_logs')
                        <strong class="cd menu-text">📋 Логи Staff</strong>
                    @else
                        <span class="menu-text">📋 Логи Staff</span>
                    @endif
                </a>

                @php
                    $mySchool = \App\Models\VolleyballSchool::where('organizer_id', $menuUser->id)->first();
                @endphp
                @if($mySchool)
                <a href="{{ route('volleyball_school.edit') }}"
                   class="menu-item {{ $activeMenu === 'school' ? 'active' : '' }}">
                    @if($activeMenu === 'school')
                        <strong class="cd menu-text">Редактировать школу</strong>
                    @else
                        <span class="menu-text">Редактировать школу</span>
                    @endif
                </a>
                <a href="{{ route('volleyball_school.show', $mySchool->slug) }}"
                   class="menu-item">
                    <span class="menu-text">Страница школы</span>
                </a>
                @else
                <a href="{{ route('volleyball_school.create') }}"
                   class="menu-item {{ $activeMenu === 'school' ? 'active' : '' }}">
                    @if($activeMenu === 'school')
                        <strong class="cd menu-text">Создать школу</strong>
                    @else
                        <span class="menu-text">Создать школу</span>
                    @endif
                </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
                    @csrf
                    <button type="submit" class="menu-item">Выйти</button>
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
            <strong class="cd menu-text">Публичный профиль</strong>
        @else
            <span class="menu-text">Публичный профиль</span>
        @endif
    </a>
    <a href="{{ route('profile.show') }}"
       class="menu-item {{ $activeMenu === 'profile' ? 'active' : '' }}">
        @if($activeMenu === 'profile')
            <strong class="cd menu-text">Мой профиль</strong>
        @else
            <span class="menu-text">Мой профиль</span>
        @endif
    </a>
    <a href="{{ url('/profile/complete') }}"
       class="menu-item {{ $activeMenu === 'profile_edit' ? 'active' : '' }}">
        @if($activeMenu === 'profile_edit')
            <strong class="cd menu-text">Редактировать профиль</strong>
        @else
            <span class="menu-text">Редактировать профиль</span>
        @endif
    </a>
    <a href="{{ route('user.photos') }}"
       class="menu-item {{ $activeMenu === 'photos' ? 'active' : '' }}">
        @if($activeMenu === 'photos')
            <strong class="cd menu-text">Мои фотографии</strong>
        @else
            <span class="menu-text">Мои фотографии</span>
        @endif
    </a>
    <a href="{{ route('notifications.index') }}"
       class="menu-item {{ $activeMenu === 'notifications' ? 'active' : '' }}">
        @if($activeMenu === 'notifications')
            <strong class="cd menu-text">Уведомления</strong>
        @else
            <span class="menu-text">Уведомления</span>
        @endif
    </a>
    <a href="{{ route('subscriptions.my') }}"
       class="menu-item {{ $activeMenu === 'subscriptions' ? 'active' : '' }}">
        @if($activeMenu === 'subscriptions')
            <strong class="cd menu-text">Мои абонементы</strong>
        @else
            <span class="menu-text">Мои абонементы</span>
        @endif
    </a>
    <a href="{{ route('coupons.my') }}"
       class="menu-item {{ $activeMenu === 'coupons' ? 'active' : '' }}">
        @if($activeMenu === 'coupons')
            <strong class="cd menu-text">Мои купоны</strong>
        @else
            <span class="menu-text">Мои купоны</span>
        @endif
    </a>
    <a href="{{ route('player.dashboard') }}"
       class="menu-item {{ $activeMenu === 'player_dashboard' ? 'active' : '' }}">
        @if($activeMenu === 'player_dashboard')
            <strong class="cd menu-text">Моя статистика</strong>
        @else
            <span class="menu-text">Моя статистика</span>
        @endif
    </a>
    <a href="{{ route('friends.index') }}"
       class="menu-item {{ $activeMenu === 'friends' ? 'active' : '' }}">
        @if($activeMenu === 'friends')
            <strong class="cd menu-text">Мои друзья</strong>
        @else
            <span class="menu-text">Мои друзья</span>
        @endif
    </a>
    @if($menuUser->isPremium())
    <a href="{{ route('profile.visitors') }}"
       class="menu-item {{ $activeMenu === 'visitors' ? 'active' : '' }}">
        @if($activeMenu === 'visitors')
            <strong class="cd menu-text">Мои гости</strong>
        @else
            <span class="menu-text">Мои гости</span>
        @endif
    </a>
    @else
    <a href="{{ route('premium.index') }}" class="menu-item" style="opacity:.6;">
        <span class="menu-text">Мои гости 👑</span>
    </a>
    @endif
    <form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
        @csrf
        <button type="submit" class="menu-item">Выйти</button>
    </form>
</nav>
@endif
