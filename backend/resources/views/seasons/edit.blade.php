<x-voll-layout body_class="seasons-page">
	<x-slot name="title">{{ $season->name }} — Управление</x-slot>
	<x-slot name="h1">{{ $season->name }}</x-slot>
	
	<x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('seasons.index') }}" itemprop="item"><span itemprop="name">Мои сезоны</span></a>
			<meta itemprop="position" content="2">
		</li>
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<span itemprop="name">{{ $season->name }}</span>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>
	<x-slot name="h2">
		{{ $season->direction === 'beach' ? 'Пляжный' : 'Классический' }}
		
	</x-slot>	
	<x-slot name="t_description">
		{{ $season->starts_at?->format('d.m.Y') ?? '—' }} — {{ $season->ends_at?->format('d.m.Y') ?? '...' }}
		
	</x-slot>
	<x-slot name="d_description">
		<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
			<span class="b-600" style="padding: 0.6rem 1.2rem; border-radius: 1rem;{{ $season->status === 'active' ? 'background:rgba(16,185,129,.15);color:#10b981' : ($season->status === 'completed' ? 'background:rgba(128,128,128,.15);color:#666' : 'background:rgba(255,152,0,.15);color:#ff9800') }}">
				{{ $season->status === 'active' ? 'Активен' : ($season->status === 'completed' ? 'Завершён' : 'Черновик') }}
			</span>
		</div>		
	</x-slot>
	
	
	<div class="container">
		
		@if(session('success'))
		<div class="ramka">	
			<div class="alert alert-success">{{ session('success') }} ✅ </div>
		</div>
		@endif
		@if(session('error'))
		<div class="ramka">	
			<div class="alert alert-danger">{{ session('error') }} ❌</div>
		</div>
		@endif
		
		<div class="row row2 form">
			<div class="col-lg-4">
				{{-- Настройки --}}
				<div class="ramka">
					<h2 class="-mt-05">Настройки</h2>
					<form action="{{ route('seasons.update', $season) }}" method="POST">
						@csrf @method('PUT')
						
						<div class="card mb-2">
							<label>Название</label>
							<input type="text" name="name" value="{{ $season->name }}" required>
						</div>
						
						<div class="card mb-2">
							<label>Направление</label>
							<select name="direction">
								<option value="classic" {{ $season->direction === 'classic' ? 'selected' : '' }}>Классический</option>
								<option value="beach" {{ $season->direction === 'beach' ? 'selected' : '' }}>Пляжный</option>
							</select>
						</div>
						
						<div class="card mb-2">
							<label>Начало</label>
							<input type="date" name="starts_at" value="{{ $season->starts_at?->format('Y-m-d') }}">
						</div>
						<div class="card mb-2">
							<label>Конец</label>
							<input type="date" name="ends_at" value="{{ $season->ends_at?->format('Y-m-d') }}">
						</div>
						
						<button type="submit" class="btn btn-primary w-100">Сохранить</button>
					</form>
				</div>
				
				{{-- Действия --}}
				<div class="ramka">
					<div class="d-flex" style="gap:1rem;flex-wrap:wrap">
						@if($season->isDraft())
						<form action="{{ route('seasons.activate', $season) }}" method="POST" style="flex:1">
							@csrf
							<button class="btn w-100">Активировать</button>
						</form>
						<form action="{{ route('seasons.destroy', $season) }}" method="POST" style="flex:1"
						onsubmit="return confirm('Удалить сезон?')">
							@csrf @method('DELETE')
							<button class="btn btn-danger btn-alert w-100"
							data-title="Удалить сезон?"
							data-text="Все лиги также будут удалены. Отменить нельзя!"
							data-icon="warning"
							data-confirm-text="Да, удалить"
							data-cancel-text="Отмена">Удалить</button>
						</form>
						@elseif($season->isActive())
						<form action="{{ route('seasons.complete', $season) }}" method="POST" style="flex:1"
						onsubmit="return confirm('Завершить сезон?')">
							@csrf
							<button class="btn btn-secondary w-100">Завершить сезон</button>
						</form>
						@endif
					</div>
					
					<div class="mt-2">
						Публичная ссылка: <br><a class="blink" href="{{ route('seasons.show.slug', $season->slug) }}" target="_blank">/s/{{ $season->slug }}</a>
					</div>
				</div>
				
			</div>
			<div class="col-lg-8">
				
				{{-- Добавить лигу --}}
				<div class="ramka">
					<h2 class="-mt-05">Добавить лигу</h2>
					<form action="{{ route('seasons.leagues.store', $season) }}" method="POST">
						@csrf
						<div class="row">
							<div class="col-md-8">
								<div class="card">
									<label>Название лиги</label>
									<input type="text" name="name" placeholder="Hard / Lite / Open" required>
								</div>
							</div>
							<div class="col-md-4">
								<div class="card">
									<label>Макс. команд</label>
									<input type="number" name="max_teams" min="2" placeholder="—">
								</div>
							</div>
						</div>	
						<div class="text-center mt-2">
							<button type="submit" class="btn btn-primary">Добавить</button>
						</div>
					</form>
				</div>
				
				{{-- Список лиг --}}
				
				@forelse($season->leagues as $league)
				<div class="ramka">
					<div class="d-flex between fvc mb-2">
						<h2 class="-mt-05 mb-0">Лига: {{ $league->name }}</h2>
						<div>
							<strong class="cd">{{ $league->activeTeams->count() }}</strong> команд {!! $league->max_teams ? ' / макс. <strong class="cd">' . $league->max_teams . '</strong>' : '' !!}
							
							@if($league->reserveTeams->count() > 0)
							· резерв: <strong class="cd">{{ $league->reserveTeams->count() }}</strong>
							@endif
						</div>
					</div>
					
					{{-- Активные команды --}}
					@if($league->activeTeams->isNotEmpty())
					@foreach($league->activeTeams as $lt)
					<div class="card d-flex between fvc mb-1" style="padding:8px 12px">
						<div>
							@if($lt->team)
							<span class="b-600">{{ $lt->team->name }}</span>
							@if($lt->team->captain)
                            <span class="f-13" style="opacity:.5">({{ $lt->team->captain->name }})</span>
							@endif
							@elseif($lt->user)
							<span class="b-600">{{ $lt->user->name }}</span>
							@endif
						</div>
						<form action="{{ route('leagues.teams.destroy', $lt) }}" method="POST"
						onsubmit="return confirm('Убрать из лиги?')">
							@csrf @method('DELETE')
							<button class="btn btn-secondary f-12" style="padding:2px 8px;color:#dc2626">✕</button>
						</form>
					</div>
					@endforeach
					@else
					<div class="alert alert-info">Нет команд</div>
					@endif
					
					{{-- Резерв --}}
					@if($league->reserveTeams->isNotEmpty())
					<div class="mt-2 p-2" style="background:rgba(128,128,128,.06);border-radius:8px">
						<span class="f-13 b-600">Резерв:</span>
						@foreach($league->reserveTeams as $lt)
						<span class="f-13 ml-1 px-2 py-1" style="background:rgba(128,128,128,.12);border-radius:6px;display:inline-block;margin:2px">
							#{{ $lt->reserve_position }} {{ $lt->team?->name ?? $lt->user?->name ?? '—' }}
						</span>
						@endforeach
					</div>
					@endif
					
					<div class="d-flex between fvc mt-1">
						<span>
							Повышение: <strong class="cd">{{ $league->promoteCount() }}</strong> · Вылет: <strong class="cd">{{ $league->eliminateCount() }}</strong>
						</span>
						<form action="{{ route('leagues.destroy', $league) }}" method="POST"
						onsubmit="return confirm('Удалить лигу «{{ $league->name }}»?')">
							@csrf @method('DELETE')
							<button class="btn btn-danger btn-alert w-100"
							data-title="Удалить лигу?"
							data-icon="warning"
							data-confirm-text="Да, удалить"
							data-cancel-text="Отмена">Удалить</button>
						</form>
					</div>
				</div>
				@empty
				<div class="ramka">
					<h2 class="-mt-05">Лиги сезона</h2>					
					<div class="alert alert-info">
						Добавьте лиги для сезона
					</div>
				</div>
				@endforelse
				
				{{-- Привязанные турниры --}}
				<div class="ramka">
					<h2 class="-mt-05">Турниры сезона</h2>
					
					@if($season->seasonEvents->isNotEmpty())
					@foreach($season->seasonEvents->sortBy('round_number') as $se)
					<div class="card d-flex between fvc mb-1" style="padding:8px 12px;flex-wrap:wrap;gap:8px">
						<div>
							<span class="b-600 f-14">Тур {{ $se->round_number }}</span>
							<a href="{{ route('events.show', $se->event) }}" class="ml-1">{{ $se->event->title }}</a>
							<span class="f-13 ml-1" style="opacity:.5">· {{ $se->league->name }}</span>
						</div>
						<div class="d-flex fvc" style="gap:8px">
							<span class="f-12 b-600 px-2 py-1" style="border-radius:6px;{{ $se->isCompleted() ? 'background:rgba(16,185,129,.15);color:#10b981' : 'background:rgba(255,152,0,.15);color:#ff9800' }}">
								{{ $se->isCompleted() ? '✓ Завершён' : 'Ожидает' }}
							</span>
							<form action="{{ route('seasons.events.detach', [$season, $se->event]) }}" method="POST"
							onsubmit="return confirm('Отвязать турнир?')">
								@csrf @method('DELETE')
								<button class="btn btn-secondary f-12" style="padding:2px 8px;color:#dc2626">✕</button>
							</form>
						</div>
					</div>
					@endforeach
					@else
					<div class="alert alert-info">Нет привязанных турниров</div>
					@endif
					
					{{-- Привязать новый --}}
					@if($availableEvents->isNotEmpty() && $season->leagues->isNotEmpty())
					<div class="mt-2 pt-2" style="border-top:1px solid rgba(128,128,128,.15)">
						<form action="{{ route('seasons.events.attach', $season) }}" method="POST">
							@csrf
							<div class="d-flex" style="gap:10px;flex-wrap:wrap;align-items:flex-end">
								<div style="flex:2">
									<label class="f-13 b-600 mb-1">Турнир</label>
									<select name="event_id" required>
										<option value="">Выберите...</option>
										@foreach($availableEvents as $ev)
										<option value="{{ $ev->id }}">{{ $ev->title }}</option>
										@endforeach
									</select>
								</div>
								<div style="flex:1">
									<label class="f-13 b-600 mb-1">Лига</label>
									<select name="league_id" required>
										@foreach($season->leagues as $lg)
										<option value="{{ $lg->id }}">{{ $lg->name }}</option>
										@endforeach
									</select>
								</div>
								<div style="width:70px">
									<label class="f-13 b-600 mb-1">Тур #</label>
									<input type="number" name="round_number" min="1" value="{{ $season->currentRound() + 1 }}">
								</div>
								<div>
									<button type="submit" class="btn btn-primary" style="padding:8px 16px">Привязать</button>
								</div>
							</div>
						</form>
					</div>
					@endif
				</div>
				
			</div>
		</div>
		
	</div>
</x-voll-layout>
