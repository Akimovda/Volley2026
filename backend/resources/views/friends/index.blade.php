<x-voll-layout body_class="friends-page">

    <x-slot name="title">Мои друзья — Volley</x-slot>
    <x-slot name="canonical">{{ route('friends.index') }}</x-slot>
    <x-slot name="h1">Мои друзья 👥</x-slot>
    <x-slot name="t_description">Игроки с кем вы часто играете</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('friends.index') }}" itemprop="item"><span itemprop="name">Друзья</span></a>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>

    <div class="container">
        <div class="ramka">

            @include('profile._menu', [
                'menuUser'       => auth()->user(),
                'isEditingOther' => false,
                'activeMenu'     => 'friends',
            ])

            @if($friends->isEmpty())
            <div class="text-center" style="padding: 4rem 0; color: #888;">
                <div style="font-size: 4rem; margin-bottom: 1.5rem;">👥</div>
                <div style="font-size: 1.8rem;">У вас пока нет друзей</div>
                <div style="font-size: 1.5rem; margin-top: 1rem;">
                    Добавляйте игроков из <a href="{{ route('users.index') }}">каталога игроков</a>
                </div>
            </div>
            @else
            <div class="row" style="margin-top: 2rem;">
                @foreach($friends as $friend)
                <div class="col-md-4 col-6" style="margin-bottom: 2rem;">
                    <div class="card">
                        <div style="display:flex; align-items:center; gap:1.5rem; padding: 1.5rem;">
                            <a href="{{ route('users.show', $friend->id) }}">
                                <span class="{{ $friend->isPremium() ? 'avatar-premium' : '' }}" style="display:inline-block;position:relative;">
                                    <img src="{{ $friend->profile_photo_url ?? asset('img/no-avatar.png') }}"
                                         style="width:5rem;height:5rem;border-radius:50%;object-fit:cover;">
                                </span>
                            </a>
                            <div>
                                <div style="font-weight:700;font-size:1.6rem;">
                                    <a href="{{ route('users.show', $friend->id) }}">{{ $friend->name }}</a>
                                    @if($friend->isPremium()) 👑 @endif
                                </div>
                                <div style="color:#888;font-size:1.4rem;">
                                    {{ position_name($friend->position ?? '') }}
                                </div>
                            </div>
                        </div>
                        <div style="padding: 0 1.5rem 1.5rem;">
                            <form method="POST" action="{{ route('friends.destroy', $friend->id) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-secondary"
                                        style="width:100%;font-size:1.4rem;">
                                    Удалить из друзей
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

        </div>
    </div>
</x-voll-layout>
