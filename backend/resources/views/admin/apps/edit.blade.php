{{-- resources/views/admin/apps/edit.blade.php --}}
@php
    $title = __('admin.app_edit_title', ['name' => $brand->display_name]);
    $themeDefaults = $groups;
    $colorLabels = [
        'primary'   => __('admin.app_c_primary'),
        'secondary' => __('admin.app_c_secondary'),
        'bg_page'   => __('admin.app_c_bg_page'),
        'bg_page_to' => __('admin.app_c_bg_page_to'),
        'orb_main'   => __('admin.app_c_orb_main'),
        'orb_center' => __('admin.app_c_orb_center'),
        'bg_card'   => __('admin.app_c_bg_card'),
        'text'      => __('admin.app_c_text'),
    ];
    $menuLabels = [
        'menu_bg'     => __('admin.app_c_menu_bg'),
        'menu_text'   => __('admin.app_c_menu_text'),
        'menu_title'  => __('admin.app_c_menu_title'),
        'menu_accent' => __('admin.app_c_menu_accent'),
        'menu_icon'       => __('admin.app_c_menu_icon'),
        'menu_icon_hover' => __('admin.app_c_menu_icon_hover'),
    ];
    $menuAllPaths = collect($menuGroups)->flatMap(fn ($g) => array_column($g['items'], 0))->all();
    $linkTexts = [
        'titleRu'   => __('admin.app_link_title_ru'),
        'titleEn'   => __('admin.app_link_title_en'),
        'url'       => __('admin.app_link_url'),
        'placeSite' => __('admin.app_link_place_site'),
        'placeUser' => __('admin.app_link_place_user'),
        'newTab'    => __('admin.app_link_new_tab'),
        'remove'    => __('admin.app_link_remove'),
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

        <div style="display:flex; flex-wrap:wrap; gap:2rem; align-items:flex-start;">
        <div style="flex:1 1 52rem; min-width:0;">
        <form method="POST" action="{{ route('admin.apps.update', $brand) }}" enctype="multipart/form-data" class="form">
            @csrf

            {{-- Цвета --}}
            @foreach($modes as $mode => $modeLabel)
                <div class="ramka" data-mode-panel="{{ $mode }}">
                    <h2 class="-mt-05">{{ __('admin.app_colors_h2') }}: {{ $modeLabel }}</h2>
                    @if($mode === 'day')<p>{{ __('admin.app_hint_empty') }}</p>@endif
                    <p style="opacity:.7;">{{ __('admin.app_modes_hint') }}</p>

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

            {{-- Шрифт --}}
            <div class="ramka">
                <h2 class="-mt-05">{{ __('admin.tok_font_h2') }}</h2>
                <p>{{ __('admin.tok_font_hint') }}</p>
                <select name="theme[font]">
                    <option value="">{{ __('admin.tok_font_default') }}</option>
                    @foreach($fonts as $fk => $f)
                        <option value="{{ $fk }}" @selected(old('theme.font', $brand->theme['font'] ?? '') === $fk)>{{ $f['label'] }}</option>
                    @endforeach
                </select>

                <h3 style="margin:2.5rem 0 1rem;">{{ __('admin.tok_fscale_h3') }}</h3>
                <p>{{ __('admin.tok_fscale_hint') }}</p>
                <select name="theme[font_scale]">
                    @foreach($fontScales as $fs)
                        <option value="{{ $fs }}" @selected((int) old('theme.font_scale', $brand->theme['font_scale'] ?? 100) === $fs)>{{ $fs }}%{{ $fs === 100 ? ' — ' . __('admin.tok_fscale_default') : '' }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Токены элементов --}}
            @foreach($modes as $mode => $modeLabel)
                <div class="ramka" data-mode-panel="{{ $mode }}">
                    <h2 class="-mt-05">{{ __('admin.tok_h2') }}: {{ $modeLabel }}</h2>
                    <p>{{ __('admin.tok_hint') }}</p>
                    @foreach($tokenGroups as $tg)
                        <details style="margin-bottom:1rem;">
                        <summary style="cursor:pointer; font-weight:600; padding:1rem 0;">{{ __($tg['title']) }} <span style="font-weight:400; opacity:.6;">({{ count($tg['tokens']) }})</span></summary>
                        @foreach($tg['tokens'] as $tkey => [$tlabel, $tdefs])
                            @php
                                $fk = 't_' . $tkey;
                                $val = old("theme.$mode.$fk", $brand->theme[$mode][$fk] ?? '');
                                $def = $tdefs[$mode === 'day' ? 0 : 1];
                            @endphp
                            <div style="display:flex; align-items:center; gap:1.2rem; flex-wrap:wrap; margin-bottom:1.2rem;">
                                <input type="color" data-color-picker value="{{ $val ?: $def }}"
                                       style="width:5rem; height:4rem; padding:0.2rem; border:0.1rem solid rgba(0,0,0,.2); border-radius:0.6rem; cursor:pointer;">
                                <input type="text" name="theme[{{ $mode }}][{{ $fk }}]" value="{{ $val }}"
                                       placeholder="{{ $def }}" maxlength="7" autocomplete="off"
                                       data-color-text data-mode="{{ $mode }}" data-key="{{ $fk }}" data-default="{{ $def }}"
                                       style="width:13rem;">
                                <button type="button" class="btn btn-small" data-color-reset>{{ __('admin.app_reset') }}</button>
                                <span style="flex:1; min-width:20rem;">{{ __($tlabel) }}</span>
                            </div>
                        @endforeach
                        </details>
                    @endforeach
                </div>
            @endforeach

            {{-- Пункты меню --}}
            @php
                $hiddenNow = old('menu_form') ? array_diff($menuAllPaths, (array) old('menu.visible', [])) : $brand->menuHidden();
                $linksNow = old('menu.links', $brand->menu['links'] ?? []);
                $linksNow = array_values((array) $linksNow);
            @endphp
            <div class="ramka">
                <h2 class="-mt-05">{{ __('admin.app_menu_h2') }}</h2>
                <input type="hidden" name="menu_form" value="1">
                <p>{{ __('admin.app_menu_hide_hint') }}</p>

                @foreach($menuGroups as $group)
                    <h3 style="margin:2rem 0 1rem;">{{ __($group['title']) }}</h3>
                    <div style="display:flex; flex-wrap:wrap; gap:0.6rem 3rem;">
                        @foreach($group['items'] as [$path, $labelKey])
                            <label class="checkbox-item" style="min-width:26rem;">
                                <input type="checkbox" name="menu[visible][]" value="{{ $path }}" @checked(!in_array($path, (array) $hiddenNow, true))>
                                <div class="custom-checkbox"></div>
                                <span>{{ __($labelKey) }}</span>
                            </label>
                        @endforeach
                    </div>
                @endforeach

                <h3 style="margin:3rem 0 1rem;">{{ __('admin.app_links_h3') }}</h3>
                <p>{{ __('admin.app_links_hint') }}</p>
                <div id="menu-links-list"></div>
                <button type="button" class="btn btn-small" id="menu-link-add">{{ __('admin.app_link_add') }}</button>
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
        <aside style="flex:0 0 42rem; max-width:100%; position:sticky; top:9rem;">
            <div class="ramka" style="padding:1.2rem;">
                <div style="display:flex; gap:1rem; align-items:center; margin-bottom:1rem;">
                    <button type="button" class="btn btn-small" data-pv-mode="day">{{ __('admin.app_section_day') }}</button>
                    <button type="button" class="btn btn-small" data-pv-mode="night">{{ __('admin.app_section_night') }}</button>
                    <span style="font-size:1.2rem; opacity:.7;">{{ __('admin.tok_live_hint') }}</span>
                </div>
                <iframe id="live-preview" src="{{ $previewUrl }}" style="width:100%; height:calc(100vh - 20rem); min-height:50rem; border:0.1rem solid rgba(128,128,128,.3); border-radius:1.2rem; background:#fff;"></iframe>
            </div>
        </aside>
        </div>

        @include('levels._scheme_form', ['action' => route('admin.apps.levels', $brand), 'owner' => 'brand', 'ownerId' => $brand->id])
    </div>

    <script>
    (function () {
        var hexRe = /^#[0-9a-fA-F]{6}$/;

        document.querySelectorAll('[data-color-text]').forEach(function (txt) {
            var row = txt.parentNode;
            var picker = row.querySelector('[data-color-picker]');
            var reset = row.querySelector('[data-color-reset]');

            picker.addEventListener('input', function () {
                txt.value = picker.value.toUpperCase();
            });
            txt.addEventListener('input', function () {
                if (hexRe.test(txt.value.trim())) { picker.value = txt.value.trim(); }
            });
            reset.addEventListener('click', function () {
                txt.value = '';
                picker.value = txt.getAttribute('data-default');
            });
        });


        // --- Свои ссылки меню ---
        var linkList = document.getElementById('menu-links-list');
        var linkIdx = 0;
        var linkTexts = @json($linkTexts);
        var initialLinks = @json($linksNow);

        function esc(v) {
            return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        function addLinkRow(d) {
            d = d || {};
            var i = linkIdx++;
            var place = d.place === 'user' ? 'user' : 'site';
            var row = document.createElement('div');
            row.className = 'menu-link-row';
            row.style.cssText = 'padding:1.4rem; margin-bottom:1.4rem; border:0.1rem solid rgba(128,128,128,.3); border-radius:1rem;';
            row.innerHTML =
                '<div style="display:flex; flex-wrap:wrap; gap:1.2rem; margin-bottom:1.2rem;">' +
                '<input type="text" name="menu[links][' + i + '][title_ru]" value="' + esc(d.title_ru) + '" placeholder="' + esc(linkTexts.titleRu) + '" maxlength="60" style="flex:1; min-width:18rem;">' +
                '<input type="text" name="menu[links][' + i + '][title_en]" value="' + esc(d.title_en) + '" placeholder="' + esc(linkTexts.titleEn) + '" maxlength="60" style="flex:1; min-width:18rem;">' +
                '</div>' +
                '<input type="text" name="menu[links][' + i + '][url]" value="' + esc(d.url) + '" placeholder="' + esc(linkTexts.url) + ' (https://…)" maxlength="500" style="width:100%; margin-bottom:1.2rem;">' +
                '<div style="display:flex; flex-wrap:wrap; gap:1rem 3rem; align-items:center;">' +
                '<label class="radio-item"><input type="radio" name="menu[links][' + i + '][place]" value="site"' + (place === 'site' ? ' checked' : '') + '><div class="custom-radio"></div><span>' + esc(linkTexts.placeSite) + '</span></label>' +
                '<label class="radio-item"><input type="radio" name="menu[links][' + i + '][place]" value="user"' + (place === 'user' ? ' checked' : '') + '><div class="custom-radio"></div><span>' + esc(linkTexts.placeUser) + '</span></label>' +
                '<label class="checkbox-item"><input type="checkbox" name="menu[links][' + i + '][new_tab]" value="1"' + (d.new_tab ? ' checked' : '') + '><div class="custom-checkbox"></div><span>' + esc(linkTexts.newTab) + '</span></label>' +
                '<button type="button" class="btn btn-small" data-link-remove>' + esc(linkTexts.remove) + '</button>' +
                '</div>';
            row.querySelector('[data-link-remove]').addEventListener('click', function () { row.remove(); });
            linkList.appendChild(row);
        }

        document.getElementById('menu-link-add').addEventListener('click', function () { addLinkRow({}); });
        initialLinks.forEach(addLinkRow);

        // --- Живой предпросмотр настоящей страницы ---
        (function () {
            var frame = document.getElementById('live-preview');
            var form = document.querySelector('form.form[action*="/admin/apps/"]');
            if (!frame || !form) { return; }
            var mode = 'day', timer = null, seq = 0, css = null;
            var url = @json(route('admin.apps.preview_css', $brand));
            var token = document.querySelector('input[name="_token"]').value;

            function apply() {
                document.querySelectorAll('[data-mode-panel]').forEach(function (p) {
                    p.style.display = p.getAttribute('data-mode-panel') === mode ? '' : 'none';
                });
                var doc = frame.contentDocument;
                document.querySelectorAll('[data-pv-mode]').forEach(function (b) {
                    b.style.opacity = b.getAttribute('data-pv-mode') === mode ? '1' : '.55';
                });
                if (!doc || !doc.body) { return; }
                if (css !== null) {
                    var st = doc.getElementById('brand-theme');
                    if (!st) { st = doc.createElement('style'); st.id = 'brand-theme'; doc.head.appendChild(st); }
                    st.textContent = css;
                }
                doc.body.classList.toggle('dark', mode === 'night');
                document.querySelectorAll('[data-pv-mode]').forEach(function (b) {
                    b.style.opacity = b.getAttribute('data-pv-mode') === mode ? '1' : '.55';
                });
            }
            function refresh() {
                var my = ++seq, p = new URLSearchParams();
                new FormData(form).forEach(function (v, k) { if (typeof v === 'string' && k.indexOf('theme[') === 0) { p.append(k, v); } });
                fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'text/css' }, body: p, credentials: 'same-origin' })
                    .then(function (r) { return r.text(); })
                    .then(function (t) { if (my === seq) { css = t; apply(); } });
            }
            function later() { clearTimeout(timer); timer = setTimeout(refresh, 350); }

            form.addEventListener('input', later);
            form.addEventListener('change', later);
            form.addEventListener('click', function (e) { if (e.target.closest('[data-color-reset]')) { later(); } });
            document.querySelectorAll('[data-pv-mode]').forEach(function (b) {
                b.addEventListener('click', function () { mode = b.getAttribute('data-pv-mode'); apply(); });
            });
            frame.addEventListener('load', function () { refresh(); });
            apply();
        })();

    })();
    </script>
</x-voll-layout>
