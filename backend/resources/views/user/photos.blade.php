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
$canUploadSchool = auth()->user()?->isAdmin() || auth()->user()?->isOrganizer();
@endphp
<x-voll-layout body_class="user-photos-page">
    
    <x-slot name="title">
		@if(!$isEditingOther)
        {{ __('profile.photos_h1_self') }}
		@else
		{{ __('profile.photos_h1_other') }}
		@endif	
	</x-slot>
    
    <x-slot name="description">
        {{ __('profile.photos_t_desc') }}
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
            
            .filepond--panel,
            .filepond--drip,
            .filepond--item,
            .filepond--browser {
            display: none !important;
            }   
            
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
            overflow: hidden;
            }
            
            .cropper-modal-overlay--active .cropper-modal-container {
            transform: scale(1);
            }
            
            body.dark .cropper-modal-container {
            background: #2a2b3a;
            color: #e9ecef;
            }
            
            .cropper-image-wrapper {
            background: #f5f5f5;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
            flex: 1;
            min-height: 0;
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
            
            .cropper-modal-container h3 {
            margin: 0 0 2rem 0;
            text-align: center;
            flex-shrink: 0;
            font-size: 2rem;
            }
            
            .cropper-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1rem;
            }
			
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
        {{ __('profile.photos_h1_self') }}
		@else
		{{ __('profile.photos_h1_other_short') }}
		@endif		
	</x-slot>
    
    <x-slot name="h2">
        @if(!empty($user->first_name) || !empty($user->last_name))
        {{ trim($user->first_name . ' ' . $user->last_name) }}
        @else
        {{ __('profile.photos_user_n', ['id' => $user->id]) }}
        @endif
	</x-slot>
    
    <x-slot name="t_description">
        {{ __('profile.photos_t_desc') }}
	</x-slot>
    
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item">
                <span itemprop="name">{{ __('profile.photos_breadcrumb_my_profile') }}</span>
			</a>
            <meta itemprop="position" content="2">
		</li>
		@if(!$isEditingOther)
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('profile.photos_h1_self') }}</span>
            <meta itemprop="position" content="3">
		</li>
		@else
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('profile.photos_h1_other_short') }}</span>
            <meta itemprop="position" content="3">
		</li>
		@endif		
	</x-slot>
    
    
    <x-slot name="script">
        <script src="/assets/fas.js"></script>     
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
            
            function processImage(file, callback) {
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;
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
                    canvas.toBlob((blob) => { callback(blob, format); }, format, 0.85);
				};
                img.src = url;
			}
            
            const pond = FilePond.create(document.querySelector('#photo'), {
                allowMultiple: false,
                instantUpload: false,
                allowProcess: false,
                labelIdle: @json(__('profile.photos_filepond_idle')),
                labelButtonRemoveItem: @json(__('profile.photos_filepond_remove')),
                allowImageTransform: false,
                allowImageCrop: false,
                allowImageResize: false,
                imagePreviewHeight: 300,
                imagePreviewMinHeight: 300,
                name: 'photo',
                server: null,
			});
			
            // Текущий тип фото и соотношение сторон
            let currentPhotoType = 'photos';
            let currentAspectRatio = 1;
            let currentMakeAvatar = 0;
			
            function setPhotoType(type, makeAvatar, aspect) {
                currentPhotoType = type;
                currentMakeAvatar = makeAvatar;
                currentAspectRatio = aspect;
                document.getElementById('photo_type_input').value = type;
                document.getElementById('make_avatar_hidden').value = makeAvatar;
			}
            
            let cropper = null;
            let currentFile = null;
            
            pond.on('addfile', (error, file) => {
                if (error) return;
                
                if (!file.file.type.startsWith('image/')) {
                    swal({
                        icon: 'error',
                        title: @json(__('profile.photos_err_format_title')),
                        text: @json(__('profile.photos_err_format_text')),
                        confirmButtonText: @json(__('profile.photos_err_understand'))
					});
                    pond.removeFile(file.id);
                    return;
				}
                
                const maxSize = 15 * 1024 * 1024;
                if (file.file.size > maxSize) {
                    swal({
                        icon: 'error',
                        title: @json(__('profile.photos_err_size_title')),
                        text: @json(__('profile.photos_err_size_text')),
                        confirmButtonText: @json(__('profile.photos_err_understand'))
					});
                    pond.removeFile(file.id);
                    return;
				}
                
                processImage(file.file, (blob, format) => {
                    currentFile = { file: blob, format: format };
                    const url = URL.createObjectURL(blob);
                    showCropperModal(url, (croppedBlob, cropFormat) => {
                        pond.removeFile(file.id);
                        sendFiles(blob, croppedBlob, cropFormat);
					});
				});
			});
            
            function sendFiles(originalFile, croppedBlob, format) {
                const formData = new FormData();
                const extension = format === 'image/webp' ? 'webp' : 'jpg';
                const originalName = `original_${Date.now()}.${extension}`;
                formData.append('photo_original', originalFile, originalName);
                const croppedExt = format === 'image/webp' ? 'webp' : 'jpg';
                const croppedName = `thumb_${Date.now()}.${croppedExt}`;
                formData.append('photo_cropped', croppedBlob, croppedName);
                formData.append('photo_type', currentPhotoType);
                formData.append('make_avatar', currentMakeAvatar);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                @if($isEditingOther)
                formData.append('user_id', '{{ $user->id }}');
                @endif
                
                fetch('/user/photos', {
                    method: 'POST',
                    body: formData,
					}).then(async (response) => {
                    const data = await response.json();
                    if (response.ok && data.success) {
                        const urlParams = new URLSearchParams(window.location.search);
                        const userId = urlParams.get('user_id');
                        const redirectUrl = userId 
						? window.location.pathname + '?upload=success&user_id=' + userId
						: window.location.pathname + '?upload=success';
                        window.location.href = redirectUrl;
						} else {
                        const modal = document.querySelector('.cropper-modal-overlay');
                        if (modal) modal.remove();
                        swal({ title: @json(__('profile.photos_err_title')), text: data.error || @json(__('profile.photos_err_upload')), icon: "error", button: @json(__('profile.photos_err_understand')) });
					}
					}).catch(() => {
                    const modal = document.querySelector('.cropper-modal-overlay');
                    if (modal) modal.remove();
                    swal({ title: @json(__('profile.photos_err_title')), text: @json(__('profile.photos_err_network')), icon: "error", button: @json(__('profile.photos_err_understand')) });
				});
			}
            
            function showCropperModal(imageUrl, onCropComplete) {
                const modal = document.createElement('div');
                modal.className = 'cropper-modal-overlay';
                const aspectRatio = currentAspectRatio;
                const isWide = aspectRatio > 1;
                const titleText = @json(__('profile.photos_crop_title'));
                
                const container = document.createElement('div');
                container.className = 'cropper-modal-container';
                const title = document.createElement('h3');
                title.textContent = titleText;
                const imgWrapper = document.createElement('div');
                imgWrapper.className = 'cropper-image-wrapper';
                const img = document.createElement('img');
                img.src = imageUrl;
                imgWrapper.appendChild(img);
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'cropper-buttons';
                const saveBtn = document.createElement('button');
                saveBtn.textContent = @json(__('profile.photos_crop_save'));
                saveBtn.className = 'btn';
                const cancelBtn = document.createElement('button');
                cancelBtn.textContent = @json(__('profile.photos_crop_cancel'));
                cancelBtn.className = 'btn btn-secondary';
                buttonContainer.appendChild(saveBtn);
                buttonContainer.appendChild(cancelBtn);
                const loading = document.createElement('div');
                loading.className = 'fancybox-loading';
                loading.style.display = 'none';
                modal.appendChild(loading);
                container.appendChild(title);
                container.appendChild(imgWrapper);
                container.appendChild(buttonContainer);
                modal.appendChild(container);
                document.body.appendChild(modal);
                modal.offsetHeight;
                requestAnimationFrame(() => { modal.classList.add('cropper-modal-overlay--active'); });
                
                img.onload = () => {
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(img, {
                        aspectRatio: aspectRatio,
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
                    modal.classList.add('loading');
                    saveBtn.disabled = true;
                    cancelBtn.disabled = true;
                    const canvas = cropper.getCroppedCanvas({
                        width: currentAspectRatio > 1 ? 640 : 360,
                        height: 360,
					});
                    const format = supportsWebP() ? 'image/webp' : 'image/jpeg';
                    canvas.toBlob((blob) => { onCropComplete(blob, format); }, format, 0.90);
				};
                
                cancelBtn.onclick = () => {
                    modal.remove();
                    if (cropper) { cropper.destroy(); cropper = null; }
                    pond.removeFile(currentFile?.id);
				};
                
                modal.onclick = (e) => { if (e.target === modal) cancelBtn.onclick(); };
			}
		</script>
        
        <script>
            const swiper = new Swiper('.photo-swiper', {
                slidesPerView: 2,
                spaceBetween: 20,
                pagination: { el: '.swiper-pagination', clickable: true },
                breakpoints: {
                    640: { slidesPerView: 3 },
                    768: { slidesPerView: 3 },
                    992: { slidesPerView: 3 },
                    1024: { slidesPerView: 3 },
                    1280: { slidesPerView: 4 }
				}
			});
		</script>
		
		@if($canUploadEventPhotos)
        <script>
            const eventSwiper = new Swiper('.event-photo-swiper', {
                slidesPerView: 1,
                spaceBetween: 20,
                pagination: { el: '.swiper-pagination', clickable: true },
                breakpoints: {
                    640: { slidesPerView: 2 },
                    768: { slidesPerView: 2 },
                    1024: { slidesPerView: 2 },
                    1280: { slidesPerView: 3 }
				}
			});
			
			
            const schoolCoverSwiper = new Swiper('.school-cover-swiper', {
                slidesPerView: 1,
                spaceBetween: 20,
                pagination: { el: '.swiper-pagination', clickable: true },
                breakpoints: {
                    640: { slidesPerView: 2 },
                    768: { slidesPerView: 2 },
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
            <div class="alert alert-success">{{ __('profile.photos_added_success') }}</div>
		</div>
        <script>window.history.replaceState({}, document.title, window.location.pathname);</script>       
        @endif      
        
        @if (session('status'))
        <div class="ramka">    
            <div class="alert alert-success">{{ session('status') }}</div>
		</div>
        @endif
        
        @if (session('error'))
        <div class="ramka">        
            <div class="alert alert-danger">{{ session('error') }}</div>
		</div>
        @endif
        
        @if ($errors->any())
        <div class="ramka">        
            <div class="alert alert-danger">
                <div class="b-600 mb-1">{{ __('profile.photos_errors_title') }}</div>
                <ul class="list">
                    @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                    @endforeach
				</ul>
			</div>
		</div>
        @endif      
        
        <div class="row row2">
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka">
                        @include('profile._menu', [
						'menuUser'      => $user,
						'isEditingOther' => $isEditingOther,
						'activeMenu'    => 'photos',
                        ])
					</div>
				</div>
			</div>
            <div class="col-lg-8 col-xl-9 order-1">    
                <div class="ramka" style="z-index:10">      
                    <h2 class="-mt-05">{{ __('profile.photos_upload_h2') }}</h2>
                    <div class="form">
                        <form id="photoUploadForm"
						action="{{ route('user.photos.store') }}"
						method="POST"
						enctype="multipart/form-data"
						class="mt-2">
                            @csrf
                            
                            @if($isEditingOther)
                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                            @endif
							
                            {{-- Тип фото — радио-группа (взаимоисключающие) --}}
                            <input type="hidden" name="photo_type" id="photo_type_input" value="photos">
                            <input type="hidden" id="make_avatar_hidden" name="make_avatar" value="0">
							
                            <label class="radio-item mb-1">
                                <input type="radio" name="photo_type_radio" value="photos" checked
								onchange="setPhotoType('photos', 0, 1)">
                                <div class="custom-radio"></div>
                                <span>{{ __('profile.photos_radio_gallery') }}</span>
							</label>
							
                            <label class="radio-item mb-1">
                                <input type="radio" name="photo_type_radio" value="photos_avatar"
								onchange="setPhotoType('photos', 1, 1)">
                                <div class="custom-radio"></div>
                                <span>{{ __('profile.photos_radio_avatar') }}</span>
							</label>
							
                            @if($canUploadEventPhotos)
                            <label class="radio-item mb-1">
                                <input type="radio" name="photo_type_radio" value="event_photos"
								onchange="setPhotoType('event_photos', 0, 16/9)">
                                <div class="custom-radio"></div>
                                <span>{{ __('profile.photos_radio_event') }}</span>
							</label>
							
                            @if($hasSchool ?? false)
							@if(isset($schoolLogos) && $schoolLogos->count() >= 1)	
                            <label class="radio-item mb-1">
                                <input type="radio" name="" value="" disabled>
                                <div class="custom-radio"></div>
                                <span>{{ __('profile.photos_radio_school_logo_disabled') }}</span>
							</label>
							@else
                            <label class="radio-item mb-1">
                                <input type="radio" name="photo_type_radio" value="school_logo"
								onchange="setPhotoType('school_logo', 0, 1)">
                                <div class="custom-radio"></div>
                                <span>{{ __('profile.photos_radio_school_logo') }}</span>
							</label>							
							@endif
                            <label class="radio-item mb-1">
                                <input type="radio" name="photo_type_radio" value="school_cover"
								onchange="setPhotoType('school_cover', 0, 16/9)">
                                <div class="custom-radio"></div>
                                <span>{{ __('profile.photos_radio_school_cover') }}</span>
							</label>
                            @endif {{-- hasSchool --}}
                            @endif
                            
                            <div class="file-wrap mt-2">
                                <input type="file" name="photo" id="photo" accept="image/*" />
							</div>
						</form>
					</div>
				</div>
				
                <div class="ramka">        
                    <h2 class="-mt-05">{{ __('profile.photos_gallery_h2') }}</h2>
                    <p>{{ __('profile.photos_total_prefix') }} <strong class="cd">{{ $photos->count() }}</strong> {{ __('profile.photos_total_suffix') }}</p>
                    <div class="form mt-2">
                        @if($photos->isEmpty())
                        <div class="alert alert-info">
                            {{ __('profile.photos_empty_first') }}
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
                                            <img src="{{ $thumbUrl }}" alt="photo" loading="lazy"/>
                                            <span></span>
                                            <div class="hover-image-circle"></div>
										</a>
									</div>                              
                                    <div class="mt-1 d-flex between fvc">
                                        @if($isAvatar)
                                        <span class="cd f-16 l-11 b-600">{{ __('profile.photos_avatar_label') }}</span>
                                        @else
                                        <form method="POST" action="{{ route('user.photos.setAvatar', ['media' => $m->id]) }}">
                                            @csrf
                                            <span onclick="event.preventDefault(); this.closest('form').submit();" class="blink f-16 l-11">
                                                {!! __('profile.photos_make_avatar') !!}
											</span>
										</form>
                                        @endif          
                                        <form method="POST" action="{{ route('user.photos.destroy', ['media' => $m->id]) }}"
										onsubmit="return confirm({!! json_encode(__('profile.photos_confirm_delete')) !!})">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-delete btn-alert btn btn-danger btn-svg"
											data-title="{{ __('profile.photos_confirm_delete') }}" data-icon="warning"
											data-confirm-text="{{ __('profile.photos_btn_delete_yes_short') }}" data-cancel-text="{{ __('profile.photos_crop_cancel') }}">
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
                
                {{-- Фото для мероприятий --}}
                @if($canUploadEventPhotos && (!$isEditingOther || auth()->user()?->isAdmin()))
                <div class="ramka">        
                    <h2 class="-mt-05">{{ __('profile.photos_event_h2') }}</h2>
                    <p>{{ __('profile.photos_total_prefix') }} <strong class="cd">{{ isset($eventPhotos) ? $eventPhotos->count() : 0 }}</strong> {{ __('profile.photos_total_suffix') }}</p>
                    <div class="form mt-2">
                        @if(!isset($eventPhotos) || $eventPhotos->isEmpty())
                        <div class="alert alert-info">{{ __('profile.photos_event_empty') }}</div>
                        @else
                        <div class="swiper event-photo-swiper">
                            <div class="swiper-wrapper">
                                @foreach($eventPhotos as $m)
                                <div class="swiper-slide">
                                    <div class="hover-image">
                                        <a href="{{ $m->getUrl() }}" class="fancybox" data-fancybox="event-gallery">
                                            <img src="{{ $m->getUrl('event_thumb') ?? $m->getUrl() }}" alt="event photo" loading="lazy"/>
                                            <span></span>
                                            <div class="hover-image-circle"></div>
										</a>
									</div>
                                    <div class="mt-1 d-flex between fvc">
                                        <div></div>
                                        <form method="POST" action="{{ route('user.photos.destroyEventPhoto', ['media' => $m->id]) }}"
										onsubmit="return confirm({!! json_encode(__('profile.photos_confirm_delete')) !!})">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-delete btn-alert btn btn-danger btn-svg"
											data-title="{{ __('profile.photos_confirm_delete') }}" data-icon="warning"
											data-confirm-text="{{ __('profile.photos_btn_delete_yes_short') }}" data-cancel-text="{{ __('profile.photos_crop_cancel') }}">
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
				
                @if($hasSchool ?? false)
                {{-- Логотипы школы --}}
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('profile.photos_school_logo_h2') }}</h2>
                    <div class="form mt-2">
                        @if(!isset($schoolLogos) || $schoolLogos->isEmpty())
                        <div class="alert alert-info">{{ __('profile.photos_school_logo_empty') }}</div>
                        @else
						<div class="row row2">
							@foreach($schoolLogos as $m)
							<div class="col-5 col-sm-4 col-md-3">
								<div class="hover-image">
									<a href="{{ $m->getUrl() }}" class="fancybox" data-fancybox="school-logo-gallery">
										<img src="{{ $m->hasGeneratedConversion('school_logo_thumb') ? $m->getUrl('school_logo_thumb') : $m->getUrl() }}"
										alt="school logo" loading="lazy"/>
										<span></span>
										<div class="hover-image-circle"></div>
									</a>
								</div>
							</div>
							@if(!$isEditingOther || auth()->user()?->isAdmin())
							<div class="col-7 col-sm-8 col-md-9">
								
								@if(isset($schoolLogos) && $schoolLogos->count() >= 1)
								<div class="alert alert-info">
									{{ __('profile.photos_school_logo_replace') }}
									<div class="text-right">
										<form method="POST" action="{{ route('user.photos.destroy', $m->id) }}"
										onsubmit="return confirm({!! json_encode(__('profile.photos_confirm_delete_logo')) !!})">
											@csrf @method('DELETE')
                                            <button type="submit" class="icon-delete btn-alert btn btn-danger btn-svg"
											data-title="{{ __('profile.photos_delete_logo_title') }}" data-icon="warning"
											data-confirm-text="{{ __('profile.photos_btn_delete_yes_short') }}" data-cancel-text="{{ __('profile.photos_crop_cancel') }}">
											</button>
										</form>					
									</div>
								</div>
								@endif								
								
								
								
							</div>									
							@endif
							@endforeach
						</div>
                        @endif
					</div>
				</div>
				
                {{-- Обложки школы --}}
                <div class="ramka">
                    <h2 class="-mt-05">{{ __('profile.photos_school_cover_h2') }}</h2>
                    <p>{{ __('profile.photos_total_prefix') }} <strong class="cd">{{ isset($schoolCovers) ? $schoolCovers->count() : 0 }}</strong> {{ __('profile.photos_total_suffix') }}</p>
                    <div class="form mt-2">
                        @if(!isset($schoolCovers) || $schoolCovers->isEmpty())
                        <div class="alert alert-info">{{ __('profile.photos_school_cover_empty') }}</div>
                        @else
                        <div class="swiper school-cover-swiper">
                            <div class="swiper-wrapper">
                                @foreach($schoolCovers as $m)
                                <div class="swiper-slide">
                                    <div class="hover-image" style="position:relative;">
                                        <a href="{{ $m->getUrl() }}" class="fancybox" data-fancybox="school-cover-gallery">
                                            <img src="{{ $m->hasGeneratedConversion('school_cover_thumb') ? $m->getUrl('school_cover_thumb') : $m->getUrl() }}"
											alt="school cover" loading="lazy"/>
                                            <span></span>
                                            <div class="hover-image-circle"></div>
										</a>
									</div>
									
									
                                    <div class="mt-1 d-flex between fvc">
										@if(!$isEditingOther || auth()->user()?->isAdmin())
										@if(($mainCoverMediaId ?? null) != $m->id)
										
										<form method="POST" action="{{ route('user.photos.setMainCover', $m->id) }}">
                                            @csrf
                                            <span onclick="event.preventDefault(); this.closest('form').submit();" class="blink f-16 l-11">
                                                {!! __('profile.photos_make_main_cover') !!}
											</span>
										</form>							
										
                                        @else
										<span class="cd f-16 l-11 b-600">{{ __('profile.photos_main_cover_label') }}</span>
                                        @endif          
										<form method="POST" action="{{ route('user.photos.destroy', $m->id) }}"
										onsubmit="return confirm({!! json_encode(__('profile.photos_confirm_delete')) !!})">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-delete btn-alert btn btn-danger btn-svg"
											data-title="{{ __('profile.photos_confirm_delete') }}" data-icon="warning"
											data-confirm-text="{{ __('profile.photos_btn_delete_yes_short') }}" data-cancel-text="{{ __('profile.photos_crop_cancel') }}">
											</button>                               
										</form>
									</div> 										
                                    @endif
								</div>
                                @endforeach
							</div>
                            <div class="swiper-pagination"></div>
						</div>
                        @endif
					</div>
				</div>
                @endif
                @endif {{-- /hasSchool --}}
                
			</div>  
		</div>          
	</div>
    
</x-voll-layout>