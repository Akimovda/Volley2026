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

    <div class="container">

        @if (session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
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
                                <input type="text" name="city" value="{{ old('city', $school->city) }}" placeholder="Москва">
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
                                <label>Описание</label>
                                <textarea name="description" rows="6">{{ old('description', $school->description) }}</textarea>
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
                    <h2 class="-mt-05">Фото</h2>
                    @php
                        $schoolLogos  = auth()->user()->getMedia('school_logo')->sortByDesc('created_at');
                        $schoolCovers = auth()->user()->getMedia('school_cover')->sortByDesc('created_at');
                        $currentLogo  = $school->getFirstMediaUrl('logo', 'thumb') ?: $school->getFirstMediaUrl('logo');
                        $currentCover = $school->getFirstMediaUrl('cover', 'thumb') ?: $school->getFirstMediaUrl('cover');
                    @endphp
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <label>Логотип школы (1:1)</label>
                                @if($currentLogo)
                                    <div class="mb-2">
                                        <div class="f-13 mb-1" style="opacity:.6">Текущий логотип:</div>
                                        <img src="{{ $currentLogo }}" alt="logo"
                                             style="width:6rem;height:6rem;border-radius:50%;object-fit:cover">
                                    </div>
                                @endif
                                @if($schoolLogos->count())
                                    <div class="f-14 mb-1">Выберите новый логотип:</div>
                                    <div class="d-flex flex-wrap gap-1 mb-2" id="logo_picker">
                                        @foreach($schoolLogos as $photo)
                                            <label style="cursor:pointer;position:relative">
                                                <input type="radio" name="logo_media_id"
                                                       value="{{ $photo->id }}"
                                                       @checked(old('logo_media_id') == $photo->id)
                                                       style="position:absolute;opacity:0">
                                                <img src="{{ $photo->hasGeneratedConversion('school_logo_thumb') ? $photo->getUrl('school_logo_thumb') : $photo->getUrl() }}"
                                                     alt="" class="logo-pick-img"
                                                     style="width:6rem;height:6rem;object-fit:cover;border-radius:50%;border:3px solid transparent;transition:.15s"
                                                     onclick="this.closest('label').querySelector('input').checked=true; document.querySelectorAll('.logo-pick-img').forEach(i=>i.style.borderColor='transparent'); this.style.borderColor='var(--cd)'">
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                                <ul class="list f-14 mt-1">
                                    <li>Загрузите логотип в <a href="{{ route('user.photos') }}" target="_blank">Ваши фотографии</a> → «🏫 Логотип школы»</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <label>Обложка страницы (16:9)</label>
                                @if($currentCover)
                                    <div class="mb-2">
                                        <div class="f-13 mb-1" style="opacity:.6">Текущая обложка:</div>
                                        <img src="{{ $currentCover }}" alt="cover"
                                             style="width:100%;max-height:10rem;object-fit:cover;border-radius:0.6rem">
                                    </div>
                                @endif
                                @if($schoolCovers->count())
                                    <div class="f-14 mb-1">Выберите новую обложку:</div>
                                    <div class="d-flex flex-wrap gap-1 mb-2" id="cover_picker">
                                        @foreach($schoolCovers as $photo)
                                            <label style="cursor:pointer;position:relative">
                                                <input type="radio" name="cover_media_id"
                                                       value="{{ $photo->id }}"
                                                       @checked(old('cover_media_id') == $photo->id)
                                                       style="position:absolute;opacity:0">
                                                <img src="{{ $photo->hasGeneratedConversion('school_cover_thumb') ? $photo->getUrl('school_cover_thumb') : $photo->getUrl() }}"
                                                     alt="" class="cover-pick-img"
                                                     style="width:8rem;height:5rem;object-fit:cover;border-radius:0.6rem;border:3px solid transparent;transition:.15s"
                                                     onclick="this.closest('label').querySelector('input').checked=true; document.querySelectorAll('.cover-pick-img').forEach(i=>i.style.borderColor='transparent'); this.style.borderColor='var(--cd)'">
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                                <ul class="list f-14 mt-1">
                                    <li>Загрузите обложку в <a href="{{ route('user.photos') }}" target="_blank">Ваши фотографии</a> → «🖼 Обложка школы»</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ramka text-center">
                    <a href="{{ route('volleyball_school.show', $school->slug) }}" class="btn btn-secondary mr-2">Отмена</a>
                    <button type="submit" class="btn">Сохранить изменения</button>
                </div>

            </form>
        </div>
    </div>

</x-voll-layout>