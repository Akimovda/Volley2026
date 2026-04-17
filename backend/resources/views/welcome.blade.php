{{-- resources/views/welcome.blade.php --}}
<x-voll-layout body_class="main-page">
	
    <x-slot name="title">Your Volley Club — волейбольный сервис</x-slot>
    <x-slot name="description">Платформа для волейболистов, тренеров, организаторов и спортивных центров. Записывайтесь на игры, находите партнёров, управляйте мероприятиями.</x-slot>
    <x-slot name="canonical">{{ url('/') }}</x-slot>
    <x-slot name="h1">Your Volley Club</x-slot>
    <x-slot name="h2">Волейбольный сервис</x-slot>
    <x-slot name="t_description">Объединяем волейбольное сообщество — от любителей до профессионалов</x-slot>
	
    <x-slot name="d_description">
        <div class="d-flex flex-wrap gap-1 m-center">
            @guest			
			<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
				<a href="{{ route('register') }}" class="btn">Начать бесплатно</a>
			</div>
			<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
				<a href="{{ route('events.index') }}" class="btn btn-secondary">Смотреть игры</a>
			</div>			
            @else
			<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
				<a href="{{ route('events.index') }}" class="btn">Найти игру</a>
			</div>
			<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
			{{--	
				<a href="{{ route('dashboard') }}" class="btn btn-secondary">Мой профиль</a>
			--}}	
			</div>					
            @endguest
		</div>
	</x-slot>
	
	
	<x-slot name="script">
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Функция для анимации цифр
				function animateNumber(element, targetNumber) {
					let currentNumber = 0;
					const duration = 2000; // Длительность анимации в мс
					const stepTime = 20; // Шаг обновления в мс
					const steps = duration / stepTime;
					const increment = targetNumber / steps;
					
					const timer = setInterval(() => {
						currentNumber += increment;
						if (currentNumber >= targetNumber) {
							currentNumber = targetNumber;
							clearInterval(timer);
						}
						// Форматируем число с пробелами для тысяч
						element.textContent = Math.floor(currentNumber).toLocaleString('ru-RU');
					}, stepTime);
				}
				
				// Получаем все элементы с цифрами
				const statNumbers = document.querySelectorAll('.stat-number.cd');
				
				// Сохраняем целевые значения и флаги анимации
				const animations = [];
				statNumbers.forEach((element, index) => {
					const targetText = element.textContent;
					const targetNumber = parseInt(targetText.replace(/\s/g, ''), 10);
					animations.push({
						element: element,
						targetNumber: targetNumber,
						animated: false,
						originalText: targetText
					});
					// Устанавливаем начальное значение 0
					element.textContent = '0';
				});
				
				// Функция проверки видимости элемента
				function isElementInViewport(el) {
					const rect = el.getBoundingClientRect();
					const windowHeight = window.innerHeight || document.documentElement.clientHeight;
					const windowWidth = window.innerWidth || document.documentElement.clientWidth;
					
					// Проверяем, виден ли элемент хотя бы частично
					const vertInView = (rect.top <= windowHeight - 10) && (rect.bottom >= 10);
					const horInView = (rect.left <= windowWidth) && (rect.right >= 0);
					
					return (vertInView && horInView);
				}
				
				// Функция проверки и запуска анимаций
				function checkAndAnimate() {
					animations.forEach(animation => {
						if (!animation.animated) {
							// Находим родительскую рамку .ramka
							const ramkaElement = animation.element.closest('.ramka');
							if (ramkaElement && isElementInViewport(ramkaElement)) {
								animation.animated = true;
								animateNumber(animation.element, animation.targetNumber);
							}
						}
					});
				}
				
				// Запускаем проверку при загрузке
				setTimeout(checkAndAnimate, 100);
				
				// Запускаем проверку при скролле
				window.addEventListener('scroll', checkAndAnimate);
				
				// Запускаем проверку при ресайзе окна
				window.addEventListener('resize', checkAndAnimate);
			});
		</script>		
	</x-slot>	
	
	
	<x-slot name="style">
        <style>
            .home-section { margin-bottom: 0; }
			
            /* Цифры-достижения */
            .stat-number {
			font-size: 4.8rem;
			font-weight: 800;
			line-height: 1;
			will-change: transform;
            }
            .stat-label {
			text-transform: uppercase;
			margin-top: 1rem;
			font-weight: 600;
			font-size: 1.5rem;
			will-change: transform;
            }
			.numbercard .ramka {
			height: calc(100% - 2rem);
			}
			.audience-card {
			justify-content: space-between;
			display: flex;
			flex-flow: column;
			}
			.audience-top {
			display: flex;
			margin-bottom: 1rem; 
			justify-content: space-between;
			}
            .audience-icon { 
			flex: 0 0 6rem;
			line-height: 1; 
			height: 6rem;
			width: 6rem;
			font-size: 5rem; 
			text-align: right;			
			}
			
			
            /* Таб-переключатель аудиторий */
            .audience-tabs .tab { font-size: 1.6rem; }
			
            /* Как это работает */
            .step-num {
			width: 4.8rem;
			height: 4.8rem;
			border-radius: 50%;
			background: var(--cd);
			color: #fff;
			font-size: 2rem;
			font-weight: 700;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
            }
			
            /* Блок CTA */
            .cta-block {
			border-radius: 2rem;
			padding: 4rem 3rem;
			text-align: center;
            }
            .cta-block .f-32 { font-size: 3.2rem; font-weight: 700; }
			
            @media (max-width: 768px) {
			.stat-number { font-size: 3.6rem; }
			.cta-block { padding: 3rem 1.6rem; }
            }
		</style>
	</x-slot>	
	
    <div class="container">
        {{-- ===== ЦИФРЫ ===== --}}
		
		<div class="row row2 text-center numbercard">
			@php
			$usersCount     = \App\Models\User::count();
			$eventsCount    = \DB::table('events')->count();
			$locationsCount = \DB::table('locations')->whereNull('organizer_id')->count();
			$citiesCount    = \DB::table('locations')->whereNull('organizer_id')->distinct('city_id')->count('city_id');
			@endphp
			<div class="col-6 col-md-3">
				<div class="ramka" data-aos="fade-up" data-aos-delay="0">
					<div class="stat-number cd">{{ number_format($usersCount) }}</div>
					<div class="stat-label">игроков в сообществе</div>
				</div>
			</div>
			<div class="col-6 col-md-3">
				<div class="ramka" data-aos="fade-up" data-aos-delay="100">
					<div class="stat-number cd">{{ number_format($eventsCount) }}</div>
					<div class="stat-label">мероприятий создано</div>
				</div>
			</div>
			<div class="col-6 col-md-3">
				<div class="ramka" data-aos="fade-up" data-aos-delay="200">
					<div class="stat-number cd">{{ number_format($locationsCount) }}</div>
					<div class="stat-label">площадок и кортов</div>
				</div>
			</div>
			<div class="col-6 col-md-3">
				<div class="ramka" data-aos="fade-up" data-aos-delay="300">
					<div class="stat-number cd">{{ number_format($citiesCount) }}</div>
					<div class="stat-label">городов</div>
				</div>
			</div>
		</div>
		
		
        {{-- ===== ДЛЯ КОГО ===== --}}
        <div class="ramka">
            <h2 class="-mt-05">Для кого это создано</h2>
			
            <div class="tabs-content audience-tabs">
                <div class="tabs">
                    <div class="tab active" data-tab="tab-players">Игрокам</div>
                    <div class="tab" data-tab="tab-trainers">Тренерам</div>
                    <div class="tab" data-tab="tab-organizers">Организаторам</div>
                    <div class="tab" data-tab="tab-centers">Спортцентрам</div>
                    <div class="tab-highlight"></div>
				</div>
				
                <div class="tab-panes">
					
                    {{-- ИГРОКИ --}}
                    <div class="tab-pane active" id="tab-players">
                        <div class="row row2">
                            <div class="col-md-6">
                                <div class="card audience-card">
									<div>
										<div class="audience-top">
											<div class="audience-title">
												<div class="f-20 b-600 cd">Для игроков</div>
												Играйте больше, находите партнёров, развивайтесь.
											</div>
											<div class="audience-icon">
												🏐										
											</div>											
										</div>	
										
										<ul class="list">
											<li>Находите игры и тренировки рядом с домом</li>
											<li>Записывайтесь онлайн в один клик</li>
											<li>Классика и пляжный волейбол — любой формат</li>
											<li>Оценивайте уровень других игроков</li>
											<li>Отмечайте тех, с кем приятно играть ❤️</li>
											<li>Профиль с вашим уровнем, амплуа и статистикой</li>
											<li>Получайте уведомления в Telegram, VK и MAX</li>
											<li>Резервный список — займёте место, если кто-то отменит</li>
										</ul>
									</div>
                                    <div class="mt-2">
                                        <a href="{{ route('events.index') }}" class="btn">Найти игру</a>
									</div>
								</div>
							</div>
                            <div class="col-md-6">
                                <div class="card audience-card">
									<div>
										<div class="audience-top">
											<div class="audience-title">
												<div class="f-20 b-600 cd">Найдите своих</div>
												Сообщество игроков вашего уровня.
											</div>
											<div class="audience-icon">
												👪
											</div>											
										</div>
										<ul class="list">
											<li>Каталог игроков с уровнем и амплуа</li>
											<li>Фильтр по городу, уровню, направлению</li>
											<li>Пляжные пары и командная запись</li>
											<li>Приглашайте партнёров на мероприятия</li>
											<li>Авторизация через Telegram, VK или Яндекс</li>
										</ul>
									</div>
                                    <div class="mt-2">
                                        <a href="{{ route('users.index') }}" class="btn">Каталог игроков</a>
									</div>
								</div>
							</div>
						</div>
					</div>
					
                    {{-- ТРЕНЕРЫ --}}
                    <div class="tab-pane" id="tab-trainers">
                        <div class="row row2 mt-2">
                            <div class="col-md-6">
                                <div class="card audience-card">
                                    <div class="audience-icon">🎓</div>
                                    <div class="audience-title">Для тренеров</div>
                                    <div class="f-17 mb-2">Организуйте тренировки и развивайте учеников.</div>
                                    <ul class="list">
                                        <li>Создавайте тренировки и тренировки+игра</li>
                                        <li>Формат «Тренер + ученик» (пляж)</li>
                                        <li>Повторяющиеся занятия по расписанию</li>
                                        <li>Управление списком участников</li>
                                        <li>Автоматические напоминания ученикам</li>
                                        <li>Анонсы тренировок в Telegram и VK каналы</li>
                                        <li>Ваш профиль тренера виден всем игрокам</li>
                                        <li>Создайте страницу своей школы волейбола</li>
                                        <li>Првайте абонементы и ведите их учет</li>
									</ul>
                                    <div class="mt-2">
                                        <a href="{{ route('volleyball_school.index') }}" class="btn">Школы волейбола</a>
									</div>
								</div>
							</div>
                            <div class="col-md-6">
                                <div class="card audience-card">
                                    <div class="audience-icon">📋</div>
                                    <div class="audience-title">Станьте организатором</div>
                                    <div class="f-17 mb-2" style="opacity:.7">Хотите проводить тренировки через сервис?</div>
                                    <ul class="list">
                                        <li>Подайте заявку на статус организатора</li>
                                        <li>Бесплатный доступ к инструментам</li>
                                        <li>Ваши мероприятия — в общем каталоге</li>
                                        <li>Поддержка команды платформы</li>
									</ul>
                                    <div class="mt-2">
                                        @guest
										<a href="{{ route('register') }}" class="btn btn-secondary">Зарегистрироваться</a>
                                        @else
										<a href="{{ route('profile.complete') }}" class="btn btn-secondary">Мой профиль</a>
                                        @endguest
									</div>
								</div>
							</div>
						</div>
					</div>
					
					{{-- ОРГАНИЗАТОРЫ --}}
					<div class="tab-pane" id="tab-organizers">
						<div class="row row2 mt-2">
							<div class="col-md-6">
								<div class="card audience-card">
									<div class="audience-icon">📣</div>
									<div class="audience-title">Для организаторов</div>
									<div class="f-17 mb-2" style="opacity:.7">Проводите игры, турниры и лиги.</div>
									<ul class="list">
										<li>Создавайте мероприятия любого формата</li>
										<li>Игры, тренировки, турниры, кемпы</li>
										<li>Классика и пляжный волейбол</li>
										<li>Повторяющиеся мероприятия по расписанию</li>
										<li>Гендерные ограничения и уровни допуска</li>
										<li>Управление списком участников вручную</li>
										<li>Автоотмена при нехватке кворума</li>
										<li>Подключите своих ботов - скоро!</li>
										<li>Помощник записи - платформа поможет наполнить мероприятие</li>
									</ul>
									<div class="mt-2">
										<a href="{{ route('events.create.event_management') }}" class="btn">Управление играми</a>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="card audience-card">
									<div class="audience-icon">📢</div>
									<div class="audience-title">Анонсы и уведомления</div>
									<div class="f-17 mb-2" style="opacity:.7">Держите игроков в курсе автоматически.</div>
									<ul class="list">
										<li>Анонсы в Telegram и VK каналы</li>
										<li>Уведомления о записи и отмене</li>
										<li>Напоминания за N часов до начала</li>
										<li>Личные уведомления каждому игроку</li>
										<li>Настраиваемые шаблоны сообщений</li>
										<li>Приватные мероприятия по ссылке</li>
									</ul>
									<div class="mt-2">
										<a href="{{ route('events.create') }}" class="btn btn-secondary">Создать мероприятие</a>
									</div>
								</div>
							</div>
						</div>
					</div>
					
					{{-- СПОРТЦЕНТРЫ --}}
					<div class="tab-pane" id="tab-centers">
						<div class="row row2 mt-2">
							<div class="col-md-6">
								<div class="card audience-card">
									<div class="audience-icon">🏟️</div>
									<div class="audience-title">Для спортивных центров</div>
									<div class="f-17 mb-2" style="opacity:.7">Привлекайте игроков и наполняйте залы.</div>
									<ul class="list">
										<li>Страница вашей локации с фото и картой</li>
										<li>Игроки находят вас через каталог площадок</li>
										<li>Все мероприятия на вашей площадке — в одном месте</li>
										<li>Фильтр «Только с активными играми»</li>
										<li>Карта локаций для удобного поиска</li>
										<li>Страница школы/сообщества для вашего бренда</li>
									</ul>
									<div class="mt-2">
										<a href="{{ route('locations.index') }}" class="btn">Каталог локаций</a>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="card audience-card">
									<div class="audience-icon">🏫</div>
									<div class="audience-title">Школа волейбола</div>
									<div class="f-17 mb-2" style="opacity:.7">Создайте публичную страницу вашей школы.</div>
									<ul class="list">
										<li>Логотип, обложка, описание, контакты</li>
										<li>Все ваши мероприятия на одной странице</li>
										<li>Профиль организатора / тренера</li>
										<li>Ваш бренд в каталоге школ волейбола</li>
										<li>Классика и пляжный — любое направление</li>
										<li>Абонементы и купоны</li>
									</ul>
									<div class="mt-2">
										<a href="{{ route('volleyball_school.index') }}" class="btn btn-secondary">Школы волейбола</a>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		
		{{-- ===== КАК ЭТО РАБОТАЕТ ===== --}}
		<div class="ramka">
			<h2 class="-mt-05">Как это работает</h2>
			
  <style>
