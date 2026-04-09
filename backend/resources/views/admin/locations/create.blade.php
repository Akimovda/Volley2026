{{-- resources/views/admin/locations/create.blade.php --}}
<x-voll-layout body_class="admin-page admin-locations-create">
    <x-slot name="title">
        Создать локацию (admin)
	</x-slot>	
	
    <x-slot name="description">
        Страница создания новой локации в административной панели
	</x-slot>
	
    <x-slot name="canonical">
        {{-- Здесь каноническая ссылка не нужна --}}
	</x-slot>
	
	
    <x-slot name="h1">
        Создать локацию
	</x-slot>
	
    <x-slot name="h2">
        Административная панель
	</x-slot>
	
    <x-slot name="t_description">
        Заполните форму для создания новой локации. Поля, отмеченные *, обязательны для заполнения.
	</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админ-панель</span></a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.locations.index') }}" itemprop="item"><span itemprop="name">Локации</span></a>
            <meta itemprop="position" content="3">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Создание</span>
            <meta itemprop="position" content="4">
		</li>
	</x-slot>
    <x-slot name="style">
        <link href="/assets/org.css" rel="stylesheet">
	</x-slot>	
	
    <x-slot name="script">
		<script src="/assets/city.js"></script>  
		<script src="/assets/org.js?v=2"></script>     
        <script>
            (function () {
                // --- trix: запрет вложений
                document.addEventListener('trix-file-accept', function (event) {
                    event.preventDefault();
				});
				
                // --- ограничение файлов (до 5)
                const photos = document.getElementById('loc_photos');
                if (photos) {
                    photos.addEventListener('change', () => {
                        if ((photos.files || []).length > 5) {
                            alert('Можно выбрать максимум 5 файлов.');
                            photos.value = '';
						}
					});
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
		<div class="ramka">
		<h2 class="-mt-05">Данные локации</h2>
            <form method="POST" action="{{ route('admin.locations.store') }}" enctype="multipart/form-data" class="form">
                @csrf
				
                <div class="row">
                    {{-- NAME --}}
                    <div class="col-12">
						<div class="card">
							<label>Название <span class="text-danger">*</span></label>
							<input
							type="text"
							name="name"
							class="@error('name') is-invalid @enderror"
							value="{{ old('name') }}"
							required
							>
							
							@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
							<div class="pb-05"></div>
						</div>
					</div>
					
					
					<div class="col-md-4">
						<div class="card">
							<label>Город *</label>
							
							{{-- То, что реально сохраняем --}}
							<input type="hidden" name="city_id" id="city_id" value="{{ old('city_id') }}" required>
							
							{{-- UI input (поиск) --}}
							<div class="city-autocomplete" id="city-autocomplete" data-search-url="{{ route('cities.search') }}">
								<input type="text"
								id="city_search"
								placeholder="Начните вводить город…"
								value="{{ old('city_label') }}"
								autocomplete="off"
								@error('city_id') class="error" @enderror>
								
								{{-- dropdown --}}
								<div id="city_dropdown" class="city-dropdown">
									<div id="city_results"></div>
								</div>
							</div>
							@error('city_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
							<div class="pb-05"></div>
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
							value="{{ old('address') }}"
							>
							@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
							<div class="pb-05"></div>
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
							value="{{ old('short_text') }}"
							>
							@error('short_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
							<div class="pb-05"></div>
						</div>
					</div>
                    {{-- LONG_TEXT (Trix) --}}
                    <div class="col-12">
						<div class="card">
							<label>Короткое иписание (только для превью)</label>
							<input id="long_text" type="hidden" name="long_text" value="{{ old('long_text') }}">
							<trix-editor
							input="long_text"
							class="trix-content"
							></trix-editor>
							@error('long_text')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
							<div class="pb-05"></div>
						</div>
					</div>
                    {{-- LONG_TEXT_FULL (Trix) --}}
                    <div class="col-12">
						<div class="card">
							<label>Полное иписание</label>
							<input id="long_text_full" type="hidden" name="long_text_full" value="{{ old('long_text_full') }}">
							<trix-editor
							input="long_text_full"
							class="trix-content"
							></trix-editor>
							<div class="pb-05"></div>
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
							value="{{ old('lat') }}"
							>
							@error('lat')<div class="invalid-feedback">{{ $message }}</div>@enderror
							<div class="pb-05"></div>
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
							value="{{ old('lng') }}"
							>
							@error('lng')<div class="invalid-feedback">{{ $message }}</div>@enderror
							<div class="pb-05"></div>
						</div>
					</div>
					
					
					
                    {{-- PHOTOS --}}
                    <div class="col-12">
						<div class="card">
							<label>Фото локации (до 5)</label>
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
							<div class="pb-05"></div>
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
							value="{{ old('note') }}"
							>
							@error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
							<div class="pb-05"></div>
						</div>					
					</div>	
					
				</div>
				
                <div class="mt-2 text-center">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
				</div>
			</form>
		</div>
	</div>
</x-voll-layout>