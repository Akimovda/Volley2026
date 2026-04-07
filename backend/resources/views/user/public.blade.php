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
	
	
	$age = '—';
	if (method_exists($user, 'ageYears') && $years = $user->ageYears()) {
    $ending = match($years % 10) {
	1 => 'год',
	2,3,4 => 'года',
	default => 'лет'
    };
    $age = $years . ' ' . $ending;
	}
    
$birth = $user->birth_date 
    ? $user->birth_date->isoFormat('D MMMM YYYY') . ' г.'
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
	
    <x-slot name="script">
        <script src="/assets/fas.js"></script>   	
        <script>
            // === Инициализация Swiper ===
            const swiper = new Swiper('.photo-swiper', {
                slidesPerView: 2,
                spaceBetween: 20,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
				},
                breakpoints: {
                    640: {
                        slidesPerView: 3, 
					},
                    768: {
                        slidesPerView: 3,
					},
                    992: {
                        slidesPerView: 3,
					},
                    1024: {
                        slidesPerView: 3,
					},
                    1280: {
                        slidesPerView: 4,
					}
				}   
			});     
		</script>	
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
		Информация об игроке
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
						<div class="d-flex flex-wrap gap-1 fc">
							
							@auth
							@if((auth()->user()->isAdmin() || auth()->user()->isOrganizer()) && $user->phone)
							<a class="btn" href="tel:{{ $user->phone }}">
								{{ $user->formatted_phone }}
							</a>
							@endif
							@endauth	
							
							
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
						</div>
                        @endif
					</div>
				</div>
                <div class="ramka">        
                    {{-- Gallery --}}
                    <h2 class="-mt-05">Фотографии</h2>
					
                    <div class="mt-2">
                        @if($photos->isEmpty())
                        <div class="alert alert-info">
							У пользователя нет загруженных фотографий
						</div>
                        @else
                        <div class="swiper photo-swiper">
                            <div class="swiper-wrapper">
                                @foreach($photos as $m)
                                @php
                                $thumbUrl = method_exists($m, 'hasGeneratedConversion') && $m->hasGeneratedConversion('thumb')
                                ? $m->getUrl('thumb')
                                : $m->getUrl();
                                @endphp
                                <div class="swiper-slide">
                                    
                                    <div class="hover-image">
                                        <a href="{{ $m->getUrl() }}" class="fancybox" data-fancybox="gallery">
                                            <img
                                            src="{{ $thumbUrl }}"
                                            alt="photo"
                                            loading="lazy"
                                            />
                                            <span></span>
                                            <div class="hover-image-circle"></div>
										</a>
									</div>                                                          
								</div>
                                @endforeach
							</div>
                            <div class="swiper-pagination"></div>
						</div>
                        @endif
					</div>
					<div class="text-right">
					    <p>Всего: <strong class="cd">{{ $photos->count() }}</strong> фото</p>
					</div>  	
				</div>  				
				
				
			</div>
		</div>
	</div>
    
</x-voll-layout>