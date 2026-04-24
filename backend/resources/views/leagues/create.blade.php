<x-voll-layout body_class="leagues-page">
	<x-slot name="title">Создать лигу</x-slot>
	<x-slot name="h1">Новая лига</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.index') }}" itemprop="item"><span itemprop="name">Мои лиги</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">Создать</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>

	<x-slot name="t_description">
		Лига — долгоживущая сущность. Внутри лиги создаются сезоны с турнирами и рейтингами.
	</x-slot>

	<div class="container form">
		<form action="{{ route('leagues.store') }}" method="POST" enctype="multipart/form-data">
			@csrf

			<div class="ramka">
				<h2 class="-mt-05">Основные настройки</h2>
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<label>Название лиги</label>
							<input type="text" name="name" id="name"
								value="{{ old('name') }}"
								placeholder="Например: Пляжная лига Москвы"
								required>
							@error('name') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<label>Направление</label>
							<select name="direction" id="direction">
								<option value="beach" {{ old('direction', 'beach') === 'beach' ? 'selected' : '' }}>Пляжный (2x2 / 3x3 / 4x4)</option>
								<option value="classic" {{ old('direction') === 'classic' ? 'selected' : '' }}>Классический (6x6)</option>
							</select>
						</div>
					</div>
				</div>

				@if(auth()->user()->isAdmin() && $organizers->isNotEmpty())
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<label>Организатор</label>
							<select name="organizer_id">
								<option value="">— Я ({{ auth()->user()->first_name }} {{ auth()->user()->last_name }})</option>
								@foreach($organizers as $org)
									<option value="{{ $org->id }}" {{ old('organizer_id') == $org->id ? 'selected' : '' }}>
										{{ $org->first_name }} {{ $org->last_name }} ({{ $org->role }})
									</option>
								@endforeach
							</select>
						</div>
					</div>
				</div>
				@endif
			</div>

			<div class="ramka">
				<h2 class="-mt-05">Описание</h2>
				<div class="card">
					<label>Описание лиги (необязательно)</label>
					<textarea name="description" rows="3" placeholder="Расскажите о вашей лиге...">{{ old('description') }}</textarea>
				</div>
			</div>

			<div class="ramka">
				<h2 class="-mt-05">Социальные сети</h2>
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<label>VK</label>
							<input type="text" name="vk" value="{{ old('vk') }}" placeholder="https://vk.com/your_group">
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<label>Telegram</label>
							<input type="text" name="telegram" value="{{ old('telegram') }}" placeholder="https://t.me/your_channel">
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<label>MAX</label>
							<input type="text" name="max_messenger" value="{{ old('max_messenger') }}" placeholder="Ссылка на MAX">
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<label>Сайт</label>
							<input type="text" name="website" value="{{ old('website') }}" placeholder="https://example.com">
							@error('website') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<label>Телефон</label>
							<input type="text" name="phone" value="{{ old('phone') }}" placeholder="+7 999 123-45-67">
						</div>
					</div>
				</div>
			</div>

			<div class="ramka">
				<h2 class="-mt-05">Логотип</h2>
				<div class="card">
					<label>Загрузить логотип лиги</label>
					<input type="file" name="logo" accept="image/*">
					@error('logo') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror
					<div class="f-13 cd mt-1">JPG, PNG, WebP. Максимум 2 МБ.</div>
				</div>
			</div>

			<div class="ramka">
				<div class="text-center">
					<button type="submit" class="btn">Создать лигу</button>
				</div>
			</div>
		</form>
	</div>
</x-voll-layout>
