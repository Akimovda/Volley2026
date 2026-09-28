{{--
    Блок «Фото школы» — выбор/загрузка логотипа (до 10 штук, один основной)
    и обложек (слайд-шоу в шапке карточки школы, до 5 штук, порядок = порядок выбора).
    Используется на create.blade.php и edit.blade.php.

    Ожидаемые переменные:
    $schoolLogos, $schoolCovers — Collection<Media> организатора
    $selectedLogoId — id текущего логотипа (опционально)
    $selectedCoverIds — array id обложек для слайд-шоу, в порядке показа (опционально)
--}}
@php
    $selectedLogoId = (int) old('logo_media_id', $selectedLogoId ?? optional($schoolLogos->first())->id);

    $defaultCoverIds = $selectedCoverIds ?? (optional($schoolCovers->first())->id ? [$schoolCovers->first()->id] : []);
    $selectedCoverIds = array_map('intval', (array) old('cover_media_ids', $defaultCoverIds));
@endphp
<div class="ramka">
    <h2 class="-mt-05">Фото школы</h2>
    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <label>Логотип школы</label>
                <input type="hidden" name="logo_media_id" id="logo_media_id_input" value="{{ $selectedLogoId ?: '' }}">

                <div id="school-logo-gallery" class="d-flex gap-1 mb-1" style="flex-wrap:wrap; {{ $schoolLogos->isEmpty() ? 'display:none' : '' }}">
                    @foreach($schoolLogos as $m)
                    <div class="school-logo-thumb {{ $selectedLogoId === $m->id ? 'school-photo-thumb--active' : '' }}" data-media-id="{{ $m->id }}" onclick="selectSchoolLogo(this)">
                        <img src="{{ $m->hasGeneratedConversion('school_logo_thumb') ? $m->getUrl('school_logo_thumb') : $m->getUrl() }}"
                        style="width:8rem;height:8rem;object-fit:cover;cursor:pointer;{{ $selectedLogoId === $m->id ? 'outline:2px solid #2967BA;' : '' }}">
                    </div>
                    @endforeach
                </div>
                <div id="school-logo-empty-hint" class="f-14 mb-1" style="opacity:.6; {{ $schoolLogos->isNotEmpty() ? 'display:none' : '' }}">Логотип не выбран</div>

                <input type="file" id="school-logo-upload" accept="image/*" style="display:none">
                <button type="button" class="btn btn-secondary w-100" id="school-logo-upload-btn" @if($schoolLogos->count() >= 10) style="display:none" @endif>+ Добавить логотип</button>
                <ul class="list f-14 mt-1"><li>Квадратное изображение, будет обрезано 1:1. До 10 штук — можно выбрать основной, кликнув по фото.</li></ul>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <label>Обложки (слайд-шоу в шапке карточки школы)</label>
                <input type="hidden" name="cover_gallery_submitted" value="1">
                <div id="cover-media-ids-inputs">
                    @foreach($selectedCoverIds as $id)
                    <input type="hidden" name="cover_media_ids[]" class="cover-media-ids-input" value="{{ $id }}">
                    @endforeach
                </div>

                <div id="school-cover-gallery" class="d-flex gap-1 mb-1" style="flex-wrap:wrap; {{ $schoolCovers->isEmpty() ? 'display:none' : '' }}">
                    @foreach($schoolCovers as $cm)
                    @php $pos = array_search($cm->id, $selectedCoverIds, true); @endphp
                    <div class="school-cover-thumb {{ $pos !== false ? 'school-photo-thumb--active' : '' }}" data-media-id="{{ $cm->id }}" onclick="toggleSchoolCover(this)" style="position:relative">
                        <img src="{{ $cm->hasGeneratedConversion('school_cover_thumb') ? $cm->getUrl('school_cover_thumb') : $cm->getUrl() }}"
                        style="width:9rem;aspect-ratio:16/9;object-fit:cover;cursor:pointer;{{ $pos !== false ? 'outline:2px solid #2967BA;' : '' }}">
                        <span class="school-cover-order-badge" style="{{ $pos !== false ? '' : 'display:none' }}">{{ $pos !== false ? $pos + 1 : '' }}</span>
                    </div>
                    @endforeach
                </div>
                <div id="school-cover-empty-hint" class="f-14 mb-1" style="opacity:.6; {{ $schoolCovers->isNotEmpty() ? 'display:none' : '' }}">
                    Обложка не выбрана — шапка карточки останется без фото
                </div>

                <input type="file" id="school-cover-upload" accept="image/*" style="display:none">
                <button type="button" class="btn btn-secondary w-100" id="school-cover-upload-btn" @if($schoolCovers->count() >= 5 && count($selectedCoverIds) >= 5) style="display:none" @endif>+ Загрузить обложку</button>
                <ul class="list f-14 mt-1">
                    <li>Широкое изображение, будет обрезано 16:9</li>
                    <li>Кликайте по фото, чтобы выбрать несколько — до 5 штук, они будут сменять друг друга слайд-шоу в шапке карточки школы в списке</li>
                    <li>Цифра на фото — порядок показа; повторный клик снимает выбор</li>
                    <li>Если не выбрать ни одной — шапка карточки останется без фото</li>
                </ul>
            </div>
        </div>
    </div>
</div>
