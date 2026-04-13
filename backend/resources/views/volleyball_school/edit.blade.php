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
					<form method="POST" action="{{ route('volleyball_school.update') }}" enctype="multipart/form-data">
						@csrf
						@method('PUT')
						
						<div class="ramka">
							<h2 class="-mt-05">Основная информация</h2>
							<div class="row">
								
								<div class="col-md-7">
									<div class="card">
										<label>Название школы / сообщества <span class="red">*</span></label>
										<input type="text" name="name" value="{{ old('name', $school->name) }}" required>
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
											<option value="classic" @selected(old('direction', $school->direction) === 'classic')>Классика</option>
											<option value="beach" @selected(old('direction', $school->direction) === 'beach')>Пляж</option>
											<option value="both" @selected(old('direction', $school->direction) === 'both')>Классика + Пляж</option>
										</select>
									</div>
								</div>
								
								<!--
									<div class="col-md-4">
									<div class="card">
									<label>URL страницы</label>
									<input type="text" value="{{ $school->slug }}" disabled>
									<ul class="list f-14 mt-1">
                                    <li>URL изменить нельзя</li>
                                    <li><a href="{{ route('volleyball_school.show', $school->slug) }}" class="cd" target="_blank">Открыть страницу →</a></li>
									</ul>
									</div>
									</div>						
								-->					
								
								<div class="col-md-7">
									<div class="card">
										<label class="checkbox-item">
											<input type="hidden" name="is_published" value="0">
											<input type="checkbox" name="is_published" value="1"
											@checked(old('is_published', $school->is_published))>
											<div class="custom-checkbox"></div>
											<span>Страница опубликована</span>
										</label>
										<ul class="list f-16 mt-1">
											<li>Снимите галочку чтобы скрыть из публичного списка</li>
										</ul>
									</div>
								</div>
								
								<div class="col-md-5">
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
										<div class="provider-card__header">
											<span class="provider-card__icon icon-vk"></span>
											<span class="provider-card__title">ВКонтакте</span>
										</div>	
										<input type="url" name="vk_url"
										value="{{ old('vk_url', $school->vk_url) }}"
										placeholder="https://vk.com/sunvolley">
									</div>
								</div>
								<div class="col-md-4">
									<div class="card">
										<div class="provider-card__header">
											<span class="provider-card__icon icon-tg"></span>
											<span class="provider-card__title">Telegram</span>
										</div>	
										<input type="url" name="tg_url"
										value="{{ old('tg_url', $school->tg_url) }}"
										placeholder="https://t.me/sunvolley">
									</div>
								</div>
								<div class="col-md-4">
									<div class="card">
										<div class="provider-card__header">
											<span class="provider-card__icon icon-max"></span>
											<span class="provider-card__title">Max</span>
										</div>	
										<input type="url" name="max_url"
										value="{{ old('max_url', $school->max_url) }}"
										placeholder="https://max.ru/sunvolley">
									</div>
								</div>
							</div>
						</div>
						
						<div class="ramka">
							<h2 class="-mt-05">Фото школы</h2>
							
							<ul class="list mt-1">
								<li>Управление логотипом и фотографиями школы доступно в вашей галерее.</li>
								<li>Перейдите в раздел <a class="blink" href="{{ route('user.photos') }}" target="_blank"><strong>Ваши фотографии</strong></a></li>
								<li>Там вы найдёте разделы «Логотип школы» и «Фотографии школы».</li>
							</ul>							
							
						</div>
						
						<div class="ramka text-center">
							<button type="submit" class="btn">Сохранить изменения</button>
						</div>
						
					</form>
				</div>
			</div>{{-- /col-lg-8 --}}
		</div>{{-- /row --}}
	</div>
	
</x-voll-layout>