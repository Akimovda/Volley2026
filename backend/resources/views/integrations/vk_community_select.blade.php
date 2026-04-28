<x-voll-layout body_class="profile-page">
<x-slot name="title">Выбор VK-сообщества</x-slot>
<x-slot name="h1">Выбор VK-сообщества</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">Профиль</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('profile.notification_channels') }}" itemprop="item"><span itemprop="name">Каналы уведомлений</span></a>
        <meta itemprop="position" content="3">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Выбор сообщества</span>
        <meta itemprop="position" content="4">
    </li>
</x-slot>

<div class="container">
    <div class="ramka" style="max-width:640px;margin:0 auto;">
        <h2 class="mt-0 mb-1">🔵 Выберите VK-сообщество</h2>
        <p class="f-15 text-muted mb-2">
            Анонсы будут публиковаться на <strong>стене</strong> выбранного сообщества.
            Вы должны быть <strong>администратором</strong> с правом публикации.
        </p>

        <form method="POST" action="{{ route('integrations.vk_community.select') }}">
            @csrf
            <input type="hidden" name="title" value="{{ $title }}">

            <div class="mb-2">
                @foreach($groups as $group)
                <label class="card mb-1 d-flex fvc gap-1" style="cursor:pointer;padding:1rem 1.2rem;">
                    <input type="radio" name="group_id" value="{{ $group['id'] }}" required
                           style="accent-color:#2967BA;width:18px;height:18px;flex-shrink:0;">
                    @if(!empty($group['photo_50']))
                        <img src="{{ $group['photo_50'] }}" alt="" width="40" height="40"
                             style="border-radius:50%;object-fit:cover;flex-shrink:0;">
                    @endif
                    <div>
                        <div class="f-16 b-600">{{ $group['name'] ?? 'Сообщество #'.$group['id'] }}</div>
                        <div class="f-13 text-muted">vk.com/{{ $group['screen_name'] ?? 'club'.$group['id'] }}</div>
                    </div>
                </label>
                @endforeach
            </div>

            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary flex-1">
                    Подключить сообщество
                </button>
                <a href="{{ route('profile.notification_channels') }}"
                   class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </div>
</div>
</x-voll-layout>
