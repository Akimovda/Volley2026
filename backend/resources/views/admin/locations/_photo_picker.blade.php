{{--
    Выбор фото локации: кнопка «+ Добавить» и миниатюры выбранных файлов (с кнопкой удаления).
    Параметры: $label (string), $max (int, по умолчанию 5).
    Файлы копятся между выборами (DataTransfer) и уходят как photos[] обычной отправкой формы.
--}}
@php
    $ppMax = (int) ($max ?? 5);
    $ppId = 'pp' . substr(md5(uniqid('', true)), 0, 6);
@endphp
<div class="card">
    <label>{{ $label }}</label>
    <input id="{{ $ppId }}-input" type="file" name="photos[]" multiple accept="image/*" style="display:none">
    <div id="{{ $ppId }}-grid" class="d-flex" style="flex-wrap:wrap;gap:1rem;align-items:center;">
        <button type="button" id="{{ $ppId }}-add" class="btn btn-outline">+ {{ __('admin.loc_photo_add') }}</button>
    </div>
    <div class="f-16 b-500 mt-1">{{ __('admin.loc_photos_hint') }}</div>
    @error('photos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @error('photos.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    <div class="pb-05"></div>
</div>
<script>
(function () {
    var MAX = @json($ppMax);
    var MSG = @json(__('admin.loc_max_5_files_alert'));
    var input = document.getElementById(@json($ppId . '-input'));
    var grid = document.getElementById(@json($ppId . '-grid'));
    var addBtn = document.getElementById(@json($ppId . '-add'));
    var files = [];

    function sync() {
        var dt = new DataTransfer();
        files.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
    }
    function render() {
        Array.prototype.slice.call(grid.querySelectorAll('.pp-thumb')).forEach(function (n) {
            var img = n.querySelector('img'); if (img) URL.revokeObjectURL(img.src); n.remove();
        });
        files.forEach(function (f, i) {
            var box = document.createElement('div');
            box.className = 'pp-thumb';
            box.style.cssText = 'position:relative;width:11rem;height:8rem;border-radius:1rem;overflow:hidden;';
            var img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            img.alt = '';
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
            var x = document.createElement('button');
            x.type = 'button';
            x.textContent = '✕';
            x.setAttribute('aria-label', 'remove');
            x.style.cssText = 'position:absolute;top:.4rem;right:.4rem;width:2.4rem;height:2.4rem;border:0;border-radius:50%;background:rgba(0,0,0,.65);color:#fff;font-size:1.3rem;line-height:2.4rem;padding:0;cursor:pointer;';
            x.addEventListener('click', function () { files.splice(i, 1); sync(); render(); });
            box.appendChild(img); box.appendChild(x);
            grid.insertBefore(box, addBtn);
        });
        addBtn.style.display = files.length >= MAX ? 'none' : '';
    }

    addBtn.addEventListener('click', function () { input.value = ''; input.click(); });
    input.addEventListener('change', function () {
        var picked = Array.prototype.slice.call(input.files || []);
        var over = false;
        picked.forEach(function (f) {
            if (files.length >= MAX) { over = true; return; }
            files.push(f);
        });
        if (over) alert(MSG);
        sync(); render();
    });
    render();
})();
</script>
