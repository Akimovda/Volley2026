{{-- resources/views/profile/widget.blade.php --}}
<x-voll-layout body_class="profile-page">

    <x-slot name="title">{{ __('profile.widget_title') }}</x-slot>
    <x-slot name="h1">{{ __('profile.widget_h1') }}</x-slot>
    <x-slot name="h2">{{ __('profile.widget_h2') }}</x-slot>
    <x-slot name="t_description">{{ __('profile.widget_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">{{ __('profile.nch_breadcrumb') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('profile.widget_breadcrumb') }}</span>
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
        {{-- ===== ЗАБЛОКИРОВАНО — НЕТ ПОДПИСКИ ===== --}}
        <div class="ramka text-center" style="padding:4rem 2rem">
            <div style="font-size:5rem;margin-bottom:1.5rem">🔒</div>
            <h2 class="-mt-05">{{ __('profile.widget_pro_section_h2') }}</h2>
            <div class="f-16 mb-3" style="opacity:.7;max-width:48rem;margin:0 auto 2rem">
                Виджет для встройки мероприятий на внешний сайт — часть подписки <strong>Организатор Pro</strong>.
                Активируйте подписку чтобы получить API-ключ и код для вставки.
            </div>
            <a href="{{ route('organizer_pro.index') }}" class="btn">
                ⭐ Подключить Организатор Pro
            </a>
        </div>
        @else
        {{-- ===== ДОСТУПНО — ПОДПИСКА АКТИВНА ===== --}}
        <div class="ramka" style="background:rgba(41,103,186,.07);padding:1.2rem 2rem;margin-bottom:2rem">
            <div class="d-flex fvc gap-1">
                <span style="font-size:2rem">⭐</span>
                <div>
                    <div class="b-600 f-15">Организатор Pro активен</div>
                    <div class="f-13" style="opacity:.6">Виджет и персональный бот доступны</div>
                </div>
            </div>
        </div>

        <div class="row">

            {{-- Настройки --}}
            <div class="col-lg-6" style="margin-bottom:2rem">
                <div class="ramka">
                    <h3 class="mt-0">⚙️ Настройки виджета</h3>

                    <form method="POST" action="{{ route('profile.widget.store') }}" class="form" id="widget-form">
                        @csrf

                        <div class="card" style="height:auto;margin-bottom:1.6rem">
                            <label class="f-15 b-600 mb-05">Кол-во мероприятий</label>
                            <select name="settings[limit]" class="w-100">
                                @foreach([5,10,20,50] as $n)
                                    <option value="{{ $n }}"
                                        {{ ($widget?->getSetting('limit',10) == $n) ? 'selected' : '' }}>
                                        {{ $n }} мероприятий
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="card" style="height:auto;margin-bottom:1.6rem">
                            <label class="f-15 b-600 mb-05">Основной цвет</label>
                            <div class="d-flex gap-1" style="align-items:center">
                                <input type="color" name="settings[color]"
                                       value="{{ $widget?->getSetting('color','#f59e0b') }}"
                                       style="height:44px;width:80px;border-radius:8px;border:1px solid var(--border);cursor:pointer">
                                <span class="f-14 text-muted">Подставляется в цвет заголовка и кнопки у новых тем</span>
                            </div>
                        </div>

                        <div class="card" style="height:auto;margin-bottom:1.6rem">
                            <label class="f-15 b-600 mb-05">
                                Разрешённые домены
                                <span class="f-13 text-muted b-400">(по одному на строку, пусто = все)</span>
                            </label>
                            <textarea name="allowed_domains" rows="3"
                                      placeholder="mysite.ru&#10;volleyball.club&#10;*.myteam.ru"
                                      class="w-100" style="font-family:monospace;font-size:14px">{{ implode("\n", $widget?->allowed_domains ?? []) }}</textarea>
                        </div>

                        {{-- ===== Оформление ===== --}}
                        <h3 class="mb-1">{{ __('profile.wst_h') }}</h3>

                        <div class="card" style="height:auto;margin-bottom:1.6rem">
                            <div class="f-15 b-600 mb-05">{{ __('profile.wst_presets') }}</div>
                            <div class="d-flex gap-1 flex-wrap" style="margin-bottom:.8rem">
                                @foreach($presets as $pKey => $pVals)
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

                        <button type="submit" class="btn btn-primary w-100">
                            {{ __('profile.wst_save') }}
                        </button>
                    </form>
                </div>
            </div>

            {{-- API ключ + статус --}}
            <div class="col-lg-6" style="margin-bottom:2rem">
                @if($widget)
                <div class="ramka">
                    <div class="d-flex between mb-1" style="align-items:center">
                        <h3 class="mt-0 mb-0">🔑 API-ключ</h3>
                        <div class="d-flex gap-05">
                            <form method="POST" action="{{ route('profile.widget.toggle') }}">
                                @csrf
                                <button type="submit"
                                        class="btn btn-small {{ $widget->is_active ? 'btn-success' : 'btn-secondary' }}">
                                    {{ $widget->is_active ? '✅ Включён' : '⏸ Отключён' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('profile.widget.regenerate_key') }}"
                                  onsubmit="return confirm('Пересоздать ключ? Старый перестанет работать.')">
                                @csrf
                                <button type="submit" class="btn btn-small btn-danger">🔄 Сбросить</button>
                            </form>
                        </div>
                    </div>

                    <div class="card" style="height:auto;margin-bottom:.8rem;user-select:all;cursor:pointer;font-family:monospace;font-size:13px;word-break:break-all">
                        {{ $widget->api_key }}
                    </div>
                    <div class="f-13 text-muted mb-2">Кликните на ключ чтобы выделить и скопировать.</div>

                    <h3>📋 Код для вставки</h3>

                    <div class="card" style="height:auto;margin-bottom:1.6rem">
                        <div class="f-15 b-600 mb-05">📦 Вариант 1 — iFrame</div>
                        <div class="f-13 text-muted mb-05">Вставить в HTML страницу</div>
                        <textarea readonly rows="3" class="w-100"
                                  style="font-family:monospace;font-size:12px;resize:none"
                                  onclick="this.select()">&lt;iframe src="{{ route('widget.iframe', ['userId' => auth()->id(), 'key' => $widget->api_key]) }}" width="100%" height="500" frameborder="0" style="border-radius:12px"&gt;&lt;/iframe&gt;</textarea>
                    </div>

                    <div class="card" style="height:auto;margin-bottom:1.6rem">
                        <div class="f-15 b-600 mb-05">⚡ Вариант 2 — JS-скрипт</div>
                        <div class="f-13 text-muted mb-05">Без рамки iframe, оформление берётся из настроек слева</div>
                        <textarea readonly rows="3" class="w-100"
                                  style="font-family:monospace;font-size:12px;resize:none"
                                  onclick="this.select()">&lt;div id="volley-widget"&gt;&lt;/div&gt;
&lt;script src="{{ route('widget.script', ['key' => $widget->api_key]) }}"&gt;&lt;/script&gt;</textarea>
                    </div>


                </div>
                @endif
                @if(!$widget)
                <div class="ramka" style="margin-bottom:2rem">
                    <div class="alert alert-info">
                        <div class="alert-title">Виджет ещё не создан</div>
                        Заполните настройки слева и нажмите «Сохранить» — API-ключ и код для вставки появятся здесь.
                    </div>
                </div>
                @endif

                <div class="ramka" style="margin-bottom:2rem">
                    <div class="card" style="height:auto;margin-bottom:1.6rem">
                        <div class="f-15 b-600 mb-05">🎛 Подгонка под дизайн вашего сайта</div>
                        <div class="f-13 text-muted mb-05">CSS-переменные, задайте их на <code>#volley-widget</code> — они перебьют настройки выше:</div>
                        <textarea readonly rows="7" class="w-100" style="font-family:monospace;font-size:12px;resize:none" onclick="this.select()">#volley-widget {
  --vw-accent: #A6D920;        /* заголовок и кнопка */
  --vw-card-bg: #ffffff;  --vw-card-radius: 14px;
  --vw-title-color: #1a1a1a;  --vw-meta-color: #666;
  --vw-button-bg: #A6D920;  --vw-button-color: #fff;
  --vw-gap: 14px;  --vw-card-min: 280px;  --vw-card-max: 420px;
}
#volley-widget::part(card) { box-shadow: 0 4px 20px #0003; }</textarea>
                        <div class="f-13 text-muted" style="margin:.8rem 0 .5rem">Части для <code>::part()</code>: header, cards, card, photo, title, meta, badge, button, empty, footer. Атрибуты контейнера перекрывают серверные настройки:</div>
                        <textarea readonly rows="3" class="w-100" style="font-family:monospace;font-size:12px;resize:none" onclick="this.select()">&lt;div id="volley-widget" data-layout="wide" data-columns="2" data-theme="dark" data-accent="#A6D920" data-limit="6" data-lang="ru" data-header="0"&gt;&lt;/div&gt;</textarea>
                        <div class="f-13 text-muted" style="margin-top:.5rem">data-layout: cards | list | wide; data-theme: light | dark | contrast | custom; data-lang: ru | en | auto; data-header="0" скрывает заголовок. Работает с вариантом «JS-скрипт».</div>
                    </div>
                </div>

                {{-- Предпросмотр (по несохранённым значениям формы) --}}
                <div class="ramka" style="position:sticky;top:12rem">
                    <h3 class="mt-0">{{ __('profile.wst_preview_h') }}</h3>
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
        </div>
        @include('levels._scheme_form', ['action' => route('profile.levels.update'), 'owner' => 'organizer', 'ownerId' => auth()->id()])
        @endif {{-- isPro --}}
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
            fetch(@json(route('profile.widget.preview')), {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).then(function (r) { return r.text(); }).then(function (html) {
                if (my === seq) frame.srcdoc = html;
            });
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
            if (window.jQuery) jQuery(el).trigger('change');   // обновляет кастомный select
        }

        // Тема = значения по умолчанию + переопределения темы (цвета заголовка/кнопки берём из «Основного цвета»)
        document.querySelectorAll('.js-wst-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var accent = form.querySelector('[name="settings[color]"]').value;
                var vals = Object.assign({}, DEFAULTS, {header_color: accent, button_bg: accent}, PRESETS[btn.dataset.preset] || {});
                Object.keys(vals).forEach(function (k) { setField(k, vals[k]); });
                refreshPreview();
            });
        });

        // Предпросмотр в реальной ширине блока (360 / 768 / 1200 px): container queries смотрят на ширину iframe
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
