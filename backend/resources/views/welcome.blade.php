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
				<a href="{{ route('dashboard') }}" class="btn btn-secondary">Мой профиль</a>
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
			$usersCount     = \App\Models\User::where('is_bot', false)->count();
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
		<div class="ramka" data-aos="fade-up">
			<h2 class="-mt-05 text-center">Как это работает</h2>

<style>

.process-roadmap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 36px;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
    border-radius: 24px;
    box-sizing: border-box;
    overflow: hidden;
}

.roadmap-left {
    position: relative;
    width: 460px;
    min-width: 460px;
    height: 520px;
}

.center-disc {
    position: absolute;
    left: 18px;
    top: 78px;
    width: 270px;
    height: 270px;
    border-radius: 50%;
    background: radial-gradient(circle at 35% 30%, #ffffff 0%, #f4f4f4 55%, #e6e6e6 100%);
    box-shadow:
        0 18px 30px rgba(0, 0, 0, 0.12),
        inset 0 2px 6px rgba(255, 255, 255, 0.9),
        inset 0 -3px 10px rgba(0, 0, 0, 0.06);
    z-index: 3;
}

.disc-ring {
    position: absolute;
    border-radius: 50%;
    inset: 0;
}

.disc-ring-1 {
    transform: scale(1.16);
    background: rgba(255,255,255,0.32);
    z-index: -2;
    filter: blur(1px);
}

.disc-ring-2 {
    transform: scale(1.28);
    background: rgba(255,255,255,0.15);
    z-index: -3;
}

.disc-core {
    position: absolute;
    inset: 22px;
    border-radius: 50%;
    background: linear-gradient(145deg, #fafafa, #ececec);
    box-shadow: inset 0 2px 8px rgba(255,255,255,0.9);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 28px 18px 16px;
    text-align: center;
    box-sizing: border-box;
}

.disc-title {
    font-size: 14px;
    font-weight: 700;
    color: #7b7b7b;
    letter-spacing: 1px;
    margin-bottom: 8px;
}

.disc-text {
    font-size: 11px;
    line-height: 1.45;
    color: #9a9a9a;
    max-width: 180px;
    margin-bottom: 18px;
}

.disc-chart {
    position: relative;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background:
        conic-gradient(
            #ff9800 0deg 40deg,
            #e91e63 40deg 80deg,
            #9c27b0 80deg 120deg,
            #673ab7 120deg 160deg,
            #3f51b5 160deg 200deg,
            #2196f3 200deg 240deg,
            #00bcd4 240deg 280deg,
            #8bc34a 280deg 320deg,
            #cddc39 320deg 360deg
        );
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 2px 6px rgba(255,255,255,0.6);
    margin-bottom: 10px;
}

.disc-chart::before {
    content: "";
    width: 92px;
    height: 92px;
    background: #f7f7f7;
    border-radius: 50%;
    box-shadow: inset 0 2px 5px rgba(0,0,0,0.06);
    position: absolute;
}

.chart-segment {
    position: absolute;
    font-size: 11px;
    font-weight: 700;
    color: rgba(0,0,0,0.5);
}

.seg-a { left: 10px; top: 96px; }
.seg-b { left: 25px; top: 58px; }
.seg-c { left: 62px; top: 24px; }
.seg-d { left: 106px; top: 24px; }
.seg-e { left: 138px; top: 56px; }
.seg-f { left: 132px; top: 100px; }

.disc-mini {
    width: 92px;
    height: 92px;
    margin-top: -76px;
    border-radius: 50%;
    background: linear-gradient(145deg, #ffffff, #eeeeee);
    box-shadow:
        0 6px 12px rgba(0,0,0,0.12),
        inset 0 2px 4px rgba(255,255,255,0.9);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

.mini-title {
    font-size: 9px;
    font-weight: 700;
    color: #8a8a8a;
    margin-bottom: 6px;
}

.mini-map {
    font-size: 10px;
    color: #4aa3df;
    letter-spacing: 2px;
}

.curve-svg {
    position: absolute;
    inset: 0;
    z-index: 2;
    overflow: visible;
}

.roadmap-right {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 22px;
    min-width: 0;
}

.roadmap-item {
    position: relative;
    display: flex;
    align-items: center;
    min-height: 78px;
    border-radius: 40px;
    padding: 12px 24px 12px 72px;
    box-shadow: 0 12px 24px rgba(0,0,0,0.10);
    color: #fff;
    overflow: visible;
}

.roadmap-item .item-icon {
    position: absolute;
    left: -12px;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(145deg, #ffffff, #ececec);
    box-shadow:
        0 8px 14px rgba(0,0,0,0.18),
        inset 0 2px 4px rgba(255,255,255,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    z-index: 2;
}

.item-content {
    flex: 1;
}

.item-title {
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    text-transform: uppercase;
}

.item-text {
    font-size: 11px;
    line-height: 1.45;
    opacity: 0.95;
    max-width: 82%;
}

.item-side-dots {
    display: flex;
    gap: 5px;
    align-items: center;
    margin-left: auto;
}

.item-side-dots span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: rgba(255,255,255,0.9);
    display: inline-block;
}

.item-line {
    position: absolute;
    left: -56px;
    top: 50%;
    width: 42px;
    height: 1px;
    background: #909090;
}

.item-line::after {
    content: "";
    position: absolute;
    right: -2px;
    top: -2px;
    width: 5px;
    height: 5px;
    background: #909090;
    border-radius: 50%;
}

.yellow {
    background: linear-gradient(90deg, #d7a600 0%, #f2bc19 100%);
}

.orange {
    background: linear-gradient(90deg, #ea6500 0%, #ff7b1f 100%);
}

.magenta {
    background: linear-gradient(90deg, #b81c8e 0%, #cf37a5 100%);
}

.purple {
    background: linear-gradient(90deg, #6d49b6 0%, #5a41b2 100%);
}

.cyan {
    background: linear-gradient(90deg, #05a9c1 0%, #16bfd1 100%);
}

.green {
    background: linear-gradient(90deg, #77bf2f 0%, #7fd443 100%);
}

@media (max-width: 1100px) {
    .process-roadmap {
        flex-direction: column;
        align-items: center;
    }

    .roadmap-left {
        width: 100%;
        max-width: 460px;
        min-width: 0;
    }

    .roadmap-right {
        width: 100%;
    }

    .roadmap-item .item-text {
        max-width: 100%;
    }
}

@media (max-width: 768px) {
    .process-roadmap {
        padding: 24px 14px;
        border-radius: 18px;
    }

    .roadmap-left {
        height: 420px;
        transform: scale(0.86);
        transform-origin: top center;
        margin-bottom: -40px;
    }

    .roadmap-item {
        padding: 14px 18px 14px 68px;
    }

    .item-text {
        font-size: 10px;
    }

    .item-title {
        font-size: 13px;
    }
}
</style>

<div class="process-roadmap">
    <div class="roadmap-left">
        <div class="center-disc">
            <div class="disc-ring disc-ring-1"></div>
            <div class="disc-ring disc-ring-2"></div>

            <div class="disc-core">
                <div class="disc-title">LOREM IPSUM</div>
                <div class="disc-text">
                    Short description text for your project block.
                    You can replace this with real content.
                </div>

                <div class="disc-chart">
                    <div class="chart-segment seg-a">A</div>
                    <div class="chart-segment seg-b">B</div>
                    <div class="chart-segment seg-c">C</div>
                    <div class="chart-segment seg-d">D</div>
                    <div class="chart-segment seg-e">E</div>
                    <div class="chart-segment seg-f">F</div>
                </div>

                <div class="disc-mini">
                    <div class="mini-title">CORE PLAN</div>
                    <div class="mini-map">● ● ●</div>
                </div>
            </div>
        </div>

        <svg class="curve-svg" viewBox="0 0 460 520" preserveAspectRatio="none">
            <defs>
                <linearGradient id="roadGradient" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#4CAF50"/>
                    <stop offset="20%" stop-color="#8BC34A"/>
                    <stop offset="40%" stop-color="#00BCD4"/>
                    <stop offset="60%" stop-color="#2196F3"/>
                    <stop offset="75%" stop-color="#9C27B0"/>
                    <stop offset="88%" stop-color="#FF9800"/>
                    <stop offset="100%" stop-color="#FDD835"/>
                </linearGradient>

                <filter id="softShadow" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="8" stdDeviation="8" flood-opacity="0.18"/>
                </filter>
            </defs>

            <path
                d="M90,450
                   C130,420 155,390 180,350
                   C205,310 215,265 235,225
                   C255,185 275,145 315,110
                   C345,82 375,65 405,55"
                fill="none"
                stroke="url(#roadGradient)"
                stroke-width="8"
                stroke-linecap="round"
                filter="url(#softShadow)"
            />

            <circle cx="90" cy="450" r="13" fill="#4CAF50"/>
            <circle cx="160" cy="372" r="9" fill="#8BC34A"/>
            <circle cx="220" cy="285" r="10" fill="#00ACC1"/>
            <circle cx="245" cy="220" r="10" fill="#1E88E5"/>
            <circle cx="285" cy="165" r="9" fill="#D81B60"/>
            <circle cx="330" cy="118" r="9" fill="#FB8C00"/>
            <circle cx="405" cy="55" r="8" fill="#FDD835"/>

            <circle cx="160" cy="372" r="15" fill="rgba(255,255,255,0.12)"/>
            <circle cx="220" cy="285" r="15" fill="rgba(255,255,255,0.12)"/>
            <circle cx="245" cy="220" r="15" fill="rgba(255,255,255,0.12)"/>
            <circle cx="285" cy="165" r="15" fill="rgba(255,255,255,0.12)"/>
            <circle cx="330" cy="118" r="15" fill="rgba(255,255,255,0.12)"/>
        </svg>
    </div>

    <div class="roadmap-right">
        @php
            $items = [
                ['title' => 'Strategy Planning', 'text' => 'Define project goals, direction and baseline architecture for the launch stage.', 'color' => 'yellow', 'icon' => '👥'],
                ['title' => 'Analytics & Metrics', 'text' => 'Collect indicators, build reports and prepare measurable performance targets.', 'color' => 'orange', 'icon' => '📊'],
                ['title' => 'User Experience', 'text' => 'Improve interaction flow, accessibility and overall usability of the product.', 'color' => 'magenta', 'icon' => '📈'],
                ['title' => 'Target Results', 'text' => 'Align business objectives with actual deliverables and execution priorities.', 'color' => 'purple', 'icon' => '🎯'],
                ['title' => 'Mobile Access', 'text' => 'Adapt the solution for smartphone usage and cross-device consistency.', 'color' => 'cyan', 'icon' => '📱'],
                ['title' => 'Automation Layer', 'text' => 'Implement process automation, integrations and scalable support operations.', 'color' => 'green', 'icon' => '⚙️'],
            ];
        @endphp

        @foreach($items as $index => $item)
            <div class="roadmap-item {{ $item['color'] }}" style="--i: {{ $index }}">
                <div class="item-line"></div>
                <div class="item-icon">{{ $item['icon'] }}</div>
                <div class="item-content">
                    <div class="item-title">{{ $item['title'] }}</div>
                    <div class="item-text">{{ $item['text'] }}</div>
                </div>
                <div class="item-side-dots">
                    <span></span><span></span><span></span>
                </div>
            </div>
        @endforeach
    </div>
</div>			
			
			
			
			
			
			
			
			
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
.hw-arrows{flex-direction:column;gap:4px}
.hw-arrow{height:56px}
.hw-arrow svg{display:none}
.hw-arrow::before{content:'';position:absolute;inset:0;border-radius:10px}
.hw-arrow:nth-child(1)::before{background:linear-gradient(135deg,#F5A050,#C06010)}
.hw-arrow:nth-child(2)::before{background:linear-gradient(135deg,#E05555,#8C1A14)}
.hw-arrow:nth-child(3)::before{background:linear-gradient(135deg,#A560CC,#4A1568)}
.hw-arrow:nth-child(4)::before{background:linear-gradient(135deg,#50CC78,#106830)}
.hw-inner{padding-left:1.25rem;flex-direction:row;gap:10px;justify-content:flex-start}
.hw-sep{display:none}
.hw-cards{grid-template-columns:1fr 1fr;gap:.75rem}
}
@media(max-width:480px){
.hw-cards{grid-template-columns:1fr}
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
    <div class="hw-inner"><span class="hw-num">1</span><div class="hw-sep"></div><span class="hw-lbl">Шаг</span></div>
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
    <div class="hw-inner"><span class="hw-num">2</span><div class="hw-sep"></div><span class="hw-lbl">Шаг</span></div>
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
    <div class="hw-inner"><span class="hw-num">3</span><div class="hw-sep"></div><span class="hw-lbl">Шаг</span></div>
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
    <div class="hw-inner"><span class="hw-num">4</span><div class="hw-sep"></div><span class="hw-lbl">Шаг</span></div>
  </div>
</div>

<div class="hw-cards">
  <div class="hw-card"><div class="hw-card-title hwt1">Регистрация без пароля</div><div class="hw-card-desc">Войдите через Telegram, VK или Яндекс. Заполните профиль — укажите уровень, амплуа и город.</div></div>
  <div class="hw-card"><div class="hw-card-title hwt2">Найдите игру</div><div class="hw-card-desc">Откройте каталог мероприятий. Фильтруйте по городу, уровню, дате и формату — классика или пляж.</div></div>
  <div class="hw-card"><div class="hw-card-title hwt3">Запишитесь</div><div class="hw-card-desc">Выберите позицию и нажмите «Записаться». Получите подтверждение и напоминание в мессенджер.</div></div>
  <div class="hw-card"><div class="hw-card-title hwt4">Играйте!</div><div class="hw-card-desc">Приходите на игру, знакомьтесь с новыми партнёрами и оценивайте уровень друг друга.</div></div>
</div>
</div>
		{{-- ===== ЛОКАЦИИ ===== --}}
		<div class="ramka" data-aos="fade-up">
			<div class="d-flex between fvc mb-2">
				<h2 class="-mt-05">📍 Площадки и корты</h2>
				<a href="{{ route('locations.index') }}" class="btn btn-secondary">Все локации</a>
			</div>
			<div class="f-17 mb-2" style="opacity:.7">
				{{ \DB::table('locations')->whereNull('organizer_id')->count() }} площадок
				в {{ \DB::table('locations')->whereNull('organizer_id')->distinct('city_id')->count('city_id') }} городах России
			</div>
			<div class="d-flex flex-wrap gap-2">
				@foreach(\DB::table('cities')->join('locations','cities.id','=','locations.city_id')->whereNull('locations.organizer_id')->select('cities.id','cities.name',\DB::raw('count(locations.id) as cnt'))->groupBy('cities.id','cities.name')->orderByDesc('cnt')->limit(8)->get() as $city)
				<a href="{{ route('locations.index', ['city_id' => $city->id]) }}"
				class="btn btn-secondary">
					{{ $city->name }} <span class="f-14" style="opacity:.6">({{ $city->cnt }})</span>
				</a>
				@endforeach
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