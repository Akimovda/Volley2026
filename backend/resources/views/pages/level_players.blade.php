{{-- resources/views/pages/level_players.blade.php --}}
<x-voll-layout body_class="level">
	<x-slot name="title">
		Уровни игроков
	</x-slot>
	
    <x-slot name="description">
		Определения с форума volleymsk.ru + наши дополнения к уровню «Продолжающий любитель (уверенный)».
	</x-slot>
	
    <x-slot name="t_description">
		Определения с форума <span class="bold">volleymsk.ru</span> + наши дополнения к уровню «Продолжающий любитель (уверенный)».
	</x-slot>
	
    <x-slot name="canonical">
        {{ route('level_players') }}
	</x-slot>
	
    <x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('level_players') }}" itemprop="item"><span itemprop="name">Уровни игроков</span></a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>	
    <x-slot name="h1">
        Уровни игроков
	</x-slot>	
	
    <div class="container">
        <div class="ramka">
			<div class="level-section">
				<div class="tabs-content">
					<div class="tabs">
						<h2 class="tab active" data-tab="classic">Классика</h2>
						<h2 class="tab" data-tab="beach">Пляжка</h2>
						<div class="tab-highlight"></div>
					</div>
					
					
					<div class="tab-panes">
						
						<div class="tab-pane active" id="classic">	
							
							<div class="tabs-content">
								<div class="tabs mb-3">
									<h3 class="tab active" data-tab="classic-child">Подростки</h3>
									<h3 class="tab" data-tab="classic-old">Взрослые</h3>
									<div class="tab-highlight"></div>
								</div>
								
								<div class="tab-panes">
									<div class="tab-pane active" id="classic-child">		
										
										
										
										<div class="row">
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>Начальный уровень <span class="level-points">0&nbsp;баллов</span></h4>
													<p>Обучение с нуля, когда ученик впервые приходит на тренировку и обучается всем навыкам с самого начала. Углублённое обучение всем техническим навыкам: передача сверху, передача снизу, техника нападающего удара, правильный разбег, правильные постановки рук и т.д. А также обучение теории, знание расстановки.</p>
												</div>
											</div>
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>Начальный уровень + <span class="level-points">0&nbsp;баллов</span></h4>
													<p>Кто уже имеет понимание в расстановке, знает где нужно стоять, а также владеет техническими навыками на начальной стадии. И также есть начальные физические навыки.</p>
												</div>
											</div>
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>Средний уровень <span class="level-points">0&nbsp;баллов</span></h4>
													<p>Ребята знающие расстановку, понимающие как можно меняться и играющие по схеме 4/2. Технически оснащены и все действия выполняют стабильно (подача, атака, передача), хорошо физически подготовлены.</p>
												</div>
											</div>
										</div>
									</div>	
									<div class="tab-pane" id="classic-old">		
										
										
										<div class="table-scrollable">
											<div class="table-drag-indicator"></div>
											<table class="table table-levels">
												<thead>
													<tr>
														<th style="min-width:26rem">Уровень</th>
														<th>Описание</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td>
															<div class="text-center">
																<strong class="levelmark level-1">1 - Начальный</strong>
															</div>
														</td>
														<td>Перебрасываем мяч через сетку в свое удовольствие, двумя руками или как получится. Кто умеет — подает сверху и атакует выше троса. Понятия амплуа отсутствуют — играют кто как может!</td>
													</tr>
													<tr>
														<td>
															<div class="text-center">
																<strong class="levelmark level-2">2 - Начальный +</strong>
															</div>
														</td>
														<td>Играем со всеми основными элементами: подача, прием, передача, атака, блок. Выделенные пасующие (обычно двое). Иногда получается «гасить», «ставить блок в тапки» и т.д. Понятия амплуа — только в части связующего. Техника паса у связующего отсутствует — пасует, как получится.</td>
													</tr>
													<tr>
														<td>
															<div class="text-center">
																<strong class="levelmark level-3">3 - Средний −</strong>
															</div>
														</td>
														<td>Стабильная и уверенная подача, прием, передача, атака, блок. Активная игра на первой линии и в защите. Выделенные пасующие (обычно двое). Знание расстановки игроков на площадке в каждой зоне. Понятия амплуа — только в части связующего. Техника паса есть, но не всегда получается — есть к чему стремиться.</td>
													</tr>
													<tr>
														<td>
															<div class="text-center">
																<strong class="levelmark level-4">4 - Средний</strong>
															</div>
														</td>
														<td>Стабильная атака, двойной блок. Как правило игра с одним пасующим (5-1) и с игроками первого темпа. Примерно на этом уровне играют средние и лидирующие команды 3–4 лиги ЛВЛ. Присутствуют понятия амплуа. У связующего хорошая техника передач, но не всегда получается играть с первым темпом и диагональными. Либеро: прием 70% и нормальная доводка до связующего.</td>
													</tr>
													<tr>
														<td>
															<div class="text-center">
																<strong class="levelmark level-5">5 - Средний +</strong>
															</div>
														</td>
														<td>Темповая и комбинационная игра (взлеты-волны-прострелы). Связка 5-1, может довести мяч до любой зоны и поддержать любую комбинацию. Уровень примерно 2 лиги ЛВЛ или перворазрядники-выпускники спортшкол. Либеро: 80% приема и доводка до связующего.</td>
													</tr>
													<tr class="level-pro">
														<td>
															<div class="text-center">
																<strong class="levelmark level-6">6 - Полупрофи (К.М.С)</strong>
															</div>
														</td>
														<td>Игроки уровня перворазрядников и КМС, бывшие профессионалы, текущие профессиональные пляжники. Команды уровня 1 и Высшей лиги ЛВЛ, КФК, высшей лиги МО.</td>
													</tr>
													<tr class="level-pro">
														<td>
															<div class="text-center">
																<strong class="levelmark level-7">7 - Профи (М.С.)</strong>
															</div>
														</td>
														<td>Игрок состоит в проф. команде, его работа — тренироваться и играть за клуб.</td>
													</tr>
													<tr class="level-banned">
														<td>
															<div class="text-center">
																<strong class="text-center">Уровень&nbsp;«БОГ»</strong>
																<strong class="text-center">БАН&nbsp;!</strong>
															</div>
														</td>
														<td>«Бог» — самоуверенный средний любитель, неадекватно оценивающий свой уровень.</td>
													</tr>
												</tbody>
											</table>
										</div>
									</div>
								</div>
							</div>
						</div>
						<!-- ПЛЯЖНЫЙ ВОЛЕЙБОЛ -->
						<div class="tab-pane" id="beach">
							
							<div class="tabs-content">
								<div class="tabs mb-3">
									<h3 class="tab" data-tab="beach-child">Подростки</h3>
									<h3 class="tab" data-tab="beach-old">Взрослые</h3>
									<div class="tab-highlight"></div>
								</div>
								
								<div class="tab-panes">
									
									<div class="tab-pane" id="beach-child">						
										<div class="row">
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>Начальный уровень <span class="level-points">0&nbsp;баллов</span></h4>
													<p>Обучение с нуля… (передача сверху/снизу, техника удара, разбег, постановка рук и т.д.), теория и расстановка.</p>
												</div>
											</div>
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>Начальный уровень + <span class="level-points">0&nbsp;баллов</span></h4>
													<p>Понимание расстановки, где стоять, базовые навыки на начальной стадии + начальная физ. подготовка.</p>
												</div>
											</div>
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>Средний уровень <span class="level-points">0&nbsp;баллов</span></h4>
													<p>Знание расстановки, схема 4/2, техническая стабильность (подача/атака/передача), хорошая физическая подготовка.</p>
												</div>
											</div>
										</div>									
										
										
									</div>
									<div class="tab-pane" id="beach-old">						
										
										<div class="table-scrollable">
											<!-- Индикатор drag-скролла -->
											<div class="table-drag-indicator"></div>
											
											
											<!-- Сама таблица -->
											<table class="table table-levels">
												<thead>
													<tr>
														<th style="min-width:26rem">Уровень</th>
														<th>Описание</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td>
															<div class="text-center">
																<strong class="levelmark level-1">1 - Начальный</strong>
															</div>
														</td>
														<td>Игрок только учится делать элементы: пас сверху/снизу, удар, подача, перемещения и стойки, базовые расстановки. Игра ещё не вырисовывается, элементы нестабильны. Цель — правильная техника в простых условиях.</td>
													</tr>
													<tr>
														<td>
															<div class="text-center">
																<strong class="levelmark level-2">2 - Начальный +</strong>
															</div>
														</td>
														<td>Умеет базовые элементы: стабильно подаёт, умеет напрыжку и удар в прыжке, пас в зону атаки, приём простых подач и несильных ударов. Цель — применять технику в сложных условиях (приём ударов/отскоков, точные пасы, усиление подачи).</td>
													</tr>
													<tr>
														<td>
															<div class="text-center">
																<strong class="levelmark level-3">3 - Средний −</strong>
															</div>
														</td>
														<td>Умеет атаковать/защищаться/пасовать/принимать, но есть ошибки в точности и качестве + не хватает физики (прыжок, сила удара, «дотянуться» в защите, подача в прыжке). Есть знания зон и расстановки; понимает знаки партнера, но не всегда может подсказать зону атаки напарнику.</td>
													</tr>
													<tr>
														<td>
															<div class="text-center">
																<strong class="levelmark level-4">4 - Средний</strong>
															</div>
														</td>
														<td>Стабильная атака/защита, точные пасы и приёмы. Ошибка чаще возникает при сильной подаче/атаке соперника или при риске на подаче/атаке. Цель — тактика: защита от ударов/скидок, обвод блока, игра «на подачах».</td>
													</tr>
													<tr>
														<td>
															<div class="text-center">
																<strong class="levelmark level-5">5 - Средний +</strong>
															</div>
														</td>
														<td>Хорошая техника, упор на тактику: подачи вразрез, удары мимо блока, блок-аут, скидки, блокирование (если позволяет высота), грамотный выбор позиции в защите. Часто специализация по амплуа или универсал. Уровень 1–2 разряда; по ЛВЛ — выше 2 лиги.</td>
													</tr>
													<tr class="level-pro">
														<td>
															<div class="text-center">
																<strong class="levelmark level-6">6 - Полупрофи (К.М.С)</strong>
															</div>
														</td>
														<td>1 разряд или КМС. Играет на крупных турнирах, высокий уровень техники и тактики, но не состоит на постоянке в проф. клубе.</td>
													</tr>
													<tr class="level-pro">
														<td>
															<div class="text-center">
																<strong class="levelmark level-7">7 - Профи (М.С.)</strong>
															</div>
														</td>
														<td>Игрок состоит в проф. команде, его работа — тренироваться и играть за клуб.</td>
													</tr>
												</tbody>
											</table>
										</div>
										
									</div>									
									
									
								</div>									
							</div>	
							
							
							
						</div>
					</div>
				</div>
				
				
				<h2>Расчёт коэффициента качества игроков</h2>
				
				<p>В описании мероприятия вы будете видеть среднее значение уровня записавшихся игроков: <strong>«Уровень игроков: 4.25 из 7»</strong>. Этот коэффициент сделан специально для вас — чтобы оценить динамику игры или тренировки.</p>
				
				<div class="formula-box">
					<h5 class="mt-0">Как считается (если записались только мужчины)</h5>
					<p>Система складывает баллы и делит на количество записавшихся.</p>
					<p><small>Пример: 18 игроков: 14 × (4 балла), 3 × (5 баллов), 1 × (6 баллов)</small></p>
					<pre>((4*14) + (5*3) + 6) / 18 = 4.28</pre>
				</div>
				
				<div class="formula-box">
					<h5 class="mt-0">Если есть девушки (и девушек меньше, чем мужчин)</h5>
					<p>Формула другая: у девушек понижающий коэффициент (равен количеству записавшихся девушек).</p>
					<p><small>Пример: 18 игроков: 10 × (3 балла), 5 × (4 балла), 3 девушки × (4 балла)</small></p>
					<pre>((3*10) + (4*5) + ((4*3) - 3)) / 18 = 3.28</pre>
					<p><small>Если девушек большинство — формула как в первом примере, без понижения.</small></p>
				</div>
				
				<p class="mt-1 text-right">С уважением, <strong class="c3">Your Volley Club!</strong></p>
			</div>
		</div>
	</div>
</x-voll-layout>

