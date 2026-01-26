@props([
'body_class' => ''
])
<!DOCTYPE html>
<html lang="ru">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="csrf-token" content="{{ csrf_token() }}">
		<title>{{ $title ?? 'Волейбольный сервис Your Volley Club!' }}</title>
		<meta name="description" content="{{ $description ?? 'Волейбольный сервис Your Volley Club!' }}">
		@if(isset($canonical))
        <link rel="canonical" href="{{ trim($canonical) }}">
		@endif
		<link href="/assets/style.css" rel="stylesheet">
		@livewireStyles
	</head>
	<body @class([$body_class ?? null])>
		<script>
			if (localStorage.getItem('theme') === 'dark') {
				document.body.classList.add('dark');
			}
		</script>	
		<header>
			<div class="fix-header">
				<div class="liquidGlass-effect"></div>
				<div class="liquidGlass-tint"></div>
				<div class="fix-header-data">
					<div class="fix-header-main">
						<div class="fix-header-logo">
							<a href="/">
								<span class="icon-logo"></span>
								<span class="icon-mlogo"></span>
							</a>
						</div>
						<div class="fix-header-nav">
							тут навигация 
						</div>
						<div class="fix-header-btn">
							<div class="fix-header-users">
								
								@php
								$user = Auth::user();
								$isAuth = auth()->check();
								@endphp
								<div class="fix-header-user">
									@if($isAuth && !empty($user->first_name) && !empty($user->last_name))
									{{ $user->first_name }}<br><span class="fix-header-user-fio">{{ $user->last_name }}</span>
									@elseif($isAuth)
									Пользователь<br><span class="fix-header-user-fio">#{{ $user->id }}</span>
									@else
									Вход
									@endif
								</div>
								
								<div class="fix-header-btn-user">
									@if($isAuth && $user->profile_photo_url)
									<span class="user-avatar-small">
										<img src="{{ $user->profile_photo_url }}" alt="avatar">
									</span>
									@elseif($isAuth)
									<span class="user-avatar-small">
										<img src="/img/no-avatar.png" alt="avatar">
									</span>
									@else
									<span class="icon-login"></span>
									@endif
								</div>
							</div>	
							<div class="fix-header-btn-hamm">
								<span class="icon-hamm"></span>
							</div>
							<div class="fix-header-btn-theme">
								<span class="icon-theme">
									<svg class="theme-icon sun"  xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 160c-52.9 0-96 43.1-96 96s43.1 96 96 96 96-43.1 96-96-43.1-96-96-96zm246.4 80.5l-94.7-47.3 33.5-100.4c4.5-13.6-8.4-26.5-21.9-21.9l-100.4 33.5-47.4-94.8c-6.4-12.8-24.6-12.8-31 0l-47.3 94.7L92.7 70.8c-13.6-4.5-26.5 8.4-21.9 21.9l33.5 100.4-94.7 47.4c-12.8 6.4-12.8 24.6 0 31l94.7 47.3-33.5 100.5c-4.5 13.6 8.4 26.5 21.9 21.9l100.4-33.5 47.3 94.7c6.4 12.8 24.6 12.8 31 0l47.3-94.7 100.4 33.5c13.6 4.5 26.5-8.4 21.9-21.9l-33.5-100.4 94.7-47.3c13-6.5 13-24.7.2-31.1zm-155.9 106c-49.9 49.9-131.1 49.9-181 0-49.9-49.9-49.9-131.1 0-181 49.9-49.9 131.1-49.9 181 0 49.9 49.9 49.9 131.1 0 181z"/></svg>	
									<svg class="theme-icon moon" viewBox="0 0 24 24">
										<path d="M12,3c-4.97,0-9,4.03-9,9s4.03,9,9,9s9-4.03,9-9c0-0.46-0.04-0.92-0.1-1.36c-0.98,1.37-2.58,2.26-4.4,2.26 c-2.98,0-5.4-2.42-5.4-5.4c0-1.81,0.89-3.42,2.26-4.4C12.92,3.04,12.46,3,12,3L12,3z"/>
									</svg>
								</span>
							</div>							
						</div>		
					</div>			
					<div class="fix-header-menu fix-header-menu-1">
						
					</div>		
					<div class="fix-header-menu fix-header-menu-2">
						@auth
						<div class="menu-user-login">
							<!-- Колонка 1: Аватар и имя -->
							<div class="menu-column column-avatar">
								<div class="avatar-container">
									<div class="avatar">
										@if($isAuth && $user->profile_photo_url)
										<img src="{{ $user->profile_photo_url }}" alt="avatar">
										@else
										<img src="/img/no-avatar.png" alt="avatar">
										@endif
									</div>
									<div class="user-info">
										<div class="user-name">
											@if($isAuth && !empty($user->first_name) && !empty($user->last_name))
											{{ $user->first_name }} {{ $user->last_name }}
											@elseif($isAuth)
											Пользователь #{{ $user->id }} 
											@else
											Error
											@endif									
										</div>
										
										@php
										$role = auth()->user()->role;
										$roleText = '';
										$roleClass = '';
										
										if($role == 'admin') {
										$roleText = 'Администратор';
										$roleClass = 'role-admin';
										} elseif($role == 'organizer') {
										$roleText = 'Организатор';
										$roleClass = 'role-organizer';
										} else {
										$roleText = 'Пользователь';
										$roleClass = 'role-user';
										}
										@endphp
										
										<div class="user-role {{ $roleClass }}">
											<span class="role-badge">{{ $roleText }}</span>
										</div>
										
									</div>
								</div>
							</div>
							<!-- Колонка 2: Основное меню -->
							<div class="menu-column column-main">
								<nav class="menu-nav">
									<a href="/user/profile" class="menu-item">
										<span class="menu-text">Профиль</span>
									</a>
									<a href="/profile/complete" class="menu-item">
										<span class="menu-text">Редактировать профиль</span>
									</a>
									<a href="/user/edit" class="menu-item">
										<span class="menu-text">Фотографии</span>
									</a>								
									<a href="/dashboard" class="menu-item">
										<span class="menu-text">Дашборд</span>
									</a>
									<a href="/settings" class="menu-item">
										<span class="menu-text">Настройки</span>
									</a>
									<form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
										@csrf
										<button type="submit" class="menu-item">Выйти</button>
									</form>
								</nav>
							</div>
							<!-- Колонка 3: Дополнительное меню -->
							@if(in_array(auth()->user()->role, ['organizer', 'admin']))
							<div class="menu-column column-secondary">
								<nav class="menu-nav">
									<div class="menu-item-title">
										<span class="menu-text">Меню организатора</span>
									</div>							
									<a href="/help" class="menu-item">
										<span class="menu-text">Ваши меропрития</span>
									</a>
									<a href="/docs" class="menu-item">
										<span class="menu-text">Добавить меропритяие</span>
									</a>
									<a href="/feedback" class="menu-item">
										<span class="menu-text">Обратная связь</span>
									</a>
								</nav>
								@if(auth()->user()->role == 'admin')
								<nav class="menu-nav">
									<div class="menu-item-title">
										<span class="menu-text">Меню администратора</span>
									</div>							
									<a href="/help" class="menu-item">
										<span class="menu-text">Админка</span>
									</a>
									<a href="/docs" class="menu-item">
										<span class="menu-text">Список юзерей</span>
									</a>
									<a href="/feedback" class="menu-item">
										<span class="menu-text">Статистика</span>
									</a>
								</nav>							
								@endif
							</div>
							@endif
						</div>
						@else
						@php
						$returnUrl = url()->full(); // страница, где нажали кнопку
						@endphp
						
						<div class="social-auth">
							<!-- Кнопка VK -->
							<div data-href="{{ route('auth.vk.redirect', ['return' => $returnUrl]) }}" class="auth-btn auth-btn-vk">
								<span class="auth-icon-circle">
									<span class="icon-vk"></span>
								</span>
								<span class="auth-text">Войти через ВКонтакте</span>
							</div>
							
							<!-- Кнопка Яндекс -->
							<div data-href="{{ route('auth.yandex.redirect', ['return' => $returnUrl]) }}" class="auth-btn auth-btn-yandex">
								<span class="auth-icon-circle">
									<span class="icon-yandex"></span>
								</span>
								<span class="auth-text">Войти с Яндекс ID</span>
							</div>
							
							<div class="auth-btn auth-btn-telegram">
								<div class="auth-btn-telegram-widget">						
									<script
									src="https://telegram.org/js/telegram-widget.js?22"
									data-telegram-login="VolleyEvent_bot"
									data-size="large"
									data-userpic="false"
									data-radius="6"
									data-request-access="write"
									data-auth-url="{{ route('auth.telegram.callback', ['return' => url()->full()]) }}"
									data-lang="ru">
									</script>								
								</div>			
								<div class="auth-btn-telegram-widget-up">					
									<span class="auth-icon-circle">
										<span class="icon-tg"></span>
									</span>
									<span class="auth-text">Войти через Telegram</span>
								</div>				
							</div>
						</div>
						@endauth
					</div>
					<div class="fix-header-menu fix-header-menu-3">
						<div class="menu-site">
							<!-- Колонка 1:  -->
							<div class="menu-column column-first">
								<nav class="menu-nav">							
									<a href="/user/profile" class="menu-item">
										<span class="menu-text">Игры и тренировки</span>
									</a>
									<a href="/user/edit" class="menu-item">
										<span class="menu-text">Клубы и Тренировки</span>
									</a>
									<a href="/user/edit" class="menu-item">
										<span class="menu-text">Правила сервиса</span>
									</a>								
									<a href="/dashboard" class="menu-item">
										<span class="menu-text">Инструкция</span>
									</a>
									<a href="/settings" class="menu-item">
										<span class="menu-text">Уровни игроков</span>
									</a>	
									<a href="/settings" class="menu-item">
										<span class="menu-text">О сервисе</span>
									</a>									
								</nav>
							</div>
							<!-- Колонка 2: -->
							<div class="menu-column column-two">
								<nav class="menu-nav">
									<a href="/user/profile" class="menu-item">
										<span class="menu-text">Игроки</span>
									</a>
									<a href="/user/edit" class="menu-item">
										<span class="menu-text">Новости</span>
									</a>
									<a href="/user/edit" class="menu-item">
										<span class="menu-text">Группы</span>
									</a>															
								</nav>
							</div>
						</div>
					</div>
				</div>		
			</div>
		</header>
		<main>	 	
			<section class="top-section">
				<div class="container">
					@if(isset($h1))
					<h1 class="anima">{{ $h1 }}</h1>
					@endif
					@if(isset($h2))
					<h2 class="anima">{{ $h2 }}</h2>
					@endif
					@if(isset($t_description))
					<div data-aos="fade-up" class="title-description">{{ $t_description }}</div>
					@endif				
					
					@if(isset($breadcrumbs))
					<ul itemscope="" itemtype="http://schema.org/BreadcrumbList" class="breadcrumbs">
						<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
							<a href="/" itemprop="item"><span itemprop="name">Главная</span></a>
							<meta itemprop="position" content="1">
						</li>
						{{ $breadcrumbs }}
					</ul>
					@endif		
				</div>
				<div class="top-section-line">
					
					<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 2156 2205" version="1.1">
						<g class="line-fill" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
							<polygon id="Fill-1" points="983.359126 0 1532.43714 548.82014 979 1102 1293.56551 1102 1847 548.822782 1297.91935 0"></polygon>
							<polygon id="Fill-6" points="553.437136 547 0 1100.17458 549.080654 1649 863.640874 1649 314.562864 1100.17722 868 547"></polygon>
							<polygon id="Fill-7" points="1352.64087 1649 803.562864 1100.17986 1357 547 1042.43449 547 489 1100.17722 1038.08065 1649"></polygon>
							<polygon id="Fill-9" points="1292.35911 1103 1841.43545 1651.82014 1288 2205 1602.56191 2205 2156 1651.82278 1606.91837 1103"></polygon>
						</g>
					</svg>	
					
				</div>
			</section>	
			
			{{-- Sidebar --}}
			@if(isset($sidebar))
			<div class="sidebar-container">	
				<div class="main-content">
					{{ $slot }}
				</div>
				<div class="sidebar">
					{{ $sidebar }}
				</div>
			</div>
			@else
			{{ $slot }}	
			@endif			
			
			
			
		</main>
		
		<footer>
			<div class="footer">
				<div class="container">
					<div class="row">
						<div class="col-3">
							<a href="/"><span class="icon-logo"></span></a>
						</div>
						<div class="col-3">
							1
						</div>
						<div class="col-3">
							2
						</div>				
					</div>	
				</div>	
			</div>		
		</footer>
		
		<svg style="display: none">
			<filter
			id="glass-distortion"
			x="0%"
			y="0%"
			width="100%"
			height="100%"
			filterUnits="objectBoundingBox"
			>
				<feTurbulence
				type="fractalNoise"
				baseFrequency="0.015 0.015"
				numOctaves="1"
				seed="5"
				result="turbulence"
				/>
				<!-- Seeds: 14, 17,  -->
				
				<feComponentTransfer in="turbulence" result="mapped">
					<feFuncR type="gamma" amplitude="1" exponent="10" offset="0.5" />
					<feFuncG type="gamma" amplitude="0" exponent="1" offset="0" />
					<feFuncB type="gamma" amplitude="0" exponent="1" offset="0.5" />
				</feComponentTransfer>
				
				<feGaussianBlur in="turbulence" stdDeviation="2" result="softMap" />
				
				<feSpecularLighting
				in="softMap"
				surfaceScale="5"
				specularConstant="1"
				specularExponent="100"
				lighting-color="white"
				result="specLight"
				>
					<fePointLight x="-200" y="-200" z="300" />
				</feSpecularLighting>
				<feComposite
				in="specLight"
				operator="arithmetic"
				k1="0"
				k2="1"
				k3="1"
				k4="0"
				result="litImage"
				/>
				<feDisplacementMap
				in="SourceGraphic"
				in2="softMap"
				scale="60"
				xChannelSelector="R"
				yChannelSelector="G"
				/>
			</filter>
		</svg> 		
		<script src="/assets/script.js"></script>     
		@livewireScripts
	</body>
</html>					