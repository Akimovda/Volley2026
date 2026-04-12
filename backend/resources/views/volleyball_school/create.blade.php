{{-- resources/views/volleyball_school/create.blade.php --}}
@php
    $isAdmin = auth()->user()->isAdmin();
    // Список организаторов для админа
    $organizers = $isAdmin
        ? \App\Models\User::whereIn('role', ['organizer', 'admin'])->orderBy('first_name')->get()
        : collect();
@endphp

<x-voll-layout body_class="volleyball-school-create-page">

    <x-slot name="title">Создать страницу школы</x-slot>
    <x-slot name="h1">Создать страницу школы</x-slot>
    <x-slot name="t_description">Расскажите о вашей школе или волейбольном сообществе</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('volleyball_school.index') }}" itemprop="item">
                <span itemprop="name">Школы волейбола</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Создать</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <x-slot name="style">
        <link href="/assets/org.css" rel="stylesheet">
        <style>
            .icon-vk, .icon-tg, .icon-max {
                width: 2rem !important;
                height: 2rem !important;
                flex-shrink: 0;
            }
            .icon-vk svg, .icon-tg svg, .icon-max svg {
                width: 2rem !important;
                height: 2rem !important;
            }
        </style>
    </x-slot>

    <x-slot name="script">
        <script src="/assets/city.js"></script>
        <script src="/assets/org.js?v=2"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Запрет вложений в trix
            document.addEventListener('trix-file-accept', function (e) { e.preventDefault(); });

            // Телефонная маска (как в профиле)
            const phoneMasked = document.getElementById('phone_masked_school');
            const phoneE164   = document.getElementById('phone_e164_school');

            function digitsOnly(s) { return String(s || '').replace(/\D/g, ''); }
            function toE164Ru(raw) {
                let d = digitsOnly(raw);
                if (d.length === 11 && d.startsWith('8')) d = '7' + d.slice(1);
                if (d.length === 11 && d.startsWith('7')) return '+7' + d.slice(1);
                if (d.length === 10) return '+7' + d;
                return d ? '+' + d : '';
            }
            function formatMask(raw) {
                let d = digitsOnly(raw);
                if (d.startsWith('7') || d.startsWith('8')) d = d.slice(1);
                d = d.slice(0, 10);
                const a = d.slice(0,3), b = d.slice(3,6), c = d.slice(6,8), e = d.slice(8,10);
                let out = '+7';
                if (a.length) out += ' (' + a;
                if (a.length < 3) return out;
                out += ')';
                if (b.length) out += ' ' + b;
                if (b.length < 3) return out;
                if (c.length) out += '-' + c;
                if (c.length < 2) return out;
                if (e.length) out += '-' + e;
                return out;
            }
            if (phoneMasked && phoneE164) {
                phoneMasked.addEventListener('input', () => { phoneE164.value = toE164Ru(phoneMasked.value); });
                phoneMasked.addEventListener('blur',  () => {
                    phoneE164.value = toE164Ru(phoneMasked.value);
                    phoneMasked.value = formatMask(phoneMasked.value);
                });
            }

            // Slug из названия
            const nameInput = document.querySelector('[name="name"]');
            const slugInput = document.querySelector('[name="slug"]');
            let slugTouched = false;
            if (nameInput && slugInput) {
                slugInput.addEventListener('input', () => { slugTouched = true; });
                nameInput.addEventListener('input', () => {
                    if (slugTouched) return;
                    slugInput.value = nameInput.value
                        .toLowerCase()
                        .replace(/[^a-z0-9а-яё\s-]/gi, '')
                        .replace(/[а-яё]/gi, c => ({
                            'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z',
                            'и':'i','й':'j','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r',
                            'с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'shch',
                            'ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'
                        }[c.toLowerCase()] || c))
                        .trim()
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .slice(0, 60);
                });
            }
        });
        </script>
    </x-slot>

    <div class="container">
        <div class="row row2">

            {{-- Sidebar меню --}}
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka">
                        @include('profile._menu', [
                            'menuUser'       => auth()->user(),
                            'isEditingOther' => false,
                            'activeMenu'     => 'school',
                        ])
                    </div>
                </div>
            </div>

            <div class="col-lg-8 col-xl-9" style="order:1;">

        @if ($errors->any())
            <div class="ramka">
                <div class="alert alert-error">
                    <div class="alert-title">Проверьте поля</div>
                    <ul class="list">
                        @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="form">
            <form method="POST" action="{{ route('volleyball_school.store') }}" enctype="multipart/form-data">
                @csrf

                {{-- АДМИНИСТРАТОР: выбор организатора --}}
                @if($isAdmin)
                <div class="ramka">
                    <h2 class="-mt-05">👤 Организатор (только для администратора)</h2>
                    <div class="card">
                        <label>Привязать к организатору</label>
                        <select name="organizer_id">
                            <option value="">— создать для себя —</option>
                            @foreach($organizers as $org)
                                <option value="{{ $org->id }}" @selected(old('organizer_id') == $org->id)>
                                    #{{ $org->id }} — {{ trim($org->first_name . ' ' . $org->last_name) ?: $org->email }}
                                    ({{ $org->role }})
                                </option>
                            @endforeach
                        </select>
                        <ul class="list f-16 mt-1">
                            <li>Если не выбрать — страница будет создана от вашего имени</li>
                        </ul>
                    </div>
                </div>
                @endif

                <div class="ramka">
                    <h2 class="-mt-05">Основная информация</h2>
                    <div class="row">

                        <div class="col-md-7">
                            <div class="card">
                                <label>Название школы / сообщества <span class="red">*</span></label>
                                <input type="text" name="name" value="{{ old('name') }}"
                                       placeholder="Напр. SunVolley, Beach Crew Moscow" required
                                       maxlength="100">
                                <ul class="list f-16 mt-1">
                                    <li>Допустимы буквы, цифры, пробелы. Нецензурные слова запрещены.</li>
                                </ul>
                                @error('name')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div class="card">
                                <label>Направление <span class="red">*</span></label>
                                <select name="direction">
                                    <option value="classic" @selected(old('direction') === 'classic')>🏐 Классика</option>
                                    <option value="beach" @selected(old('direction') === 'beach')>🏖 Пляж</option>
                                    <option value="both" @selected(old('direction') === 'both')>🏐🏖 Классика + Пляж</option>
                                </select>
                                @error('direction')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="card">
                                <label>URL страницы (slug) <span class="red">*</span></label>
                                <input type="text" name="slug" value="{{ old('slug') }}"
                                       placeholder="sunvolley" required maxlength="60">
                                <ul class="list f-16 mt-1">
                                    <li>Только латиница, цифры, дефис</li>
                                    <li>Генерируется автоматически из названия</li>
                                    <li>/volleyball_school/<strong>sunvolley</strong></li>
                                </ul>
                                @error('slug')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- ГОРОД --}}
                        <div class="col-md-5">
                            <div class="card">
                                <label>Город</label>
                                <input type="hidden" name="city_id" id="city_id" value="{{ old('city_id') }}">
                                <div class="city-autocomplete" id="city-autocomplete" data-search-url="{{ route('cities.search') }}">
                                    <input type="text" id="city_search"
                                           placeholder="Начните вводить город…"
                                           value="{{ old('city_label') }}"
                                           autocomplete="off">
                                    <div id="city_dropdown" class="city-dropdown">
                                        <div id="city_results"></div>
                                    </div>
                                </div>
                                <input type="hidden" name="city_name" id="city_name_hidden" value="{{ old('city_name') }}">
                                @error('city_id')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- ОПИСАНИЕ через Trix --}}
                        <div class="col-md-12">
                            <div class="card">
                                <label>Описание школы</label>
                                <input id="school_description" type="hidden" name="description" value="{{ old('description') }}">
                                <trix-editor input="school_description" class="trix-content"></trix-editor>
                                <ul class="list f-16 mt-1">
                                    <li>Расскажите о школе, тренерах, программах. Нецензурные слова запрещены.</li>
                                </ul>
                                @error('description')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                    </div>
                </div>

                <div class="ramka">
                    <h2 class="-mt-05">Контакты</h2>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <label>Телефон</label>
                                <input type="text" id="phone_masked_school"
                                       placeholder="+7 (___) ___-__-__"
                                       value="{{ old('phone') }}"
                                       inputmode="tel">
                                <input type="hidden" name="phone" id="phone_e164_school"
                                       value="{{ old('phone') }}">
                                <ul class="list f-16 mt-1"><li>Формат: +7 (999) 000-00-00</li></ul>
                                @error('phone')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <label>Email</label>
                                <input type="email" name="email" value="{{ old('email') }}"
                                       placeholder="info@sunvolley.ru">
                                @error('email')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <label>Сайт</label>
                                <input type="url" name="website" value="{{ old('website') }}"
                                       placeholder="https://sunvolley.ru">
                                @error('website')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
               

                <div class="ramka">
                    <h2 class="-mt-05">Социальные сети</h2>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <label class="d-flex fvc gap-1"><span class="icon-vk" style="width:2rem;height:2rem;flex-shrink:0;color:#0077FF;"></span><span style="color:#0077FF;font-weight:600;">ВКонтакте</span></label>
                                <input type="url" name="vk_url" value="{{ old('vk_url') }}"
                                       placeholder="https://vk.com/sunvolley">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <label class="d-flex fvc gap-1"><span class="icon-tg" style="width:2rem;height:2rem;flex-shrink:0;color:#26A5E4;"></span><span style="color:#26A5E4;font-weight:600;">Telegram</span></label>
                                <input type="url" name="tg_url" value="{{ old('tg_url') }}"
                                       placeholder="https://t.me/sunvolley">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <label class="d-flex fvc gap-1"><span class="icon-max" style="width:2rem;height:2rem;flex-shrink:0;color:#8B5CF6;"></span><span style="color:#8B5CF6;font-weight:600;">Max</span></label>
                                <input type="url" name="max_url" value="{{ old('max_url') }}"
                                       placeholder="https://max.ru/sunvolley">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ramka text-center">
                    <button type="submit" class="btn">Создать страницу школы</button>
                </div>
 </div>
            </form>
        </div>
            </div>{{-- /col-lg-8 --}}
        </div>{{-- /row --}}
    </div>

</x-voll-layout>