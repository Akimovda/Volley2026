{{-- resources/views/user/public.blade.php --}}
<x-voll-layout body_class="user-public-page">
	
	
    @php
	/** @var \App\Models\User $user */
	$age = method_exists($user, 'ageYears') ? $user->ageYears() : null;
	$isSelf = auth()->check() && auth()->id() === $user->id;
	
	$posMap = [
	'setter'   => 'Связующий',
	'outside'  => 'Доигровщик',
	'opposite' => 'Диагональный',
	'middle'   => 'Центральный блокирующий',
	'libero'   => 'Либеро',
	];
	
	$classicPrimary = optional($user->classicPositions)->firstWhere('is_primary', true)?->position;
	$classicExtras  = optional($user->classicPositions)->where('is_primary', false)->pluck('position')->values()->all() ?? [];
	
	$beachPrimary = optional($user->beachZones)->firstWhere('is_primary', true)?->zone;
	$beachExtras  = optional($user->beachZones)->where('is_primary', false)->pluck('zone')->values()->all() ?? [];
	
	// --- Contacts logic ---
	$allowContact = (bool)($user->allow_user_contact ?? true);
	$isAuthed = auth()->check();
	$isSelf = $isAuthed && auth()->id() === $user->id;
	
	$tgUrl = null;
	$tgUsername = trim((string)($user->telegram_username ?? ''));
	if ($tgUsername !== '') {
	$tgUsername = ltrim($tgUsername, '@');
	if ($tgUsername !== '') {
	$tgUrl = 'https://t.me/' . $tgUsername;
	}
	}
	
	$vkUrl = null;
	$vkId = trim((string)($user->vk_id ?? ''));
	if ($vkId !== '') {
	$vkUrl = 'https://vk.com/id' . $vkId;
	}
	
	$hasAnyContact = (bool)($tgUrl || $vkUrl);
	
	$canShowContactButtons = $isAuthed && !$isSelf && $allowContact && $hasAnyContact;
	
	
$age = method_exists($user, 'ageYears') && $user->ageYears() 
    ? $user->ageYears() . ' лет' 
    : '—';
    