.steps ul {
	list-style: none;
	margin: 2rem 0 0 0;
	padding: 0;	
	position: relative;
	width: 100%;
	display: flex;
	filter: drop-shadow(10px 10px 24px rgba(0, 0, 0, 0.1)); 
	border-radius: 1rem;
	overflow: hidden;
}
.steps ul li {	
	top: 0;
	position: absolute;
	width: calc(25% + 4rem);
	height: 100%;
	left: calc(75% - 2rem);
	background: #fff;
	clip-path: polygon(calc(100% - 6rem) 0%, 100% 50%, calc(100% - 6rem) 100%, 0% 100%, 6rem 50%, 0% 0%);
	padding: 2rem 5rem 2rem 8rem;
	transition: background 0.3s, color 0.1s;
	will-change: transform;
}

.steps ul li:hover,
.steps ul li.active {	
	background: #2967BA;
	color: #fff;
}
body.dark .steps ul li {	
	background: #222333;
}
body.dark .steps ul li:hover,
body.dark .steps ul li.active {	
	background: #E7612F;
}
.steps ul li:first-child {	
	left: 0%;
	width: calc(25% + 2rem);
	clip-path: polygon(calc(100% - 6rem) 0%,100% 50%,calc(100% - 6rem) 100%,0% 100%,0% 0%);
	padding: 2rem 5rem 2rem 2rem;
}
.steps ul li:nth-child(2) {	
	left: calc(25% - 2rem);
}	
.steps ul li:nth-child(3) {	
	left: calc(50% - 2rem);
	position: relative;
}	
.steps ul li:last-child {	
	left:  calc(75% - 2rem);
	width: calc(25% + 2rem);
	clip-path: polygon(100% 0%,100% 100%,0% 100%, 6rem 50%,0% 0%);
	padding: 2rem 2rem 2rem 8rem;
}	
/* стили текста */
.step-title {
	font-size: 2rem;
	font-weight: 600;
	margin-bottom: 0.4rem;
	line-height: 1.2;
}
.step-desc {
	font-size: 1.6rem;
	opacity: 0.8;
}
.steps ul li:hover .step-desc,
.steps ul li.active .step-desc {
	opacity: 1;
}

