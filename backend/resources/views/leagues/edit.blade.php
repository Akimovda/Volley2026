<x-voll-layout body_class="leagues-page">
	<x-slot name="title">{{ $league->name }} — Управление</x-slot>
	<x-slot name="h1">{{ $league->name }}</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.index') }}" itemprop="item"><span itemprop="name">Мои лиги</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ $league->name }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>

	<x-slot name="h2">
		{{ $league->direction === 'beach' ? 'Пляжный' : 'Классический' }}
	</x-slot>

	<x-slot name="t_description">
		Публичная ссылка: <a class="blink" href="{{ route('leagues.show.slug', $league->slug) }}" target="_blank">/l/{{ $league->slug }}</a>
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
					<h2 class="-mt-05">Настройки лиги</h2>
					<form action="{{ route('leagues.update.league', $league) }}" method="POST" enctype="multipart/form-data">
						@csrf @method('PUT')

						<div class="card mb-2">
							<label>Название</label>
							<input type="text" name="name" value="{{ $league->name }}" required>
						</div>

						<div class="card mb-2">
							<label>Направление</label>
							<select name="direction">
								<option value="beach" {{ $league->direction === 'beach' ? 'selected' : '' }}>Пляжный</option>
								<option value="classic" {{ $league->direction === 'classic' ? 'selected' : '' }}>Классический</option>
							</select>
						</div>

						<div class="card mb-2">
							<label>Описание</label>
							<textarea name="description" rows="3">{{ $league->description }}</textarea>
						</div>

						<div class="card mb-2">
							<label>Статус</label>
							<select name="status">
								<option value="active" {{ $league->status === 'active' ? 'selected' : '' }}>Активна</option>
								<option value="archived" {{ $league->status === 'archived' ? 'selected' : '' }}>Архив</option>
							</select>
						</div>

						<div class="card mb-2">
							<label>Логотип</label>
							@if($league->logo_url)
								<div class="mb-1">
									<img src="{{ $league->logo_url }}" alt="Логотип" style="max-width:100px;border-radius:8px">
								</div>
								<label class="checkbox-item">
									<input type="checkbox" name="remove_logo" value="1">
									<div class="custom-checkbox"></div>
									<span class="f-13">Удалить логотип</span>
								</label>
							@endif
							<input type="file" name="logo" accept="image/*">
							<div class="f-13 cd mt-1">JPG, PNG, WebP. Макс. 2 МБ.</div>
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
							<input type="text" name="max_messenger" value="{{ $league->max_messenger }}" placeholder="Ссылка на MAX">
						</div>
						<div class="card mb-2">
							<label>Сайт</label>
							<input type="text" name="website" value="{{ $league->website }}" placeholder="https://...">
						</div>
						<div class="card mb-2">
							<label>Телефон</label>
							<input type="text" name="phone" value="{{ $league->phone }}" placeholder="+7 999 123-45-67">
						</div>

						<button type="submit" class="btn btn-primary w-100">Сохранить</button>
					</form>
				</div>
			</div>

			<div class="col-lg-8">
				{{-- Сезоны лиги --}}
				<div class="ramka">
					<div class="d-flex between fvc mb-2">
						<h2 class="-mt-05 mb-0">Сезоны</h2>
						<a href="{{ route('seasons.create', $league) }}" class="btn btn-primary f-13" style="padding:6px 14px">Добавить сезон</a>
					</div>

					@if($league->seasons->isEmpty())
						<div class="alert alert-info">
							Нет сезонов. Создайте первый сезон для лиги.
						</div>
					@else
						@foreach($league->seasons as $season)
							<div class="card mb-2" style="padding:12px 16px">
								<div class="d-flex between fvc mb-1">
									<div class="b-600">{{ $season->name }}</div>
									@php
										$statusColors = [
											'active' => ['bg' => 'rgba(16,185,129,.15)', 'color' => '#10b981', 'label' => 'Активен'],
											'completed' => ['bg' => 'rgba(128,128,128,.15)', 'color' => '#6b7280', 'label' => 'Завершён'],
											'draft' => ['bg' => 'rgba(231,97,47,.15)', 'color' => '#E7612F', 'label' => 'Черновик'],
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
										Дивизионы: {{ $season->leagues->pluck('name')->implode(', ') }}
									</div>
								@endif

								<div class="f-16 mb-1 cd">
									Туров: {{ $season->seasonEvents->count() }}
								</div>

								<div class="d-flex" style="gap:8px">
									<a href="{{ route('seasons.edit', $season) }}" class="btn btn-primary f-13" style="padding:6px 14px">Управление</a>
									@php $seasonEvent = $season->seasonEvents->unique('event_id')->first(); @endphp
									@if($seasonEvent && $seasonEvent->event)
										<a href="{{ route('tournament.setup', $seasonEvent->event) }}" class="btn btn-primary f-13" style="padding:6px 14px;background:#E7612F;border-color:#E7612F">Турнир</a>
									@endif
									<a href="{{ route('seasons.show.slug', [$season->league?->slug ?? 'league', $season->slug]) }}" class="btn btn-secondary f-13" style="padding:6px 14px">Публичная</a>
								</div>
							</div>
						@endforeach
					@endif
				</div>
			</div>
		</div>
	</div>
</x-voll-layout>
