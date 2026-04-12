{{-- resources/views/volleyball_school/edit.blade.php --}}
<x-voll-layout body_class="volleyball-school-edit-page">

    <x-slot name="title">Редактировать — {{ $school->name }}</x-slot>
    <x-slot name="h1">Редактировать страницу школы</x-slot>
    <x-slot name="h2">{{ $school->name }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('volleyball_school.index') }}" itemprop="item">
                <span itemprop="name">Школы волейбола</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('volleyball_school.show', $school->slug) }}" itemprop="item">
                <span itemprop="name">{{ $school->name }}</span>
            </a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Редактировать</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    <x-slot name="script">
        <script src="/assets/city.js"></script>
        <script src="/assets/org.js?v=2"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('trix-file-accept', function (e) { e.preventDefault(); });
        });
        </script>
    </x-slot>

    <x-slot name="style">
        <link href="/assets/org.css" rel="stylesheet">
        <style>
            .icon-vk, .icon-tg, .icon-max { width:2rem!important;height:2rem!important;flex-shrink:0; }
            .icon-vk svg, .icon-tg svg, .icon-max svg { width:2rem!important;height:2rem!important; }
            trix-toolbar .trix-button--icon-attach { display:none !important; }
        </style>
    </x-slot>

    <div class="container">

        @if (session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

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
            <div class="col-lg-8" style="order:1;">

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
            <form method="POST" action="{{ route('volleyball_school.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="ramka">
                    <h2 class="-mt-05">Основная информация</h2>
                    <div class="row">

                        <div class="col-md-6">
                            <div class="card">
                                <label>Название школы / сообщества <span class="red">*</span></label>
                                <input type="text" name="name" value="{{ old('name', $school->name) }}" required>
                                @error('name')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card">
                                <label>URL страницы</label>
                                <input type="text" value="{{ $school->slug }}" disabled>
                                <ul class="list f-14 mt-1">
                                    <li>URL изменить нельзя</li>
                                    <li><a href="{{ route('volleyball_school.show', $school->slug) }}" class="cd" target="_blank">Открыть страницу →</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card">
                                <label>Направление <span class="red">*</span></label>
                                <select name="direction">
                                    <option value="classic" @selected(old('direction', $school->direction) === 'classic')>🏐 Классика</option>
                                    <option value="beach" @selected(old('direction', $school->direction) === 'beach')>🏖 Пляж</option>
                                    <option value="both" @selected(old('direction', $school->direction) === 'both')>🏐🏖 Классика + Пляж</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <label>Город</label>
                                <input type="hidden" name="city_id" id="city_id" value="{{ old('city_id', $school->city_id) }}">
                                <div class="city-autocomplete" id="city-autocomplete" data-search-url="{{ route('cities.search') }}">
                                    <input type="text" id="city_search"
                                           placeholder="Начните вводить город…"
                                           value="{{ old('city_label', $school->city) }}"
                                           autocomplete="off">
                                    <div id="city_dropdown" class="city-dropdown">
                                        <div id="city_results"></div>
                                    </div>
                                </div>
                                <input type="hidden" name="city_name" id="city_name_hidden" value="{{ old('city_name', $school->city) }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card">
                                <label class="checkbox-item">
                                    <input type="hidden" name="is_published" value="0">
                                    <input type="checkbox" name="is_published" value="1"
                                           @checked(old('is_published', $school->is_published))>
                                    <div class="custom-checkbox"></div>
                                    <span>Страница опубликована</span>
                                </label>
                                <ul class="list f-14 mt-1">
                                    <li>Снимите галочку чтобы скрыть из публичного списка</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="card">
                                <label>Описание школы</label>
                                <input id="school_description" type="hidden" name="description"
                                       value="{{ old('description', $school->description) }}">
                                <trix-editor input="school_description" class="trix-content"
                                             data-direct-upload-url="#"
                                             data-blob-url-template="#"></trix-editor>
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
                                <input type="text" name="phone" value="{{ old('phone', $school->phone) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <label>Email</label>
                                <input type="email" name="email" value="{{ old('email', $school->email) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <label>Сайт</label>
                                <input type="url" name="website" value="{{ old('website', $school->website) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ramka">
                    <h2 class="-mt-05">Социальные сети</h2>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <label class="d-flex fvc gap-1">
                                    <span class="icon-vk" style="width:2rem;height:2rem;flex-shrink:0;color:#0077FF;"></span>
                                    <span style="color:#0077FF;font-weight:600;">ВКонтакте</span>
                                </label>
                                <input type="url" name="vk_url"
                                       value="{{ old('vk_url', $school->vk_url) }}"
                                       placeholder="https://vk.com/sunvolley">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <label class="d-flex fvc gap-1">
                                    <span class="icon-tg" style="width:2rem;height:2rem;flex-shrink:0;color:#26A5E4;"></span>
                                    <span style="color:#26A5E4;font-weight:600;">Telegram</span>
                                </label>
                                <input type="url" name="tg_url"
                                       value="{{ old('tg_url', $school->tg_url) }}"
                                       placeholder="https://t.me/sunvolley">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <label class="d-flex fvc gap-1">
                                    <span class="icon-max" style="width:2rem;height:2rem;flex-shrink:0;color:#8B5CF6;"></span>
                                    <span style="color:#8B5CF6;font-weight:600;">Max</span>
                                </label>
                                <input type="url" name="max_url"
                                       value="{{ old('max_url', $school->max_url) }}"
                                       placeholder="https://max.ru/sunvolley">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ramka">
                    <h2 class="-mt-05">📸 Фото школы</h2>
                    <div class="card">
                        <div class="f-16 mb-1">
                            Управление логотипом и фотографиями школы доступно в вашей галерее.
                        </div>
                        <div class="f-15" style="opacity:.6;">
                            Перейдите в <a href="{{ route('user.photos') }}" target="_blank"><strong>Ваши фотографии</strong></a>
                            — там вы найдёте разделы «Логотип школы» и «Обложка школы».
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('user.photos') }}" class="btn btn-secondary">📸 Перейти в галерею</a>
                        </div>
                    </div>
                </div>

                <div class="ramka text-center">
                    <a href="{{ route('volleyball_school.show', $school->slug) }}" class="btn btn-secondary mr-2">Отмена</a>
                    <button type="submit" class="btn">Сохранить изменения</button>
                </div>

            </form>
        </div>
            </div>{{-- /col-lg-8 --}}
        </div>{{-- /row --}}
    </div>

</x-voll-layout>