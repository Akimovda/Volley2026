{{-- resources/views/users/index.blade.php --}}
<x-voll-layout body_class="users-page">

    <x-slot name="title">
        Игроки — Страница {{ request()->page ?? 1 }}
    </x-slot>

    <x-slot name="description">
        @if(request()->has('role'))
            Игроки с ролью {{ request()->role }}
        @else
            Все игроки платформы
        @endif
    </x-slot>

    <x-slot name="canonical">
        {{ route('users.index') }}
    </x-slot>

    <x-slot name="style">
        <link href="/assets/volley.css" rel="stylesheet">
        <style>
            .users-filters-sticky {
                position: sticky;
                top: 8rem; /* под шапку */
            }
            @media (max-width: 992px) {
                .users-filters-sticky { position: static; top: auto; }
            }
        </style>
    </x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('users.index') }}" itemprop="item">
                <span itemprop="name">Игроки</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>

    <x-slot name="h1">Игроки</x-slot>

    {{-- Сайдбар: фильтры (sticky) --}}
    <x-slot name="sidebar">
        <div class="users-filters-sticky">
            <div class="ramka">
                <div style="font-weight:600; margin-bottom: 1rem;">Фильтры</div>

                <form method="GET" action="{{ route('users.index') }}" class="form">
                    <div style="display:flex; flex-direction:column; gap: 1.2rem;">
                        <div>
                            <label class="block mb-1 font-medium">Поиск</label>
                            <input
                                name="q"
                                class="v-input w-full"
                                value="{{ $filters['q'] ?? '' }}"
                                placeholder="Имя / фамилия / @telegram"
                            />
                            <div class="text-xs text-gray-500 mt-1">Например: “Иван”, “@nickname”.</div>
                        </div>

                        <div>
                            <label class="block mb-1 font-medium">Город</label>
                            <select name="city_id" class="v-input w-full">
                                <option value="">— любой —</option>
                                @foreach($cities as $c)
                                    <option value="{{ $c->id }}" @selected((string)($filters['city_id'] ?? '') === (string)$c->id)>
                                        {{ $c->name }}@if($c->region) ({{ $c->region }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block mb-1 font-medium">Пол</label>
                            <select name="gender" class="v-input w-full">
                                <option value="">— любой —</option>
                                <option value="m" @selected(($filters['gender'] ?? '') === 'm')>Мужчина</option>
                                <option value="f" @selected(($filters['gender'] ?? '') === 'f')>Женщина</option>
                            </select>
                        </div>

                        <div>
                            <label class="block mb-1 font-medium">Уровень (классика)</label>
                            <input
                                name="classic_level"
                                class="v-input w-full"
                                value="{{ $filters['classic_level'] ?? '' }}"
                                placeholder="1..7"
                                inputmode="numeric"
                            />
                        </div>

                        <div>
                            <label class="block mb-1 font-medium">Уровень (пляж)</label>
                            <input
                                name="beach_level"
                                class="v-input w-full"
                                value="{{ $filters['beach_level'] ?? '' }}"
                                placeholder="1..7"
                                inputmode="numeric"
                            />
                        </div>

                        <div>
                            <label class="block mb-1 font-medium">Возраст</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input
                                        name="age_min"
                                        class="v-input w-full"
                                        value="{{ $filters['age_min'] ?? '' }}"
                                        placeholder="от, напр. 18"
                                        inputmode="numeric"
                                    />
                                </div>
                                <div class="col-6">
                                    <input
                                        name="age_max"
                                        class="v-input w-full"
                                        value="{{ $filters['age_max'] ?? '' }}"
                                        placeholder="до, напр. 45"
                                        inputmode="numeric"
                                    />
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; gap: 1rem; flex-wrap:wrap;">
                            <button class="btn" type="submit">Искать</button>
                            <a class="btn btn-secondary" href="{{ route('users.index') }}">Сбросить</a>
                        </div>

                        @if(!empty(array_filter($filters ?? [])))
                            <div>
                                <a class="v-btn v-btn--secondary" href="{{ route('users.index') }}">
                                    Сбросить фильтры
                                </a>
                            </div>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="container">
        <div class="ramka">
            {{-- Header line --}}
            <div class="row align-items-center g-2" style="margin-bottom: 1.6rem;">
                <div class="col-12 col-md">
                    <div style="display:flex; align-items:baseline; gap: 1.2rem; flex-wrap:wrap;">
                        <div style="font-weight:600; font-size:2rem;">Каталог игроков</div>
                        <div style="opacity:.75;">
                            Найдено: <span style="font-weight:600;">{{ (int)($users->total() ?? $users->count() ?? 0) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-auto">
                    @if(!empty(array_filter($filters ?? [])))
                        <a class="v-btn v-btn--secondary" href="{{ route('users.index') }}">
                            Сбросить фильтры
                        </a>
                    @endif
                </div>
            </div>

            {{-- Results --}}
            @if(($users ?? collect())->isEmpty())
                <div class="v-card">
                    <div class="v-card__body text-sm text-gray-600">
                        Ничего не найдено. Попробуй сбросить фильтры или изменить условия поиска.
                    </div>
                </div>
            @else
                <div class="row g-3">
                    @foreach($users as $u)
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2">
                            @include('users._card', ['u' => $u])
                        </div>
                    @endforeach
                </div>

                <div style="margin-top: 2rem;">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>

</x-voll-layout>
