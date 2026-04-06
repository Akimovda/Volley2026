{{-- resources/views/user/photos.blade.php --}}

@php
$isEditingOther = false;
$editingUserId = request()->get('user_id');
if ($editingUserId && auth()->user()?->isAdmin()) {
$isEditingOther = true;
$editingUser = \App\Models\User::find($editingUserId);
if ($editingUser) {
$user = $editingUser;
}
}
$canUploadEventPhotos = auth()->user()?->isAdmin() || auth()->user()?->isOrganizer();
@endphp
<x-voll-layout body_class="user-photos-page">
    
    <x-slot name="title">
		@if(!$isEditingOther)
        Ваши фотографии
		@else
		Редактирование фотографий профиля
		@endif	
	</x-slot>
    
    <x-slot name="description">
        Управление фотографиями пользователя
	</x-slot>
    
    <x-slot name="canonical">
        {{ route('user.photos') }}
	</x-slot>
    
    <x-slot name="style">
        
        <link href="/css/cropper.min.css" rel="stylesheet" />
        <style>     
            .filepond--credits {
            display: none;
            }   
            /* filepond-base.css - минимальный стиль для дропзоны */
            
            .filepond--root {
            font-family: inherit;
            margin-bottom: 0;
            position: relative;
            }
            
            .file-wrap {
            overflow: hidden; 
            height: 146px;
            position: relative;
            }
            .filepond--root {
            opacity: 0;
            transition: opacity 0.3s;
            }
            body.loaded .filepond--root {
            opacity: 1;
            }           
            .file-wrap input {
            display: none;
            }   
            .filepond--drop-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 140px;
            background: #fff;
            border: 0.2rem dashed rgba(0, 0, 0, 0.1);
            border-radius: 1rem;
            transition: border 0.2s ease;
            cursor: pointer;
            font-weight: 500;
            transform: unset!important;
            }
            .filepond--root[data-hopper-state="drag-over"] .filepond--drop-label {
            background: rgba(0, 0, 0, 0.05);
            transition: background 0.3s;
            }
            body.dark .filepond--drop-label {
            background: #222333;
            border: 0.2rem dashed rgba(255, 255, 255, 0.1);
            }
            body.dark .filepond--root[data-hopper-state="drag-over"] .filepond--drop-label {
            background: rgba(255, 255, 255, 0.05);
            }
            .filepond--drop-label:hover {
            border-color: #2967BA;
            }
            body.dark .filepond--drop-label:hover {
            border-color: #E7612F;
            }
            .filepond--drop-label label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 140px;
            cursor: pointer;
            font-size: 1.6rem;
            transform: none;
            font-weight: 500;
            margin-bottom: 0.2rem;
            text-align: center;
            }
            
            .filepond--label-action {
            color: #0d6efd;
            text-decoration: none;
            cursor: pointer;
            }
            
            .filepond--label-action:hover {
            text-decoration: underline;
            }
            
            /* Скрываем всё лишнее */
            .filepond--panel,
            .filepond--drip,
            .filepond--item,
            .filepond--browser {
            display: none !important;
            }   
            
            /* Стили для модалки кропа */
            .cropper-modal-overlay {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            display: flex;
            flex-flow: column;
            align-items: center;
            justify-content: center;
            font-size: 0;
            overflow: hidden;
            z-index: 10000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            }
            
            .cropper-modal-overlay--active {
            opacity: 1;
            pointer-events: auto;
            }
            
            /* Анимированные полосы */
            .cropper-modal-overlay:before,
            .cropper-modal-overlay:after {
            content: "";
            position: absolute;
            top: 100vh;
            width: 100%;
            height: 100%;
            background: #fff;
            opacity: 0.8;
            transition-duration: 0.4s;
            transition-property: all;
            transition-timing-function: cubic-bezier(.47, 0, .74, .71);
            clip-path: polygon(100% 80%, 100% 100%, 0% 100%, 0% 20%);
            }
            
            .cropper-modal-overlay:after {
            clip-path: polygon(100% 0%, 100% 80%, 0% 20%, 0% 0%);
            top: -100vh;
            opacity: 0.5;
            }
            
            .cropper-modal-overlay--active:before,
            .cropper-modal-overlay--active:after {
            top: 0;
            left: 0;
            transition-timing-function: cubic-bezier(.22, .61, .36, 1);
            }
            
            body.dark .cropper-modal-overlay:before,
            body.dark .cropper-modal-overlay:after {
            background: #000;
            }
            
            /* Контейнер модалки - без скролла */
            .cropper-modal-container {
            position: relative;
            z-index: 10001;
            background: #fff;
            border-radius: 1.6rem;
            padding: 2rem;
            width: 90vw;
            max-width: 100rem;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: rgba(0, 0, 0, 0.1) 0px 1rem 2.2rem, rgba(0, 0, 0, 0.05) 0px 0.5rem 1.2rem;
            transform: scale(0.95);
            transition: transform 0.3s ease;
            overflow: hidden;  /* 👈 убираем скролл у контейнера */
            }
            
            .cropper-modal-overlay--active .cropper-modal-container {
            transform: scale(1);
            }
            
            body.dark .cropper-modal-container {
            background: #2a2b3a;
            color: #e9ecef;
            }
            
            /* Обертка для изображения - адаптивная высота */
            .cropper-image-wrapper {
            background: #f5f5f5;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
            flex: 1;
            min-height: 0;  /* 👈 важно для flex-сжатия */
            display: flex;
            align-items: center;
            justify-content: center;
            }
            
            body.dark .cropper-image-wrapper {
            background: #1e1e2a;
            }
            
            .cropper-image-wrapper img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
            cursor: move;
            }
            
            /* Заголовок - фиксированный */
            .cropper-modal-container h3 {
            margin: 0 0 2rem 0;
            text-align: center;
            flex-shrink: 0;
            font-size: 2rem;
            }
            
            /* Кнопки - фиксированные */
            .cropper-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1rem;
            }
            /* Позиционирование спиннера внутри модалки */
            .cropper-modal-overlay .fancybox-loading {
            position: absolute;
            top: calc(50% - 75px);
            left: calc(50% - 75px);
            width: 150px;
            height: 150px;
            display: none;
            z-index: 10002;
            }
            .cropper-modal-overlay .fancybox-loading.active,
            .cropper-modal-overlay .fancybox-loading[style*="block"] {
            display: block !important;
            }
            /* Затемнение фона при загрузке */
            .cropper-modal-overlay.loading .cropper-container {
            opacity: 0.5;
            transition: opacity 0.2s ease;
            }
            
            .cropper-modal-overlay.loading .cropper-modal-container * {
            pointer-events: none;
            }
            
            .cropper-modal-overlay.loading .fancybox-loading {
            display: block !important;
            }
		</style>
	</x-slot>
    
    <x-slot name="h1">
		
		@if(!$isEditingOther)
        Ваши фотографии
		@else
		Редактирование фотографий
		@endif		
		
	</x-slot>
    
    <x-slot name="h2">
        @if(!empty($user->first_name) || !empty($user->last_name))
        {{ trim($user->first_name . ' ' . $user->last_name) }}
        @else
        Пользователь #{{ $user->id }}
        @endif
	</x-slot>
    
    <x-slot name="t_description">
        Загружайте и управляйте своими фотографиями
	</x-slot>
    
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item">
                <span itemprop="name">Профиль</span>
			</a>
            <meta itemprop="position" content="2">
		</li>
		
		@if(!$isEditingOther)
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">Ваши фотографии</span>
            <meta itemprop="position" content="3">
		</li>
		@else
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">Редактирование фотографий</span>
            <meta itemprop="position" content="3">
		</li>
		@endif		
		
		
		
	</x-slot>
    
    
    <x-slot name="script">
        <script src="/assets/fas.js"></script>     
        {{-- FilePond scripts (CDN) --}}
        
        
        <script src="/js/filepond.js"></script>
        <script src="/js/cropper.min.js"></script>
        <script>
            
            function supportsWebP() {
                try {
                    const canvas = document.createElement('canvas');
                    return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
                    } catch (e) {
                    return false;
				}
			}       
            
            // === Функция обработки изображения ===
            function processImage(file, callback) {
                const url = URL.createObjectURL(file);
                const img = new Image();
                
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;
                    
                    // 1. Ресайз до 1920x1920 (сохраняем пропорции)
                    const maxSize = 1920;
                    if (width > maxSize || height > maxSize) {
                        const ratio = Math.min(maxSize / width, maxSize / height);
                        width = Math.round(width * ratio);
                        height = Math.round(height * ratio);
					}
                    
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    const format = supportsWebP() ? 'image/webp' : 'image/jpeg';
                    const quality = 0.85;
                    
                    canvas.toBlob((blob) => {
                        callback(blob, format);
					}, format, quality);
				};
                
                img.src = url;
			}
            
            
            // === Создаем FilePond ===
            const pond = FilePond.create(document.querySelector('#photo'), {
                allowMultiple: false,
                instantUpload: false,
                allowProcess: false,  // Отключаем авто-отправку
                
                labelIdle: 'Перетащи фото или <span class="btn mt-1 mb-1">выбери файл</span> <span>Допустимые форматы: <b class="d-inline-block">JPEG, PNG, WEBP, AVIF</b></span>',
                labelButtonRemoveItem: 'Удалить',
                //fileAcceptAttribute: 'image/jpeg,image/png,image/webp',
                allowImageTransform: false,
                allowImageCrop: false,
                allowImageResize: false,
                
                imagePreviewHeight: 300,
                imagePreviewMinHeight: 300,
                
                name: 'photo',
                server: null,  // Убираем отправку через FilePond
			});
            
            
            // Блокировка одновременного выбора галочек
            const avatarCheckbox = document.querySelector('#make_avatar');
            const eventsCheckbox = document.querySelector('#for_events');
            
            if (avatarCheckbox && eventsCheckbox) {
                avatarCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        eventsCheckbox.checked = false;
					}
				});
                
                eventsCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        avatarCheckbox.checked = false;
					}
				});
			}           
            
            
            // === Переменные ===
            let cropper = null;
            let currentFile = null;
            
            pond.on('addfile', (error, file) => {
                if (error) return;
                
                
                // Проверяем, что это изображение
                if (!file.file.type.startsWith('image/')) {
                    swal({
                        icon: 'error',
                        title: 'Неподдерживаемый формат',
                        text: 'Можно загружать только изображения\n (JPEG, PNG, WEBP, AVIF)',
                        confirmButtonText: 'Понятно'
					});
                    pond.removeFile(file.id);
                    return;
				}               
                
                
                const maxSize = 15 * 1024 * 1024;
                if (file.file.size > maxSize) {
                    swal({
                        icon: 'error',
                        title: 'Файл слишком большой',
                        text: 'Максимальный размер: 15 МБ',
                        confirmButtonText: 'Понятно'
					});
                    pond.removeFile(file.id);
                    return;
				}
                
                const type = file.file.type;
                
                // JPEG/PNG/WEBP/AVIF идём через processImage
                processImage(file.file, (blob, format) => {
                    currentFile = { file: blob, format: format };
                    
                    const url = URL.createObjectURL(blob);
                    
                    showCropperModal(url, (croppedBlob, cropFormat) => {
                        pond.removeFile(file.id);
                        const forEvents = document.querySelector('#for_events')?.checked ? 1 : 0;
                        sendFiles(blob, croppedBlob, cropFormat, forEvents);
					});
				});
                
                
			});
            
            
            
            // === Отправка двух файлов на сервер ===
            function sendFiles(originalFile, croppedBlob, format, forEvents = 0) {
                const formData = new FormData();
                
                // Оригинальный файл (уже сконвертированный в WEBP или оригинал)
                const extension = format === 'image/webp' ? 'webp' : 'jpg';
                const originalName = `original_${Date.now()}.${extension}`;
                
                formData.append('photo_original', originalFile, originalName);
                
                // 2. Обрезанный квадрат (всегда jpg)
                const croppedExt = format === 'image/webp' ? 'webp' : 'jpg';
                const croppedName = `thumb_${Date.now()}.${croppedExt}`;
                
                formData.append('photo_cropped', croppedBlob, croppedName);
                
                // 3. Дополнительные поля
                const makeAvatar = document.querySelector('#make_avatar')?.checked ? 1 : 0;
                formData.append('make_avatar', makeAvatar);
                formData.append('for_events', forEvents);  // 👈 новое поле
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                
                // Добавляем user_id если редактируем другого
                @if($isEditingOther)
                formData.append('user_id', '{{ $user->id }}');
                @endif
                
                fetch('/user/photos', {
                    method: 'POST',
                    body: formData,
                    }).then(async (response) => {
                    const data = await response.json();
                    
					if (response.ok && data.success) {
						// Сохраняем user_id если он есть
						const urlParams = new URLSearchParams(window.location.search);
						const userId = urlParams.get('user_id');
						const redirectUrl = userId 
						? window.location.pathname + '?upload=success&user_id=' + userId
						: window.location.pathname + '?upload=success';
						window.location.href = redirectUrl;
						} else {
                        // Сначала закрываем модалку
                        const modal = document.querySelector('.cropper-modal-overlay');
                        if (modal) modal.remove();
                        
                        // Потом показываем ошибку
                        swal({
                            title: "Ошибка",
                            text: data.error || 'Ошибка загрузки',
                            icon: "error",
                            button: "Понятно"
						});
					}
                    }).catch(() => {
                    // Сначала закрываем модалку
                    const modal = document.querySelector('.cropper-modal-overlay');
                    if (modal) modal.remove();
                    
                    swal({
                        title: "Ошибка",
                        text: "Проблема с соединением",
                        icon: "error",
                        button: "Понятно"
					});
				});
			}
            
            function showCropperModal(imageUrl, onCropComplete) {
                const modal = document.createElement('div');
                modal.className = 'cropper-modal-overlay';
                
                const forEvents = document.querySelector('#for_events')?.checked ? true : false;
                const aspectRatio = forEvents ? 16/9 : 1;
                const titleText = forEvents ? 'Выберите область для эскиза мероприятия' : 'Выберите область для эскиза';      
                
                
                const container = document.createElement('div');
                container.className = 'cropper-modal-container';
                
                const title = document.createElement('h3');
                title.textContent = titleText;  // 👈 динамический заголовок
                
                const imgWrapper = document.createElement('div');
                imgWrapper.className = 'cropper-image-wrapper';  // 👈 используем класс
                
                const img = document.createElement('img');
                img.src = imageUrl;
                imgWrapper.appendChild(img);
                
                
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'cropper-buttons';
                
                const saveBtn = document.createElement('button');
                saveBtn.textContent = 'Загрузить';
                saveBtn.className = 'btn';
                
                const cancelBtn = document.createElement('button');
                cancelBtn.textContent = 'Отмена';
                cancelBtn.className = 'btn btn-secondary';
                
                buttonContainer.appendChild(saveBtn);
                buttonContainer.appendChild(cancelBtn);
                
                // 👇 ИСПОЛЬЗУЕМ СУЩЕСТВУЮЩИЙ fancybox-loading
                const loading = document.createElement('div');
                loading.className = 'fancybox-loading';
                loading.style.display = 'none';
                modal.appendChild(loading);    
                
                
                container.appendChild(title);
                container.appendChild(imgWrapper);
                container.appendChild(buttonContainer);
                modal.appendChild(container);
                document.body.appendChild(modal);
                
                modal.offsetHeight; // рефлоу
                
                requestAnimationFrame(() => {
                    modal.classList.add('cropper-modal-overlay--active');
				});
                
                img.onload = () => {
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(img, {
                        aspectRatio: aspectRatio,  // 👈 динамический aspect ratio,
                        viewMode: 1,
                        background: true,
                        dragMode: 'crop',
                        autoCropArea: 0.8,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        zoomable: true,
                        zoomOnWheel: true,
                        wheelZoomRatio: 0.1,
                        movable: true,
                        guides: true,
                        center: true,
                        highlight: true,
                        responsive: true,
                        restore: false,
					});
				};
                
                saveBtn.onclick = () => {
                    if (!cropper) return;
                    
                    
                    
                    // Добавляем класс loading для затемнения
                    modal.classList.add('loading');
                    
                    saveBtn.disabled = true;
                    cancelBtn.disabled = true;
                    
                    const canvas = cropper.getCroppedCanvas({
                        width: forEvents ? 640 : 360,
                        height: forEvents ? 360 : 360,
					});
                    
                    const format = supportsWebP() ? 'image/webp' : 'image/jpeg';
                    
                    canvas.toBlob((blob) => {
                        onCropComplete(blob, format);
					}, format, 0.90);
				};
                
                cancelBtn.onclick = () => {
                    modal.remove();
                    if (cropper) {
                        cropper.destroy();
                        cropper = null;
					}
                    pond.removeFile(currentFile?.id);
				};
                
                modal.onclick = (e) => {
                    if (e.target === modal) cancelBtn.onclick();
				};
			}       
            
            
		</script>
        
        <script>
            // === Инициализация Swiper ===
            const swiper = new Swiper('.photo-swiper', {
                slidesPerView: 2,
                spaceBetween: 20,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
				},
                breakpoints: {
                    640: {
                        slidesPerView: 3, 
					},
                    768: {
                        slidesPerView: 3,
					},
                    992: {
                        slidesPerView: 3,
					},
                    1024: {
                        slidesPerView: 3,
					},
                    1280: {
                        slidesPerView: 4,
					}
				}   
			});     
		</script>
		
		@if($canUploadEventPhotos)
        <script>
            // Инициализация второго свайпера
            const eventSwiper = new Swiper('.event-photo-swiper', {
                slidesPerView: 1,
                spaceBetween: 20,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
				},
                breakpoints: {
                    640: { slidesPerView: 2 },
                    768: { slidesPerView: 2 },
                    992: { slidesPerView: 2 },
                    1024: { slidesPerView: 2 },
                    1280: { slidesPerView: 3 }
				}
			});
		</script>
        @endif  
		
	</x-slot>
    
    <div class="container">
        
        @if(request()->get('upload') == 'success')
        <div class="ramka">    
            <div class="alert alert-success">
                Фото добавлено ✅
			</div>
		</div>
        <script>
            window.history.replaceState({}, document.title, window.location.pathname);
		</script>       
        @endif      
        
        
        {{-- FLASH --}}
        @if (session('status'))
        <div class="ramka">    
            <div class="alert alert-success">
                {{ session('status') }}
			</div>
		</div>
        @endif
        
        @if (session('error'))
        <div class="ramka">        
            <div class="alert alert-danger">
                {{ session('error') }}
			</div>
		</div>
        @endif
        
        @if ($errors->any())
        <div class="ramka">        
            <div class="alert alert-danger">
                <div class="b-600 mb-1">Ошибки:</div>
                <ul class="list">
                    @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                    @endforeach
				</ul>
			</div>
		</div>
        @endif      
        
        
        <div class="row">
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka mb-2">
                        <div class="row">
                            <div class="col-3 col-lg-12">
                                <div class="profile-avatar">
                                    <img
                                    src="{{ $user->profile_photo_url }}"
                                    alt="avatar"
                                    class="avatar"
                                    />          
								</div>
							</div>
                            <div class="col-9 col-lg-12">
                                <nav class="menu-nav sidebar-menu">
                                    @if(!$isEditingOther)
									<a href="{{ route('users.show', ['user' => $user->id]) }}" class="menu-item">
										<span class="menu-text">Публичный профиль</span>
									</a>										
                                    <a href="{{ route('profile.show') }}" class="menu-item">
                                        <span class="menu-text">Ваш профиль</span>
									</a>
                                    <a href="{{ url('/profile/complete') }}" class="menu-item">
                                        <span class="menu-text">Редактировать профиль</span>
									</a>
                                    <a href="{{ route('user.photos') }}" class="menu-item active">
                                        <strong class="cd menu-text">Ваши фотографии</strong>
									</a>
									
									
									<a href="{{ route('notifications.index') }}" class="menu-item">
										<span class="menu-text">Уведомления</span>
										@if(!empty($notificationsUnread) && $notificationsUnread > 0)
										<span class="notificationsUnread">
											{{ $notificationsUnread > 99 ? '99+' : $notificationsUnread }}
										</span>
										@endif										
									</a>									
									
									
                                    {{-- logout: только logout --}}
                                    <form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
                                        @csrf
                                        <button type="submit" class="menu-item">Выйти</button>
									</form>                                         
                                    @else
                                    <a href="{{ route('users.show', ['user' => $user->id]) }}" class="menu-item">
                                        <span class="menu-text">Публичный профиль пользователя</span>
									</a>
                                    
                                    <a href="{{ url('/profile/complete?user_id=' . $user->id) }}" class="menu-item">
                                        <span class="menu-text">Редактировать пользователя</span>
									</a>
                                    
                                    <a href="{{ url('/user/photos?user_id=' . $user->id) }}" class="menu-item active">
                                        <strong class="cd menu-text">
                                            Редактировать фото пользователя
										</strong>
									</a>
                                    @endif
                                    
								</nav>
							</div>  
						</div>
					</div>
				</div>  
			</div> 
            <div class="col-lg-8 col-xl-9 order-1">    
                <div class="ramka" style="z-index:10">      
                    <h2 class="-mt-05">Загрузить фото</h2>
                    {{-- Upload --}}
                    
                    <div class="form">
                        {{--
                        <p>Обрезка 1:1, поворот, авто‑сжатие. Можно "Загрузить" или "Отменить".</p>
                        --}}  
                        
                        <form id="photoUploadForm"
						action="{{ $isEditingOther ? route('user.photos.store', ['user_id' => $user->id]) : route('user.photos.store') }}"
						method="POST"
						enctype="multipart/form-data"
						class="mt-2">
                            @csrf
                            
                            @if($isEditingOther)
                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                            @endif
                            
                            <label class="checkbox-item mb-2">
                                <input id="make_avatar" name="make_avatar" type="checkbox" class="rounded border-gray-300" />
                                <div class="custom-checkbox"></div>
                                <span>Сделать это фото аватаром</span>
							</label>
                            
							@if($canUploadEventPhotos)
                            <label class="checkbox-item mb-2" id="forEventsLabel">
                                <input id="for_events" name="for_events" type="checkbox" class="rounded border-gray-300" />
                                <div class="custom-checkbox"></div>
                                <span>Сделать это фото для мероприятий</span>
							</label>
                            @endif
                            
                            <div class="file-wrap">
                                <input type="file" name="photo" id="photo" accept="image/*" />
							</div>
                            
                            
						</form>
                        {{--
                        <div class="text-muted small mt-3">
                            Если увидишь 413 от nginx — увеличь <code>client_max_body_size</code> (вы это уже правили).
						</div>
                        --}}
					</div>
				</div>
                <div class="ramka">        
                    {{-- Gallery --}}
                    <h2 class="-mt-05">Галерея</h2>
                    <p>Всего: <strong class="cd">{{ $photos->count() }}</strong> фото</p>
                    
                    <div class="form mt-2">
                        
                        @if($photos->isEmpty())
                        <div class="alert alert-info">
                            Фотографий нет, загрузите первое — и оно станет аватаром автоматически.
						</div>
                        @else
                        <div class="swiper photo-swiper">
                            <div class="swiper-wrapper">
                                @foreach($photos as $m)
                                @php
                                $thumbUrl = method_exists($m, 'hasGeneratedConversion') && $m->hasGeneratedConversion('thumb')
                                ? $m->getUrl('thumb')
                                : $m->getUrl();
                                $isAvatar = $user->avatar_media_id && $user->avatar_media_id == $m->id;
                                @endphp
                                <div class="swiper-slide">
                                    
                                    <div class="hover-image">
                                        <a href="{{ $m->getUrl() }}" class="fancybox" data-fancybox="gallery">
                                            <img
                                            src="{{ $thumbUrl }}"
                                            alt="photo"
                                            loading="lazy"
                                            />
                                            <span></span>
                                            <div class="hover-image-circle"></div>
										</a>
									</div>                              
									
                                    
                                    <div class="mt-1 d-flex between fvc">
                                        
                                        @if($isAvatar)
                                        <span class="cd f-16 l-11 b-600">
                                            Аватар
										</span>
                                        
                                        
                                        @else
                                        <form method="POST" action="{{ route('user.photos.setAvatar', ['media' => $m->id]) }}">
                                            @csrf
                                            <span onclick="event.preventDefault(); this.closest('form').submit();" 
                                            class="blink f-16 l-11">
                                                Сделать фото<br>аватаром
											</span>
										</form>
                                        @endif          
                                        <form method="POST"
                                        action="{{ route('user.photos.destroy', ['media' => $m->id]) }}"
                                        onsubmit="return confirm('Удалить фото?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                            class="icon-delete btn-alert btn btn-danger btn-svg"
                                            data-title="Удалить фото?"
                                            data-icon="warning"
                                            data-confirm-text="Да, удалить"
                                            data-cancel-text="Отмена">
											</button>                               
										</form>
									</div>                              
								</div>
                                @endforeach
							</div>
                            <div class="swiper-pagination"></div>
						</div>
                        @endif
					</div>
				</div>  
                
				{{-- После существующей галереи --}}
				@if($canUploadEventPhotos && (!$isEditingOther || auth()->user()?->isAdmin()))
				<div class="ramka">        
					<h2 class="-mt-05">Фото для мероприятий</h2>
					<p>Всего: <strong class="cd">{{ isset($eventPhotos) ? $eventPhotos->count() : 0 }}</strong> фото</p>
					
					<div class="form mt-2">
						@if(!isset($eventPhotos) || $eventPhotos->isEmpty())
						<div class="alert alert-info">
							Нет фото для мероприятий. Загрузите фото с галочкой "Для мероприятий".
						</div>
						@else
						<div class="swiper event-photo-swiper">
							<div class="swiper-wrapper">
								@foreach($eventPhotos as $m)
								<div class="swiper-slide">
									<div class="hover-image">
										<a href="{{ $m->getUrl() }}" class="fancybox" data-fancybox="event-gallery">
											<img
											src="{{ $m->getUrl('event_thumb') ?? $m->getUrl() }}"
											alt="event photo"
											loading="lazy"
											/>
											<span></span>
											<div class="hover-image-circle"></div>
										</a>
									</div>
									
									<div class="mt-1 d-flex between fvc">
										<div></div>
										<form method="POST"
										action="{{ route('user.photos.destroyEventPhoto', ['media' => $m->id]) }}"
										onsubmit="return confirm('Удалить фото из галереи мероприятий?')">
											@csrf
											@method('DELETE')
											<button type="submit" 
											class="icon-delete btn-alert btn btn-danger btn-svg"
											data-title="Удалить фото?"
											data-text="Если в ваших мероприятиях используется эта фотография, она также будет удалена"
											data-icon="warning"
											data-confirm-text="Да, удалить"
											data-cancel-text="Отмена">
											</button>
										</form>
									</div>
								</div>
								@endforeach
							</div>
							<div class="swiper-pagination"></div>
						</div>
						@endif
					</div>
				</div>
				@endif        
				
			</div>  
		</div>          
	</div>
    
    
</x-voll-layout>