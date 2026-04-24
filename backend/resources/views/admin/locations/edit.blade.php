{{-- resources/views/admin/locations/edit.blade.php --}}
<x-voll-layout body_class="admin-page admin-locations-edit">
    <x-slot name="title">
        Редактировать локацию (admin)
	</x-slot>
	
    <x-slot name="description">
        Страница редактирования локации в административной панели
	</x-slot>
	
    <x-slot name="canonical">
        {{-- Здесь каноническая ссылка не нужна --}}
	</x-slot>
	
    <x-slot name="h1">
        Редактировать локацию
	</x-slot>
	
    <x-slot name="h2">
        Административная панель
	</x-slot>
	
    <x-slot name="t_description">
        Отредактируйте информацию о локации. Поля, отмеченные *, обязательны для заполнения.
	</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админ-панель</span></a>
            <meta itemprop="position" content="1">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.locations.index') }}" itemprop="item"><span itemprop="name">Локации</span></a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ $location->name }}</span>
            <meta itemprop="position" content="3">
		</li>
	</x-slot>
	
    <x-slot name="style">
    <link rel="stylesheet" type="text/css" href="/assets/trix.css?v={{ time() }}">
        <link href="/assets/org.css" rel="stylesheet">
	</x-slot>	
	
    <x-slot name="script">
    <script src="/assets/trix.js?v={{ time() }}"></script>
		<script src="/assets/city.js"></script>  
		<script src="/assets/org.js?v=2"></script>     
        <script>
            (function () {
                // --- trix: запрет вложений
                document.addEventListener('trix-file-accept', function (event) {
                    event.preventDefault();
				});

                document.addEventListener('trix-paste', function(e) {
                    var paste = e.paste;
                    if (paste.html) {
                        var clean = paste.html.replace(/<(?!\/?(br|p|b|i|u|strong|em|a |ul|ol|li))[^>]+>/gi, '');
                        e.preventDefault();
                        e.target.editor.insertHTML(clean);
                    }
                });
				
                // --- photos reorder
                const grid = document.getElementById('photos_grid');
                if (grid) {
                    const hint = document.getElementById('photos_hint');
                    const saveBtn = document.getElementById('photos_save_btn');
                    const reorderUrl = @json(route('admin.locations.photos.reorder', $location));
                    const csrf = @json(csrf_token());
					
                    function currentOrderIds() {
                        return Array.from(grid.querySelectorAll('[data-photo-id]'))
						.map(el => Number(el.getAttribute('data-photo-id')))
						.filter(n => Number.isFinite(n));
					}
					
                    async function saveOrder() {
                        const photo_ids = currentOrderIds();
                        if (!photo_ids.length) return;
						
                        if (hint) hint.textContent = 'Сохраняю порядок...';
                        try {
                            const res = await fetch(reorderUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
								},
                                body: JSON.stringify({ photo_ids }),
							});
							
                            if (!res.ok) {
                                if (hint) hint.textContent = 'Не удалось сохранить порядок (HTTP ' + res.status + ').';
                                return;
							}
                            if (hint) hint.textContent = 'Порядок фото сохранён ✅';
							} catch (e) {
                            if (hint) hint.textContent = 'Ошибка сети при сохранении порядка.';
						}
					}
					
                    new Sortable(grid, { animation: 150, ghostClass: 'opacity-50' });
                    saveBtn?.addEventListener('click', saveOrder);
				}
			})();
		</script>
	</x-slot>
	
	
	
    <div class="container">
		
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
                <div class="font-semibold mb-2">Ошибки:</div>
                <ul class="list-disc ml-5 space-y-1">
                    @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                    @endforeach
				</ul>
			</div>
		</div>
        @endif
		
		
		@php
		// Конфигурация отображения города (все true)
		$cityDisplayConfig = [
        'showCountry' => true,
        'showRegion' => true,
        'inputShowCountry' => true,
        'inputShowRegion' => true,
		];
		
		// Подготавливаем метку для города
		$selectedCityId = old('city_id', $location->city_id);
		$selectedCityLabel = '';
		
		// Формируем метку для инпута: Город (Страна, Регион)
		if (!empty($selectedCityId) && ($location->city ?? null)) {
        $city = $location->city;
        $cityName = $city->name ?? '';
        $details = [];
		
        if ($cityDisplayConfig['inputShowCountry'] && !empty($city->country_code)) {
		$details[] = $city->country_code;
        }
        if ($cityDisplayConfig['inputShowRegion'] && !empty($city->region)) {
		$details[] = $city->region;
        }
		
        if (!empty($details)) {
		$selectedCityLabel = $cityName . ' (' . implode(', ', $details) . ')';
        } else {
		$selectedCityLabel = $cityName;
        }
		}
		@endphp
		
		
		
        <div class="ramka">
		<h2 class="-mt-05">Данные локации</h2>
            <form method="POST" action="{{ route('admin.locations.update', $location) }}" enctype="multipart/form-data" class="form">
                @csrf
                @method('PUT')
				
                <div class="row">
                    {{-- NAME --}}
                    <div class="col-12">
                        <div class="card">
                            <label>Название <span class="text-danger">*</span></label>
                            <input
							type="text"
							name="name"
							class="@error('name') is-invalid @enderror"
							value="{{ old('name', $location->name) }}"
							required
                            >
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- CITY --}}
					<div class="col-md-4">
						<div class="card">
							<label>Город *</label>
							
							{{-- То, что реально сохраняем --}}
							<input type="hidden" name="city_id" id="city_id" value="{{ old('city_id', $location->city_id) }}" required>
							
							{{-- UI input (поиск) --}}
							<div class="city-autocomplete" id="city-autocomplete" data-search-url="{{ route('cities.search') }}">
								<input type="text"
								id="city_search"
								placeholder="Начните вводить город…"
								value="{{ old('city_label', $selectedCityLabel) }}"
								autocomplete="off"
								@error('city_id') class="error" @enderror>
								
								{{-- dropdown --}}
								<div id="city_dropdown" class="city-dropdown">
									<div id="city_results"></div>
								</div>
							</div>
							@error('city_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- ADDRESS --}}
                    <div class="col-md-8">
                        <div class="card">
                            <label>Адрес</label>
                            <input
							type="text"
							name="address"
							class="@error('address') is-invalid @enderror"
							value="{{ old('address', $location->address) }}"
                            >
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- SHORT_TEXT --}}
                    <div class="col-12">
                        <div class="card">
                            <label>Description</label>
                            <input
							type="text"
							name="short_text"
							class="@error('short_text') is-invalid @enderror"
							value="{{ old('short_text', $location->short_text) }}"
                            >
                            @error('short_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- LONG_TEXT (Trix) --}}
                    <div class="col-12">
                        <div class="card">
                            <label>Короткое описание (только для превью)</label>
                            <input id="long_text" type="hidden" name="long_text" value="{{ old('long_text', $location->long_text) }}">
                            <trix-editor
							input="long_text"
							class="trix-content"
                            ></trix-editor>
                            @error('long_text')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- LONG_TEXT_FULL (Trix) --}}
                    <div class="col-12">
                        <div class="card">
                            <label>Полное описание</label>
                            <input id="long_text_full" type="hidden" name="long_text_full" value="{{ old('long_text_full', $location->long_text_full) }}">
                            <trix-editor
							input="long_text_full"
							class="trix-content"
                            ></trix-editor>
                            @error('long_text_full')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- COORDS --}}
                    <div class="col-md-6">
                        <div class="card">
                            <label>Широта (lat)</label>
                            <input
							type="number"
							name="lat"
							step="any"
							class="@error('lat') is-invalid @enderror"
							value="{{ old('lat', $location->lat) }}"
                            >
                            @error('lat')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    <div class="col-md-6">
                        <div class="card">
                            <label>Долгота (lng)</label>
                            <input
							type="number"
							name="lng"
							step="any"
							class="@error('lng') is-invalid @enderror"
							value="{{ old('lng', $location->lng) }}"
                            >
                            @error('lng')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- PHOTOS (новые) --}}
                    <div class="col-12">
                        <div class="card">
                            <label>Добавить новые фото (до 5)</label>
                            <input
							id="loc_photos"
							type="file"
							name="photos[]"
							multiple
							accept="image/*"
							class="@error('photos') is-invalid @enderror"
                            >
                            <div class="f-16 b-500 mt-1">
                                jpg/jpeg/png/webp, до 5MB каждое, максимум 5 файлов
							</div>
                            @error('photos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('photos.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- NOTE --}}
                    <div class="col-12">
                        <div class="card">
                            <label>Примечание</label>
                            <input
							type="text"
							name="note"
							class="@error('note') is-invalid @enderror"
							value="{{ old('note', $location->note) }}"
                            >
                            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
				</div>
				
				<div class="mt-2 text-center">
					<button type="submit" class="btn btn-primary">Сохранить изменения</button>
				</div>
			</form>
			{{-- 
			<div class="row">
				<div class="col-6 text-end">
					<div class="mt-3">
						<form method="POST"
						action="{{ route('admin.locations.destroy', $location) }}"
						onsubmit="return confirm('Удалить локацию и все её фото?')"
						style="display: inline;">
							@csrf
							@method('DELETE')
							<button class="btn btn-danger" type="submit">Удалить локацию</button>
						</form>
					</div>
				</div>			
			</div>
			--}}
		</div>
		
        {{-- PHOTOS (D&D SORT) --}}
        @if(!$photos->isEmpty())
        <div class="ramka">
			<h2 class="-mt-05">Загруженные фотографии</h2>
			<div class="f-16 b-500"> Перетащи карточки мышкой и нажми «Сохранить порядок».</div>
			
			
			
			
			
			
            <div id="photos_grid" class="row mt-2">
                @foreach($photos as $m)
				<div class="col-md-3 col-6">
					<div class="card cursor-move"
					data-photo-id="{{ $m->id }}">
						@php
						$u = $m->getUrl('thumb');
						if (empty($u)) $u = $m->getUrl();
						@endphp
						<img src="{{ $u }}"
						class="w-full h-32 object-cover rounded-lg"
						alt="">
						<div class="mt-1 d-flex between fvc">
							<span class="b-600 cd">#{{ $m->id }}</span>
							<form method="POST"
							action="{{ route('admin.locations.photos.destroy', [$location, $m]) }}"
							onsubmit="return confirm('Удалить фото?')">
								@csrf
								@method('DELETE')
								<button type="submit" 
								class="btn btn-small btn-danger btn-alert"
								data-title="Удалить фото?"
								data-icon="warning"
								data-confirm-text="Да, удалить"
								data-cancel-text="Отмена">
									Удалить
								</button>								
								
							</form>
						</div>
					</div>
				</div>
                @endforeach
			</div>
			<div class="mt-2 text-center">
				<button type="button" id="photos_save_btn" class="btn">
					Сохранить порядок
				</button>
			</div>
			
		</div>
        @endif
	</div>
</x-voll-layout>