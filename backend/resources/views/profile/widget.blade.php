{{-- resources/views/profile/widget.blade.php --}}
<x-voll-layout body_class="profile-page">

    <x-slot name="title">Виджет на сайт</x-slot>
    <x-slot name="h1">🌐 Виджет на сайт</x-slot>
    <x-slot name="h2">Организатор Pro</x-slot>
    <x-slot name="t_description">Встройте список ваших мероприятий на любой внешний сайт.</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">Профиль</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Виджет</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">

        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

        <div class="row row2">

            {{-- Настройки --}}
            <div class="col-lg-6">
                <div class="ramka">
                    <h3 class="mt-0">⚙️ Настройки виджета</h3>

                    <form method="POST" action="{{ route('profile.widget.store') }}" class="form">
                        @csrf

                        <div class="card">
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

                        <div class="card">
                            <label class="f-15 b-600 mb-05">Цвет акцента</label>
                            <div class="d-flex gap-1" style="align-items:center">
                                <input type="color" name="settings[color]"
                                       value="{{ $widget?->getSetting('color','#f59e0b') }}"
                                       style="height:44px;width:80px;border-radius:8px;border:1px solid var(--border);cursor:pointer">
                                <span class="f-14 text-muted">Цвет кнопок и заголовков виджета</span>
                            </div>
                        </div>

                        <div class="card">
                            <div class="d-flex gap-1 flex-wrap">
                                <label class="d-flex gap-05" style="align-items:center;cursor:pointer">
                                    <input type="checkbox" name="settings[show_slots]" value="1"
                                           {{ $widget?->getSetting('show_slots',true) ? 'checked' : '' }}>
                                    <span class="f-15">Показывать свободные места</span>
                                </label>
                                <label class="d-flex gap-05" style="align-items:center;cursor:pointer">
                                    <input type="checkbox" name="settings[show_location]" value="1"
                                           {{ $widget?->getSetting('show_location',true) ? 'checked' : '' }}>
                                    <span class="f-15">Показывать локацию</span>
                                </label>
                            </div>
                        </div>

                        <div class="card">
                            <label class="f-15 b-600 mb-05">
                                Разрешённые домены
                                <span class="f-13 text-muted b-400">(по одному на строку, пусто = все)</span>
                            </label>
                            <textarea name="allowed_domains" rows="3"
                                      placeholder="mysite.ru&#10;volleyball.club&#10;*.myteam.ru"
                                      class="w-100" style="font-family:monospace;font-size:14px">{{ implode("\n", $widget?->allowed_domains ?? []) }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Сохранить настройки
                        </button>
                    </form>
                </div>
            </div>

            {{-- API ключ + статус --}}
            <div class="col-lg-6">
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

                    <div class="card" style="user-select:all;cursor:pointer;font-family:monospace;font-size:13px;word-break:break-all">
                        {{ $widget->api_key }}
                    </div>
                    <div class="f-13 text-muted mb-2">Кликните на ключ чтобы выделить и скопировать.</div>

                    <h3>📋 Код для вставки</h3>

                    <div class="card mb-1">
                        <div class="f-15 b-600 mb-05">📦 Вариант 1 — iFrame</div>
                        <div class="f-13 text-muted mb-05">Вставить в HTML страницу</div>
                        <textarea readonly rows="3" class="w-100"
                                  style="font-family:monospace;font-size:12px;resize:none"
                                  onclick="this.select()">&lt;iframe src="https://volley-bot.store/embed/org/{{ auth()->id() }}?key={{ $widget->api_key }}" width="100%" height="500" frameborder="0" style="border-radius:12px"&gt;&lt;/iframe&gt;</textarea>
                    </div>

                    <div class="card mb-1">
                        <div class="f-15 b-600 mb-05">⚡ Вариант 2 — JS-скрипт</div>
                        <div class="f-13 text-muted mb-05">Гибкий, без рамки iframe</div>
                        <textarea readonly rows="3" class="w-100"
                                  style="font-family:monospace;font-size:12px;resize:none"
                                  onclick="this.select()">&lt;div id="volley-widget" data-color="{{ $widget->getSetting('color','#f59e0b') }}"&gt;&lt;/div&gt;
&lt;script src="https://volley-bot.store/widget/events.js?key={{ $widget->api_key }}"&gt;&lt;/script&gt;</textarea>
                    </div>

                    <div class="card">
                        <div class="f-15 b-600 mb-1">👁 Предпросмотр</div>
                        <iframe src="{{ route('widget.iframe', ['userId' => auth()->id(), 'key' => $widget->api_key]) }}"
                                width="100%" height="380" frameborder="0"
                                style="border-radius:8px;border:1px solid var(--border)"></iframe>
                    </div>
                </div>
                @else
                <div class="ramka">
                    <div class="alert alert-info">
                        <div class="alert-title">Виджет ещё не создан</div>
                        Заполните настройки слева и нажмите «Сохранить» — API-ключ и код для вставки появятся здесь.
                    </div>
                </div>
                @endif
            </div>

        </div>
    </div>

</x-voll-layout>
