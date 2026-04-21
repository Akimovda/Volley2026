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
			text-align: center;
            }
            .cta-block .f-32 { font-size: 3.2rem; font-weight: 700; }
			
            @media (max-width: 768px) {
			.stat-number { font-size: 3.6rem; }
			.cta-block { padding: 3rem 1.6rem; }
            }
			


.sgroup-container {
	position: relative;
	height: 55rem;
	border-radius: 1.6rem;
}	
.sgroup {
	position: absolute;
	z-index: 0;
	width: calc(50% - 1rem);
	height: calc(50% - 1rem);
	overflow: hidden;
	cursor: pointer;
	transform: scale(1);
	transition: all 500ms cubic-bezier(0.4, 0, 0.2, 1);
	background-size: cover;
	background-position: 50% 50%;
	box-shadow: 0 1rem 2.2rem rgba(0, 0, 0, 0.04), 0 0.5rem 1.2rem rgba(0, 0, 0, 0.02);	
	border-radius: 1.6rem;
}
.sgroup:after {
	content: "";
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(255,255,255,0.3);
	transition: background-color 500ms cubic-bezier(0.4, 0, 0.2, 1);
}
.sgroup:hover:after {
	content: "";
	background-color: rgba(255,255,255,0.1);
}
body.dark .sgroup:after {
	background-color: rgba(0,0,0,0.3);
}
body.dark .sgroup:hover:after {
	background-color: rgba(0,0,0,0.1);
}
.sgroup-1 {
	top: 0;
	left: 0;
}
.sgroup-2 {
	top: 0;
	left: calc(50% + 1rem);
}
.sgroup-3 {
	top: calc(50% + 1rem);
	left: 0;
}
.sgroup-4 {
	top: calc(50% + 1rem);
	left: calc(50% + 1rem);
}
.sgroup.is-expanded {
	top: 0;
	left: 0;
	z-index: 9;
	width: 100%;
	height: 100%;
	cursor: initial;
}
.has-expanded-item .sgroup:not(.is-expanded) {
	transform: scale(0);
}
.close-sgroup {
	z-index: 6;
	position: absolute;
	top: 0;
	right: 0;
	display: flex;
	align-items: center;
	justify-content: center;	
	width: 6rem;
	height: 6rem;
	line-height: 1;
	font-size: 5rem;
	text-align: center;
	color: #000;
	opacity: 0;
	cursor: pointer;
	pointer-events: none;
	transition: opacity 150ms linear;
	will-change: opacity;
}
body.dark .close-sgroup {
	color: #cacaca;
}
.sgroup.is-expanded .close-sgroup {
	opacity: 1;
	transition-delay: 500ms;
	pointer-events: initial;
}
.title-box {
	margin: 2rem 3rem;
	display: flex;
	position: absolute;
	font-size: 2.2rem;
	font-weight: 600;
	text-transform: uppercase;
	z-index: 1;
	line-height: 1;
	padding: 0.8rem 1.4rem;
	text-shadow: 0 0 0.5rem rgba(255,255,255,1), 0 0 1rem rgba(255,255,255,0.9);
	border-radius: 1rem;
}
body.dark .title-box {
	text-shadow: 0 0 0.5rem rgba(34, 35, 51, 1),  0 0 1rem rgba(34, 35, 51,0.9);
}
.sgroup-1 .title-box { 
	right: 0;
	top: 0;
}
.sgroup-2 .title-box { 
	top: 0;
}
.sgroup-3 .title-box { 
	bottom: 0;
	right: 0;
}
.sgroup-4 .title-box { 
	bottom: 0;
}
@media screen and (min-width: 992px) {
	.title-box:after {
		content: "";
		height: 0.3rem;
		width: 0;
		background: #2967BA;
		opacity: 0.7;
		position: absolute;
		left: 50%;
		transform: translateX(-50%);
		z-index: 1;
		bottom: -0.4rem;
		transition: width 0.6s;
	}
	body.dark .title-box:after {
		background: #E7612F;
	}	
	.sgroup-3 .title-box:after,
	.sgroup-4 .title-box:after { 
		top: -0.4rem;
		bottom: auto;
	}
	.sgroup:hover .title-box:after {
		width: 90%;
	}
	.has-expanded-item .title-box:after  {
		display: none;
	}
.sgroup-1 .title-box { 
	top: 25%;
}
.sgroup-2 .title-box { 
	top: 25%;
}
.sgroup-3 .title-box { 
	bottom: 25%;
}
.sgroup-4 .title-box { 
	bottom: 25%;
}	
}
.sgroup .img-box { 
	will-change: transform;
	transition: transform 1.6s;
	height: 100%;
	width: 100%;
	overflow: hidden; 
	will-change: transform;
}
.sgroup .img-box img { 
	width: 100%;
	height: 100%;
	object-fit: cover;
}
.sgroup:hover .img-box  {
	transform: scale(1.15);
}
.has-expanded-item .sgroup:hover .img-box  {
	transform: scale(1);
}
.sgroup.sgroup-1 .img-box { 
	transform-origin: left center; 
}
.sgroup.sgroup-1 .img-box img { 
	object-position: left center;
}
.sgroup.sgroup-2 .img-box { 
	transform-origin: right top; 
}
.sgroup.sgroup-2 .img-box img { 
	object-position: right center;
}
.sgroup.sgroup-3 .img-box { 
	transform-origin: left top; 
}
.sgroup.sgroup-3 .img-box img { 
	object-position: left 80%;
}
.sgroup.sgroup-4 .img-box { 
	transform-origin: right bottom; 
}
.sgroup.sgroup-4 .img-box img { 
	object-position: right center;
}
.sgroup.is-expanded:after {
	background-color: rgba(255,255,255,0.95);
}
body.dark .sgroup.is-expanded:after {
	background-color: rgba(30,31,45,0.95);
}
.sgroup.is-expanded .title-box {
	top: 2.3rem;
	left: 8rem;
	right: auto;
	bottom: auto;
}
.info-box {
	overflow-y: auto;
	opacity: 0;
	transition: opacity 0.5s linear 0.4s;
	will-change: transform;
}
.info-box .row {
	height: 100%;
}
.sgroup.is-expanded .info-box {
	z-index: 2;
	position: absolute;
	width: 100%;
	top: 11rem;
	padding: 1rem;
	height: calc(100% - 11rem);
	opacity: 1;
}
.sgroup-circle,
.sgroup-logo {
	border-radius: 50%;
	width: 16.6rem;
	height: 16.6rem;
	box-sizing: content-box;
	transition: 0.4s;
	will-change: transform;
}
.sgroup-circle {
	position: absolute;
	top: calc(50% - 10rem);
	left: calc(50% - 10rem);
	background-color: rgba(255,255,255,0.9);
	position: relative;
	box-shadow: inset 0 2rem 2rem rgba(0,0,0,.05), 0 2rem 3rem rgba(0,0,0,.1);
	z-index: 12;
	padding: 1.8rem;
}
body.dark .sgroup-circle {
	background-color: rgba(30,31,45,0.9);
}
.has-expanded-item .sgroup-circle,
.has-expanded-item .sgroup-logo {
	border-radius: 50%;
	width: 8rem;
	height: 8rem;
} 
.has-expanded-item .sgroup-circle {
	top: 1rem;
	left: 1rem;
	padding: 0.4rem;
}
.sgroup-circle::before {
	content: "";
	position: absolute;
	top: 0;
	left: 0;
	bottom: 0;
	right: 0;
	box-shadow: inset 0 .2rem .2rem #fff;
	border-radius: 50%;
}
body.dark .sgroup-circle::before {
	box-shadow: inset 0 .2rem .2rem #2a2c3f;
}
.sgroup-logo {
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: inset 0 .3rem .6rem rgba(0, 0, 0, .1);
	background-color: rgba(255,255,255,0.9);
	transition: all .3s ease-in-out;	
}
body.dark .sgroup-logo {
	box-shadow: inset 0 .3rem .6rem rgba(0, 0, 0, .35);
	background-color: rgba(34, 35, 51,1);
}
.sgroup-logo svg {
	width: 72%;
}
.audience-card {
	justify-content: space-between;
	display: flex;
	flex-flow: column;
	height: 100%;
	padding-bottom: 1rem;
}
.audience-top {
	display: flex;
	margin-bottom: 1rem; 
}
.audience-icon { 
	flex: 0 0 6rem;
	line-height: 1; 
	height: 6rem;
	width: 6rem;
	font-size: 5rem; 	
}
.audience-title {
	padding-left: 1.4rem;
}
@media screen and (max-width: 991px) {
	/*
.sgroup {
border:1rem solid #fff;
}	
*/	
	
	.sgroup.sgroup-1 .img-box { 
		transform-origin: center center; 
	}
	.sgroup.sgroup-1 .img-box img { 
		object-position: 20% center;
	}
	.sgroup.sgroup-2 .img-box { 
		transform-origin: center center; 
	}
	.sgroup.sgroup-2 .img-box img { 
		object-position: 90% center;
	}
	.sgroup.sgroup-3 .img-box { 
		transform-origin: center center; 
	}
	.sgroup.sgroup-3 .img-box img { 
		object-position: left bottom;
	}
	.sgroup.sgroup-4 .img-box { 
		transform-origin: center center; 
	}
	.sgroup.sgroup-4 .img-box img { 
		object-position: right bottom;
	}
	.audience-card {
		padding-bottom: 2.5rem;
	}
	.sgroup-container {
		height: 65rem;
	}	
	.sgroup:not(.is-expanded) .title-box {
		margin: 0;
		font-size: 1.8rem;
		width: 100%;
		text-align: center;
		padding: 1rem;
		justify-content: center;
		background: rgba(255,255,255,0.8);
		border-radius: 0;
	}
	body.dark .sgroup:not(.is-expanded) .title-box {
		background: rgba(34, 35, 51,0.8);
	}	
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
			
<div class="sgroup-container">
	<div class="sgroup-circle">
		<div class="sgroup-logo">
			<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="330px" height="207px" viewBox="0 0 330 207" version="1.1"><defs><linearGradient x1="73.168378%" y1="4.69814595%" x2="73.168378%" y2="105.568873%" id="y4lyj6"><stop stop-color="#FFB171" offset="0%"></stop><stop stop-color="#E7612F" offset="100%"></stop></linearGradient></defs><g id="wwx84k" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"><g id="b2ykf5" transform="translate(0.494300, 0.001035)"><path class="logo-path" d="M48.6233553,50.4985671 C89.7217309,33.087751 127.16881,33.2898824 156.705752,39.4979096 L159.083221,40.0141953 L159.083221,40.0141953 L161.425517,40.5554284 C164.91233,41.3853013 168.278732,42.2947318 171.517062,43.2628387 L173.656891,43.9167071 C174.010338,44.0270575 174.362184,44.1380751 174.712417,44.2497312 L176.794379,44.9271 L176.794379,44.9271 L178.837133,45.6184172 L178.837133,45.6184172 L180.840173,46.3223079 L180.840173,46.3223079 L182.802997,47.0373973 L182.802997,47.0373973 L184.725098,47.7623103 L189.965252,49.7682932 C193.936509,59.7432727 196.463481,70.0037926 197.532574,80.3253175 L196.102281,79.7490932 C195.860245,79.6531908 195.616391,79.5573776 195.37073,79.4616862 L193.875178,78.8892609 L193.875178,78.8892609 L192.336837,78.3213243 L192.336837,78.3213243 L190.756279,77.7594414 C190.489366,77.6663856 190.220719,77.5736473 189.950348,77.481259 L188.307539,76.9313908 C188.030322,76.8405331 187.751407,76.7500907 187.470804,76.6600961 L185.767032,76.1257635 L185.767032,76.1257635 L184.023336,75.6037442 C183.435513,75.4319639 182.841131,75.2624966 182.240286,75.0956031 L180.418457,74.602905 L180.418457,74.602905 L178.558421,74.1272149 C177.932105,73.9716601 177.299516,73.8192008 176.660751,73.6700977 L174.726019,73.2331183 C174.074996,73.0909886 173.41789,72.9524761 172.754799,72.8178415 L170.747664,72.4258323 L170.747664,72.4258323 L168.705186,72.0586555 L168.705186,72.0586555 L166.627938,71.7178761 C143.938151,68.1747363 115.228263,69.5541214 83.7757013,84.8093831 C68.2938973,74.4377471 56.8873968,62.6775526 48.6233553,50.4985671 Z M37.9774505,197.760338 L39.3544881,196.641887 L39.3544881,196.641887 L40.7356544,195.491961 L40.7356544,195.491961 L42.1198121,194.310286 C42.5815702,193.911073 43.0436374,193.506523 43.505824,193.096591 L44.8925532,191.850601 C45.1236713,191.64023 45.3547719,191.428502 45.5858313,191.215411 L46.971504,189.92047 C47.2023039,189.701906 47.4330152,189.481968 47.6636142,189.26065 L49.0456718,187.916142 C49.2757271,187.689283 49.5056227,187.461032 49.7353349,187.231385 L51.1112189,185.836697 C51.5689872,185.366183 52.0258327,184.890037 52.4815657,184.408213 L53.8452381,182.945662 C54.2985569,182.452437 54.7505737,181.953489 55.201099,181.448771 L56.5480113,179.917266 C56.9953643,179.400965 57.4410363,178.87885 57.8848378,178.350875 L59.2104413,176.749324 C74.3859903,158.128283 87.1096014,132.478397 89.5986743,97.9319994 C109.003523,88.4459163 127.168396,84.8539424 143.338977,84.5869749 C136.975419,126.65885 119.001629,157.837159 99.6714082,180.091077 L98.0114494,181.976746 C96.6257808,183.53 95.2346863,185.038009 93.8419028,186.50149 L92.169991,188.236339 L92.169991,188.236339 L90.4978927,189.928717 C89.3834172,191.042874 88.2697739,192.128899 87.1588762,193.187162 L85.4948278,194.75378 L85.4948278,194.75378 L83.8363326,196.279029 L83.8363326,196.279029 L82.1848256,197.763185 C81.0864692,198.738985 79.9937281,199.687574 78.9085157,200.609318 L77.2865827,201.971847 C77.0172813,202.195594 76.7485102,202.417676 76.4802994,202.638097 L74.8779963,203.940742 L71.71575,206.478946 C60.0872529,205.303824 48.7244463,202.348767 37.9774505,197.760338 Z M61.3539044,147.308555 C13.9935954,111.095309 2.54660708,63.5724092 0.418854097,34.4998834 L0.28090179,32.4308102 C0.260491251,32.09146 0.241340463,31.7548921 0.223402913,31.4211585 L0.129964397,29.4531861 L0.129964397,29.4531861 L0.0634111142,27.5557372 L0.0634111142,27.5557372 L0.0215105612,25.7313098 C0.0164575019,25.4334625 0.0123386187,25.1387619 0.00910740117,24.84726 L7.58676276e-14,23.1370494 L7.58676276e-14,23.1370494 L0.00996407156,21.5061061 L0.00996407156,21.5061061 L0.036767113,19.9569285 L0.036767113,19.9569285 L0.24115837,12.3844802 C4.52411565,7.93819887 9.19178759,3.78450488 14.25525,-4.54893855e-14 L14.4851725,1.53436777 L14.4851725,1.53436777 L14.7387624,3.10614572 C14.7830579,3.37115252 14.8283832,3.63766281 14.8747602,3.90564893 L15.1658167,5.53105463 C15.2164877,5.80483356 15.268254,6.08003304 15.3211375,6.35662545 L15.6520195,8.03267386 L15.6520195,8.03267386 L16.0107622,9.74082572 C16.0729327,10.0281193 16.1363076,10.3166952 16.200909,10.6065259 L16.6034085,12.3603454 C16.6730028,12.6550843 16.7438672,12.9510228 16.8160234,13.248133 L17.2646385,15.0446351 C17.3420498,15.3463221 17.4207966,15.6491255 17.5009009,15.9530179 L17.9979904,17.7892175 L17.9979904,17.7892175 L18.5287067,19.6502239 L18.5287067,19.6502239 L19.094098,21.5347102 C19.6771763,23.4304943 20.3138397,25.3595084 21.0088058,27.3157824 L21.7233809,29.2809219 C29.923721,51.3215087 45.5901735,76.5131074 75.2916237,96.5428506 C73.8906279,116.688294 68.6329935,133.431986 61.3539044,147.308555 Z" id="hfe8gf" fill="url(#y4lyj6)"></path><path d="M177.720744,187.960102 C181.261765,187.960102 184.290269,187.7855 186.806258,187.436297 C189.322246,187.087093 191.86153,186.516728 194.424111,185.7252 L194.424111,185.7252 L193.865003,174.620533 C191.628568,174.806775 189.368838,174.946456 187.085812,175.039577 C184.802785,175.132698 181.914058,175.179258 178.41963,175.179258 C176.695712,175.179258 175.48431,174.876615 174.785425,174.271329 C174.086539,173.666044 173.748744,172.525313 173.772041,170.849136 C173.795337,169.17296 174.016651,166.751817 174.435982,163.585706 C174.808721,160.745519 175.193108,158.475697 175.589143,156.77624 C175.985178,155.076784 176.474398,153.808012 177.056803,152.969924 C177.639208,152.131836 178.349742,151.57311 179.188404,151.293747 C180.027067,151.014385 181.075395,150.874703 182.33339,150.874703 C184.476639,150.874703 186.340334,150.886344 187.924475,150.909624 C189.508615,150.932904 191.034516,150.944544 192.502176,150.944544 C193.969835,150.944544 195.542328,150.967824 197.219654,151.014385 L197.219654,151.014385 L198.966868,140.049399 C197.429319,139.583794 195.903419,139.211311 194.389167,138.931948 C192.874915,138.652585 191.220885,138.443063 189.427079,138.303382 C187.633272,138.163701 185.524967,138.09386 183.102164,138.09386 C179.09522,138.09386 175.682328,138.466344 172.863489,139.211311 C170.044651,139.956278 167.715032,141.22505 165.874633,143.017628 C164.034234,144.810205 162.543278,147.289549 161.401765,150.455659 C160.260252,153.62177 159.340052,157.625969 158.641167,162.468255 C157.755912,168.800477 157.8258,173.829005 158.850832,177.553841 C159.875865,181.278677 161.937577,183.944263 165.03597,185.550598 C168.134363,187.156934 172.362621,187.960102 177.720744,187.960102 Z M210.568369,187.401376 C214.575313,187.401376 218.62885,187.354816 222.728979,187.261695 C226.829108,187.168574 230.48661,186.912492 233.701483,186.493448 L233.701483,186.493448 L233.981038,175.109418 L218.046445,175.109418 C216.974821,175.109418 216.264287,174.899896 215.914844,174.480852 C215.565401,174.061807 215.437272,173.34012 215.530457,172.315791 L215.530457,172.315791 L220.492545,138.931948 L205.256838,138.931948 L200.085085,176.157028 C199.758938,178.298808 200.003548,180.219427 200.818915,181.918883 C201.634281,183.61834 202.880627,184.956953 204.557953,185.934722 C206.235278,186.912492 208.23875,187.401376 210.568369,187.401376 Z M258.092592,188.099783 C263.217753,188.099783 267.32953,187.447937 270.427923,186.144244 C273.526316,184.840552 275.902527,182.687131 277.556557,179.683982 C279.210586,176.680833 280.387044,172.618434 281.085929,167.496784 L281.085929,167.496784 L285.209354,138.931948 L270.043536,138.931948 L266.059888,166.938059 C265.640557,169.498883 265.186281,171.500983 264.697061,172.944357 C264.207841,174.387731 263.485659,175.40042 262.530516,175.982426 C261.575372,176.564432 260.212545,176.855434 258.442035,176.855434 C256.764709,176.855434 255.530011,176.587712 254.737941,176.052267 C253.945871,175.516822 253.503243,174.562332 253.410058,173.188799 C253.316873,171.815266 253.456651,169.917927 253.82939,167.496784 L253.82939,167.496784 L257.882926,138.931948 L242.786997,138.931948 L238.73346,166.938059 C238.081167,172.059708 238.372369,176.168668 239.607067,179.264938 C240.841765,182.361208 243.00831,184.607749 246.106703,186.004563 C249.205096,187.401376 253.200393,188.099783 258.092592,188.099783 Z M285.279243,187.122014 L286.984961,187.22241 C288.684126,187.318441 290.363636,187.401376 292.023489,187.471217 C294.236627,187.564338 296.682727,187.622538 299.361788,187.645819 C301.683642,187.665995 304.364205,187.677428 307.403477,187.680118 L308.831689,187.680739 C312.838633,187.680739 316.135044,187.354816 318.72092,186.70297 C321.306797,186.051123 323.298621,184.852192 324.696393,183.106175 C326.094164,181.360158 327.002715,178.880814 327.422046,175.668143 C327.981155,171.663944 327.58512,168.521114 326.233941,166.239652 C324.882762,163.95819 322.296885,162.654497 318.47631,162.328574 L318.47631,162.328574 L318.546199,161.90953 C321.90085,161.630167 324.277061,160.652398 325.674832,158.976222 C327.072604,157.300046 328.004451,154.809061 328.470375,151.50327 C328.936299,148.104357 328.645096,145.450411 327.596768,143.541433 C326.548439,141.632454 324.731337,140.293841 322.14546,139.525594 C319.559583,138.757346 316.123395,138.373223 311.836897,138.373223 C309.041354,138.373223 306.525366,138.384863 304.288932,138.408143 C302.052498,138.431423 299.955841,138.477984 297.998961,138.547824 C296.042082,138.617665 294.061906,138.745706 292.058434,138.931948 L292.058434,138.931948 L285.279243,187.122014 Z M305.480033,148.849324 L311.347677,148.849324 C312.18634,148.849324 312.815337,148.965725 313.234668,149.198527 C313.654,149.431329 313.921906,149.815453 314.038387,150.350898 C314.154868,150.886344 314.14322,151.619671 314.003442,152.55088 C313.817073,153.947693 313.549167,155.006943 313.199724,155.72863 C312.850281,156.450317 312.349413,156.939202 311.69712,157.195285 C311.044827,157.451367 310.112979,157.579408 308.901577,157.579408 L308.901577,157.579408 L304.251991,157.579408 L305.480033,148.849324 Z M305.896369,177.204638 C304.436475,177.204638 303.030938,177.202051 301.679759,177.196878 L301.491393,177.196656 L302.964045,166.728537 L308.622023,166.728537 C309.680941,166.728537 310.499195,166.834356 311.076787,167.045994 L311.242844,167.11266 C311.825249,167.368743 312.18634,167.834347 312.326117,168.509474 C312.465894,169.1846 312.442598,170.17401 312.256228,171.477703 C312.023266,173.060758 311.69712,174.259689 311.277788,175.074497 C310.858457,175.889305 310.22946,176.448031 309.390797,176.750673 C308.552134,177.053316 307.387325,177.204638 305.896369,177.204638 Z" id="lw69ms" fill="#2967BA" fill-rule="nonzero"></path><path d="M172.376258,123.773196 C173.94228,123.773196 175.024258,123.004948 175.622194,121.468454 L175.622194,121.468454 L186.598581,94.3237113 L176.860774,94.3237113 L170.454323,113.316495 C170.255011,113.914021 170.069935,114.511546 169.899097,115.109072 C169.728258,115.706598 169.585892,116.332577 169.472,116.98701 L169.472,116.98701 L168.361548,116.98701 C168.390022,116.361031 168.411376,115.720825 168.425613,115.066392 C168.439849,114.411959 168.418495,113.771753 168.361548,113.145773 L168.361548,113.145773 L167.336516,94.3237113 L158.025806,94.3237113 L161.357161,121.12701 C161.442581,121.866804 161.755785,122.492784 162.296774,123.004948 C162.837763,123.517113 163.506882,123.773196 164.304129,123.773196 L164.304129,123.773196 L172.376258,123.773196 Z M194.67071,124.285361 C197.518022,124.285361 199.824344,123.943918 201.589677,123.261031 C203.355011,122.578144 204.714602,121.447113 205.668452,119.867938 C206.622301,118.288763 207.284301,116.161856 207.654452,113.487216 C208.053075,110.641856 207.924946,108.37268 207.270065,106.679691 C206.615183,104.986701 205.412194,103.756082 203.661097,102.987835 C201.91,102.219588 199.610796,101.835464 196.763484,101.835464 C193.916172,101.835464 191.595613,102.184021 189.801806,102.881134 C188.008,103.578247 186.64129,104.723505 185.701677,106.316907 C184.762065,107.910309 184.092946,110.072784 183.694323,112.80433 C183.295699,115.592784 183.438065,117.826392 184.121419,119.505155 C184.804774,121.183918 186.029118,122.400309 187.794452,123.15433 C189.559785,123.908351 191.851871,124.285361 194.67071,124.285361 Z M195.012387,118.224742 C194.272086,118.224742 193.723978,118.103814 193.368065,117.861959 C193.012151,117.620103 192.827075,117.157732 192.812839,116.474845 C192.798602,115.791959 192.876903,114.796082 193.047742,113.487216 C193.275527,111.922268 193.517548,110.741443 193.773806,109.944742 C194.030065,109.148041 194.371742,108.607423 194.798839,108.322887 C195.225935,108.038351 195.823871,107.896082 196.592645,107.896082 C197.304473,107.896082 197.816989,108.024124 198.130194,108.280206 C198.443398,108.536289 198.614237,109.012887 198.64271,109.71 C198.671183,110.407113 198.585763,111.438557 198.386452,112.80433 C198.158667,114.283918 197.923763,115.414948 197.681742,116.197423 C197.43972,116.979897 197.119398,117.513402 196.720774,117.797938 C196.322151,118.082474 195.752688,118.224742 195.012387,118.224742 Z M216.922452,124.370722 C217.406495,124.370722 218.040022,124.335155 218.823032,124.264021 C219.606043,124.192887 220.389054,124.086186 221.172065,123.943918 C221.955075,123.801649 222.574366,123.602474 223.029935,123.346392 L223.029935,123.346392 L222.517419,117.541856 L220.809032,117.541856 C220.040258,117.541856 219.541978,117.449381 219.314194,117.264433 C219.086409,117.079485 219.029462,116.716701 219.143355,116.176082 L219.143355,116.176082 L222.346581,93.8969072 L213.420258,93.8969072 L209.960774,118.224742 C209.732989,120.10268 210.238387,121.596495 211.476968,122.706186 C212.715548,123.815876 214.53071,124.370722 216.922452,124.370722 Z M232.297935,124.370722 C232.781978,124.370722 233.415505,124.335155 234.198516,124.264021 C234.981527,124.192887 235.764538,124.086186 236.547548,123.943918 C237.330559,123.801649 237.949849,123.602474 238.405419,123.346392 L238.405419,123.346392 L237.892903,117.541856 L236.184516,117.541856 C235.415742,117.541856 234.917462,117.449381 234.689677,117.264433 C234.461892,117.079485 234.404946,116.716701 234.518839,116.176082 L234.518839,116.176082 L237.722065,93.8969072 L228.795742,93.8969072 L225.336258,118.224742 C225.108473,120.10268 225.613871,121.596495 226.852452,122.706186 C228.091032,123.815876 229.906194,124.370722 232.297935,124.370722 Z M251.944387,124.285361 C253.083312,124.285361 254.236473,124.228454 255.403871,124.114639 C256.571269,124.000825 257.72443,123.801649 258.863355,123.517113 C260.00228,123.232577 261.070022,122.86268 262.066581,122.407423 L262.066581,122.407423 L261.468645,116.94433 C260.614452,117.029691 259.610774,117.107938 258.457613,117.179072 C257.304452,117.250206 256.208237,117.307113 255.168968,117.349794 C254.129699,117.392474 253.311097,117.413814 252.713161,117.413814 C251.745075,117.413814 251.019011,117.307113 250.534968,117.093711 C250.050925,116.880309 249.773312,116.432165 249.702129,115.749278 C249.693231,115.663918 249.686669,115.572999 249.682443,115.476524 L249.680774,115.467588 L255.019484,115.066392 C256.713634,114.941907 258.151638,114.741176 259.333495,114.464198 L259.824323,114.340825 C261.091376,113.999381 262.080817,113.47299 262.792645,112.761649 C263.504473,112.050309 263.945806,111.054433 264.116645,109.774021 C264.372903,108.038351 264.223419,106.587216 263.668194,105.420619 C263.112968,104.254021 262.023871,103.364845 260.400903,102.753093 C258.777935,102.14134 256.485849,101.835464 253.524645,101.835464 C251.616946,101.835464 249.908559,101.991959 248.399484,102.304948 C246.890409,102.617938 245.594882,103.172784 244.512903,103.969485 C243.430925,104.766186 242.548258,105.890103 241.864903,107.341237 C241.181548,108.792371 240.725978,110.656082 240.498194,112.932371 C240.270409,115.350928 240.441247,117.406701 241.01071,119.099691 C241.580172,120.79268 242.733333,122.080206 244.470194,122.962268 C246.207054,123.84433 248.698452,124.285361 251.944387,124.285361 Z M250.229594,111.086443 L250.25223,110.989559 C250.323982,110.668602 250.396873,110.377237 250.470903,110.115464 C250.655978,109.461031 250.876645,108.955979 251.132903,108.600309 C251.389161,108.244639 251.716602,107.988557 252.115226,107.832062 C252.513849,107.675567 253.012129,107.59732 253.610065,107.59732 C254.208,107.568866 254.649333,107.59732 254.934065,107.68268 C255.218796,107.768041 255.403871,107.910309 255.48929,108.109485 C255.57471,108.30866 255.603183,108.564742 255.57471,108.877732 C255.517763,109.418351 255.36828,109.816701 255.126258,110.072784 C254.884237,110.328866 254.563914,110.506701 254.16529,110.606289 C253.766667,110.705876 253.268387,110.784124 252.670452,110.841031 L252.670452,110.841031 L250.229594,111.086443 Z M267.362581,132.565361 C269.782796,132.679175 271.989462,132.415979 273.982581,131.775773 C275.975699,131.135567 277.805097,130.111237 279.470774,128.702784 C281.136452,127.29433 282.638409,125.53732 283.976645,123.431753 C284.318323,122.919588 284.752538,122.115773 285.27929,121.020309 C285.806043,119.924845 286.375505,118.644433 286.987677,117.179072 C287.599849,115.713711 288.204903,114.141649 288.802839,112.462887 C289.400774,110.784124 289.934645,109.084021 290.404452,107.362577 C290.874258,105.641134 291.208817,103.997938 291.408129,102.43299 L291.408129,102.43299 L281.798452,102.43299 C281.428301,104.993814 281.022559,107.298557 280.581226,109.347216 C280.139892,111.395876 279.627376,113.280928 279.043677,115.002371 C278.693458,116.035237 278.299675,117.050177 277.862328,118.047192 L277.702594,118.395464 L276.67329,118.395464 C276.282802,118.395464 276.059666,118.228226 276.003882,117.893751 L275.989935,117.712577 L275.648258,102.43299 L265.825032,102.43299 L268.473032,121.511134 C268.558452,122.137113 268.843183,122.670619 269.327226,123.111649 C269.811269,123.55268 270.409204,123.773196 271.121032,123.773196 L271.121032,123.773196 L274.484419,123.773196 L274.237059,124.041134 C273.719797,124.57543 273.193045,125.021203 272.656801,125.378454 L272.252839,125.629794 C271.441355,126.099278 270.587161,126.476289 269.690258,126.760825 C268.793355,127.045361 267.832387,127.372577 266.807355,127.742474 L266.807355,127.742474 L267.362581,132.565361 Z" id="kzxh97" fill="#2967BA" fill-rule="nonzero"></path></g></g></svg>
		</div>
	</div> 	
	<div class="sgroup sgroup-1">
		<div class="close-sgroup">&times;</div>
		<div class="img-box"><img data-light="/img/main/bg-1-1.png" data-dark="/img/main/bg-1-1-d.png" alt="Игрокам" src="/img/pixel.png"></div>
		<div class="title-box">Игрокам</div>
		<div class="info-box">
			<div class="row row2">
				<div class="col-md-6">
					<div class="audience-card">
						<div>
							<div class="audience-top">
								<div class="audience-icon">
									🏐										
								</div>												
								<div class="audience-title">
									<div class="f-20 b-600 cd">Для игроков</div>
									Играйте больше, находите партнёров, развивайтесь.
								</div>									
							</div>	
							
							<ul class="list f-16">
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
						<div class="mt-1 text-center">
							<a href="{{ route('events.index') }}" class="btn">Найти игру</a>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="audience-card">
						<div>
							<div class="audience-top">
								<div class="audience-icon">
									👪
								</div>											
								<div class="audience-title">
									<div class="f-20 b-600 cd">Найдите своих</div>
									Сообщество игроков вашего уровня.
								</div>										
							</div>
							<ul class="list f-16">
								<li>Каталог игроков с уровнем и амплуа</li>
								<li>Фильтр по городу, уровню, направлению</li>
								<li>Пляжные пары и командная запись</li>
								<li>Приглашайте партнёров на мероприятия</li>
								<li>Авторизация через Telegram, VK или Яндекс</li>
							</ul>
						</div>
						<div class="mt-1 text-center">
							<a href="{{ route('users.index') }}" class="btn">Каталог игроков</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="sgroup sgroup-2">
		<div class="close-sgroup">&times;</div>
		<div class="img-box"><img data-light="/img/main/bg-2.png" data-dark="/img/main/bg-2-d.png" alt="Тренерам" src="/img/pixel.png"></div>
		<div class="title-box">Тренерам</div>
		<div class="info-box">
			<div class="row row2">
				<div class="col-md-6">
					<div class="audience-card">
						<div>
							<div class="audience-top">
								<div class="audience-icon">
									🎓										
								</div>												
								<div class="audience-title">
									<div class="f-20 b-600 cd">Для тренеров</div>
									Организуйте тренировки и развивайте учеников
								</div>									
							</div>																	
							<ul class="list f-16">
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
						</div>
						<div class="mt-1 text-center">
							<a href="{{ route('volleyball_school.index') }}" class="btn">Школы волейбола</a>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="audience-card">
						<div>
							<div class="audience-top">
								<div class="audience-icon">
									📋									
								</div>												
								<div class="audience-title">
									<div class="f-20 b-600 cd">Станьте организатором</div>
									Хотите проводить тренировки через сервис?
								</div>									
							</div>								
							<ul class="list f-16">
								<li>Подайте заявку на статус организатора</li>
								<li>Бесплатный доступ к инструментам</li>
								<li>Ваши мероприятия — в общем каталоге</li>
								<li>Поддержка команды платформы</li>
							</ul>
						</div>
						<div class="mt-1 text-center">
							@guest
							<a href="{{ route('register') }}" class="btn">Зарегистрироваться</a>
							@else
							<a href="{{ route('profile.complete') }}" class="btn">Мой профиль</a>
							@endguest
						</div>
					</div>
				</div>
			</div>
		</div>	
	</div>  
	<div class="sgroup sgroup-3">
		<div class="close-sgroup">&times;</div>
		<div class="img-box"><img data-light="/img/main/bg-3.png" data-dark="/img/main/bg-3-d.webp" alt="Организаторам" src="/img/pixel.png"></div>
		<div class="title-box">Организаторам</div>
		<div class="info-box">
			<div class="row row2">
				<div class="col-md-6">
					<div class="audience-card">
						<div>
							<div class="audience-top">
								<div class="audience-icon">
									📣									
								</div>												
								<div class="audience-title">
									<div class="f-20 b-600 cd">Для организаторов</div>
									Проводите игры, турниры и лиги.
								</div>									
							</div>								
							<ul class="list f-16">								
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
						</div>
						<div class="mt-1 text-center">
							<a href="{{ route('events.create.event_management') }}" class="btn">Управление играми</a>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="audience-card">
						<div>
							<div class="audience-top">
								<div class="audience-icon">
									📢								
								</div>												
								<div class="audience-title">
									<div class="f-20 b-600 cd">Анонсы и уведомления</div>
									Держите игроков в курсе автоматически.
								</div>									
							</div>								
							<ul class="list f-16">										
								<li>Анонсы в Telegram и VK каналы</li>
								<li>Уведомления о записи и отмене</li>
								<li>Напоминания за N часов до начала</li>
								<li>Личные уведомления каждому игроку</li>
								<li>Настраиваемые шаблоны сообщений</li>
								<li>Приватные мероприятия по ссылке</li>
							</ul>
						</div>
						<div class="mt-1 text-center">
							<a href="{{ route('events.create') }}" class="btn">Создать мероприятие</a>
						</div>
					</div>
				</div>
			</div>
		</div>	
	</div>
	<div class="sgroup sgroup-4">
		<div class="close-sgroup">&times;</div>
		<div class="img-box"><img data-light="/img/main/bg-4.png" data-dark="/img/main/bg-2-d.webp" alt="Спортцентрам" src="/img/pixel.png"></div>
		<div class="title-box">Спортцентрам</div>
		<div class="info-box">
			<div class="row row2">
				<div class="col-md-6">
					<div class="audience-card">		
						<div>
							<div class="audience-top">
								<div class="audience-icon">
									🏟️							
								</div>												
								<div class="audience-title">
									<div class="f-20 b-600 cd">Для спортивных центров</div>
									Привлекайте игроков и наполняйте залы.
								</div>									
							</div>								
							<ul class="list f-16">									
								<li>Страница вашей локации с фото и картой</li>
								<li>Игроки находят вас через каталог площадок</li>
								<li>Все мероприятия на вашей площадке — в одном месте</li>
								<li>Фильтр «Только с активными играми»</li>
								<li>Карта локаций для удобного поиска</li>
								<li>Страница школы/сообщества для вашего бренда</li>
							</ul>
						</div>
						<div class="mt-1 text-center">
							<a href="{{ route('locations.index') }}" class="btn">Каталог локаций</a>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="audience-card">
						<div>
							<div class="audience-top">
								<div class="audience-icon">
									🏫						
								</div>												
								<div class="audience-title">
									<div class="f-20 b-600 cd">Школа волейбола</div>
									Создайте публичную страницу вашей школы.
								</div>									
							</div>								
							<ul class="list f-16">									
								<li>Логотип, обложка, описание, контакты</li>
								<li>Все ваши мероприятия на одной странице</li>
								<li>Профиль организатора / тренера</li>
								<li>Ваш бренд в каталоге школ волейбола</li>
								<li>Классика и пляжный — любое направление</li>
								<li>Абонементы и купоны</li>
							</ul>
						</div>
						<div class="mt-1 text-center">
							<a href="{{ route('volleyball_school.index') }}" class="btn">Школы волейбола</a>
						</div>
					</div>
				</div>
			</div>
		</div>	
	</div>
</div>
<script>
document.addEventListener("DOMContentLoaded", () => {
	var ThemeManager = (function() {		
		function init() {
			var isDark = localStorage.getItem('theme') === 'dark';
			updateImages(isDark);
		}	
		function updateImages(isDark) {
			$('.sgroup .img-box img').each(function() {
				var $img = $(this);
				var lightSrc = $img.data('light');
				var darkSrc = $img.data('dark');
				
				if (isDark && darkSrc) {
					$img.attr('src', darkSrc);
				} else if (!isDark && lightSrc) {
					$img.attr('src', lightSrc);
				}
			});
		}		
		function refresh() {
			var isDark = $('body').hasClass('dark');
			updateImages(isDark);
		}		
		return {
			init: init,
			refresh: refresh
		};		
	})();
	
	ThemeManager.init(); // ← просто так, без $(document).ready()
	
	$(document).on('click', '.fix-header-btn-theme', function() {
		setTimeout(() => {
			ThemeManager.refresh();
		}, 10);
	});
	
	// ===== BOXLAYOUT (без изменений) =====
	var Boxlayout = (function () {
		var wrapper = document.querySelector(".sgroup-container"),
		sgroups = Array.from(document.querySelectorAll(".sgroup")),
		closeButtons = Array.from(document.querySelectorAll(".close-sgroup")),
		expandedClass = "is-expanded",
		hasExpandedClass = "has-expanded-item";
		
		return { init: init };
		
		function init() {
			_initEvents();
		}
		
		function _initEvents() {
			sgroups.forEach(function (element) {
				element.onclick = function (e) {
					e.stopPropagation();
					_opensgroup(this);
				};
			});
			
			closeButtons.forEach(function (element) {
				element.onclick = function (e) {
					e.stopPropagation();
					_closesgroup(this.parentElement);
				};
			});
			
			document.addEventListener('click', function(e) {
				if (!wrapper.classList.contains(hasExpandedClass)) {
					return;
				}
				
				if (!wrapper.contains(e.target)) {
					var expandedSgroup = wrapper.querySelector('.sgroup.is-expanded');
					if (expandedSgroup) {
						_closesgroup(expandedSgroup);
					}
				}
			});		
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && wrapper.classList.contains(hasExpandedClass)) {
					var expandedSgroup = wrapper.querySelector('.sgroup.is-expanded');
					if (expandedSgroup) {
						_closesgroup(expandedSgroup);
					}
				}
			});
		}		
		function _opensgroup(element) {
			if (!element.classList.contains(expandedClass)) {
				element.classList.add(expandedClass);
				wrapper.classList.add(hasExpandedClass);
			}
		}
		
		function _closesgroup(element) {
			if (element.classList.contains(expandedClass)) {
				element.classList.remove(expandedClass);
				wrapper.classList.remove(hasExpandedClass);
			}
		}
	})();
	Boxlayout.init();	
});	
</script>
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
	transition: transform 1s, opacity 1s, background 0.3s, color 0.1s!important;
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
	transition-delay: 0.1s, 0.1s, 0s, 0s!important;
}	
.steps ul li:nth-child(3) {	
	left: calc(50% - 2rem);
	position: relative;
	transition-delay: 0.2s, 0.2s, 0s, 0s!important;
}	
.steps ul li:last-child {	
	left:  calc(75% - 2rem);
	width: calc(25% + 2rem);
	clip-path: polygon(100% 0%,100% 100%,0% 100%, 6rem 50%,0% 0%);
	padding: 2rem 2rem 2rem 8rem;
	transition-delay: 0.3s, 0.3s, 0s, 0s!important;
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
			<div class="cta-block">
				<div class="f-32 mb-1">🏐 Готовы играть?</div>
				<div class="f-18 mb-3">
					Присоединяйтесь к сообществу волейболистов — бесплатно и без лишних шагов.
				</div>
				<div class="d-flex flex-wrap gap-2" style="justify-content:center">
					<a href="{{ route('events.index') }}" class="btn">Смотреть игры</a>
				</div>
			</div>
		</div>
		@endguest
	</div>
	
	
</x-voll-layout>	