$birth = $user->birth_date 
    ? $user->birth_date->isoFormat('D MMMM YYYY') 
    : '—';
	
    @endphp	
	
    <x-slot name="title">
        @if($isSelf)
		Ваш публичный профиль
        @else
		Профиль игрока: {{ $user->name }}
        @endif
	</x-slot>
	
	<x-slot name="description">
        @if($isSelf)
		Ваш публичный профиль на платформе
        @else
		Профиль игрока {{ $user->name }}
        @endif
	</x-slot>
	
    <x-slot name="canonical">
        {{ route('users.show', $user->id) }}
	</x-slot>
	
    <x-slot name="style">
        <style>
			
		</style>
	</x-slot>
	
    <x-slot name="h1">
        @if($isSelf)
		Ваш публичный профиль
        @else
		Профиль игрока
        @endif
	</x-slot>
	
    <x-slot name="h2">
		{{ $user->name }}
	</x-slot>
	
    <x-slot name="t_description">
        @if($isSelf)
		Примерно так его видят другие пользователи
        @else
		Информация об игроке
        @endif
	</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('users.index') }}" itemprop="item">
                <span itemprop="name">Игроки</span>
			</a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">
                @if($isSelf)
				Ваш публичный профиль
                @else
				{{ $user->name }}
                @endif
			</span>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
	
    <x-slot name="script">
        <script>
            // Дополнительные скрипты при необходимости
		</script>
	</x-slot>
	
	
    <div class="container">
		
		{{-- FLASH --}}
		@if (session('status'))
		<div class="ramka">
			<div class="alert alert-success mb-4">
				{{ session('status') }}
			</div>
		</div>
		@endif
		@if (session('error'))
		<div class="ramka">
			<div class="alert alert-danger mb-4">
				{{ session('error') }}
			</div>
		</div>
		@endif	
		
		
        <div class="row row2">
            {{-- SIDEBAR (левая колонка) --}}
            <div class="col-lg-4 col-xl-4 order-1 order-lg-1">
                <div class="sticky">
                    <div class="ramka">
                        <div class="card-body">
                            <div class="row row2">
                                <div class="col-12 text-center">
									<div class="profile-avatar">
										<img
										src="{{ $user->profile_photo_url }}"
										alt="avatar"
										class="avatar"
										/>			
									</div>
								</div>
								<div class="col-12">    
									<ul class="list mb-0 mt-2 -ml-1">	
										<li><span class="b-600">Фамилия:</span> {{ $user->last_name ?? '—' }}</li>
										<li><span class="b-600">Имя:</span> {{ $user->first_name ?? '—' }}</li>
										@if(auth()->user()->isAdmin() || auth()->user()->isOrganizer())		
										<li><span class="b-600">Отчество:</span> {{ $user->patronymic ?? '—' }}</li>
										@endif	
										<li><span class="b-600">Пол:</span>
											@if($user->gender === 'm') Мужчина
											@elseif($user->gender === 'f') Женщина
											@else — @endif										
										</li>
										<li><span class="b-600">Рост:</span> {{ !empty($user->height_cm) ? ($user->height_cm.' см') : '—' }}</li>
										<li><span class="b-600">Город:</span>
											@if($user->city)
											{{ $user->city->name }}@if($user->city->region) ({{ $user->city->region }})@endif
											@else
											—
										@endif</li>
										<li><span class="b-600">Дата рождения:</span> {{ $birth }} 	
										</li>
										<li><span class="b-600">Возраст:</span>	{{ $age }}								
										</li>										
									</ul>
								</div>
								
                                <div class="col-12">          
									@auth
									@if(!$isSelf)
									@if(auth()->user()->isAdmin() || auth()->user()->isOrganizer())		
									<hr class="mt-1 mb-1">
									<nav class="menu-nav">   		
										<a href="{{ url('/profile/complete?user_id=' . $user->id) }}" class="menu-item">
											<span class="menu-text">
												@if(auth()->user()->isAdmin())
												Редактировать пользователя
												@else
												Настроить уровни
												@endif
											</span>
										</a>
										@if(auth()->user()->isAdmin())       
										<a href="{{ url('/user/photos?user_id=' . $user->id) }}" class="menu-item">
											<span class="menu-text">
												Редактировать фото пользователя
											</span>
										</a>											
										@endif	
									</nav>
									@endif
									@endif
									
									
									@endauth	
								</div>  
							</div>
						</div>
					</div>
				</div>   
			</div>
			
            {{-- MAIN CONTENT (правая колонка) --}}
            <div class="col-lg-8 col-xl-8 order-2 order-lg-2">
				
                {{-- Skills --}}
				<div class="ramka">  	
					<h2 class="-mt-05">Навыки в волейболе</h2>
					<div class="row">
						<div class="col-md-6 col-lg-12 col-xl-6">
							<div class="card">
								<p class="b-600 mb-1">Классический волейбол</p>
								
								<div class="level-wrap">
									<div class="level-levelmark levelmark level-{{ $user->classic_level ?? '—' }}">
										<div class="f-11">Уровень: </div>
										<div class="f-22 l-13">{{ $user->classic_level ?? '—' }}</div>
										<div class="f-11">{{ level_name($user->classic_level) ?? '—' }}</div>
									</div>	
									<div class="level-level">	
										<ul class="list">
											<li>
												<span class="b-600">Амплуа игрока:</span><br>
												
												@if($classicPrimary)
												{{ $posMap[$classicPrimary] ?? $classicPrimary }}
												@if(!empty($classicExtras))
												</li><li><span class="b-600">Дополнительно:</span><br>{{ collect($classicExtras)->map(fn($p) => $posMap[$p] ?? $p)->join(', ') }}
												@endif
												@else
												—
												@endif
											</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-6 col-lg-12 col-xl-6">
							<div class="card">
								<p class="b-600 mb-1">Пляжный волейбол</p>
								
								
								<div class="level-wrap">
									<div class="level-levelmark levelmark level-{{ $user->beach_level ?? '—' }}">
										<div class="f-11">Уровень: </div>
										<div class="f-22 l-13">{{ $user->beach_level ?? '—' }}</div>
										<div class="f-11">{{ level_name($user->beach_level) ?? '—' }}</div>
									</div>	
									<div class="level-level">	
										<ul class="list">
											<li>
												<span class="b-600">Зона игры:</span><br>
												
												
												@if(!empty($user->beach_universal))
												Универсал (2 и 4)
												@elseif(!is_null($beachPrimary))
												Основная: {{ $beachPrimary }}
												@if(!empty($beachExtras))
												; Доп.: {{ collect($beachExtras)->join(', ') }}
												@endif
												@else
												—
												@endif
												
											</li>
										</ul>
									</div>
								</div>								
							</div>
						</div>
					</div>
				</div>	
				
                {{-- Contacts --}}
                <div class="ramka">
                    <div class="card-body">
					<h2 class="-mt-05">Контакты</h2>
 
                        @if(!$allowContact)
						<div class="alert alert-info">
							Пользователь запретил связываться с ним через Telegram/VK.
						</div>
                        @elseif(!$hasAnyContact)
						<div class="alert alert-info">
							Пользователь не указал Telegram/VK для связи.
						</div>
                        @elseif(!$isAuthed)
						<div class="alert alert-info">
							Чтобы написать пользователю в Telegram/VK, нужно войти в аккаунт.
						</div>
                        @elseif($canShowContactButtons)
							@if($tgUrl)
							<a class="btn"
							href="{{ $tgUrl }}" target="_blank" rel="noopener noreferrer">
								Написать в Telegram
							</a>
							@endif
							
							@if($vkUrl)
							<a class="btn"
							href="{{ $vkUrl }}" target="_blank" rel="noopener noreferrer">
								Написать в VK
							</a>
							@endif
                        @endif
					</div>
				</div>
			</div>
		</div>
	</div>
    
</x-voll-layout>