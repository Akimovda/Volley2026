<x-voll-layout body_class="organizer-pro-page">
    <x-slot name="title">{{ __('ui.pro_analytics_locked_title') }}</x-slot>
    <x-slot name="h1">⭐ {{ __('ui.pro_analytics_locked_title') }}</x-slot>

    <div class="container">
        <div class="ramka text-center">
            <h2 class="-mt-05">{{ __('ui.pro_analytics_locked_h2') }}</h2>
            <p class="mb-2">{{ __('ui.pro_analytics_locked_text') }}</p>
            <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap">
                <a href="{{ route('organizer_pro.index') }}" class="btn">{{ __('ui.pro_analytics_locked_btn') }}</a>
                <a href="{{ route('org.dashboard') }}" class="btn btn-secondary">{{ __('ui.pro_analytics_locked_back') }}</a>
            </div>
        </div>
    </div>
</x-voll-layout>
