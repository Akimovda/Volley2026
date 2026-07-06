{{-- resources/views/pages/help.blade.php --}}
<x-voll-layout body_class="help">
<x-slot name="title">{{ __('pages.help_title') }}</x-slot>
<x-slot name="description">{{ __('pages.help_description') }}</x-slot>
<x-slot name="t_description">{{ __('pages.help_t_description') }}</x-slot>
<x-slot name="canonical">{{ route('help') }}</x-slot>
<x-slot name="breadcrumbs">
<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
<a href="{{ route('help') }}" itemprop="item"><span itemprop="name">{{ __('pages.help_breadcrumb') }}</span></a>
<meta itemprop="position" content="2">
</li>
</x-slot>
<x-slot name="h1">{{ __('pages.help_h1') }}</x-slot>

<div class="container">
<div class="row row2">

{{-- SIDEBAR --}}
<div class="col-lg-5 col-xl-4 order-1 order-lg-1">
<div class="sticky">
<div class="ramka">
<nav class="menu-nav">
<div class="tabs-content">
<div class="tabs w-100">
    <div class="tab active" data-tab="player">{{ __('help.tab_player') }}</div>
    <div class="tab" data-tab="org">{{ __('help.tab_org') }}</div>
    <div class="tab-highlight"></div>
</div>
<div class="tab-panes">

<div class="tab-pane active" id="player">
    <a href="#p1" class="menu-item"><span class="menu-text">{{ __('help.menu_p1') }}</span></a>
    <a href="#p2" class="menu-item"><span class="menu-text">{{ __('help.menu_p2') }}</span></a>
    <a href="#p-app" class="menu-item"><span class="menu-text">{{ __('help.menu_p_app') }}</span></a>
    <a href="#p3" class="menu-item"><span class="menu-text">{{ __('help.menu_p3') }}</span></a>
    <a href="#p-waitlist" class="menu-item"><span class="menu-text">{{ __('help.menu_p_waitlist') }}</span></a>
    <a href="#p-team" class="menu-item"><span class="menu-text">{{ __('help.menu_p_team') }}</span></a>
    <a href="#p-booking" class="menu-item"><span class="menu-text">{{ __('help.menu_p_booking') }}</span></a>
    <a href="#p-activity" class="menu-item"><span class="menu-text">{{ __('help.menu_p_activity') }}</span></a>
    <a href="#p4" class="menu-item"><span class="menu-text">{{ __('help.menu_p4') }}</span></a>
    <a href="#p5" class="menu-item"><span class="menu-text">{{ __('help.menu_p5') }}</span></a>
    <a href="#p6" class="menu-item"><span class="menu-text">{{ __('help.menu_p6') }}</span></a>
    <a href="#p7" class="menu-item"><span class="menu-text">{{ __('help.menu_p7') }}</span></a>
    <a href="#p-rating" class="menu-item"><span class="menu-text">{{ __('help.menu_p_rating') }}</span></a>
</div>

<div class="tab-pane" id="org">
    <a href="#o1" class="menu-item"><span class="menu-text">{{ __('help.menu_o1') }}</span></a>
    <a href="#o2" class="menu-item"><span class="menu-text">{{ __('help.menu_o2') }}</span></a>
    <a href="#o3" class="menu-item"><span class="menu-text">{{ __('help.menu_o3') }}</span></a>
    <a href="#o-waitlist" class="menu-item"><span class="menu-text">{{ __('help.menu_o_waitlist') }}</span></a>
    <a href="#o-tournament" class="menu-item"><span class="menu-text">{{ __('help.menu_o_tournament') }}</span></a>
    <a href="#o-league" class="menu-item"><span class="menu-text">{{ __('help.menu_o_league') }}</span></a>
    <a href="#o-venue" class="menu-item"><span class="menu-text">{{ __('help.menu_o_venue') }}</span></a>
    <a href="#o4" class="menu-item"><span class="menu-text">{{ __('help.menu_o4') }}</span></a>
    <a href="#o6" class="menu-item"><span class="menu-text">{{ __('help.menu_o6') }}</span></a>
    <a href="#o-crm" class="menu-item"><span class="menu-text">{{ __('help.menu_o_crm') }}</span></a>
    <a href="#o7" class="menu-item"><span class="menu-text">{{ __('help.menu_o7') }}</span></a>
</div>

</div>
</div>
</nav>
</div>
</div>
</div>

{{-- CONTENT --}}
<div class="col-lg-7 col-xl-8 order-2 order-lg-2">

{{-- ИГРОК --}}
<div class="ramka">

<h2 class="-mt-05" id="p1">{{ __('help.p1_h') }}</h2>
<p>{!! __('help.p1_p1') !!}</p>
<p>{!! __('help.p1_p2') !!}</p>
<p>{!! __('help.p1_p3') !!}</p>