.desc-top {
	display: flex;
	margin-bottom: 1.2rem;
	align-items: center;
}
/* ===== SVG ЦИФРЫ С АНИМАЦИЕЙ ===== */
.step-number {
	flex: 0 0 6.2rem;
}
.step-number svg {
	width: 5rem;
	height: 5rem;
}
.step-number svg circle {
	stroke: #2967BA;
	stroke-width: 3;
	fill: none;
	stroke-dasharray: 180;
	stroke-dashoffset: 180;
	transition: stroke-dashoffset 0.6s ease-out, stroke 0.2s;
}
.step-number svg text {
	fill: #2967BA;
	font-size: 22px;
	font-weight: 700;
	font-family: 'Inter', monospace;
	dominant-baseline: middle;
	text-anchor: middle;
	transition: fill 0.2s;
	transform: scale(1.4);
	transform-origin: center;
	transition: transform 0.2s;
}
.steps ul li:hover .step-number svg text,
.steps ul li.active .step-number svg text {	
	transform: scale(1);
}
body.dark .step-number svg circle {
	stroke: #E7612F;
}
body.dark .step-number svg text {
	fill: #E7612F;
}


/* при активации или ховере — рисуем круг */
.steps ul li.active .step-number svg circle,
.steps ul li:hover .step-number svg circle {
	stroke-dashoffset: 0;
}
.steps ul li.active .step-number svg text,
.steps ul li:hover .step-number svg text {
	fill: #ffffff;
}
.steps ul li.active .step-number svg circle,
.steps ul li:hover .step-number svg circle {
	stroke: #ffffff;
}	
@media screen and (max-width: 1199px) {
      .steps ul {
       flex-wrap: wrap;
      }
	.steps ul li {
	position: static!important;
	width: 100%!important;
		padding: 4rem 2rem 5rem 2rem;
		margin-top: -1rem;
	}
	.steps ul li:first-child {
        clip-path: polygon(50% 0%, 100% 0%, 100% calc(100% - 3rem), 50% 100%, 0% calc(100% - 3rem), 0% 0%);
		padding: 2rem 2rem 5rem 2rem;
		margin-top: 0;
	}
	.steps ul li:nth-child(2),
	.steps ul li:nth-child(3) {
		clip-path: polygon(0% calc(100% - 3rem), 50% 100%, 100% calc(100% - 3rem), 100% 0%, 50% 3rem, 0% 0%);
	}	  
	.steps ul li:last-child {
       clip-path: polygon(0% 100%, 100% 100%, 100% 0%, 50% 3rem, 0% 0%);
        margin-bottom: 0;
		padding: 4rem 2rem 2rem 2rem;
	}
}	
	
  </style>			
	
  <div class="steps">
    <ul>
      <li class="active" data-aos="fade-right" data-aos-delay="0">
	  <div class="desc-top">
            <div class="step-number"><svg viewBox="0 0 60 60">
          <circle cx="30" cy="30" r="26" stroke="currentColor" fill="none"/>
          <text x="30" y="32" fill="currentColor" font-size="22" font-weight="700" text-anchor="middle" dominant-baseline="middle">01</text>
        </svg></div>
            <div class="step-title">Регистрация <span class="d-inline-block">без пароля</span></div>
		</div>	
            <div class="step-desc">Войдите через Telegram, VK или Яндекс. Заполните профиль — укажите уровень, амплуа и город.</div>
      </li>
      <li data-aos="fade-right" data-aos-delay="100">
	  <div class="desc-top">
            <div class="step-number"><svg viewBox="0 0 60 60">
          <circle cx="30" cy="30" r="26" stroke="currentColor" fill="none"/>
          <text x="30" y="32" fill="currentColor" font-size="22" font-weight="700" text-anchor="middle" dominant-baseline="middle">02</text>
        </svg></div>
            <div class="step-title">Найдите игру</div>
			</div>
            <div class="step-desc">Откройте каталог мероприятий. Фильтруйте по городу, уровню, дате и формату — классика или пляж.</div>
			
      </li>
      <li data-aos="fade-right" data-aos-delay="200">
	  <div class="desc-top">
            <div class="step-number"><svg viewBox="0 0 60 60">
          <circle cx="30" cy="30" r="26" stroke="currentColor" fill="none"/>
          <text x="30" y="32" fill="currentColor" font-size="22" font-weight="700" text-anchor="middle" dominant-baseline="middle">03</text>
        </svg></div>
            <div class="step-title">Запишитесь</div>
			</div>
            <div class="step-desc">Выберите позицию и нажмите «Записаться». Получите подтверждение и напоминание в мессенджер.</div>
      </li>
      <li data-aos="fade-right" data-aos-delay="300">
	  <div class="desc-top">
          <div class="step-number"><svg viewBox="0 0 60 60">
          <circle cx="30" cy="30" r="26" stroke="currentColor" fill="none"/>
          <text x="30" y="32" fill="currentColor" font-size="22" font-weight="700" text-anchor="middle" dominant-baseline="middle">04</text>
        </svg></div>
            <div class="step-title">Играйте!</div>
			</div>
            <div class="step-desc">Приходите на игру, знакомьтесь с новыми партнёрами и оценивайте уровень друг друга.</div>
      </li>
    </ul>
  </div>
