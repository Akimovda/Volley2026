{{-- resources/views/admin/apps/edit.blade.php --}}
@php
    $title = __('admin.app_edit_title', ['name' => $brand->display_name]);
    $themeDefaults = $groups;
    $colorLabels = [
        'primary'   => __('admin.app_c_primary'),
        'secondary' => __('admin.app_c_secondary'),
        'bg_page'   => __('admin.app_c_bg_page'),
        'bg_card'   => __('admin.app_c_bg_card'),
        'text'      => __('admin.app_c_text'),
    ];
    $menuLabels = [
        'menu_bg'     => __('admin.app_c_menu_bg'),
        'menu_text'   => __('admin.app_c_menu_text'),
        'menu_title'  => __('admin.app_c_menu_title'),
        'menu_accent' => __('admin.app_c_menu_accent'),
    ];
    $modes = [
        'day'   => __('admin.app_section_day'),
        'night' => __('admin.app_section_night'),
    ];
@endphp
<x-voll-layout body_class="admin-apps-page">

    <x-slot name="title">{{ $title }} — {{ __('admin.breadcrumb_dashboard') }}</x-slot>
    <x-slot name="h1">{{ $title }}</x-slot>
    <x-slot name="t_description">{{ __('admin.app_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a itemprop="item" href="{{ route('admin.dashboard') }}">
                <span itemprop="name">{{ __('admin.breadcrumb_dashboard') }}</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a itemprop="item" href="{{ route('admin.apps.index') }}">
                <span itemprop="name">{{ __('admin.app_title') }}</span>
            </a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ $brand->display_name }}</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    <div class="container">

        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if($errors->any())
            <div class="ramka">
                <div class="alert alert-danger">
                    @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.apps.update', $brand) }}" enctype="multipart/form-data" class="form">
            @csrf

            {{-- Цвета --}}
            @foreach($modes as $mode => $modeLabel)
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('admin.app_colors_h2') }}: {{ $modeLabel }}</h2>
                    @if($mode === 'day')<p>{{ __('admin.app_hint_empty') }}</p>@endif

                    @foreach([$colorLabels, $menuLabels] as $gi => $labels)
                        @if($gi === 1)<h3 style="margin:2.5rem 0 1.5rem;">{{ __('admin.app_menu_h3') }}</h3>@endif
                    @foreach($labels as $key => $label)
                        @php
                            $val = old("theme.$mode.$key", $brand->theme[$mode][$key] ?? '');
                            $def = $themeDefaults[$mode][$key];
                        @endphp
                        <div style="display:flex; align-items:center; gap:1.2rem; flex-wrap:wrap; margin-bottom:1.2rem;">
                            <input type="color" data-color-picker value="{{ $val ?: $def }}"
                                   style="width:5rem; height:4rem; padding:0.2rem; border:0.1rem solid rgba(0,0,0,.2); border-radius:0.6rem; cursor:pointer;">
                            <input type="text" name="theme[{{ $mode }}][{{ $key }}]" value="{{ $val }}"
                                   placeholder="{{ $def }}" maxlength="7" autocomplete="off"
                                   data-color-text data-mode="{{ $mode }}" data-key="{{ $key }}" data-default="{{ $def }}"
                                   style="width:13rem;">
                            <button type="button" class="btn btn-small" data-color-reset>{{ __('admin.app_reset') }}</button>
                            <span style="flex:1; min-width:20rem;">{{ $label }}</span>
                        </div>
                    @endforeach
                    @endforeach
                </div>
            @endforeach

            {{-- Предпросмотр --}}
            <div class="ramka">
                <h2 class="-mt-05">{{ __('admin.app_preview_h2') }}</h2>
                <div style="display:flex; gap:2rem; flex-wrap:wrap;">
                    @foreach($modes as $mode => $modeLabel)
                        <div data-preview="{{ $mode }}" style="flex:1; min-width:26rem; padding:2rem; border-radius:1.2rem;">
                            <div style="font-size:1.3rem; margin-bottom:1rem; opacity:.7;">{{ $modeLabel }}</div>
                            <div data-pv="card" style="padding:1.6rem; border-radius:1rem; border:0.1rem solid rgba(128,128,128,.25);">
                                <p data-pv="text" style="margin-bottom:1rem;">{{ __('admin.app_preview_text') }}</p>
                                <p style="margin-bottom:1.2rem;"><a href="javascript:void(0)" data-pv="link" style="text-decoration:underline;">{{ __('admin.app_preview_link') }}</a></p>
                                <div style="display:flex; flex-wrap:wrap; gap:1.2rem;">
                                    <span data-pv="primary" style="display:inline-block; padding:0.9rem 1.8rem; border-radius:0.8rem; color:#fff;">{{ __('admin.app_preview_btn_primary') }}</span>
                                    <span data-pv="secondary" style="display:inline-block; padding:0.9rem 1.8rem; border-radius:0.8rem; color:#fff;">{{ __('admin.app_preview_btn_secondary') }}</span>
                                </div>
                            </div>
                            <div data-pv="menu" style="margin-top:1.4rem; padding:1.2rem 1.4rem; border-radius:1rem; border:0.1rem solid rgba(128,128,128,.25);">
                                <div data-pv="menu_title" style="font-size:1.2rem; font-weight:600; text-transform:uppercase; margin-bottom:0.6rem;">{{ __('admin.app_preview_menu_title') }}</div>
                                <div data-pv="menu_text" style="padding:0.5rem 0;">{{ __('admin.app_preview_menu_item') }}</div>
                                <div data-pv="menu_text" style="padding:0.5rem 0; display:inline-block;">{{ __('admin.app_preview_menu_active') }}<span data-pv="menu_accent" style="display:block; height:0.2rem; margin-top:0.2rem;"></span></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Логотипы --}}
            <div class="ramka">
                <h2 class="-mt-05">{{ __('admin.app_logo_h2') }}</h2>
                    <p>{{ __('admin.app_logo_hint') }}</p>
                    @foreach(['logo_day' => ['app_logo_day', $brand->logo_day_url], 'logo_night' => ['app_logo_night', $brand->logo_night_url]] as $field => [$labelKey, $url])
                        <div style="margin-bottom:2rem;">
                            <div style="font-weight:600; margin-bottom:0.8rem;">{{ __('admin.' . $labelKey) }}</div>
                            @if($url)
                                <div style="display:inline-block; padding:1rem; border-radius:1rem; margin-bottom:1rem; background:{{ $field === 'logo_night' ? '#161721' : '#c8dcff' }};">
                                    <img src="{{ $url }}" alt="" style="max-height:6rem; max-width:24rem; display:block;">
                                </div>
                            @endif
                            <input type="file" name="{{ $field }}" accept=".png,.jpg,.jpeg,.webp,.svg">
                            @if($url)
                                <label class="checkbox-item" style="margin-top:0.8rem;">
                                    <input type="checkbox" name="remove_{{ $field }}" value="1">
                                    <div class="custom-checkbox"></div>
                                    <span>{{ __('admin.app_remove') }}</span>
                                </label>
                            @endif
                        </div>
                    @endforeach
            </div>

            {{-- Иконка приложения --}}
            <div class="ramka">
                <h2 class="-mt-05">{{ __('admin.app_icon_h2') }}</h2>
                <p>{{ __('admin.app_icon_hint') }}</p>
                @if($brand->app_icon_url)
                    <img src="{{ $brand->app_icon_url }}" alt="" style="width:9rem; height:9rem; border-radius:2rem; object-fit:cover; display:block; margin-bottom:1rem;">
                @endif
                <input type="file" name="app_icon" accept=".png,.jpg,.jpeg,.webp">
                @if($brand->app_icon_url)
                    <label class="checkbox-item" style="margin-top:0.8rem;">
                        <input type="checkbox" name="remove_app_icon" value="1">
                        <div class="custom-checkbox"></div>
                        <span>{{ __('admin.app_remove') }}</span>
                    </label>
                @endif
            </div>

            <div class="ramka">
                <div style="display:flex; flex-wrap:wrap; gap:1.5rem;">
                    <button type="submit" class="btn btn-primary">{{ __('admin.app_save') }}</button>
                    <a href="{{ route('admin.apps.index') }}" class="btn btn-secondary">{{ __('admin.app_back') }}</a>
                </div>
            </div>
        </form>
    </div>

    <script>
    (function () {
        var defaults = @json($themeDefaults);
        var hexRe = /^#[0-9a-fA-F]{6}$/;

        function val(mode, key) {
            var el = document.querySelector('[data-color-text][data-mode="' + mode + '"][data-key="' + key + '"]');
            var v = el ? el.value.trim() : '';
            return hexRe.test(v) ? v : defaults[mode][key];
        }

        function renderPreview() {
            ['day', 'night'].forEach(function (mode) {
                var box = document.querySelector('[data-preview="' + mode + '"]');
                if (!box) { return; }
                box.style.background = val(mode, 'bg_page');
                box.style.color = val(mode, 'text');
                box.querySelector('[data-pv="card"]').style.background = val(mode, 'bg_card');
                box.querySelector('[data-pv="link"]').style.color = val(mode, 'primary');
                box.querySelector('[data-pv="primary"]').style.background = val(mode, 'primary');
                box.querySelector('[data-pv="secondary"]').style.background = val(mode, 'secondary');
                box.querySelector('[data-pv="menu"]').style.background = val(mode, 'menu_bg');
                box.querySelector('[data-pv="menu_title"]').style.color = val(mode, 'menu_title');
                box.querySelectorAll('[data-pv="menu_text"]').forEach(function (el) { el.style.color = val(mode, 'menu_text'); });
                box.querySelector('[data-pv="menu_accent"]').style.background = val(mode, 'menu_accent');
            });
        }

        document.querySelectorAll('[data-color-text]').forEach(function (txt) {
            var row = txt.parentNode;
            var picker = row.querySelector('[data-color-picker]');
            var reset = row.querySelector('[data-color-reset]');

            picker.addEventListener('input', function () {
                txt.value = picker.value.toUpperCase();
                renderPreview();
            });
            txt.addEventListener('input', function () {
                if (hexRe.test(txt.value.trim())) { picker.value = txt.value.trim(); }
                renderPreview();
            });
            reset.addEventListener('click', function () {
                txt.value = '';
                picker.value = txt.getAttribute('data-default');
                renderPreview();
            });
        });

        renderPreview();
    })();
    </script>
</x-voll-layout>