<h2 id="p2">{{ __('help.p2_h') }}</h2>
<p>{!! __('help.p2_p1') !!}</p>
<p>{!! __('help.p2_p2', ['profile_url' => route('profile.show')]) !!}</p>
<p>{!! __('help.p2_p3') !!}</p>
<p>{!! __('help.p2_p4') !!}</p>

<h2 id="p-app">{{ __('help.p_app_h') }}</h2>
<p>{!! __('help.p_app_p1') !!}</p>
<ul class="list">
    <li>{!! __('help.p_app_li1') !!}</li>
    <li>{!! __('help.p_app_li2') !!}</li>
</ul>
<p>{!! __('help.p_app_p2') !!}</p>
<ul class="list">
    <li>{!! __('help.p_app_li3') !!}</li>
    <li>{!! __('help.p_app_li4') !!}</li>
    <li>{!! __('help.p_app_li5') !!}</li>
</ul>
<p>{!! __('help.p_app_p3') !!}</p>

<h2 id="p3">{{ __('help.p3_h') }}</h2>
<p>{!! __('help.p3_p1', ['events_url' => route('events.index')]) !!}</p>
<p>{!! __('help.p3_p2') !!}</p>
<p>{!! __('help.p3_p3') !!}</p>

<h2 id="p-waitlist">{{ __('help.p_waitlist_h') }}</h2>
<p>{!! __('help.p_waitlist_p1') !!}</p>
<p>{!! __('help.p_waitlist_p2') !!}</p>
<p>{!! __('help.p_waitlist_p3') !!}</p>
<p>{!! __('help.p_waitlist_p4') !!}</p>

<h2 id="p-team">{{ __('help.p_team_h') }}</h2>
<p>{!! __('help.p_team_p1') !!}</p>
<ul class="list">
    <li>{!! __('help.p_team_li1') !!}</li>
    <li>{!! __('help.p_team_li2') !!}</li>
    <li>{!! __('help.p_team_li3') !!}</li>
</ul>
<p>{!! __('help.p_team_p2', ['profile_url' => route('profile.show')]) !!}</p>
<p>{!! __('help.p_team_p3') !!}</p>

<h2 id="p-booking">{{ __('help.p_booking_h') }}</h2>
<p>{!! __('help.p_booking_p1') !!}</p>
<p>{!! __('help.p_booking_p2') !!}</p>
<p>{!! __('help.p_booking_p3') !!}</p>

<h2 id="p-activity">{{ __('help.p_activity_h') }}</h2>
<p>{!! __('help.p_activity_p1') !!}</p>
<ul class="list">
    <li>{!! __('help.p_activity_li1') !!}</li>
    <li>{!! __('help.p_activity_li2') !!}</li>
    <li>{!! __('help.p_activity_li3') !!}</li>
</ul>
<p>{!! __('help.p_activity_p2') !!}</p>

<h2 id="p4">{{ __('help.p4_h') }}</h2>
<p>{!! __('help.p4_p1') !!}</p>
<p>{!! __('help.p4_p2') !!}</p>
<p>{!! __('help.p4_p3', ['profile_url' => route('profile.show')]) !!}</p>

<h2 id="p5">{{ __('help.p5_h') }}</h2>
<p>{!! __('help.p5_p1') !!}</p>
<p>{!! __('help.p5_p2') !!}</p>
<p>{!! __('help.p5_p3') !!}</p>

<h2 id="p6">{{ __('help.p6_h') }}</h2>
<p>{!! __('help.p6_p1') !!}</p>
<p>{!! __('help.p6_p2') !!}</p>
<p>{!! __('help.p6_p3') !!}</p>

<h2 id="p7">{{ __('help.p7_h') }}</h2>
<p>{!! __('help.p7_p1') !!}</p>
<p>{!! __('help.p7_p2') !!}</p>
<p>{!! __('help.p7_p3') !!}</p>
<p>{!! __('help.p7_p4') !!}</p>

<h2 id="p-rating">{{ __('help.p_rating_h') }}</h2>
<p>{!! __('help.p_rating_p1') !!}</p>
<p>{!! __('help.p_rating_p2') !!}</p>
<ul class="list">
    <li>{!! __('help.p_rating_li1') !!}</li>
    <li>{!! __('help.p_rating_li2') !!}</li>
    <li>{!! __('help.p_rating_li3') !!}</li>
</ul>
<p>{!! __('help.p_rating_p3', ['rating_url' => route('players.rating'), 'rating_info_url' => route('pages.rating_info')]) !!}</p>

</div>

{{-- ОРГАНИЗАТОР --}}
<div class="ramka">

<h2 class="-mt-05" id="o1">{{ __('help.o1_h') }}</h2>
<p>{!! __('help.o1_p1', ['profile_url' => route('profile.show')]) !!}</p>
<p>{!! __('help.o1_p2') !!}</p>

