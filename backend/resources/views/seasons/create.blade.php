<x-voll-layout body_class="seasons-page">
	<x-slot name="title">Создать сезон и лигу</x-slot>
	<x-slot name="h1">Новый сезон</x-slot>
	
	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('seasons.index') }}" itemprop="item"><span itemprop="name">Мои сезоны</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">Создать</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>
	
	<x-slot name="t_description">
		<strong>Сезон</strong> — это серия регулярных турниров с накопительной статистикой, рейтингом игроков и системой промоушена между лигами.
	</x-slot>
	
	
	<div class="container form">
		<form action="{{ route('seasons.store') }}" method="POST">
			<div class="ramka" style="z-index:5">			
				<h2 class="-mt-05">Основные настройки</h2>			
                @csrf
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							
							
							<label>Название сезона</label>
							<input type="text" name="name" id="name"
							value="{{ old('name') }}"
							placeholder="Например: Лига Среда — Весна 2026"
							required>
							@error('name') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror
						</div>	
					</div>	
					<div class="col-md-6">	
						<div class="card">
							<label>Направление</label>
							<select name="direction" id="direction">
								<option value="classic" {{ old('direction') === 'classic' ? 'selected' : '' }}>🏐 Классический (6x6)</option>
								<option value="beach" {{ old('direction') === 'beach' ? 'selected' : '' }}>🏖 Пляжный (2x2 / 3x3 / 4x4)</option>
							</select>
						</div>
					</div>
				</div>
			</div>	
			<div class="ramka">			
				
				<h2 class="-mt-05">Даты проведения</h2>
				<div class="row">
					<div class="col-sm-6">
						<div class="card">
                            <label>Начало</label>
                            <input type="date" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d')) }}">
						</div>
					</div>
					<div class="col-sm-6">
						<div class="card">
                            <label>Окончание</label>
                            <input type="date" name="ends_at" value="{{ old('ends_at') }}" placeholder="Оставьте пустым для бессрочного">
							<ul class="list f-16 mt-1">
								<li>Окончание можно не указывать — сезон будет бессрочным</li>
							</ul>							
						</div>
					</div>
				</div>
			</div>
			<div class="ramka">			
			<div class="text-center">
				<button type="submit" class="btn">Создать сезон</button>
			</div>
			</div>
		</form>
	</div>	
</x-voll-layout>