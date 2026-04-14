{{-- resources/views/pages/help.blade.php --}}
<x-voll-layout body_class="help">
	<x-slot name="title">
		Помощь
	</x-slot>
	
    <x-slot name="description">
		Ответы на частые вопросы и поддержка
	</x-slot>
	
    <x-slot name="t_description">
		Ответы на частые вопросы и поддержка
	</x-slot>
	
    <x-slot name="canonical">
        {{ route('help') }}
	</x-slot>
	
    <x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('help') }}" itemprop="item"><span itemprop="name">Помощь</span></a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>	
    <x-slot name="h1">
        Помощь
	</x-slot>	
	
    <div class="container">
		
		<div class="row row2">
			<div class="col-lg-5 col-xl-4 order-1 order-lg-1">
				<div class="sticky">
					<div class="ramka">	
						
						<nav class="menu-nav">
							<div class="tabs-content">
								<div class="tabs w-100">
									<div class="tab active" data-tab="player">Игрок</div>
									<div class="tab" data-tab="org">Организатор</div>
									<div class="tab-highlight"></div>
								</div>
								<div class="tab-panes">
									<div class="tab-pane active" id="player">	
										<a href="#p1" class="menu-item">
											<span class="menu-text">Регистрация и вход</span>
										</a>
										<a href="#p2" class="menu-item">
											<span class="menu-text">Привязка дополнительных провайдеров</span>
										</a>										
									</div>
									<div class="tab-pane" id="org">
										<a href="#o1" class="menu-item">
											<span class="menu-text">Как стать организатором</span>
										</a>
										<a href="#o2" class="menu-item">
											<span class="menu-text">Как создать мероприятие</span>
										</a>
									</div>						
								</div>
							</div>
						</nav>					
						
						
					</div>
				</div>
			</div>
			
			<div class="col-lg-7 col-xl-8 order-2 order-lg-2">
				<div class="ramka">
					<h2 class="-mt-05" id="p1">Регистрация и вход</h2>
					<p>Тут про то как мы регимся, какие данные запрашиваются, как это работает (чтобы не пугало что сайт просит данные от ВК или ТГ, пароли и доступ к личной переписке с фото :))</p>
					<p>Как потом заходим на сайт</p>
					<p>Что будет если зайти под другим провайдером, не привязав его</p>
					
					<h2 id="p2">Привязка дополнительных провайдеров</h2>
					<p>Как привязать</p>
					<p>Зачем привязать</p>		
					<p>Как отвязать</p>	
				</div>		
				
				
				<div class="ramka">
					<h2 class="-mt-05" id="o1">Как стать организатором</h2>
					<p>Пишем запрос и т.д.</p>

					<h2 id="o2">Как создать мероприятие</h2>
					<p>Как создаем</p>	
				</div>				
				
			</div>
		</div>
		
	</div>
</x-voll-layout>