<h2 id="o2">{{ __('help.o2_h') }}</h2>
<p>{!! __('help.o2_p1') !!}</p>
<ul class="list">
    <li>{!! __('help.o2_li1') !!}</li>
    <li>{!! __('help.o2_li2') !!}</li>
    <li>{!! __('help.o2_li3') !!}</li>
</ul>
<p>{!! __('help.o2_p2') !!}</p>

<h2 id="o3">{{ __('help.o3_h') }}</h2>
<p>{!! __('help.o3_p1') !!}</p>
<ul class="list">
    <li>{!! __('help.o3_li1') !!}</li>
    <li>{!! __('help.o3_li2') !!}</li>
    <li>{!! __('help.o3_li3') !!}</li>
    <li>{!! __('help.o3_li4') !!}</li>
    <li>{!! __('help.o3_li5') !!}</li>
</ul>
<p>{!! __('help.o3_p2') !!}</p>

<h2 id="o-waitlist">{{ __('help.o_waitlist_h') }}</h2>
<p>{!! __('help.o_waitlist_p1') !!}</p>
<p>{!! __('help.o_waitlist_p2') !!}</p>
<p>{!! __('help.o_waitlist_p3') !!}</p>
<p>{!! __('help.o_waitlist_p4') !!}</p>

<h2 id="o-tournament">{{ __('help.o_tournament_h') }}</h2>
<p>{!! __('help.o_tournament_p1') !!}</p>
<ul class="list">
    <li>{!! __('help.o_tournament_li1') !!}</li>
    <li>{!! __('help.o_tournament_li2') !!}</li>
    <li>{!! __('help.o_tournament_li3') !!}</li>
    <li>{!! __('help.o_tournament_li4') !!}</li>
</ul>
<p>{!! __('help.o_tournament_p2') !!}</p>

<h2 id="o-league">{{ __('help.o_league_h') }}</h2>
<p>{!! __('help.o_league_p1') !!}</p>
<ul class="list">
    <li>{!! __('help.o_league_li1') !!}</li>
    <li>{!! __('help.o_league_li2') !!}</li>
    <li>{!! __('help.o_league_li3') !!}</li>
</ul>
<p>{!! __('help.o_league_p2') !!}</p>
<p>{!! __('help.o_league_p3') !!}</p>

<h2 id="o-venue">{{ __('help.o_venue_h') }}</h2>
<p>{!! __('help.o_venue_p1') !!}</p>
<p>{!! __('help.o_venue_p2') !!}</p>
<p>{!! __('help.o_venue_p3') !!}</p>
<ul class="list">
    <li>{!! __('help.o_venue_li1') !!}</li>
    <li>{!! __('help.o_venue_li2') !!}</li>
    <li>{!! __('help.o_venue_li3') !!}</li>
</ul>
<p>{!! __('help.o_venue_p4') !!}</p>

<h2 id="o4">{{ __('help.o4_h') }}</h2>
<p>{!! __('help.o4_p1', ['profile_url' => route('profile.show')]) !!}</p>
<p>{!! __('help.o4_p2') !!}</p>
<p>{!! __('help.o4_p3') !!}</p>
<p>{!! __('help.o4_p4') !!}</p>

<h2 id="o6">{{ __('help.o6_h') }}</h2>
<p>{!! __('help.o6_p1') !!}</p>
<p>{!! __('help.o6_p2') !!}</p>
<p>{!! __('help.o6_p3') !!}</p>

<h2 id="o-crm">{{ __('help.o_crm_h') }}</h2>
<p>{!! __('help.o_crm_p1') !!}</p>
<ul class="list">
    <li>{!! __('help.o_crm_li1') !!}</li>
    <li>{!! __('help.o_crm_li2') !!}</li>
    <li>{!! __('help.o_crm_li3') !!}</li>
    <li>{!! __('help.o_crm_li4') !!}</li>
    <li>{!! __('help.o_crm_li5') !!}</li>
</ul>
<p>{!! __('help.o_crm_p2') !!}</p>

<h2 id="o7">{{ __('help.o7_h') }}</h2>
<p>{!! __('help.o7_p1') !!}</p>
<p>{!! __('help.o7_p2') !!}</p>

</div>

{{-- КОНТАКТЫ --}}
<div class="ramka">
<h2 class="-mt-05">{{ __('help.contact_h') }}</h2>
<p>{{ __('help.contact_p1') }}</p>
<div class="d-flex gap-1 flex-wrap mt-2 fc">
    @if(config('services.telegram.bot_username'))
    <a href="https://t.me/{{ config('services.telegram.bot_username') }}" target="_blank" class="btn">
        {{ __('help.contact_telegram_btn') }}
    </a>
    @endif
    @if(config('services.vk.bot_link'))
    <a href="{{ config('services.vk.bot_link') }}" target="_blank" class="btn">
        {{ __('help.contact_vk_btn') }}
    </a>
    @endif
</div>
</div>

</div>
</div>
</div>
</x-voll-layout>
