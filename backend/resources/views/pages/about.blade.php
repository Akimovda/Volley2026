{{-- resources/views/pages/about.blade.php --}}
<x-voll-layout body_class="about">
<x-slot name="title">{{ __('pages.about_title') }}</x-slot>
<x-slot name="description">{{ __('pages.about_description') }}</x-slot>
<x-slot name="t_description">{{ __('pages.about_t_description') }}</x-slot>
<x-slot name="canonical">{{ route('about') }}</x-slot>
<x-slot name="breadcrumbs">
<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
<a href="{{ route('about') }}" itemprop="item"><span itemprop="name">{{ __('pages.about_breadcrumb') }}</span></a>
<meta itemprop="position" content="2">
</li>
</x-slot>
<x-slot name="h1">{{ __('pages.about_h1') }}</x-slot>

<x-slot name="style">
    <style>
        html.is-app .about-store-buttons { display: none; }
        .about-hero-link { color: #fff; }
        .about-hero-link:before { border-bottom-color: #fff; }
        .about-hero-link:after { border-bottom-color: rgba(255, 255, 255, .45); }
        .about-store-logo { display: block; }
        .about-bot-icon { width: 5.6rem; height: 5.6rem; margin: 0 auto .5rem; }
        .about-bot-icon svg { width: 2.8rem; height: 2.8rem; }
        .about-store-logo img { display: block; width: auto; height: 5.5rem; border-radius: 1rem; border: 1px solid rgba(41, 103, 186, .3); transition: transform .25s ease, box-shadow .25s ease; }
        .about-store-logo:hover img { transform: translateY(-.2rem); box-shadow: 0 .4rem 1.2rem rgba(41, 103, 186, .2); }
        body.dark .about-store-logo img { filter: invert(94%); }
    </style>
</x-slot>

<div class="container">
@php
    $a = trans('pages.about');
    $canOrgDash = auth()->check() && in_array(auth()->user()->role, ['organizer', 'admin'], true);
    $orgApplyUrl = route('profile.show') . '#organizer-request';
    // Порядок секций и кнопки под ними (тексты — lang/*/pages.php → about.sections.<id>)
    $sectionOrder = ['players', 'premium', 'rating', 'activity', 'tournaments', 'stats_game', 'leagues', 'club', 'org', 'pro', 'white_label', 'school', 'app'];
    $sectionButtons = [
        'premium'     => [[route('premium.index'), 'btn_premium', 'link']],
        'rating'      => [[route('players.rating'), 'btn_rating', 'link'], [route('players.teams'), 'btn_teams', 'link'], [route('pages.rating_info'), 'btn_rating_info', 'link']],
        'activity'    => [[route('activity.index'), 'btn_activity', 'link']],
        'leagues'     => [[route('leagues.public'), 'btn_leagues', 'link']],
        'club'        => [[route('locations.index'), 'btn_locations', 'link']],
        'org'         => array_filter([[$orgApplyUrl, 'btn_apply_org', 'primary'], $canOrgDash ? [route('org.dashboard'), 'btn_org_dash', 'link'] : null]),
        'pro'         => [[route('organizer_pro.index'), 'btn_pro', 'link']],
        'white_label' => [['https://t.me/akimovda', 'btn_wl', 'link']],
        'school'      => [[route('volleyball_school.index'), 'btn_schools', 'link']],
        'app'         => [['https://apps.apple.com/ru/app/volleyclub/id6764748613', 'btn_ios', '/img/appstore.png'], ['https://www.rustore.ru/catalog/app/club.volleyplay.app', 'btn_android', '/img/rustore.png'], [config('app.android_apk_url'), 'btn_apk', '/img/apk-android.svg']],
    ];
@endphp

{{-- HERO --}}
<div class="ramka text-center" style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);color:#fff;padding:3rem 2rem">
    <div style="font-size:3rem;margin-bottom:1rem">🏐</div>
    <h2 style="color:#fff;font-size:1.8rem;margin-bottom:1rem">VolleyPlay.Club</h2>
    <p style="font-size:1.15rem;opacity:.9;max-width:660px;margin:0 auto 1.5rem">{{ $a['hero_text'] }}</p>
    <div><a href="{{ route('events.index') }}" class="btn">{{ $a['btn_find'] }}</a></div>
    <div class="d-flex flex-wrap mt-2" style="justify-content:center;gap:.8rem 2.4rem">
        <a href="{{ route('players.rating') }}" class="blink b-600 about-hero-link">{{ $a['btn_rating'] }} →</a>
        <a href="{{ route('about') }}#org" class="blink b-600 about-hero-link">{{ $a['btn_become_org'] }} →</a>
    </div>
</div>

{{-- ПОЧЕМУ VOLLEY CLUB --}}
<div class="ramka">
    <div class="row">
        <div class="col-md-6">
            <div class="card" style="height:100%">
                <h3 style="margin:.25rem 0 .75rem">{{ $a['why_player_title'] }}</h3>
                <p class="f-15" style="opacity:.85">{{ $a['why_player_text'] }}</p>
                <ul class="list f-15">
                    @foreach($a['why_player_items'] as $item)
                    <li>{{ $item }}</li>
                    @endforeach
                </ul>
                <div class="mt-2"><a href="{{ route('events.index') }}" class="btn">{{ $a['btn_find'] }}</a></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card" style="height:100%">
                <h3 style="margin:.25rem 0 .75rem">{{ $a['why_org_title'] }}</h3>
                <p class="f-15" style="opacity:.85">{{ $a['why_org_text'] }}</p>
                <ul class="list f-15">
                    @foreach($a['why_org_items'] as $item)
                    <li>{{ $item }}</li>
                    @endforeach
                </ul>
                <div class="mt-2"><a href="{{ route('about') }}#org" class="btn">{{ $a['btn_become_org'] }}</a></div>
            </div>
        </div>
    </div>
</div>

{{-- ДЛЯ КОГО --}}
<div class="ramka">
    <h2 class="-mt-05">{{ $a['audience_title'] }}</h2>
    <div class="row mt-2">
        @foreach($a['audiences'] as [$icon, $title, $text])
        <div class="col-md-6 col-lg-4">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2.5rem">{{ $icon }}</div>
                <h3 style="margin:.5rem 0">{{ $title }}</h3>
                <p class="f-14">{{ $text }}</p>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- СЕКЦИИ ВОЗМОЖНОСТЕЙ --}}
