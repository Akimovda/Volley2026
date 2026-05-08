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

                <button class="btn" type="submit">Сохранить настройки</button>

                @if(session('status'))
                <div class="mt-2 f-16" style="color:#4caf50;">{{ session('status') }}</div>
                @endif

            </form>

        </div>
    </div>

</x-voll-layout>
