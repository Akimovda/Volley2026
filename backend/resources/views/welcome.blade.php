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
			
/* Карточка */
.atuinCard {
	position: relative;
}
.atuinCard-txt {
	position: relative;
	z-index: 1;
	padding: 1rem 1rem 14.8rem;
    border: 0.2rem solid rgba(0, 0, 0, 0.1);
    border-radius: 1rem;
	background: #fff;
	box-shadow: 0 0.2rem 0.4rem rgba(0, 0, 0, 0.03);
	transition: all 0.4s ease;
}
.atuinCard-txt strong {  
	text-transform: uppercase;
	font-weight: 600;
	font-size: 1.8rem; 
	display: block;
	margin: .6rem 0 1.2rem 0;	
	line-height: 1.3;
	text-align: center;
}
.atuinCard-txt p { 
	font-size: 1.8rem;
	line-height: 1.3;
	margin: 1rem 0 1rem 0;
}
.atuinCard-txt ul {
	margin: 0;
	padding: 0 0 0 2.4rem;
	position: relative;
	list-style: none;
}
.atuinCard-txt li { 
	font-size: 1.6rem;
	line-height: 1.3;
	margin: .6rem 0;
}
.atuinCard-txt li:before {
	font-weight: bold;
	line-height: 1.3;
	color: #2967BA;
	content: "\27A0";
	transition: 0.5s;
	position: absolute;	
	left: 0;
}
.atuinCard-txt li:hover:before {
	color: #2967BA;
	left: .4rem;
}	
.atuinCard-image-wrap {
	height: 14.8rem;
	position: absolute;
	left: 50%;
	transform: translateX(-50%);
	bottom: 0;
	z-index: 3;
}
.atuinCard-image {
	display: flex;
}
.atuinCard-front,
.atuinCard-back {
	background-size: cover;
	background-position: center;
	background-color: #2967BA;
	transition: transform .7s cubic-bezier(0.4, 0.2, 0.2, 1);
	backface-visibility: hidden;
	text-align: center;
	border-radius: 50%;
	height: 12.8rem;
	width: 12.8rem;	
	border: .6rem solid #2967BA;	
	margin: 0 .6rem;
}
.atuinCard-inner {
	height: 100%;
}
.atuinCard-inner a {
	font-weight: 600;
	text-transform: uppercase;
	text-decoration: none;
	color: #2967BA;
	text-align: center;
	margin: auto;
    display: flex;
    flex-flow: column;
    align-items: center;
    justify-content: center;
	font-size: 1.4rem; 
	height: 100%;
	width: 100%;
}
@media (min-width: 576px) {
	.atuinCard {
		height: 100%;	
	}
	.atuinCard-txt {
		padding: 1rem 2rem;
		margin-right: 10rem;
		min-height: 24rem;
		height: 100%;
	}
	.atuinCard-txt strong {  
		font-size: 2rem; 
		margin: .6rem 6rem 1.2rem 0;	
		text-align: left;
	}
	.atuinCard-txt p { 
		font-size: 1.8rem;
		margin: 1rem 6rem 1rem 0;
	}
	.atuinCard-txt ul {
		padding: 0 6rem 0 3rem;
	}
	.atuinCard-txt li { 
		font-size: 1.6rem;
		margin: .6rem 1rem .6rem 0;
	}	
	.atuinCard-image-wrap {
		border-left: 0.2rem solid rgba(0, 0, 0, 0.1);
		border-top: 0.2rem solid rgba(0, 0, 0, 0.1);
		transition: all 0.4s ease;
		border-radius: 50%;
		width: 20rem;
		height: 20rem;
		top: 2rem;
		left: auto;
		right: 0;
		transform: unset;
	}
	.atuinCard-image-lay {
		width: 10rem;
		height: 20rem;
		position: absolute;
		top: -.2rem;
		z-index: 2;
		right: -.2rem;
	}
	.atuinCard-image {
		transform-style: preserve-3d;
		perspective: 100rem;
		border-radius: 50%;
		position: absolute;
		z-index: 3;
		height: 17.6rem;
		width: 17.6rem;
		top: .2rem;
		right: .2rem;
	}
	.atuinCard-front,
	.atuinCard-back {
		background-color: transparent;
		height: 17.6rem;
		width: 17.6rem;	
		position: absolute;
		margin: 0;
	}
	.atuinCard-back {
		transform: rotateY(180deg);
		transform-style: preserve-3d;
	}
	.atuinCard:hover .atuinCard-back {
		transform: rotateY(0deg);
		transform-style: preserve-3d;
	}
	.atuinCard:hover .atuinCard-front {
		transform: rotateY(-180deg);
		transform-style: preserve-3d;
	}
	.atuinCard-inner {
		transform: translateY(-50%) translateZ(6rem);
		top: 50%;
		position: absolute;
		margin: auto;
		z-index: 4;
		width: 100%;
		height: auto;
	}
	.atuinCard-inner a {
		height: 14.2rem;
		width: 14.2rem;
		border: .6rem solid #2967BA;
		border-radius: 50%;
		transition: 0.5s ease-in-out;
		overflow: hidden;	
	}
	.atuinCard-inner a:hover {
		color: #fff;
		box-shadow: inset 0 0 0 15rem #2967BA;
	}	
}			
	
