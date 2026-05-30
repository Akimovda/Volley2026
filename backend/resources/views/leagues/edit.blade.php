<x-voll-layout body_class="leagues-page">
	<x-slot name="title">{{ $league->name }} — {{ __('seasons.leagues_edit_title') }}</x-slot>
	<x-slot name="h1">{{ $league->name }}</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.index') }}" itemprop="item"><span itemprop="name">{{ __('seasons.leagues_idx_breadcrumb') }}</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ $league->name }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>

	<x-slot name="h2">
		{{ $league->direction === 'beach' ? __('seasons.leagues_dir_beach_short') : __('seasons.leagues_dir_classic_short') }}
	</x-slot>

	<x-slot name="t_description">
		{{ __('seasons.leagues_public_link_label') }} <a class="blink" href="{{ route('leagues.show.slug', $league->slug) }}" target="_blank">/l/{{ $league->slug }}</a>
	</x-slot>

	<div class="container">

		@if(session('success'))
		<div class="ramka">
			<div class="alert alert-success">{{ session('success') }}</div>
		</div>
		@endif
		@if(session('error'))
		<div class="ramka">
			<div class="alert alert-danger">{{ session('error') }}</div>
		</div>
		@endif

		<div class="row row2 form">
			<div class="col-lg-4">
				{{-- Настройки лиги --}}
				<div class="ramka">
					<h2 class="-mt-05">{{ __('seasons.leagues_settings_h2') }}</h2>
					<form action="{{ route('leagues.update.league', $league) }}" method="POST" enctype="multipart/form-data">
						@csrf @method('PUT')

						<div class="card mb-2">
							<label>{{ __('seasons.leagues_label_name_short') }}</label>
							<input type="text" name="name" value="{{ $league->name }}" required>
						</div>

						<div class="card mb-2">
							<label>{{ __('seasons.leagues_label_direction') }}</label>
							<select name="direction">
								<option value="beach" {{ $league->direction === 'beach' ? 'selected' : '' }}>{{ __('seasons.leagues_dir_beach_short') }}</option>
								<option value="classic" {{ $league->direction === 'classic' ? 'selected' : '' }}>{{ __('seasons.leagues_dir_classic_short') }}</option>
							</select>
						</div>

						<div class="card mb-2">
							<label>{{ __('seasons.leagues_label_description_short') }}</label>
							<textarea name="description" rows="3">{{ $league->description }}</textarea>
						</div>

						<div class="card mb-2">
							<label>{{ __('seasons.label_status') }}</label>
							<select name="status">
								<option value="active" {{ $league->status === 'active' ? 'selected' : '' }}>{{ __('seasons.leagues_status_active') }}</option>
								<option value="archived" {{ $league->status === 'archived' ? 'selected' : '' }}>{{ __('seasons.leagues_status_archived') }}</option>
							</select>
						</div>

						<div class="card mb-2">
							<label>{{ __('seasons.leagues_section_logo') }}</label>
							@if($league->logo_url)
								<div class="mb-1">
									<img src="{{ $league->logo_url }}" alt="{{ __('seasons.leagues_section_logo') }}" style="max-width:100px;border-radius:8px">
								</div>
								<label class="checkbox-item mb-2">
									<input type="checkbox" name="remove_logo" value="1">
									<div class="custom-checkbox"></div>
									<span class="f-13">{{ __('seasons.leagues_remove_logo') }}</span>
								</label>
							@endif

							{{-- Табы: файл / из галереи --}}
							<div class="d-flex gap-1 mb-1" id="logo-tabs">
								<button type="button" class="btn btn-secondary f-13 logo-tab-btn logo-tab-active" data-tab="file" style="padding:4px 12px">{{ __('seasons.leagues_logo_tab_file') }}</button>
								<button type="button" class="btn btn-secondary f-13 logo-tab-btn" data-tab="gallery" style="padding:4px 12px">{{ __('seasons.leagues_logo_tab_gallery') }}</button>
							</div>

							<div id="logo-tab-file">
								<input type="file" name="logo" accept="image/*" id="logo-file-input">
								<div class="f-13 cd mt-1">{{ __('seasons.leagues_logo_hint') }}</div>
							</div>

							<div id="logo-tab-gallery" style="display:none">
								<input type="hidden" name="logo_media_id" id="logo_media_id_input" value="">

								<div id="no-event-photos-msg" class="alert alert-info f-14 mt-1" @if(!$eventPhotos->isEmpty()) style="display:none" @endif>
									{{ __('seasons.leagues_logo_no_event_photos') }}
									<a href="{{ route('user.photos') }}" target="_blank" class="blink">{{ __('seasons.leagues_logo_no_event_link') }}</a>.
								</div>

								<div id="league-logo-swiper-wrap" @if($eventPhotos->isEmpty()) style="display:none" @endif>
									<div class="swiper leagueLogoSwiper mt-1" style="padding-bottom:2.5rem">
										<div class="swiper-wrapper">
											@foreach($eventPhotos as $photo)
											<div class="swiper-slide" style="cursor:pointer" data-media-id="{{ $photo->id }}" onclick="selectLeagueLogo(this)">
												<div class="hover-image" style="position:relative">
													<img src="{{ $photo->getUrl('event_thumb') }}" alt="" loading="lazy" style="border-radius:8px;aspect-ratio:16/9;object-fit:cover;width:100%">
													<div class="league-logo-check" style="display:none;position:absolute;top:6px;right:6px;background:#2967BA;color:#fff;border-radius:50%;width:2.4rem;height:2.4rem;align-items:center;justify-content:center;font-size:1.4rem;font-weight:900">✓</div>
												</div>
											</div>
											@endforeach
										</div>
										<div class="swiper-pagination"></div>
									</div>
									<div id="logo-gallery-selected" class="f-14 cd mt-05" style="display:none">✅ Фото выбрано</div>
								</div>

								<div class="mt-1">
									<input type="file" id="league-photo-upload" accept="image/*" style="display:none">
									<button type="button" class="btn btn-secondary f-13" id="league-upload-photo-btn" style="padding:6px 14px">
										+ {{ __('seasons.leagues_logo_add_photo') }}
									</button>
								</div>
							</div>
						</div>

						<div class="card mb-2">
							<label>VK</label>
							<input type="text" name="vk" value="{{ $league->vk }}" placeholder="https://vk.com/...">
						</div>
						<div class="card mb-2">
							<label>Telegram</label>
							<input type="text" name="telegram" value="{{ $league->telegram }}" placeholder="https://t.me/...">
						</div>
						<div class="card mb-2">
							<label>MAX</label>
							<input type="text" name="max_messenger" value="{{ $league->max_messenger }}" placeholder="{{ __('seasons.leagues_ph_max') }}">
						</div>
						<div class="card mb-2">
							<label>{{ __('seasons.leagues_label_website') }}</label>
							<input type="text" name="website" value="{{ $league->website }}" placeholder="https://...">
						</div>
						<div class="card mb-2">
							<label>{{ __('seasons.leagues_label_phone') }}</label>
							<input type="text" name="phone" value="{{ $league->phone }}" placeholder="+7 999 123-45-67">
						</div>

						<button type="submit" class="btn btn-primary w-100">{{ __('seasons.btn_save') }}</button>
					</form>
				</div>
			</div>

			<div class="col-lg-8">
				{{-- Сезоны лиги --}}
				<div class="ramka">
					<div class="d-flex between fvc mb-2">
						<h2 class="-mt-05 mb-0">{{ __('seasons.leagues_section_seasons') }}</h2>
						<a href="{{ route('seasons.create', $league) }}" class="btn btn-primary f-13" style="padding:6px 14px">{{ __('seasons.leagues_btn_add_season') }}</a>
					</div>

					@if($league->seasons->isEmpty())
						<div class="alert alert-info">
							{{ __('seasons.leagues_no_seasons_extended') }}
						</div>
					@else
						@foreach($league->seasons as $season)
							<div class="card mb-2" style="padding:12px 16px">
								<div class="d-flex between fvc mb-1">
									<div class="b-600">{{ $season->name }}</div>
									@php
										$statusColors = [
											'active' => ['bg' => 'rgba(16,185,129,.15)', 'color' => '#10b981', 'label' => __('seasons.status_active')],
											'completed' => ['bg' => 'rgba(128,128,128,.15)', 'color' => '#6b7280', 'label' => __('seasons.status_completed')],
											'draft' => ['bg' => 'rgba(231,97,47,.15)', 'color' => '#E7612F', 'label' => __('seasons.status_draft')],
										];
										$st = $statusColors[$season->status] ?? $statusColors['draft'];
									@endphp
									<span class="f-13 b-600" style="background:{{ $st['bg'] }}; padding: 0.4rem 1rem; border-radius:1rem;color:{{ $st['color'] }}">
										{{ $st['label'] }}
									</span>
								</div>

								<div class="f-16 mb-1 cd">
									{{ $season->starts_at?->format('d.m.Y') ?? '—' }} — {{ $season->ends_at?->format('d.m.Y') ?? '...' }}
								</div>

								@if($season->leagues->isNotEmpty())
									<div class="f-16 mb-1 cd">
										{{ __('seasons.divisions_label') }} {{ $season->leagues->pluck('name')->implode(', ') }}
									</div>
								@endif

								<div class="f-16 mb-1 cd">
									{{ __('seasons.rounds_label') }} {{ $season->seasonEvents->count() }}
								</div>

								<div class="d-flex" style="gap:8px">
									<a href="{{ route('seasons.edit', $season) }}" class="btn btn-primary f-13" style="padding:6px 14px">{{ __('seasons.btn_manage') }}</a>
									@php $seasonEvent = $season->seasonEvents->unique('event_id')->first(); @endphp
									@if($seasonEvent && $seasonEvent->event)
										<a href="{{ route('tournament.setup', $seasonEvent->event) }}" class="btn btn-primary f-13" style="padding:6px 14px;background:#E7612F;border-color:#E7612F">{{ __('seasons.btn_tournament_short') }}</a>
									@endif
									<a href="{{ route('seasons.show.slug', [$season->league?->slug ?? 'league', $season->slug]) }}" class="btn btn-secondary f-13" style="padding:6px 14px">{{ __('seasons.btn_public') }}</a>
								</div>
							</div>
						@endforeach
					@endif
				</div>
			</div>
		</div>
	</div>

	<x-slot name="style">
		<link href="/css/cropper.min.css" rel="stylesheet">
		<style>
			.cropper-modal-overlay {
				position: fixed; top: 0; bottom: 0; left: 0; right: 0;
				text-align: center; display: flex; flex-flow: column;
				align-items: center; justify-content: center;
				font-size: 0; overflow: hidden; z-index: 10000;
				pointer-events: none; opacity: 0; transition: opacity 0.3s ease;
			}
			.cropper-modal-overlay--active { opacity: 1; pointer-events: auto; }
			.cropper-modal-overlay:before, .cropper-modal-overlay:after {
				content: ""; position: absolute; top: 100vh; width: 100%; height: 100%;
				background: #fff; opacity: 0.8; transition-duration: 0.4s;
				transition-property: all;
				transition-timing-function: cubic-bezier(.47, 0, .74, .71);
				clip-path: polygon(100% 80%, 100% 100%, 0% 100%, 0% 20%);
			}
			.cropper-modal-overlay:after {
				clip-path: polygon(100% 0%, 100% 80%, 0% 20%, 0% 0%);
				top: -100vh; opacity: 0.5;
			}
			.cropper-modal-overlay--active:before, .cropper-modal-overlay--active:after {
				top: 0; left: 0;
				transition-timing-function: cubic-bezier(.22, .61, .36, 1);
			}
			body.dark .cropper-modal-overlay:before, body.dark .cropper-modal-overlay:after { background: #000; }
			.cropper-modal-container {
				position: relative; z-index: 10001; background: #fff;
				border-radius: 1.6rem; padding: 2rem; width: 90vw;
				max-width: 100rem; max-height: 90vh; display: flex;
				flex-direction: column;
				box-shadow: rgba(0,0,0,.1) 0px 1rem 2.2rem, rgba(0,0,0,.05) 0px .5rem 1.2rem;
				transform: scale(0.95); transition: transform 0.3s ease; overflow: hidden;
			}
			.cropper-modal-overlay--active .cropper-modal-container { transform: scale(1); }
			body.dark .cropper-modal-container { background: #2a2b3a; color: #e9ecef; }
			.cropper-image-wrapper {
				background: #f5f5f5; border-radius: 8px; overflow: hidden;
				margin-bottom: 1rem; flex: 1; min-height: 0;
				display: flex; align-items: center; justify-content: center;
			}
			body.dark .cropper-image-wrapper { background: #1e1e2a; }
			.cropper-image-wrapper img {
				max-width: 100%; max-height: 100%; width: auto; height: auto;
				display: block; margin: 0 auto; cursor: move;
			}
			.cropper-modal-container h3 {
				margin: 0 0 2rem 0; text-align: center; flex-shrink: 0; font-size: 2rem;
			}
			.cropper-buttons {
				display: flex; gap: 1rem; justify-content: center; flex-shrink: 0; margin-top: 1rem;
			}
			.cropper-modal-overlay .fancybox-loading {
				position: absolute; top: calc(50% - 75px); left: calc(50% - 75px);
				width: 150px; height: 150px; display: none; z-index: 10002;
			}
			.cropper-modal-overlay.loading .cropper-modal-container * { pointer-events: none; }
			.cropper-modal-overlay.loading .fancybox-loading { display: block !important; }
		</style>
	</x-slot>

	<x-slot name="script">
		<script src="/assets/fas.js"></script>
		<script src="/js/cropper.min.js"></script>
		<script>
		(function() {
			// Табы логотипа
			document.querySelectorAll('.logo-tab-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					document.querySelectorAll('.logo-tab-btn').forEach(function(b) { b.classList.remove('logo-tab-active', 'btn-primary'); b.classList.add('btn-secondary'); });
					btn.classList.add('logo-tab-active', 'btn-primary');
					btn.classList.remove('btn-secondary');
					var tab = btn.dataset.tab;
					document.getElementById('logo-tab-file').style.display    = tab === 'file'    ? '' : 'none';
					document.getElementById('logo-tab-gallery').style.display = tab === 'gallery' ? '' : 'none';
					if (tab === 'file') {
						document.getElementById('logo_media_id_input').value = '';
						document.querySelectorAll('.swiper-slide[data-media-id]').forEach(function(s) { s.querySelector('.league-logo-check').style.display = 'none'; s.style.outline = ''; });
						var sel = document.getElementById('logo-gallery-selected');
						if (sel) sel.style.display = 'none';
					} else {
						var inp = document.getElementById('logo-file-input');
						if (inp) inp.value = '';
					}
				});
			});
		}());

		function selectLeagueLogo(slide) {
			document.querySelectorAll('.swiper-slide[data-media-id]').forEach(function(s) {
				s.querySelector('.league-logo-check').style.display = 'none';
				s.style.outline = '';
			});
			var check = slide.querySelector('.league-logo-check');
			check.style.display = 'flex';
			slide.style.outline = '2px solid #2967BA';
			document.getElementById('logo_media_id_input').value = slide.dataset.mediaId;
			var sel = document.getElementById('logo-gallery-selected');
			if (sel) sel.style.display = '';
		}

		var leagueLogoSwiper = null;
		@if(isset($eventPhotos) && $eventPhotos->isNotEmpty())
		leagueLogoSwiper = new Swiper('.leagueLogoSwiper', {
			slidesPerView: 2,
			spaceBetween: 12,
			pagination: { el: '.swiper-pagination', clickable: true },
			breakpoints: { 640: { slidesPerView: 3 }, 1024: { slidesPerView: 2 } }
		});
		@endif

		// --- Загрузка фото с кропом ---
		var leagueCropper = null;

		function supportsWebPLeague() {
			try {
				var c = document.createElement('canvas');
				return c.toDataURL('image/webp').indexOf('data:image/webp') === 0;
			} catch(e) { return false; }
		}

		function processImageLeague(file, callback) {
			var url = URL.createObjectURL(file);
			var img = new Image();
			img.onload = function() {
				var w = img.width, h = img.height, maxSize = 1920;
				if (w > maxSize || h > maxSize) {
					var r = Math.min(maxSize / w, maxSize / h);
					w = Math.round(w * r); h = Math.round(h * r);
				}
				var canvas = document.createElement('canvas');
				canvas.width = w; canvas.height = h;
				canvas.getContext('2d').drawImage(img, 0, 0, w, h);
				var fmt = supportsWebPLeague() ? 'image/webp' : 'image/jpeg';
				canvas.toBlob(function(blob) { callback(blob, fmt); }, fmt, 0.85);
			};
			img.src = url;
		}

		function showLeagueCropperModal(imageUrl, onCropComplete) {
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
			requestAnimationFrame(function() { modal.classList.add('cropper-modal-overlay--active'); });

			img.onload = function() {
				if (leagueCropper) leagueCropper.destroy();
				leagueCropper = new Cropper(img, {
					aspectRatio: 16 / 9,
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

			saveBtn.onclick = function() {
				if (!leagueCropper) return;
				modal.classList.add('loading');
				saveBtn.disabled = true;
				cancelBtn.disabled = true;
				var canvas = leagueCropper.getCroppedCanvas({ width: 640, height: 360 });
				var fmt = supportsWebPLeague() ? 'image/webp' : 'image/jpeg';
				canvas.toBlob(function(blob) { onCropComplete(blob, fmt); }, fmt, 0.90);
			};

			cancelBtn.onclick = function() {
				modal.remove();
				if (leagueCropper) { leagueCropper.destroy(); leagueCropper = null; }
				document.getElementById('league-photo-upload').value = '';
			};

			modal.onclick = function(e) { if (e.target === modal) cancelBtn.onclick(); };
		}

		function sendLeaguePhoto(originalBlob, croppedBlob, format) {
			var ext = format === 'image/webp' ? 'webp' : 'jpg';
			var ts = (new Date()).getTime();
			var formData = new FormData();
			formData.append('photo_original', originalBlob, 'original_' + ts + '.' + ext);
			formData.append('photo_cropped',  croppedBlob, 'thumb_' + ts + '.' + ext);
			formData.append('photo_type', 'event_photos');
			formData.append('make_avatar', '0');
			formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

			fetch('/user/photos', {
				method: 'POST',
				body: formData,
			}).then(function(response) {
				return response.json().then(function(data) {
					var modal = document.querySelector('.cropper-modal-overlay');
					if (response.ok && data.success) {
						if (modal) modal.remove();
						onLeaguePhotoUploaded(data.media_id, data.thumb_url);
					} else {
						if (modal) modal.remove();
						swal({ title: 'Ошибка', text: data.error || 'Не удалось загрузить фото', icon: 'error', button: 'Понятно' });
					}
				});
			}).catch(function() {
				var modal = document.querySelector('.cropper-modal-overlay');
				if (modal) modal.remove();
				swal({ title: 'Ошибка', text: 'Ошибка сети. Попробуйте ещё раз.', icon: 'error', button: 'Понятно' });
			});
		}

		function onLeaguePhotoUploaded(mediaId, thumbUrl) {
			var slideHtml = '<div class="swiper-slide" style="cursor:pointer" data-media-id="' + mediaId + '" onclick="selectLeagueLogo(this)">' +
				'<div class="hover-image" style="position:relative">' +
				'<img src="' + thumbUrl + '" alt="" loading="lazy" style="border-radius:8px;aspect-ratio:16/9;object-fit:cover;width:100%">' +
				'<div class="league-logo-check" style="display:none;position:absolute;top:6px;right:6px;background:#2967BA;color:#fff;border-radius:50%;width:2.4rem;height:2.4rem;align-items:center;justify-content:center;font-size:1.4rem;font-weight:900">✓</div>' +
				'</div></div>';

			document.getElementById('no-event-photos-msg').style.display = 'none';
			document.getElementById('league-logo-swiper-wrap').style.display = '';

			if (!leagueLogoSwiper) {
				leagueLogoSwiper = new Swiper('.leagueLogoSwiper', {
					slidesPerView: 2,
					spaceBetween: 12,
					pagination: { el: '.swiper-pagination', clickable: true },
					breakpoints: { 640: { slidesPerView: 3 }, 1024: { slidesPerView: 2 } }
				});
			}

			leagueLogoSwiper.prependSlide(slideHtml);
			leagueLogoSwiper.slideTo(0);

			var newSlide = document.querySelector('.swiper-slide[data-media-id="' + mediaId + '"]');
			if (newSlide) selectLeagueLogo(newSlide);

			document.getElementById('league-photo-upload').value = '';
		}

		document.getElementById('league-upload-photo-btn').addEventListener('click', function() {
			document.getElementById('league-photo-upload').click();
		});

		document.getElementById('league-photo-upload').addEventListener('change', function(e) {
			var file = e.target.files[0];
			if (!file) return;
			if (!file.type.startsWith('image/')) {
				swal({ title: 'Ошибка', text: 'Пожалуйста, выберите изображение', icon: 'error', button: 'Понятно' });
				this.value = '';
				return;
			}
			if (file.size > 15 * 1024 * 1024) {
				swal({ title: 'Ошибка', text: 'Файл слишком большой. Максимум 15 МБ.', icon: 'error', button: 'Понятно' });
				this.value = '';
				return;
			}
			processImageLeague(file, function(blob, fmt) {
				var url = URL.createObjectURL(blob);
				showLeagueCropperModal(url, function(croppedBlob, cropFmt) {
					sendLeaguePhoto(blob, croppedBlob, cropFmt);
				});
			});
		});
		</script>
	</x-slot>
</x-voll-layout>
