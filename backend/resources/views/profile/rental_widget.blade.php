{{-- resources/views/profile/rental_widget.blade.php — виджет аренды кортов и площадок (Организатор Pro + управляющий клубом) --}}
<x-voll-layout body_class="profile-page">

    <x-slot name="title">{{ __('profile.rw_title') }}</x-slot>
    <x-slot name="h1">{{ __('profile.rw_title') }}</x-slot>
    <x-slot name="h2">{{ __('profile.widget_h2') }}</x-slot>
    <x-slot name="t_description">{{ __('profile.rw_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">{{ __('profile.nch_breadcrumb') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('profile.rw_title') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">

        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if(session('error'))
            <div class="ramka"><div class="alert alert-danger">{{ session('error') }}</div></div>
        @endif

        @if(!($isPro ?? false))
        <div class="ramka text-center" style="padding:4rem 2rem">
            <div style="font-size:5rem;margin-bottom:1.5rem"><x-ico name="lock" size="1em" /></div>
            <h2 class="-mt-05">{{ __('profile.widget_pro_section_h2') }}</h2>
            <div class="f-16 mb-3" style="opacity:.7;max-width:48rem;margin:0 auto 2rem">{!! __('profile.rw_pro_text') !!}</div>
            <a href="{{ route('organizer_pro.index') }}" class="btn"><x-ico name="star" /> {{ __('ui.pro_analytics_locked_btn') }}</a>
        </div>
        @else
        <div class="ramka" style="background:rgba(41,103,186,.07);padding:1.2rem 2rem;margin-bottom:2rem">
            <div class="d-flex fvc gap-1">
                <span style="font-size:2rem"><x-ico name="star" size="1em" /></span>
                <div>
                    <div class="b-600 f-15">{{ __('profile.rw_pro_active') }}</div>
                    <div class="f-13" style="opacity:.6">{{ __('profile.rw_pro_active_hint') }}</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6" style="margin-bottom:2rem">
                <div class="ramka">
                    <h3 class="mt-0"><x-ico name="sliders" /> {{ __('profile.rw_settings') }}</h3>

                    <form method="POST" action="{{ route('profile.rental_widget.store') }}" class="form" id="widget-form">
                        @csrf

                        <div class="card" style="height:auto;margin-bottom:1.6rem">
                            <label class="f-15 b-600 mb-05"><x-ico name="building" /> {{ __('profile.rw_locations') }}</label>
                            <div class="f-13 text-muted mb-1">{{ __('profile.rw_locations_hint') }}</div>
                            @foreach($locations as $loc)
                            <label class="checkbox-item">
                                <input type="checkbox" name="settings[location_ids][]" value="{{ $loc->id }}" @checked(in_array($loc->id, $selectedIds, true))>
                                <div class="custom-checkbox"></div>
                                <span class="f-15">{{ $loc->name }}</span>
                            </label>
                            @endforeach
                        </div>

                        <div class="card" style="height:auto;margin-bottom:1.6rem">
                            <label class="f-15 b-600 mb-05">{{ __('profile.rw_color') }}</label>
                            <div class="d-flex gap-1" style="align-items:center">
                                <input type="color" name="settings[color]" value="{{ $widget?->getSetting('color','#f59e0b') }}"
                                       style="height:44px;width:80px;border-radius:8px;border:1px solid var(--border);cursor:pointer">
                                <span class="f-14 text-muted">{{ __('profile.rw_color_hint') }}</span>
                            </div>
                        </div>

                        <div class="card" style="height:auto;margin-bottom:1.6rem">
                            <label class="f-15 b-600 mb-05">
                                {{ __('profile.rw_domains') }}
                                <span class="f-13 text-muted b-400">{{ __('profile.rw_domains_hint') }}</span>
                            </label>
                            <textarea name="allowed_domains" rows="3" placeholder="mysite.ru&#10;*.myclub.ru"
                                      class="w-100" style="font-family:monospace;font-size:14px">{{ implode("\n", $widget?->allowed_domains ?? []) }}</textarea>
                        </div>

                        <h3 class="mb-1">{{ __('profile.wst_h') }}</h3>

                        <div class="card" style="height:auto;margin-bottom:1.6rem">
                            <div class="f-15 b-600 mb-05">{{ __('profile.wst_presets') }}</div>
                            <div class="d-flex gap-1 flex-wrap" style="margin-bottom:.8rem">
                                @foreach(['light', 'dark', 'sport', 'minimal'] as $pKey)
                                    <button type="button" class="btn btn-outline btn-small js-wst-preset" data-preset="{{ $pKey }}">{{ __('profile.wst_p_'.$pKey) }}</button>
                                @endforeach
                            </div>
                            <div class="f-13 text-muted">{{ __('profile.wst_presets_hint') }}</div>
                        </div>

                        @foreach($styleGroups as $gKey => $fields)
                        <details class="card wst-group" style="height:auto;margin-bottom:1.6rem" @if($gKey === 'general') open @endif>
                            <summary class="f-15 b-600" style="cursor:pointer">{{ __('profile.wst_g_'.$gKey) }}</summary>
                            <div style="padding-top:1.2rem">
                            @foreach($fields as $fKey => $def)
                                @php
                                    $type = $def[0];
                                    $val  = $style[$fKey] ?? null;
                                    $name = 'style['.$fKey.']';
                                @endphp
                                <div style="margin-bottom:1.2rem">
                                @if($type === 'bool')
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="{{ $name }}" value="1" data-wst="{{ $fKey }}" {{ $val ? 'checked' : '' }}>
                                        <div class="custom-checkbox"></div>
                                        <span class="f-15">{{ __('profile.wst_f_'.$fKey) }}</span>
                                    </label>
                                @else
                                    <label class="f-14 b-600 mb-05" for="wst-{{ $fKey }}" style="display:block">{{ __('profile.wst_f_'.$fKey) }}</label>
                                    @if($type === 'color')
                                        <input type="color" id="wst-{{ $fKey }}" name="{{ $name }}" value="{{ $val }}" data-wst="{{ $fKey }}"
                                               style="height:44px;width:80px;border-radius:8px;border:1px solid var(--border);cursor:pointer">
                                    @elseif($type === 'int')
                                        <input type="number" id="wst-{{ $fKey }}" name="{{ $name }}" value="{{ $val }}" data-wst="{{ $fKey }}"
                                               min="{{ $def[2][0] }}" max="{{ $def[2][1] }}" class="w-100">
                                    @elseif($type === 'text')
                                        <input type="text" id="wst-{{ $fKey }}" name="{{ $name }}" value="{{ $val }}" data-wst="{{ $fKey }}"
                                               maxlength="{{ $def[2] }}" class="w-100">
                                    @elseif($type === 'enum')
                                        <select id="wst-{{ $fKey }}" name="{{ $name }}" data-wst="{{ $fKey }}" class="w-100">
                                            @foreach($def[2] as $opt)
                                                <option value="{{ $opt }}" {{ (string)$val === (string)$opt ? 'selected' : '' }}>{{ __('profile.wst_v_'.$opt) }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                @endif
                                </div>
                            @endforeach
                            </div>
                        </details>
                        @endforeach

                        <button type="submit" class="btn btn-primary w-100">{{ __('profile.wst_save') }}</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-6" style="margin-bottom:2rem">
                @if($widget)
                <div class="ramka">
                    <div class="d-flex between mb-1" style="align-items:center">
                        <h3 class="mt-0 mb-0"><x-ico name="key" /> {{ __('profile.rw_api_key') }}</h3>
                        <div class="d-flex gap-05">
                            <form method="POST" action="{{ route('profile.rental_widget.toggle') }}">
                                @csrf
                                <button type="submit" class="btn btn-small {{ $widget->is_active ? 'btn-success' : 'btn-secondary' }}">
                                    <x-ico :name="$widget->is_active ? 'check' : 'dot-gray'" /> {{ $widget->is_active ? __('profile.rw_on') : __('profile.rw_off') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('profile.rental_widget.regenerate_key') }}"
                                  onsubmit="return confirm({{ json_encode(__('profile.rw_regen_confirm')) }})">
                                @csrf
                                <button type="submit" class="btn btn-small btn-danger"><x-ico name="refresh" /> {{ __('profile.rw_regen') }}</button>
                            </form>
                        </div>
                    </div>

                    <div class="card" style="height:auto;margin-bottom:.8rem;user-select:all;cursor:pointer;font-family:monospace;font-size:13px;word-break:break-all">{{ $widget->api_key }}</div>
                    <div class="f-13 text-muted mb-2">{{ __('profile.rw_key_hint') }}</div>

                    <h3><x-ico name="clipboard" /> {{ __('profile.rw_code') }}</h3>

                    <div class="card" style="height:auto;margin-bottom:1.6rem">
                        <div class="f-15 b-600 mb-05">{{ __('profile.rw_variant_iframe') }}</div>
                        <div class="f-13 text-muted mb-05">{{ __('profile.rw_variant_iframe_hint') }}</div>
                        <textarea readonly rows="3" class="w-100" style="font-family:monospace;font-size:12px;resize:none" onclick="this.select()">&lt;iframe src="{{ route('rental_widget.iframe', ['userId' => auth()->id(), 'key' => $widget->api_key]) }}" width="100%" height="520" frameborder="0" style="border:0;max-width:100%"&gt;&lt;/iframe&gt;</textarea>
                    </div>

                    <div class="card" style="height:auto;margin-bottom:1.6rem">
                        <div class="f-15 b-600 mb-05">{{ __('profile.rw_variant_js') }}</div>
                        <div class="f-13 text-muted mb-05">{{ __('profile.rw_variant_js_hint') }}</div>
                        <textarea readonly rows="3" class="w-100" style="font-family:monospace;font-size:12px;resize:none" onclick="this.select()">&lt;div id="volley-rental-widget"&gt;&lt;/div&gt;
&lt;script src="{{ route('rental_widget.script', ['key' => $widget->api_key]) }}"&gt;&lt;/script&gt;</textarea>
                    </div>
                </div>
                @else
                <div class="ramka" style="margin-bottom:2rem">
                    <div class="alert alert-info">
                        <div class="alert-title">{{ __('profile.rw_not_created') }}</div>
                        {{ __('profile.rw_not_created_hint') }}
                    </div>
                </div>
                @endif

                <div class="ramka" style="margin-bottom:2rem">
                    <div class="card" style="height:auto;margin-bottom:1.6rem">
                        <div class="f-15 b-600 mb-05"><x-ico name="sliders" /> {{ __('profile.rw_fit_title') }}</div>
                        <div class="f-13 text-muted mb-05">{!! __('profile.rw_fit_hint') !!}</div>
                        <textarea readonly rows="6" class="w-100" style="font-family:monospace;font-size:12px;resize:none" onclick="this.select()">#volley-rental-widget {
  --vw-accent: #A6D920;
  --vw-card-bg: #ffffff;  --vw-card-radius: 14px;
  --vw-title-color: #1a1a1a;  --vw-meta-color: #666;
  --vw-button-bg: #A6D920;  --vw-button-color: #fff;
  --vw-gap: 14px;  --vw-card-min: 280px;  --vw-card-max: 420px;
}</textarea>
                        <div class="f-13 text-muted" style="margin:.8rem 0 .5rem">{!! __('profile.rw_fit_attrs') !!}</div>
                        <textarea readonly rows="2" class="w-100" style="font-family:monospace;font-size:12px;resize:none" onclick="this.select()">&lt;div id="volley-rental-widget" data-layout="wide" data-columns="2" data-theme="dark" data-lang="en"&gt;&lt;/div&gt;</textarea>
                    </div>
                </div>

                <div class="ramka" style="position:sticky;top:12rem">
                    <h3 class="mt-0"><x-ico name="eye" /> {{ __('profile.wst_preview_h') }}</h3>
                    <div class="d-flex gap-1 flex-wrap" style="margin-bottom:1rem;align-items:center">
                        <span class="f-13 text-muted">{{ __('profile.wst_preview_width') }}:</span>
                        @foreach([360, 768, 1200] as $pw)
                            <button type="button" class="btn btn-outline btn-small js-wst-width" data-width="{{ $pw }}">{{ $pw }}</button>
                        @endforeach
                        <button type="button" class="btn btn-outline btn-small js-wst-width" data-width="0">{{ __('profile.wst_preview_auto') }}</button>
                    </div>
                    <div style="overflow-x:auto">
                        <iframe id="wst-preview" title="preview" width="100%" height="520" frameborder="0"
                                style="border-radius:8px;border:1px solid var(--border);background:#f3f4f6;max-width:100%"></iframe>
                    </div>
                    <div class="f-13 text-muted" style="margin-top:.8rem">{{ __('profile.wst_preview_hint') }}</div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if($isPro ?? false)
    <script>
    (function () {
        var form = document.getElementById('widget-form');
        var frame = document.getElementById('wst-preview');
        if (!form || !frame) return;

        var PRESETS = @json($presets);
        var DEFAULTS = @json($styleDefaults);
        var timer = null, seq = 0;

        function refreshPreview() {
            var my = ++seq;
            fetch(@json(route('profile.rental_widget.preview')), {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).then(function (r) { return r.text(); }).then(function (html) { if (my === seq) frame.srcdoc = html; });
        }
        function schedule() { clearTimeout(timer); timer = setTimeout(refreshPreview, 350); }

        form.addEventListener('input', schedule);
        form.addEventListener('change', schedule);
        window.addEventListener('message', function (e) {
            if (e.source === frame.contentWindow && e.data && e.data.type === 'volley-widget-height') {
                frame.style.height = Math.min(Math.max(e.data.height + 4, 200), 900) + 'px';
            }
        });

        function setField(key, val) {
            var el = form.querySelector('[data-wst="' + key + '"]');
            if (!el) return;
            if (el.type === 'checkbox') { el.checked = !!val; return; }
            el.value = val;
            if (window.jQuery) jQuery(el).trigger('change');
        }

        document.querySelectorAll('.js-wst-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var accent = form.querySelector('[name="settings[color]"]').value;
                var vals = Object.assign({}, DEFAULTS, {header_color: accent, button_bg: accent}, PRESETS[btn.dataset.preset] || {});
                Object.keys(vals).forEach(function (k) { setField(k, vals[k]); });
                refreshPreview();
            });
        });

        document.querySelectorAll('.js-wst-width').forEach(function (b) {
            b.addEventListener('click', function () {
                var w = parseInt(b.dataset.width, 10);
                frame.style.width = w ? w + 'px' : '100%';
            });
        });

        refreshPreview();
    })();
    </script>
    @endif

</x-voll-layout>