.atuinCard:hover .atuinCard-txt {
    box-shadow: 0 0.4rem 1.2rem rgba(0, 0, 0, 0.08);
    border: 0.2rem solid rgba(0, 0, 0, 0.2)	
}
.atuinCard:hover .atuinCard-image-wrap {
    border: 0.2rem solid rgba(0, 0, 0, 0.2)	
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
							
							
<div class="atuinCard">
	<div class="atuinCard-txt">
		<strong class="f-20 b-600 cd">Для игроков</strong>
		<p>Играйте больше, находите партнёров, развивайтесь.</p>
		<ul>
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
	<div class="atuinCard-image-wrap">
		<div class="atuinCard-image-lay"></div>
		<div class="atuinCard-image">
			<div class="atuinCard-front" style="background-image: url(https://atuin.ru/demo/cards/hotel-1.jpg)"></div>
			<div class="atuinCard-back">
				<div class="atuinCard-inner">					
					<a href="{{ route('events.index') }}">Найти игру</a>
				</div>
			</div>
		</div>	
	</div>	
</div>							
							
							</div>
                            <div class="col-md-6">
							
							
<div class="atuinCard">
	<div class="atuinCard-txt">
		<strong class="f-20 b-600 cd">Найдите своих</strong>
		<p>Сообщество игроков вашего уровня.</p>
		<ul>
											<li>Каталог игроков с уровнем и амплуа</li>
											<li>Фильтр по городу, уровню, направлению</li>
											<li>Пляжные пары и командная запись</li>
											<li>Приглашайте партнёров на мероприятия</li>
											<li>Авторизация через Telegram, VK или Яндекс</li>
		</ul>
	</div>	
	<div class="atuinCard-image-wrap">
		<div class="atuinCard-image-lay"></div>
		<div class="atuinCard-image">
			<div class="atuinCard-front" style="background-image: url(https://atuin.ru/demo/cards/hotel-1.jpg)"></div>
			<div class="atuinCard-back">
				<div class="atuinCard-inner">					
					<a href="{{ route('users.index') }}">Каталог игроков</a>
				</div>
			</div>
		</div>	
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
	transition: all 1s, background 0.3s, color 0.1s!important;
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
	transition-delay: 0.1s, 0s, 0s!important;
}	
.steps ul li:nth-child(3) {	
	left: calc(50% - 2rem);
	position: relative;
	transition-delay: 0.2s, 0s, 0s!important;
}	
.steps ul li:last-child {	
	left:  calc(75% - 2rem);
	width: calc(25% + 2rem);
	clip-path: polygon(100% 0%,100% 100%,0% 100%, 6rem 50%,0% 0%);
	padding: 2rem 2rem 2rem 8rem;
	transition-delay: 0.3s, 0s, 0s!important;
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
	if (window.innerWidth < 768) return;	
	if ($('.steps ul li:not(.aos-animate)').length > 0) return;
	if(count === 4) { count = 0; }
	$('.steps ul li').removeClass('active');
	$('.steps ul li:eq(' + count + ')').addClass('active');
	count++;
}
	Inter();
	siteInterval = setInterval(Inter, 4000);		
	$('.steps ul li').mouseenter(function(e) {
		clearInterval(siteInterval);
		$('.steps ul li').removeClass('active');
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