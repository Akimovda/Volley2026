@php
    $menuUser = $menuUser ?? auth()->user();
    $isEditingOther = $isEditingOther ?? false;
    $activeMenu = $activeMenu ?? '';
@endphp

<div class="profile-avatar mb-2 text-center">
    <img src="{{ $menuUser->profile_photo_url }}" alt="avatar" class="avatar"/>
    <div class="b-600 mt-1">{{ $menuUser->name }}</div>
    @if($menuUser->classic_level)
    <div class="f-14" style="opacity:.6">{{ level_name($menuUser->classic_level) }}</div>
    @endif
</div>

<nav class="menu-nav sidebar-menu">
    @if(!$isEditingOther)

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
            <strong class="cd menu-text">Ваш профиль</strong>
        @else
            <span class="menu-text">Ваш профиль</span>
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
            <strong class="cd menu-text">Ваши фотографии</strong>
        @else
            <span class="menu-text">Ваши фотографии</span>
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

    @if($menuUser->isOrganizer() || $menuUser->isAdmin())

    <div class="menu-divider f-13 mt-1 mb-05" style="opacity:.4;padding:.25rem .5rem">Организатор</div>

    <a href="{{ route('org.dashboard') }}"
       class="menu-item {{ $activeMenu === 'org_dashboard' ? 'active' : '' }}">
        @if($activeMenu === 'org_dashboard')
            <strong class="cd menu-text">Панель организатора</strong>
        @else
            <span class="menu-text">Панель организатора</span>
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

    @endif

    @if(isset($walletRoute))
    <a href="{{ route('wallet.index') }}"
       class="menu-item {{ $activeMenu === 'wallet' ? 'active' : '' }}">
        @if($activeMenu === 'wallet')
            <strong class="cd menu-text">Мой кошелёк</strong>
        @else
            <span class="menu-text">Мой кошелёк</span>
        @endif
    </a>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
        @csrf
        <button type="submit" class="menu-item">Выйти</button>
    </form>

    @else

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

    @endif
</nav>