@foreach($sectionOrder as $id)
@php $sec = $a['sections'][$id]; @endphp
<div class="ramka" id="{{ $id === 'players' ? 'players' : ($id === 'stats_game' ? 'stats-game' : ($id === 'white_label' ? 'white-label' : $id)) }}">
    <h2 class="-mt-05">{{ $sec['title'] }}</h2>
    @if(!empty($sec['intro']))
    <p class="f-15" style="opacity:.8;margin-bottom:1.5rem">{{ $sec['intro'] }}</p>
    @endif
    @if(!empty($sec['cards']))
    <div class="row">
        @foreach($sec['cards'] as [$cTitle, $cText])
        <div class="col-md-4">
            <div class="card" style="height:100%">
                <h3 style="margin:.25rem 0 .5rem">{{ $cTitle }}</h3>
                <p class="f-14">{{ $cText }}</p>
            </div>
        </div>
        @endforeach
    </div>
    @endif
    @foreach($sec['blocks'] as $bi => $block)
    <div class="row {{ ($bi > 0 || !empty($sec['cards'])) ? 'mt-1' : '' }}">
        <div class="col-md-6">
            @if(!empty($block['left_title']))<h3 style="margin:.25rem 0 .75rem">{{ $block['left_title'] }}</h3>@endif
            <ul class="list f-15">
                @foreach($block['left'] as $item)<li>{{ $item }}</li>@endforeach
            </ul>
        </div>
        <div class="col-md-6">
            @if(!empty($block['right_title']))<h3 style="margin:.25rem 0 .75rem">{{ $block['right_title'] }}</h3>@endif
            <ul class="list f-15">
                @foreach($block['right'] as $item)<li>{{ $item }}</li>@endforeach
            </ul>
        </div>
    </div>
    @endforeach
    @if(!empty($sectionButtons[$id]))
    <div class="mt-2 d-flex flex-wrap {{ $id === 'app' ? 'about-store-buttons' : '' }}" style="gap:.8rem 2.4rem;align-items:center">
        @foreach($sectionButtons[$id] as [$url, $labelKey, $kind])
        @php $external = parse_url($url, PHP_URL_HOST) !== request()->getHost(); @endphp
        @if($id === 'app')
        {{-- Логотипы магазинов (те же картинки 256×72, что в футере) --}}
        <a href="{{ $url }}" class="about-store-logo" @if(str_contains($url, '/downloads/')) download @else target="_blank" rel="noopener noreferrer" @endif>
            <img src="{{ $kind }}" alt="{{ $a[$labelKey] }}" width="256" height="72" loading="lazy">
        </a>
        @elseif($kind === 'primary')
        {{-- Единственная заливная кнопка секции — главное действие --}}
        <a href="{{ $url }}" class="btn btn-small" @if($external) target="_blank" rel="noopener noreferrer" @endif>{{ $a[$labelKey] }}</a>
        @else
        {{-- Остальное — текстовые ссылки в стиле сайта (blink), чтобы страница не состояла из кнопок --}}
        <a href="{{ $url }}" class="blink b-600" @if($external) target="_blank" rel="noopener noreferrer" @endif>{{ $a[$labelKey] }} →</a>
        @endif
        @endforeach
    </div>
    @endif
