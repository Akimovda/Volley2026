<x-voll-layout body_class="seasons-page">
	<x-slot name="title">Новый сезон — {{ $league->name }}</x-slot>
	<x-slot name="h1">Новый сезон</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.index') }}" itemprop="item"><span itemprop="name">Мои лиги</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.edit', $league) }}" itemprop="item"><span itemprop="name">{{ $league->name }}</span></a>
			<meta itemprop="position" content="3">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">Новый сезон</span>
			<meta itemprop="position" content="4">
		</li>
	</x-slot>

	<x-slot name="h2">{{ $league->name }} · {{ $league->direction === 'beach' ? 'Пляжный' : 'Классический' }}</x-slot>

	<x-slot name="t_description">
		Сезон — временной период внутри лиги с турнирами, рейтингом и промоушеном.
	</x-slot>

	<div class="container form">
		<form action="{{ route('seasons.store', $league) }}" method="POST">
			@csrf
			<div class="ramka">
				<h2 class="-mt-05">Основные настройки</h2>
				<div class="row">
					<div class="col-md-12">
						<div class="card">
							<label>Название сезона</label>
							<input type="text" name="name" id="name"
								value="{{ old('name') }}"
								placeholder="Например: Весна 2026"
								required>
							@error('name') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror
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
							<input type="date" name="ends_at" value="{{ old('ends_at') }}">
							<ul class="list f-16 mt-1">
								<li>Можно не указывать — сезон будет бессрочным</li>
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
