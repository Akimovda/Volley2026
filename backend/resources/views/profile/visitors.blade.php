<x-voll-layout body_class="visitors-page">

    <x-slot name="title">Мои гости — Volley</x-slot>
    <x-slot name="canonical">{{ route('profile.visitors') }}</x-slot>
    <x-slot name="h1">Мои гости 👀</x-slot>
    <x-slot name="t_description">Кто заходил на вашу страницу за последние 7 дней</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.visitors') }}" itemprop="item"><span itemprop="name">Мои гости</span></a>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>

    <div class="container">
        <div class="ramka">

            @include('profile._menu', [
                'menuUser'       => auth()->user(),
                'isEditingOther' => false,
                'activeMenu'     => 'visitors',
            ])

            @if($visitors->isEmpty())
            <div class="text-center" style="padding:4rem 0;color:#888;">
                <div style="font-size:4rem;margin-bottom:1.5rem;">👀</div>
                <div style="font-size:1.8rem;">За последние 7 дней никто не заходил</div>
                <div style="font-size:1.5rem;margin-top:1rem;color:#aaa;">
                    Заполните профиль — вас будут чаще находить
                </div>
            </div>
            @else
            <div class="row" style="margin-top:2rem;">
                @foreach($visitors as $visit)
                @php $visitor = $visit->visitor; @endphp
                @if(!$visitor) @continue @endif
                <div class="col-md-4 col-6" style="margin-bottom:2rem;">
                    <div class="card">
                        <div style="display:flex;align-items:center;gap:1.5rem;padding:1.5rem;">
                            <a href="{{ route('users.show', $visitor->id) }}">
                                <span class="{{ $visitor->isPremium() ? 'avatar-premium' : '' }}" style="display:inline-block;position:relative;">
                                    <img src="{{ $visitor->profile_photo_url ?? asset('img/no-avatar.png') }}"
                                         style="width:5rem;height:5rem;border-radius:50%;object-fit:cover;">
                                </span>
                            </a>
                            <div>
                                <div style="font-weight:700;font-size:1.6rem;">
                                    <a href="{{ route('users.show', $visitor->id) }}">{{ $visitor->name }}</a>
                                    @if($visitor->isPremium()) 👑 @endif
                                </div>
                                <div style="color:#888;font-size:1.4rem;">
                                    {{ $visit->visited_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                        <div style="padding:0 1.5rem 1.5rem;display:flex;gap:1rem;">
                            <a href="{{ route('users.show', $visitor->id) }}"
                               class="btn btn-secondary" style="flex:1;text-align:center;font-size:1.4rem;">
                                Профиль
                            </a>
                            @if(!auth()->user()->isFriendWith($visitor->id))
                            <form method="POST" action="{{ route('friends.store', $visitor->id) }}" style="flex:1;">
                                @csrf
                                <button class="btn" style="width:100%;font-size:1.4rem;">
                                    + Друг
                                </button>
                            </form>
                            @else
                            <span style="flex:1;text-align:center;font-size:1.4rem;padding-top:0.8rem;color:#888;">
                                ✅ В друзьях
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

        </div>
    </div>
</x-voll-layout>
