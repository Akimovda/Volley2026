<x-voll-layout body_class="leagues-page">
	<x-slot name="title">Мои лиги</x-slot>
	<x-slot name="h1">Мои лиги</x-slot>

	<x-slot name="canonical">{{ route('leagues.index') }}</x-slot>

	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('leagues.index') }}" itemprop="item">
				<span itemprop="name">Мои лиги</span>
			</a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>

	<x-slot name="t_description">
		Лига — долгоживущая серия турниров с сезонами, дивизионами и накопительным рейтингом игроков.
	</x-slot>

	<x-slot name="d_description">
		<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
			<a href="{{ route('leagues.create') }}" class="btn btn-primary">Создать лигу</a>
		</div>
	</x-slot>

	<div class="container">
		<div class="ramka">
			@if($leagues->isEmpty())
				<div class="alert alert-info">
					<p><strong>У вас пока нет лиг</strong></p>
					<p>Создайте лигу, затем добавьте в неё сезоны с турнирами.</p>
				</div>
			@else
				<div class="row">
					@foreach($leagues as $league)
						<div class="col-md-6 col-lg-4">
							<div class="card">
								<div class="text-center mb-1">
								@if($league->logo_url)
									<div class="mb-1">
										<img src="{{ $league->logo_url }}" alt="{{ $league->name }}" style="width:60px;height:60px;border-radius:10px;object-fit:cover">
									</div>
								@endif
								<div class="b-600">{{ $league->name }}</div>
							</div>
								<div class="d-flex mb-1" style="justify-content:center">
									@php
										$statusColors = [
											'active' => ['bg' => 'rgba(16,185,129,.15)', 'color' => '#10b981', 'label' => 'Активна'],
											'archived' => ['bg' => 'rgba(128,128,128,.15)', 'color' => '#6b7280', 'label' => 'Архив'],
										];
										$st = $statusColors[$league->status] ?? $statusColors['active'];
									@endphp
									<span class="f-13 b-600" style="background:{{ $st['bg'] }}; padding: 0.4rem 1rem; border-radius:1rem;color:{{ $st['color'] }}">
										{{ $st['label'] }}
									</span>
								</div>

								<div class="f-16 mb-1 text-center">
									{{ $league->direction === 'beach' ? '🏖 Пляжная лига' : '🏐 Классическая лига' }}
								</div>

								@if($league->description)
									<div class="f-16 mb-1 cd">{{ Str::limit($league->description, 80) }}</div>
								@endif

								<div class="f-16 mb-1 cd">
									Сезонов: {{ $league->seasons->count() }}
								</div>

								@if(auth()->user()->isAdmin() && $league->organizer)
									<div class="f-13 mb-1 cd">
										Организатор: <a href="{{ route('users.show', $league->organizer->id) }}" class="blink">{{ $league->organizer->first_name }} {{ $league->organizer->last_name }}</a>
									</div>
								@endif

								<div class="d-flex mt-auto" style="gap:8px;margin-top:auto;justify-content:center">
									<a href="{{ route('leagues.edit', $league) }}" class="btn btn-primary f-13" style="padding:6px 14px" title="Редактировать">⚙️</a>
									<a href="{{ route('leagues.show.slug', $league->slug) }}" class="btn btn-secondary f-13" style="padding:6px 14px" title="Публичная страница">🔗</a>
									@if(auth()->check() && auth()->user()->isAdmin())
									<form method="POST" action="{{ route('leagues.destroy', $league) }}">
										@csrf @method('DELETE')
										<button type="submit"
											class="btn-alert btn btn-danger f-13"
											style="padding:6px 14px"
											data-title="Удалить лигу?"
											data-text="{{ $league->name }}"
											data-confirm-text="Да, удалить"
											data-cancel-text="Отмена"
											title="Удалить">🗑</button>
									</form>
									@endif
								</div>
							</div>
						</div>
					@endforeach
				</div>
			@endif
		</div>
	</div>
</x-voll-layout>
