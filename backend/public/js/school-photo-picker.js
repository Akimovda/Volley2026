// Логотип и обложка школы волейбола — загрузка с кропом (используется на
// /volleyball_school/create и /volleyball_school/my/edit).
(function () {
    function supportsWebPSchool() {
        try {
            var c = document.createElement('canvas');
            return c.toDataURL('image/webp').indexOf('data:image/webp') === 0;
        } catch (e) { return false; }
    }

    function processImageSchool(file, callback) {
        var url = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function () {
            var w = img.width, h = img.height, maxSize = 1920;
            if (w > maxSize || h > maxSize) {
                var r = Math.min(maxSize / w, maxSize / h);
                w = Math.round(w * r); h = Math.round(h * r);
            }
            var canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            canvas.getContext('2d').drawImage(img, 0, 0, w, h);
            var fmt = supportsWebPSchool() ? 'image/webp' : 'image/jpeg';
            canvas.toBlob(function (blob) { callback(blob, fmt); }, fmt, 0.85);
        };
        img.src = url;
    }

    var schoolCropper = null;

    function showSchoolCropperModal(imageUrl, aspectRatio, cropW, cropH, onCropComplete, onCancel) {
        var modal = document.createElement('div');
        modal.className = 'cropper-modal-overlay';

        var container = document.createElement('div');
        container.className = 'cropper-modal-container';

        var title = document.createElement('h3');
        title.textContent = 'Обрезать фото';

        var imgWrapper = document.createElement('div');
        imgWrapper.className = 'cropper-image-wrapper';

        var img = document.createElement('img');
        img.src = imageUrl;
        imgWrapper.appendChild(img);

        var btnContainer = document.createElement('div');
        btnContainer.className = 'cropper-buttons';

        var saveBtn = document.createElement('button');
        saveBtn.textContent = 'Добавить';
        saveBtn.type = 'button';
        saveBtn.className = 'btn';

        var cancelBtn = document.createElement('button');
        cancelBtn.textContent = 'Отмена';
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-secondary';

        btnContainer.appendChild(saveBtn);
        btnContainer.appendChild(cancelBtn);

        var loading = document.createElement('div');
        loading.className = 'fancybox-loading';
        loading.style.display = 'none';
        modal.appendChild(loading);

        container.appendChild(title);
        container.appendChild(imgWrapper);
        container.appendChild(btnContainer);
        modal.appendChild(container);
        document.body.appendChild(modal);

        modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('cropper-modal-overlay--active'); });

        img.onload = function () {
            if (schoolCropper) schoolCropper.destroy();
            schoolCropper = new Cropper(img, {
                aspectRatio: aspectRatio, viewMode: 1, background: true, dragMode: 'crop',
                autoCropArea: 0.8, cropBoxMovable: true, cropBoxResizable: true,
                zoomable: true, zoomOnWheel: true, wheelZoomRatio: 0.1, movable: true,
                guides: true, center: true, highlight: true, responsive: true, restore: false,
            });
        };

        saveBtn.onclick = function () {
            if (!schoolCropper) return;
            modal.classList.add('loading');
            saveBtn.disabled = true;
            cancelBtn.disabled = true;
            var canvas = schoolCropper.getCroppedCanvas({ width: cropW, height: cropH });
            var fmt = supportsWebPSchool() ? 'image/webp' : 'image/jpeg';
            canvas.toBlob(function (blob) { onCropComplete(blob, fmt); }, fmt, 0.90);
        };

        cancelBtn.onclick = function () {
            modal.remove();
            if (schoolCropper) { schoolCropper.destroy(); schoolCropper = null; }
            if (onCancel) onCancel();
        };

        modal.onclick = function (e) { if (e.target === modal) cancelBtn.onclick(); };
    }

    function sendSchoolPhoto(photoType, originalBlob, croppedBlob, format, onDone) {
        var ext = format === 'image/webp' ? 'webp' : 'jpg';
        var ts = (new Date()).getTime();
        var formData = new FormData();
        formData.append('photo_original', originalBlob, 'original_' + ts + '.' + ext);
        formData.append('photo_cropped', croppedBlob, 'thumb_' + ts + '.' + ext);
        formData.append('photo_type', photoType);
        formData.append('make_avatar', '0');
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        fetch('/user/photos', { method: 'POST', body: formData })
            .then(function (response) {
                return response.json().then(function (data) {
                    var modal = document.querySelector('.cropper-modal-overlay');
                    if (modal) modal.remove();
                    if (response.ok && data.success) {
                        onDone(data.media_id, data.thumb_url);
                    } else {
                        swal({ title: 'Ошибка', text: data.error || 'Не удалось загрузить фото', icon: 'error', button: 'Понятно' });
                    }
                });
            })
            .catch(function () {
                var modal = document.querySelector('.cropper-modal-overlay');
                if (modal) modal.remove();
                swal({ title: 'Ошибка', text: 'Ошибка сети. Попробуйте ещё раз.', icon: 'error', button: 'Понятно' });
            });
    }

    function setupSchoolPhotoUpload(opts) {
        var btn = document.getElementById(opts.btnId);
        var input = document.getElementById(opts.inputId);
        if (!btn || !input) return;
        btn.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function (e) {
            var file = e.target.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                swal({ title: 'Ошибка', text: 'Пожалуйста, выберите изображение', icon: 'error', button: 'Понятно' });
                input.value = ''; return;
            }
            if (file.size > 15 * 1024 * 1024) {
                swal({ title: 'Ошибка', text: 'Файл слишком большой. Максимум 15 МБ.', icon: 'error', button: 'Понятно' });
                input.value = ''; return;
            }
            processImageSchool(file, function (blob) {
                var url = URL.createObjectURL(blob);
                showSchoolCropperModal(url, opts.aspect, opts.cropW, opts.cropH, function (croppedBlob, fmt) {
                    sendSchoolPhoto(opts.photoType, blob, croppedBlob, fmt, function (mediaId, thumbUrl) {
                        opts.onUploaded(mediaId, thumbUrl);
                        input.value = '';
                    });
                }, function () { input.value = ''; });
            });
        });
    }

    // --- Логотип: одиночный выбор ---
    function selectSchoolThumb(el, groupSelector, hiddenInputId) {
        document.querySelectorAll(groupSelector).forEach(function (t) {
            t.classList.remove('school-photo-thumb--active');
            var img = t.querySelector('img'); if (img) img.style.outline = '';
        });
        el.classList.add('school-photo-thumb--active');
        var img = el.querySelector('img'); if (img) img.style.outline = '2px solid #2967BA';
        document.getElementById(hiddenInputId).value = el.dataset.mediaId;
    }

    window.selectSchoolLogo = function (el) { selectSchoolThumb(el, '.school-logo-thumb', 'logo_media_id_input'); };

    function addLogoToGallery(mediaId, thumbUrl) {
        var gallery = document.getElementById('school-logo-gallery');
        if (!gallery) return;
        var hint = document.getElementById('school-logo-empty-hint');
        if (hint) hint.style.display = 'none';
        gallery.style.display = '';
        gallery.querySelectorAll('.school-logo-thumb').forEach(function (t) { t.classList.remove('school-photo-thumb--active'); });

        var thumb = document.createElement('div');
        thumb.className = 'school-logo-thumb school-photo-thumb--active';
        thumb.dataset.mediaId = mediaId;
        thumb.onclick = function () { window.selectSchoolLogo(thumb); };
        thumb.innerHTML = '<img src="' + thumbUrl + '" style="width:8rem;height:8rem;object-fit:cover;cursor:pointer;outline:2px solid #2967BA;">';
        gallery.prepend(thumb);

        if (gallery.querySelectorAll('.school-logo-thumb').length >= 10) {
            var btn = document.getElementById('school-logo-upload-btn');
            if (btn) btn.style.display = 'none';
        }
    }

    // --- Обложки: множественный выбор, до MAX_COVERS штук, порядок = порядок выбора ---
    var MAX_COVERS = 5;

    function coverSelectedIds() {
        var container = document.getElementById('cover-media-ids-inputs');
        if (!container) return [];
        return Array.prototype.map.call(container.querySelectorAll('input'), function (i) { return i.value; });
    }

    function renderCoverSelectionState() {
        var ids = coverSelectedIds();
        document.querySelectorAll('.school-cover-thumb').forEach(function (t) {
            var pos = ids.indexOf(t.dataset.mediaId);
            var img = t.querySelector('img');
            var badge = t.querySelector('.school-cover-order-badge');
            if (pos !== -1) {
                t.classList.add('school-photo-thumb--active');
                if (img) img.style.outline = '2px solid #2967BA';
                if (badge) { badge.style.display = ''; badge.textContent = String(pos + 1); }
            } else {
                t.classList.remove('school-photo-thumb--active');
                if (img) img.style.outline = '';
                if (badge) { badge.style.display = 'none'; badge.textContent = ''; }
            }
        });
        var btn = document.getElementById('school-cover-upload-btn');
        if (btn) btn.style.display = ids.length >= MAX_COVERS ? 'none' : '';
    }

    function addCoverId(mediaId) {
        var container = document.getElementById('cover-media-ids-inputs');
        if (!container) return false;
        if (coverSelectedIds().indexOf(String(mediaId)) !== -1) return true;
        if (coverSelectedIds().length >= MAX_COVERS) {
            swal({ title: 'Максимум ' + MAX_COVERS + ' обложек', text: 'Снимите одну, чтобы выбрать другую.', icon: 'warning', button: 'Понятно' });
            return false;
        }
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'cover_media_ids[]';
        input.className = 'cover-media-ids-input';
        input.value = mediaId;
        container.appendChild(input);
        return true;
    }

    window.toggleSchoolCover = function (el) {
        var id = el.dataset.mediaId;
        var container = document.getElementById('cover-media-ids-inputs');
        var existing = container.querySelector('input[value="' + id + '"]');
        if (existing) {
            existing.remove();
        } else {
            addCoverId(id);
        }
        renderCoverSelectionState();
    };

    function addCoverToGallery(mediaId, thumbUrl) {
        var gallery = document.getElementById('school-cover-gallery');
        if (!gallery) return;
        var hint = document.getElementById('school-cover-empty-hint');
        if (hint) hint.style.display = 'none';
        gallery.style.display = '';

        var thumb = document.createElement('div');
        thumb.className = 'school-cover-thumb';
        thumb.dataset.mediaId = mediaId;
        thumb.style.position = 'relative';
        thumb.onclick = function () { window.toggleSchoolCover(thumb); };
        thumb.innerHTML = '<img src="' + thumbUrl + '" style="width:9rem;aspect-ratio:16/9;object-fit:cover;cursor:pointer;">' +
            '<span class="school-cover-order-badge" style="display:none"></span>';
        gallery.prepend(thumb);

        addCoverId(mediaId);
        renderCoverSelectionState();
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderCoverSelectionState();

        setupSchoolPhotoUpload({
            btnId: 'school-logo-upload-btn', inputId: 'school-logo-upload',
            photoType: 'school_logo', aspect: 1, cropW: 360, cropH: 360,
            onUploaded: function (mediaId, thumbUrl) {
                document.getElementById('logo_media_id_input').value = mediaId;
                addLogoToGallery(mediaId, thumbUrl);
            }
        });

        setupSchoolPhotoUpload({
            btnId: 'school-cover-upload-btn', inputId: 'school-cover-upload',
            photoType: 'school_cover', aspect: 16 / 9, cropW: 640, cropH: 360,
            onUploaded: function (mediaId, thumbUrl) {
                addCoverToGallery(mediaId, thumbUrl);
            }
        });
    });
})();
