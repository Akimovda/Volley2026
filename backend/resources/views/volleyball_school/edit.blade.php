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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <label>Логотип</label>
                                @php $logo = $school->getFirstMediaUrl('logo', 'thumb') ?: $school->getFirstMediaUrl('logo'); @endphp
                                @if($logo)
                                    <img src="{{ $logo }}" alt="logo"
                                         style="width:8rem;height:8rem;border-radius:50%;object-fit:cover;display:block;margin-bottom:1rem">
                                @endif
                                <input type="file" name="logo" accept="image/*">
                                <ul class="list f-14 mt-1"><li>JPG/PNG/WebP, до 2MB</li></ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <label>Обложка</label>
                                @php $cover = $school->getFirstMediaUrl('cover', 'thumb') ?: $school->getFirstMediaUrl('cover'); @endphp
                                @if($cover)
                                    <img src="{{ $cover }}" alt="cover"
                                         style="width:100%;max-height:16rem;object-fit:cover;border-radius:0.8rem;display:block;margin-bottom:1rem">
                                @endif
                                <input type="file" name="cover" accept="image/*">
                                <ul class="list f-14 mt-1"><li>JPG/PNG/WebP, до 5MB. Формат 16:9</li></ul>
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