{{-- resources/views/admin/apps/index.blade.php --}}
<x-voll-layout body_class="admin-apps-page">

    <x-slot name="title">{{ __('admin.app_title') }} — {{ __('admin.breadcrumb_dashboard') }}</x-slot>
    <x-slot name="h1">{{ __('admin.app_title') }}</x-slot>
    <x-slot name="t_description">{{ __('admin.app_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a itemprop="item" href="{{ route('admin.dashboard') }}">
                <span itemprop="name">{{ __('admin.breadcrumb_dashboard') }}</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('admin.app_title') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">
        <div class="ramka">
            <p class="-mt-05">{{ __('admin.app_list_hint') }}</p>

            <div style="display:flex; flex-wrap:wrap; gap:2.5rem; margin-top:2rem;">
                @foreach($brands as $b)
                    @php
                        $iconUrl = $b->app_icon_url ?: $b->logo_day_url;
                    @endphp
                    <a href="{{ route('admin.apps.edit', $b) }}"
                       style="display:flex; flex-direction:column; align-items:center; width:14rem; text-decoration:none; text-align:center;">
                        <span style="width:11rem; height:11rem; border-radius:2.4rem; overflow:hidden; display:flex; align-items:center; justify-content:center; background:#fff; border:0.2rem solid rgba(0,0,0,0.1); box-shadow:0 0.4rem 1.2rem rgba(0,0,0,0.08);">
                            @if($iconUrl)
                                <img src="{{ $iconUrl }}" alt="{{ $b->display_name }}" style="max-width:100%; max-height:100%; object-fit:contain;">
                            @else
                                <span style="font-size:4rem; font-weight:700; color:#999;">{{ mb_strtoupper(mb_substr($b->display_name, 0, 1)) }}</span>
                            @endif
                        </span>
                        <span style="margin-top:1rem; font-weight:600;">{{ $b->display_name }}</span>
                        <span style="font-size:1.3rem; opacity:.7;">
                            {{ $b->slug }}@if($b->is_default) · {{ __('admin.app_default_badge') }}@endif
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-voll-layout>
