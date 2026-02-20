{{-- resources/views/users/index.blade.php --}}
<x-voll-layout body_class="users-page">
	
    <x-slot name="title">
        Игроки — Страница {{ request()->page ?? 1 }}
	</x-slot>
	
    <x-slot name="description">
        @if(request()->has('role'))
		Игроки с ролью {{ request()->role }}
        @else
		Все игроки платформы
        @endif
	</x-slot>
	
    <x-slot name="canonical">
        {{ route('users.index') }}
	</x-slot>
	

	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('users.index') }}" itemprop="item">
                <span itemprop="name">Игроки</span>
			</a>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
	
    <x-slot name="h1">Игроки платформы</x-slot>
    <x-slot name="t_description">
		
@php
    $count = (int)($users->total() ?? $users->count() ?? 0);
    $word = trans_choice('человек|человека|человек', $count);
    
    if(!empty(array_filter($filters ?? []))) {
        $foundWord = trans_choice('найден|найдено|найдено', $count);
    }
@endphp

@if(!empty(array_filter($filters ?? [])))
    {{ ucfirst($foundWord) }} <strong class="cd">{{ $count }}</strong> {{ $word }}
@else
    Зарегистрировано <strong class="cd">{{ $count }}</strong> {{ $word }}
@endif
		
		
	</x-slot>
    <x-slot name="d_description">
		<div data-aos-delay="250" data-aos="fade-up">
			<button class="btn ufilter-btn mt-2">Фильтр</button>
		</div> 
	</x-slot>

	
	
	
    <div class="container">	
        <div class="users-filter">
            <div class="ramka">
                <form method="GET" action="{{ route('users.index') }}" class="form">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <label>Имя / фамилия</label>
                            <input
							name="q"
							value="{{ $filters['q'] ?? '' }}"
							placeholder="Иван Иванов"
                            />
						</div>
						
                        <div class="col-md-4 col-sm-6">
                            <label>Город</label>
                            <select name="city_id">
                                <option value="">— любой —</option>
                                @foreach($cities as $c)
								<option value="{{ $c->id }}" @selected((string)($filters['city_id'] ?? '') === (string)$c->id)>
									{{ $c->name }}@if($c->region) ({{ $c->region }})@endif
								</option>
                                @endforeach
							</select>
						</div>
						
						<div class="col-md-4 col-sm-6">
                            <label>Пол</label>
                            <select name="gender">
                                <option value="">— любой —</option>
                                <option value="m" @selected(($filters['gender'] ?? '') === 'm')>Мужчина</option>
                                <option value="f" @selected(($filters['gender'] ?? '') === 'f')>Женщина</option>
							</select>
						</div>
						
						<div class="col-md-4 col-sm-6">
                            <label>Уровень (классика)</label>
                            <input
							name="classic_level"
							value="{{ $filters['classic_level'] ?? '' }}"
							placeholder="1..7"
							inputmode="numeric"
                            />
						</div>
						
						<div class="col-md-4 col-sm-6">
                            <label>Уровень (пляж)</label>
                            <input
							name="beach_level"
							value="{{ $filters['beach_level'] ?? '' }}"
							placeholder="1..7"
							inputmode="numeric"
                            />
						</div>
						
                        <div class="col-md-4 col-sm-6">
                            <label>Возраст</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input
									name="age_min"
									value="{{ $filters['age_min'] ?? '' }}"
									placeholder="от, напр. 18"
									inputmode="numeric"
                                    />
								</div>
                                <div class="col-6">
                                    <input
									name="age_max"
									value="{{ $filters['age_max'] ?? '' }}"
									placeholder="до, напр. 45"
									inputmode="numeric"
                                    />
								</div>
							</div>
						</div>
						
                        <div class="col-12 text-right m-center">
                            <button class="btn" type="submit">Искать</button>
							
							@if(!empty(array_filter($filters ?? [])))
							<a class="btn btn-secondary" href="{{ route('users.index') }}">Сбросить</a>
							@endif
						</div>
					</div>
				</form>
			</div>
		</div>	
		
		
		
        
            {{-- Header line --}}
			
            {{-- Results --}}
            @if(($users ?? collect())->isEmpty())
			<div class="ramka">
			<div class="alert alert-info">
					Ничего не найдено. Попробуй сбросить фильтры или изменить условия поиска.
			</div>
			</div>
            @else
			<div class="row mb-0">
				@foreach($users as $u)
				<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3">
					@include('users._card', ['u' => $u])
				</div>
				@endforeach
			</div>

            @endif
		{{ $users->links() }}
	</div>
	
</x-voll-layout>
