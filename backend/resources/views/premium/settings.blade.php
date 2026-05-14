<x-voll-layout body_class="premium-settings-page">

    <x-slot name="title">{{ __('profile.premium_settings_title') }}</x-slot>
    <x-slot name="canonical">{{ route('premium.settings') }}</x-slot>
    <x-slot name="h1">{{ __('profile.premium_settings_h1') }}</x-slot>
    <x-slot name="t_description">{{ __('profile.premium_settings_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('premium.index') }}" itemprop="item"><span itemprop="name">Premium</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Настройки</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">
        <div class="ramka">

            <div class="card" style="margin-bottom:2rem;padding:2rem;">
                <div class="f-15" style="opacity:.6;">
                    Подписка активна до <strong>{{ $sub->expires_at->format('d.m.Y') }}</strong>
                    · план: {{ match($sub->plan) {
                        'trial'   => 'Пробный',
                        'month'   => '1 месяц',
                        'quarter' => '3 месяца',
                        'year'    => 'Год',
                    } }}
                </div>
            </div>

            <form method="POST" action="{{ route('premium.settings.update') }}" class="form">
                @csrf

                {{-- Недельная сводка --}}
                <div class="ramka" style="margin-bottom:2rem;">
                    <h2 class="-mt-05">🔔 Недельная сводка</h2>
                    <div class="f-15 mb-2" style="opacity:.6;">
                        Каждый понедельник в 09:00 — список игр на неделю в вашем городе
                    </div>

                    <label class="checkbox-item">
                        <input type="checkbox" name="weekly_digest" value="1"
                               {{ $sub->weekly_digest ? 'checked' : '' }}>
                        <div class="custom-checkbox"></div>
                        <span>Получать недельную сводку игр</span>
                    </label>
                </div>

                {{-- Фильтр по уровню --}}
                <div class="ramka" style="margin-bottom:2rem;">
                    <h2 class="-mt-05">🎯 Фильтр по уровню игры</h2>
                    <div class="f-15 mb-2" style="opacity:.6;">
                        Получайте уведомления только об играх вашего уровня
                    </div>

                    <div class="row row2">
                        <div class="col-6">
                            <label class="f-15 mb-05">Уровень от</label>
                            <select name="notify_level_min">
                                <option value="">Любой</option>
                                @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}" {{ $sub->notify_level_min == $i ? 'selected' : '' }}>
                                    {{ $i }}
                                </option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="f-15 mb-05">Уровень до</label>
                            <select name="notify_level_max">
                                <option value="">Любой</option>
                                @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}" {{ $sub->notify_level_max == $i ? 'selected' : '' }}>
                                    {{ $i }}
                                </option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Приватность: скрывать свои записи от подписчиков --}}
                <div class="ramka" style="margin-bottom:2rem;">
                    <h2 class="-mt-05">🔒 Приватность записей</h2>
                    <div class="f-15 mb-2" style="opacity:.6;">
                        Если включено — ваши друзья с Premium не будут получать уведомления о ваших записях на мероприятия
                    </div>

                    <label class="checkbox-item">
                        <input type="checkbox" name="hide_from_followers" value="1"
                               {{ $sub->hide_from_followers ? 'checked' : '' }}>
                        <div class="custom-checkbox"></div>
                        <span>Скрывать мои записи от подписчиков</span>
                    </label>
                </div>

                <button class="btn" type="submit">Сохранить настройки</button>

                @if(session('status'))
                <div class="mt-2 f-16" style="color:#4caf50;">{{ session('status') }}</div>
                @endif

            </form>

            {{-- Секция: Слежу за записями --}}
            <div class="ramka mt-3">
                <h2 class="-mt-05">👁 Слежу за записями</h2>
                <div class="f-15 mb-2" style="opacity:.6;">
                    Вы будете получать уведомления, когда эти игроки записываются на мероприятия
                </div>

                {{-- Список отслеживаемых --}}
                @if($follows->isEmpty())
                <div class="f-15 mb-2" style="opacity:.5;">Вы пока не следите ни за кем.</div>
                @else
                <div class="mb-2">
                    @foreach($follows as $follow)
                    @php $followed = $follow->followed; @endphp
                    <div class="d-flex fvc between mb-1 p-1" style="background:rgba(41,103,186,.06);border-radius:8px;gap:10px">
                        <div class="d-flex fvc" style="gap:10px">
                            @if($followed?->avatar_media_id)
                            <img src="{{ $followed->getFirstMediaUrl('avatar', 'thumb') }}" style="width:36px;height:36px;border-radius:50%;object-fit:cover" alt="">
                            @else
                            <div style="width:36px;height:36px;border-radius:50%;background:rgba(41,103,186,.2);display:flex;align-items:center;justify-content:center;font-size:16px">👤</div>
                            @endif
                            <div>
                                <div class="b-600">{{ $followed?->name ?? '—' }}</div>
                                @if($followed)
                                <a href="{{ route('users.show', $followed->id) }}" class="f-13 cd" style="opacity:.6">Профиль</a>
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('premium.follows.destroy', $follow->followed_user_id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-secondary btn-small" style="font-size:12px">Отписаться</button>
                        </form>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Добавить нового --}}
                @if($friends->isNotEmpty())
                <div class="mt-2">
                    <div class="f-14 mb-1 b-600">Добавить из друзей:</div>
                    <div class="d-flex" style="flex-wrap:wrap;gap:8px">
                        @foreach($friends as $friend)
                        <form method="POST" action="{{ route('premium.follows.store', $friend->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-small" style="font-size:12px">
                                + {{ $friend->name }}
                            </button>
                        </form>
                        @endforeach
                    </div>
                </div>
                @elseif($follows->isNotEmpty())
                <div class="f-14 mt-1" style="opacity:.5;">Все ваши друзья уже в списке.</div>
                @else
                <div class="f-14 mt-1" style="opacity:.5;">Добавьте друзей, чтобы подписаться на их записи.</div>
                @endif
            </div>

        </div>
    </div>

</x-voll-layout>
