{{-- resources/views/profile/athlete.blade.php --}}
<x-voll-layout body_class="profile-page">

    <x-slot name="title">{{ __('activity.page_title') }}</x-slot>
    <x-slot name="h1">{{ __('activity.page_title') }}</x-slot>
    <x-slot name="h2">{{ __('activity.page_subtitle') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">{{ __('profile.menu_my_profile') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('activity.page_title') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka">
                        @include('profile._menu', [
                            'menuUser'   => $user,
                            'activeMenu' => 'athlete',
                        ])
                    </div>
                </div>
            </div>
            <div class="col-lg-8 col-xl-9 order-1">

                @if(session('status'))
                    <div class="alert alert-success mb-2">{{ session('status') }}</div>
                @endif

                <div class="ramka">
                    <h2 class="-mt-05">{{ __('activity.settings_heading') }}</h2>

                    <form method="POST" action="{{ route('profile.athlete.update') }}" class="form">
                        @csrf

                        <div class="row">

                            {{-- Пульс в покое --}}
                            <div class="col-sm-6">
                                <div class="card">
                                    <label>
                                        <div>{{ __('activity.resting_hr_label') }}</div>
                                    </label>
                                    <input type="number"
                                           name="resting_hr"
                                           class="{{ $errors->has('resting_hr') ? 'input-error' : '' }}"
                                           value="{{ old('resting_hr', $profile?->resting_hr) }}"
                                           min="30" max="100"
                                           placeholder="{{ __('activity.resting_hr_placeholder') }}">
                                    <ul class="list f-16 mt-1">
                                        @error('resting_hr')<li class="red b-600">{{ $message }}</li>@enderror
                                        <li>{{ __('activity.resting_hr_hint') }}</li>
                                    </ul>
                                </div>
                            </div>

                            {{-- Максимальный пульс --}}
                            <div class="col-sm-6">
                                <div class="card">
                                    <label>
                                        <div>{{ __('activity.max_hr_label') }}</div>
                                    </label>
                                    <input type="number"
                                           name="max_hr"
                                           class="{{ $errors->has('max_hr') ? 'input-error' : '' }}"
                                           value="{{ old('max_hr', $profile?->max_hr) }}"
                                           min="100" max="250"
                                           placeholder="{{ __('activity.max_hr_placeholder') }}">
                                    <ul class="list f-16 mt-1">
                                        @error('max_hr')<li class="red b-600">{{ $message }}</li>@enderror
                                        @if($suggestedMaxHr)
                                            <li>{{ __('activity.max_hr_age_hint', ['bpm' => $suggestedMaxHr]) }}</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>

                            {{-- Вес (опционально) --}}
                            <div class="col-sm-6">
                                <div class="card">
                                    <label>
                                        <div>{{ __('activity.weight_label') }}</div>
                                    </label>
                                    <input type="number"
                                           name="weight_kg"
                                           class="{{ $errors->has('weight_kg') ? 'input-error' : '' }}"
                                           value="{{ old('weight_kg', $profile?->weight_kg) }}"
                                           min="30" max="300" step="0.1"
                                           placeholder="{{ __('activity.weight_placeholder') }}">
                                    <ul class="list f-16 mt-1">
                                        @error('weight_kg')<li class="red b-600">{{ $message }}</li>@enderror
                                        <li>{{ __('activity.weight_hint') }}</li>
                                    </ul>
                                </div>
                            </div>

                        </div>

                        <button type="submit" class="btn btn-primary mt-1">{{ __('activity.save_btn') }}</button>
                    </form>
                </div>

                {{-- Зоны ЧСС --}}
                <div class="ramka mt-2">
                    <h3 class="-mt-05">{{ __('activity.zones_heading') }}</h3>
                    <p class="f-14 cd2">{{ __('activity.zones_description') }}</p>
                    <div class="row">
                        @foreach(['z1','z2','z3','z4','z5'] as $z)
                            <div class="col-sm-6 col-md col-lg mb-1">
                                <div class="card text-center py-1">
                                    <div class="b-700 f-16">{{ strtoupper($z) }}</div>
                                    <div class="f-14 cd2">{{ __('activity.' . $z . '_name') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="f-12 cd3 mt-1 mb-0">{{ __('activity.zones_karvonen_note') }}</p>
                </div>

            </div>
        </div>
    </div>

</x-voll-layout>