<script>
document.addEventListener("DOMContentLoaded", () => {
	let siteInterval,
	count = 0;
	function Inter() {
		if(count===4){count=0}
		$('.steps ul li').removeClass('active');
		$('.steps ul li:eq('+count+')').addClass('active');
		count++	
	}
	Inter();
	siteInterval = setInterval(Inter, 4000);		
	$('.steps ul li').mouseenter(function(e) {
		clearInterval(siteInterval);
		$('.steps ul li').removeClass('active aos-init aos-animate');
		$('.steps ul li').removeAttr('data-aos data-aos-delay');
		$(this).addClass('active');
		
				
	});
	$('.steps ul li').mouseleave(function(e) {
		count = $(this).index('.steps ul li');
		count = count+1;
		//Inter();
		siteInterval = setInterval(Inter, 4000);		
	});	
});		



</script>	
			
{{--

			
			
			
			
<div class="hw-steps-wrap">
<style>
.hw-steps-wrap{padding:1rem 0 2rem}
.hw-arrows{display:flex;width:100%;margin-bottom:2rem}
.hw-arrow{flex:1;position:relative;height:96px;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:default;z-index:1;transition:z-index 0s}
.hw-arrow:hover{z-index:10}
.hw-arrow svg{position:absolute;inset:0;width:100%;height:100%;overflow:visible;transition:filter .25s ease}
.hw-arrow:hover svg{filter:brightness(1.12)}
.hw-poly-main{transition:transform .25s ease;transform-origin:center center}
.hw-arrow:hover .hw-poly-main{transform:scaleY(1.06)}
.hw-inner{position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:5px;padding-left:14px;transition:transform .25s ease}
.hw-arrow:first-child .hw-inner{padding-left:4px}
.hw-arrow:hover .hw-inner{transform:scale(1.05)}
.hw-num{font-size:30px;font-weight:500;color:#fff;line-height:1;text-shadow:0 2px 8px rgba(0,0,0,.35)}
.hw-sep{width:30px;height:1.5px;background:rgba(255,255,255,.5);border-radius:1px}
.hw-lbl{font-size:10px;font-weight:500;letter-spacing:.13em;color:rgba(255,255,255,.85);text-transform:uppercase}
.hw-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem}
.hw-card-title{font-size:14px;font-weight:600;margin-bottom:.4rem;line-height:1.3}
.hw-card-desc{font-size:13px;opacity:.7;line-height:1.65}
.hwt1{color:#C06010}.hwt2{color:#A02820}.hwt3{color:#5A2580}.hwt4{color:#1A8045}
@media(max-width:767px){
.hw-arrows{flex-direction:column;gap:6px}
.hw-arrow{height:auto;min-height:64px;border-radius:12px;overflow:hidden;margin-left:0!important;z-index:1!important}
.hw-arrow svg{display:none}
.hw-arrow::before{content:'';position:absolute;inset:0;border-radius:12px}
.hw-arrow:nth-child(1)::before{background:linear-gradient(135deg,#F5A050,#C06010)}
.hw-arrow:nth-child(2)::before{background:linear-gradient(135deg,#E05555,#8C1A14)}
.hw-arrow:nth-child(3)::before{background:linear-gradient(135deg,#A560CC,#4A1568)}
.hw-arrow:nth-child(4)::before{background:linear-gradient(135deg,#50CC78,#106830)}
.hw-inner{padding:0 1.25rem;flex-direction:row;gap:14px;justify-content:flex-start;align-items:center;width:100%}
.hw-num{font-size:28px;min-width:36px}
.hw-sep{display:none}
.hw-lbl{display:none}
.hw-mobile-text{display:block}
.hw-cards{display:none}
}
@media(min-width:768px){
.hw-mobile-text{display:none}
}
</style>

<div class="hw-arrows">
  <div class="hw-arrow">
    <svg viewBox="0 0 300 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="hwg1" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#F5A050"/><stop offset="50%" stop-color="#E8811C"/><stop offset="100%" stop-color="#A85408"/></linearGradient>
        <linearGradient id="hwgi1" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="rgba(0,0,0,0.18)"/><stop offset="40%" stop-color="rgba(0,0,0,0)"/><stop offset="100%" stop-color="rgba(0,0,0,0.22)"/></linearGradient>
        <filter id="hwf1"><feDropShadow dx="7" dy="0" stdDeviation="5" flood-color="rgba(0,0,0,.5)"/></filter>
      </defs>
      <polygon class="hw-poly-main" points="0,0 270,0 300,50 270,100 0,100" fill="url(#hwg1)" filter="url(#hwf1)"/>
      <polygon points="0,0 270,0 300,50 270,100 0,100" fill="url(#hwgi1)"/>
      <polygon points="0,0 270,0 300,50 270,100 0,100" fill="rgba(255,255,255,0.06)"/>
      <line x1="1" y1="1.5" x2="269" y2="1.5" stroke="rgba(255,255,255,0.4)" stroke-width="1.5"/>
      <line x1="1" y1="98.5" x2="269" y2="98.5" stroke="rgba(0,0,0,0.2)" stroke-width="1"/>
    </svg>
    <div class="hw-inner"><span class="hw-num">1</span><div class="hw-sep"></div><span class="hw-lbl">Шаг</span><div class="hw-mobile-text" style="color:#fff;padding:.75rem 0"><div style="font-size:14px;font-weight:600;text-shadow:0 1px 4px rgba(0,0,0,.3)">Регистрация без пароля</div><div style="font-size:12px;opacity:.85;margin-top:2px">Войдите через Telegram, VK или Яндекс</div></div></div>
  </div>
  <div class="hw-arrow" style="margin-left:-26px;z-index:2">
    <svg viewBox="0 0 300 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="hwg2" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#E05555"/><stop offset="50%" stop-color="#C73228"/><stop offset="100%" stop-color="#841810"/></linearGradient>
        <linearGradient id="hwgi2" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="rgba(0,0,0,0.2)"/><stop offset="35%" stop-color="rgba(0,0,0,0)"/><stop offset="100%" stop-color="rgba(0,0,0,0.22)"/></linearGradient>
        <filter id="hwf2"><feDropShadow dx="7" dy="0" stdDeviation="5" flood-color="rgba(0,0,0,.5)"/></filter>
      </defs>
      <polygon class="hw-poly-main" points="0,0 270,0 300,50 270,100 0,100 30,50" fill="url(#hwg2)" filter="url(#hwf2)"/>
      <polygon points="0,0 270,0 300,50 270,100 0,100 30,50" fill="url(#hwgi2)"/>
      <polygon points="0,0 270,0 300,50 270,100 0,100 30,50" fill="rgba(255,255,255,0.05)"/>
      <line x1="31" y1="1.5" x2="269" y2="1.5" stroke="rgba(255,255,255,0.38)" stroke-width="1.5"/>
      <line x1="31" y1="98.5" x2="269" y2="98.5" stroke="rgba(0,0,0,0.2)" stroke-width="1"/>
    </svg>
    <div class="hw-inner"><span class="hw-num">2</span><div class="hw-sep"></div><span class="hw-lbl">Шаг</span><div class="hw-mobile-text" style="color:#fff;padding:.75rem 0"><div style="font-size:14px;font-weight:600;text-shadow:0 1px 4px rgba(0,0,0,.3)">Найдите игру</div><div style="font-size:12px;opacity:.85;margin-top:2px">Фильтруйте по городу, уровню и формату</div></div></div>
  </div>
  <div class="hw-arrow" style="margin-left:-26px;z-index:3">
    <svg viewBox="0 0 300 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="hwg3" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#A560CC"/><stop offset="50%" stop-color="#7B35A0"/><stop offset="100%" stop-color="#4A1568"/></linearGradient>
        <linearGradient id="hwgi3" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="rgba(0,0,0,0.2)"/><stop offset="35%" stop-color="rgba(0,0,0,0)"/><stop offset="100%" stop-color="rgba(0,0,0,0.22)"/></linearGradient>
        <filter id="hwf3"><feDropShadow dx="7" dy="0" stdDeviation="5" flood-color="rgba(0,0,0,.5)"/></filter>
      </defs>
      <polygon class="hw-poly-main" points="0,0 270,0 300,50 270,100 0,100 30,50" fill="url(#hwg3)" filter="url(#hwf3)"/>
      <polygon points="0,0 270,0 300,50 270,100 0,100 30,50" fill="url(#hwgi3)"/>
      <polygon points="0,0 270,0 300,50 270,100 0,100 30,50" fill="rgba(255,255,255,0.05)"/>
      <line x1="31" y1="1.5" x2="269" y2="1.5" stroke="rgba(255,255,255,0.38)" stroke-width="1.5"/>
      <line x1="31" y1="98.5" x2="269" y2="98.5" stroke="rgba(0,0,0,0.2)" stroke-width="1"/>
    </svg>
    <div class="hw-inner"><span class="hw-num">3</span><div class="hw-sep"></div><span class="hw-lbl">Шаг</span><div class="hw-mobile-text" style="color:#fff;padding:.75rem 0"><div style="font-size:14px;font-weight:600;text-shadow:0 1px 4px rgba(0,0,0,.3)">Запишитесь</div><div style="font-size:12px;opacity:.85;margin-top:2px">Выберите позицию и нажмите «Записаться»</div></div></div>
  </div>
  <div class="hw-arrow" style="margin-left:-26px;z-index:4">
    <svg viewBox="0 0 300 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="hwg4" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#50CC78"/><stop offset="50%" stop-color="#28A85C"/><stop offset="100%" stop-color="#106830"/></linearGradient>
        <linearGradient id="hwgi4" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="rgba(0,0,0,0.2)"/><stop offset="35%" stop-color="rgba(0,0,0,0)"/><stop offset="100%" stop-color="rgba(0,0,0,0.15)"/></linearGradient>
        <filter id="hwf4"><feDropShadow dx="7" dy="0" stdDeviation="5" flood-color="rgba(0,0,0,.5)"/></filter>
      </defs>
      <polygon class="hw-poly-main" points="0,0 270,0 300,50 270,100 0,100 30,50" fill="url(#hwg4)" filter="url(#hwf4)"/>
      <polygon points="0,0 270,0 300,50 270,100 0,100 30,50" fill="url(#hwgi4)"/>
      <polygon points="0,0 270,0 300,50 270,100 0,100 30,50" fill="rgba(255,255,255,0.05)"/>
      <line x1="31" y1="1.5" x2="269" y2="1.5" stroke="rgba(255,255,255,0.38)" stroke-width="1.5"/>
      <line x1="31" y1="98.5" x2="269" y2="98.5" stroke="rgba(0,0,0,0.2)" stroke-width="1"/>
    </svg>
    <div class="hw-inner"><span class="hw-num">4</span><div class="hw-sep"></div><span class="hw-lbl">Шаг</span><div class="hw-mobile-text" style="color:#fff;padding:.75rem 0"><div style="font-size:14px;font-weight:600;text-shadow:0 1px 4px rgba(0,0,0,.3)">Играйте!</div><div style="font-size:12px;opacity:.85;margin-top:2px">Знакомьтесь с партнёрами на площадке</div></div></div>
  </div>
</div>

<div class="hw-cards">
  <div class="hw-card"><div class="hw-card-title hwt1">Регистрация без пароля</div><div class="hw-card-desc">Войдите через Telegram, VK или Яндекс. Заполните профиль — укажите уровень, амплуа и город.</div></div>
  <div class="hw-card"><div class="hw-card-title hwt2">Найдите игру</div><div class="hw-card-desc">Откройте каталог мероприятий. Фильтруйте по городу, уровню, дате и формату — классика или пляж.</div></div>
  <div class="hw-card"><div class="hw-card-title hwt3">Запишитесь</div><div class="hw-card-desc">Выберите позицию и нажмите «Записаться». Получите подтверждение и напоминание в мессенджер.</div></div>
  <div class="hw-card"><div class="hw-card-title hwt4">Играйте!</div><div class="hw-card-desc">Приходите на игру, знакомьтесь с новыми партнёрами и оценивайте уровень друг друга.</div></div>
</div>
</div>
--}}


</div>


<style>
.rf-map {
	display: flex;
	align-items: center;
}	
.district-links {
	flex: 0 0 340px;
	overflow: hidden;
}
.district-links a {
	display: block;
	font-size: 18px;
	padding: 6px 6px 6px 0;
	color: #2e3f7f;
	position: relative;
    text-decoration: none;
	font-weight: 500;	
	transition: 0.3s;
}
.district-links a:before,
.district-links a:after {
    content: "";
    border-bottom: 2px solid #2967BA;
    position: absolute;
    width: 100%;
    left: -50px;
    bottom: 3px;
    opacity: 0;
    transition: 0.3s;
    display: inline-block;
    pointer-events: none;    
}
.district-links a:before {
    border-bottom: 2px solid rgba(0,0,0,0.2);
    opacity: 1;
    bottom: 3px;
	left: 0;
	width: 100%;
}
body.dark .district-links a:before,
body.dark .district-links a:after {
    border-bottom: 2px solid #E7612F;
}
body.dark .district-links a:before {
    border-bottom: 2px solid rgba(255,255,255,0.2);
}
.map-name {
	transition: 0.3s;
}
.map-region {
	color: #000;
}
body.dark .map-region {
	color: #cacaca;
}
.district-links a.hover .map-name, 
.district-links a:hover .map-name {
    color: #000;
	padding-left: 6px;
}
body.dark .district-links a.hover .map-name, 
body.dark .district-links a:hover .map-name {
    color: #fff;
}
.district-links a.hover:after,
.district-links a:hover:after {
    opacity: 1;  
	left: 0;
	width: 100%;
}
.rusmap {
	padding-left: 60px; 
	width: 100%;
}
.rusmap svg {
	width: 100%;
	height: auto;
	filter: drop-shadow(0 5px 12px rgba(0, 0, 0, 0.1));    
}
.rusmap path {
    stroke: #FFFFFF;
    stroke-width: 2;
    stroke-linejoin: round; 
	fill: rgba(0,0,0,0.1);
}
body.dark .rusmap path {
       stroke: #222333;
	   fill: rgba(255,255,255,0.1);
}
.rusmap [data-code] {
    transition: fill 0.2s;
}
.rusmap path[data-color] {
    fill: #2967BA;
	transition: 0.3s;
}
.rusmap path[data-color].hover, 
.rusmap path[data-color]:hover{
	fill: #183e6f;
}
body.dark .rusmap path[data-color] {
    fill: #E7612F;
}
body.dark .rusmap path[data-color].hover, 
body.dark .rusmap path[data-color]:hover{
	fill: #FFB171;
}
@media screen and (max-width:767px) {
.rf-map {
	display: flex;
	flex-wrap: wrap;
}	
.district-links {
	display: flex;
	flex: 0 0 100%;
	flex-wrap: wrap;
	order: 2;
}
.district-links a {
	width: calc(50% - 18px);
	font-size: 16px;
	margin: 0 6px;
}
.rusmap {
	order: 1;
	padding: 0;
}
}
@media screen and (max-width:575px) {
.district-links a {
	width: 100%;
}
}
</style>
<script src="/assets/map.js"></script>	

   @php
    $isoCodes = [
        'Алтайский край' => 'RU-ALT',
        'Амурская область' => 'RU-AMU',
        'Архангельская область' => 'RU-ARK',
        'Астраханская область' => 'RU-AST',
        'Белгородская область' => 'RU-BEL',
        'Брянская область' => 'RU-BRY',
        'Владимирская область' => 'RU-VLA',
        'Волгоградская область' => 'RU-VGG',
        'Вологодская область' => 'RU-VLG',
        'Воронежская область' => 'RU-VOR',
        'Еврейская автономная область' => 'RU-YEV',
        'Забайкальский край' => 'RU-ZAB',
        'Ивановская область' => 'RU-IVA',
        'Иркутская область' => 'RU-IRK',
        'Кабардино-Балкарская Республика' => 'RU-KB',
        'Калининградская область' => 'RU-KGD',
        'Калужская область' => 'RU-KLU',
        'Камчатский край' => 'RU-KAM',
        'Карачаево-Черкесская Республика' => 'RU-KC',
        'Кемеровская область' => 'RU-KEM',
        'Кировская область' => 'RU-KIR',
        'Костромская область' => 'RU-KOS',
        'Краснодарский край' => 'RU-KDA',
        'Красноярский край' => 'RU-KYA',
        'Курганская область' => 'RU-KGN',
        'Курская область' => 'RU-KRS',
        'Ленинградская область' => 'RU-LEN',
        'Липецкая область' => 'RU-LIP',
        'Магаданская область' => 'RU-MAG',
        'Москва' => 'RU-MOW',
        'Московская область' => 'RU-MOS',
        'Мурманская область' => 'RU-MUR',
        'Нижегородская область' => 'RU-NIZ',
        'Новгородская область' => 'RU-NGR',
        'Новосибирская область' => 'RU-NVS',
        'Омская область' => 'RU-OMS',
        'Оренбургская область' => 'RU-ORE',
        'Орловская область' => 'RU-ORL',
        'Пензенская область' => 'RU-PNZ',
        'Пермский край' => 'RU-PER',
        'Приморский край' => 'RU-PRI',
        'Псковская область' => 'RU-PSK',
        'Республика Адыгея' => 'RU-AD',
        'Республика Алтай' => 'RU-AL',
        'Республика Башкортостан' => 'RU-BA',
        'Республика Бурятия' => 'RU-BU',
        'Республика Дагестан' => 'RU-DA',
        'Республика Ингушетия' => 'RU-IN',
        'Республика Калмыкия' => 'RU-KL',
        'Республика Карелия' => 'RU-KR',
        'Республика Коми' => 'RU-KO',
        'Республика Марий Эл' => 'RU-ME',
        'Республика Мордовия' => 'RU-MO',
        'Республика Саха (Якутия)' => 'RU-SA',
        'Республика Северная Осетия-Алания' => 'RU-SE',
        'Республика Татарстан' => 'RU-TA',
        'Республика Тыва' => 'RU-TY',
        'Республика Хакасия' => 'RU-KK',
        'Ростовская область' => 'RU-ROS',
        'Рязанская область' => 'RU-RYA',
        'Самарская область' => 'RU-SAM',
        'Санкт-Петербург' => 'RU-SPE',
        'Саратовская область' => 'RU-SAR',
        'Сахалинская область' => 'RU-SAK',
        'Свердловская область' => 'RU-SVE',
        'Смоленская область' => 'RU-SMO',
        'Ставропольский край' => 'RU-STA',
        'Тамбовская область' => 'RU-TAM',
        'Тверская область' => 'RU-TVE',
        'Томская область' => 'RU-TOM',
        'Тульская область' => 'RU-TUL',
        'Тюменская область' => 'RU-TYU',
        'Удмуртская Республика' => 'RU-UD',
        'Ульяновская область' => 'RU-ULY',
        'Хабаровский край' => 'RU-KHA',
        'Ханты-Мансийский автономный округ' => 'RU-KHM',
        'Челябинская область' => 'RU-CHE',
        'Чеченская Республика' => 'RU-CE',
        'Чувашская Республика' => 'RU-CU',
        'Ямало-Ненецкий автономный округ' => 'RU-YAN',
        'Ярославская область' => 'RU-YAR',
        // Казахстан
        'Абайская область' => 'KZ-10',
        'Акмолинская область' => 'KZ-11',
        'Актюбинская область' => 'KZ-15',
        'Алматинская область' => 'KZ-19',
        'Алматы' => 'KZ-75',
        'Астана' => 'KZ-71',
        'Атырауская область' => 'KZ-23',
        'Байконур' => 'KZ-89',
        'Восточно-Казахстанская область' => 'KZ-63',
        'Жамбылская область' => 'KZ-31',
        'Жетысуская область' => 'KZ-33',
        'Западно-Казахстанская область' => 'KZ-27',
        'Карагандинская область' => 'KZ-35',
        'Костанайская область' => 'KZ-39',
        'Кызылординская область' => 'KZ-43',
        'Мангистауская область' => 'KZ-47',
        'Павлодарская область' => 'KZ-55',
        'Северо-Казахстанская область' => 'KZ-59',
        'Туркестанская область' => 'KZ-61',
        'Улытауская область' => 'KZ-65',
        'Шымкент' => 'KZ-79',
        // Узбекистан
        'Андижанская область' => 'UZ-AN',
        'Бухарская область' => 'UZ-BU',
        'Джизакская область' => 'UZ-JI',
        'Кашкадарьинская область' => 'UZ-QA',
        'Навоийская область' => 'UZ-NG',
        'Наманганская область' => 'UZ-NM',
        'Республика Каракалпакстан' => 'UZ-QR',
        'Самаркандская область' => 'UZ-SA',
        'Сурхандарьинская область' => 'UZ-SU',
        'Сырдарьинская область' => 'UZ-SI',
        'Ташкент' => 'UZ-TK',
        'Ташкентская область' => 'UZ-TO',
        'Ферганская область' => 'UZ-FA',
        'Хорезмская область' => 'UZ-XR',
    ];
    @endphp

		<div class="ramka">
			<div class="d-flex between fvc mb-2">
				<h2 class="-mt-05">Площадки и корты</h2>
				<a href="{{ route('locations.index') }}" class="d-none d-sm-inline-block btn btn-secondary">Все локации</a>
			</div>
			
			<div class="rf-map">
				<div class="district-links mb-1">
    @foreach(\DB::table('cities')
        ->join('locations','cities.id','=','locations.city_id')
        ->whereNull('locations.organizer_id')
        ->select('cities.id','cities.name', 'cities.region', \DB::raw('count(locations.id) as cnt'))
        ->groupBy('cities.id','cities.name', 'cities.region')
        ->orderByDesc('cnt')
        ->limit(20)
        ->get() as $city)
        <a 
            data-code="{{ $isoCodes[$city->region] ?? '' }}" 
            href="{{ route('locations.index', ['city_id' => $city->id]) }}"
        >
            <span class="d-flex between">
                <span class="d-block">
                     <span class="map-name b-600">{{ $city->name }}</span><span class="map-region f-13 pl-05">({{ $city->region }})</span>
                </span>
                <span class="f-13 b-600">{{ $city->cnt }}</span>
            </span>
        </a>
    @endforeach	
				</div>
				<div class="rusmap" data-aos="fade-up"></div>
			</div>							
			<div class="text-right m-center">
				{{ \DB::table('locations')->whereNull('organizer_id')->count() }} площадок
				в {{ \DB::table('locations')->whereNull('organizer_id')->distinct('city_id')->count('city_id') }} городах России
			</div>
				<div class="mt-1 d-sm-none text-center">
					<a href="{{ route('locations.index') }}" class="btn btn-secondary">Все локации</a>
				</div>				
		</div>
		
		{{-- ===== CTA ===== --}}
		@guest
		<div class="ramka" data-aos="fade-up">
			<div class="cta-block card">
				<div class="f-32 mb-1">🏐 Готовы играть?</div>
				<div class="f-18 mb-3" style="opacity:.7">
					Присоединяйтесь к сообществу волейболистов — бесплатно и без лишних шагов.
				</div>
				<div class="d-flex flex-wrap gap-2" style="justify-content:center">
					<a href="{{ route('register') }}" class="btn">Зарегистрироваться</a>
					<a href="{{ route('events.index') }}" class="btn btn-secondary">Смотреть игры</a>
				</div>
			</div>
		</div>
		@endguest
	</div>
	
	
</x-voll-layout>	