</div>
@endforeach

{{-- БОТЫ --}}
<div class="ramka">
    <h2 class="-mt-05">{{ $a['bots_title'] }}</h2>
    <div class="row">
        @foreach($a['bots'] as [$icon, $title, $text])
        <div class="col-md-4">
            <div class="card text-center" style="height:100%">
                {{-- Реальные логотипы сетей: круглые значки провайдеров (SVG подставляет lib.js по классу icon-tg/icon-vk/icon-max) --}}
                <span class="provider-card__icon icon-{{ $icon }} about-bot-icon"></span>
                <h3 style="margin:.5rem 0">{{ $title }}</h3>
                <p class="f-14">{{ $text }}</p>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- ГОРОДА --}}
<div class="ramka">
    <h2 class="-mt-05">{{ $a['cities_title'] }}</h2>
    <div class="d-flex flex-wrap gap-1 mt-2">
        @foreach($a['cities'] as [$city, $emoji])
        <div class="card" style="padding:.5rem 1rem;margin:0;height:auto">
            <span>{{ $emoji }} {{ $city }}</span>
        </div>
        @endforeach
    </div>
    <p class="f-14 mt-2" style="opacity:.6">{{ $a['cities_text'] }} <a href="{{ route('help') }}">{{ $a['cities_contact'] }}</a>.</p>
</div>

{{-- КАК НАЧАТЬ --}}
<div class="ramka">
    <h2 class="-mt-05">{{ $a['start_title'] }}</h2>
    <div class="row mt-2">
        @foreach($a['steps'] as $si => [$title, $text])
        <div class="col-md-3">
            <div class="card text-center" style="height:100%">
                <div style="font-size:2rem;font-weight:700;opacity:.3">{{ sprintf('%02d', $si + 1) }}</div>
                <h3 style="margin:.25rem 0">{{ $title }}</h3>
                <p class="f-14">{{ $text }}</p>
            </div>
        </div>
        @endforeach
    </div>
    <div class="text-center mt-3">
        <a href="{{ route('events.index') }}" class="btn">{{ $a['btn_find_arrow'] }}</a>
    </div>
</div>

</div>
</x-voll-layout>
