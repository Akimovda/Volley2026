{{-- resources/views/admin/dashboard/index.blade.php --}}
<x-voll-layout body_class="admin-dashboard-page">
    
    <x-slot name="title">
		Админ-панель
	</x-slot>
    
    <x-slot name="description">
        Сводка по пользователям, провайдерам, блокировкам и мероприятиям
	</x-slot>
    
    <x-slot name="canonical">
        {{ route('admin.dashboard') }}
	</x-slot>
    
    <x-slot name="style">
        <style>
            /* Дополнительные стили для чипов */
            .provider-chip {
			display: inline-flex;
			align-items: center;
			gap: 0.5rem;
			padding: 0.25rem 0.75rem;
			border-radius: 9999px;
			border: 1px solid #e5e7eb;
			background-color: white;
			font-size: 0.75rem;
			font-weight: 600;
            }
            .provider-chip .badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 18px;
			height: 18px;
			border-radius: 9999px;
			border: 1px solid #d1d5db;
			font-size: 11px;
            }
		</style>
	</x-slot>
    
    <x-slot name="h1">
		Админ-панель
	</x-slot>
    
    
    <x-slot name="t_description">
        Статистика и мониторинг системы
	</x-slot>
    
    <x-slot name="d_description">
		<div data-aos-delay="250" data-aos="fade-up">
			<button class="btn ufilter-btn mt-2">Навигация</button>
		</div>	
	</x-slot>	
	
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">Админ-панель</span>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
    
    
    <x-slot name="script">
        <script>
            // Дополнительные скрипты при необходимости
		</script>
	</x-slot>
    
    <div class="container">
		
		
		<div class="users-filter">
			<div class="ramka">
				
				<div class="row">
					<div class="col-sm-6 col-lg-3">	
						<nav class="menu-nav">
							<div class="menu-item-title cd">
								<span class="menu-text">Пользователи</span>
							</div>							
							<a href="/admin/users" class="menu-item">
								<span class="menu-text">Список пользователей</span>
							</a>							
							<a href="/admin/" class="menu-item">
								<span class="menu-text">Управление ботами</span>
							</a>	
							<a href="/admin/" class="menu-item">
								<span class="menu-text">Бан список</span>
							</a>							
							<a href="/admin/" class="menu-item">
								<span class="menu-text">Создать пользователя</span>
							</a>							
						</nav>					
					</div>
					<div class="col-sm-6 col-lg-3">	
						<nav class="menu-nav">
							<div class="menu-item-title cd">
								<span class="menu-text">Мероприятия</span>
							</div>							
							<a href="/events/create/event_management" class="menu-item">
								<span class="menu-text">Управление мероприятиями</span>
							</a>
							<a href="/events/create" class="menu-item">
								<span class="menu-text">Создать мероприятие</span>
							</a>														
						</nav>					
					</div>						
					<div class="col-sm-6 col-lg-3">	
						<nav class="menu-nav">
							<div class="menu-item-title cd">
								<span class="menu-text">Уведомления</span>
							</div>	
							<a href="/admin/broadcasts/" class="menu-item">
								<span class="menu-text">Рассылки</span>
							</a>								
							<a href="/admin/notification-templates" class="menu-item">
								<span class="menu-text">Шаблоны уведомлений</span>
							</a>								
							<a href="/admin/broadcasts/create" class="menu-item">
								<span class="menu-text">Новая рассылка</span>
							</a>															
						</nav>					
					</div>						
					<div class="col-sm-6 col-lg-3">	
						<nav class="menu-nav">
							<div class="menu-item-title cd">
								<span class="menu-text">Контент</span>
							</div>							
							<a href="/admin/locations" class="menu-item">
								<span class="menu-text">Локации</span>
							</a>	
							<a href="/admin/locations/create" class="menu-item">
								<span class="menu-text">Создать локацию</span>
							</a>	
							<a href="/admin/" class="menu-item">
								<span class="menu-text">Новости</span>
							</a>	
							<a href="/admin/" class="menu-item">
								<span class="menu-text">Создать новость</span>
							</a>							
						</nav>					
					</div>						
					
				</div>		
			</div>
		</div>
		
		
		@if (session('status'))
		<div class="ramka">
			<div class="alert alert-success">
				{{ session('status') }}
			</div>
		</div>
		@endif
		
		@php
		// providers map from controller
		$p = $providers ?? [];
		
		// total "has at least 1 provider"
		$totalConnected = ($p['tg_only'] ?? 0) + ($p['vk_only'] ?? 0) + ($p['ya_only'] ?? 0) +
		($p['tg_vk'] ?? 0) + ($p['tg_ya'] ?? 0) + ($p['ya_vk'] ?? 0) +
		($p['ya_vk_tg'] ?? 0);
		@endphp
		
		{{-- ROW 1: KPI CARDS --}}
		<div class="row row2">
			<div class="col-12 col-sm-6 col-lg-3">
				<div class="ramka">
					<div class="card-body">
						<div class="text-muted small">Users</div>
						<div class="fs-1 fw-bold mt-1">{{ $totalUsers }}</div>
						<div class="text-muted small mt-2">Всего пользователей</div>
					</div>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<div class="ramka">
					<div class="card-body">
						<div class="text-muted small">Active</div>
						<div class="fs-1 fw-bold mt-1">{{ $activeUsers }}</div>
						<div class="text-muted small mt-2">Без deleted_at</div>
					</div>
				</div>
			</div>
			
			<div class="col-12 col-sm-6 col-lg-3">
				<div class="ramka">
					<div class="card-body">
						<div class="text-muted small">Events</div>
						<div class="fs-1 fw-bold mt-1">{{ $eventsCount ?? 0 }}</div>
						<div class="text-muted small mt-2">Кол-во мероприятий</div>
					</div>
				</div>
			</div>
			
			<div class="col-12 col-sm-6 col-lg-3">
				<div class="ramka">
					<div class="card-body">
						<div class="text-muted small">Restrictions</div>
						<div class="fs-1 fw-bold mt-1">{{ $eventAllRestrictions ?? 0 }}</div>
						<div class="text-muted small mt-2">Event All (active)</div>
					</div>
				</div>
			</div>
		</div>
		
		{{-- ROW 2: USERS DETAILS --}}
		<div class="ramka">
			<div class="card-body">
				<div class="d-flex align-items-center justify-content-between">
					<div class="fw-semibold fs-5">Users / динамика</div>
					<a href="{{ route('admin.users.index') }}" class="btn btn-primary">Открыть пользователей</a>
				</div>
				
				<div class="row mt-4 text-center text-sm-start">
					<div class="col-6 col-md">
						<div class="text-muted small">Всего</div>
						<div class="fs-4 fw-bold">{{ $totalUsers }}</div>
					</div>
					<div class="col-6 col-md">
						<div class="text-muted small">Активные</div>
						<div class="fs-4 fw-bold">{{ $activeUsers }}</div>
					</div>
					<div class="col-6 col-md">
						<div class="text-muted small">Удалённые</div>
						<div class="fs-4 fw-bold">{{ $deletedUsers }}</div>
					</div>
					<div class="col-6 col-md">
						<div class="text-muted small">Регистраций сегодня</div>
						<div class="fs-4 fw-bold">{{ $usersCreatedToday }}</div>
					</div>
					<div class="col-6 col-md">
						<div class="text-muted small">Удалений сегодня</div>
						<div class="fs-4 fw-bold">{{ $usersDeletedToday }}</div>
					</div>
				</div>
			</div>
		</div>
		
		{{-- ROW 3: PROVIDERS + RESTRICTIONS --}}
		<div class="ramka">
			<div class="row">
				{{-- PROVIDERS --}}
				<div class="col-12 col-lg-8">
					<div class="card">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between mb-3">
								<div class="fw-semibold fs-5">Провайдеры</div>
								<div class="text-muted small">
									С ≥1 провайдером: <strong>{{ $totalConnected }}</strong>
								</div>
							</div>
							
							<div class="small">
								{{-- TG only --}}
								<div class="d-flex align-items-center justify-content-between border-top pt-3">
									<div class="d-flex align-items-center gap-2">
										<span class="text-muted" style="width: 70px;">TG only:</span>
										<span class="provider-chip">
											<span class="badge">TG</span>
											<span>TG</span>
										</span>
										<span class="text-muted">только Telegram</span>
									</div>
									<div class="fw-bold">{{ $p['tg_only'] ?? 0 }}</div>
								</div>
								
								{{-- VK only --}}
								<div class="d-flex align-items-center justify-content-between mt-2">
									<div class="d-flex align-items-center gap-2">
										<span class="text-muted" style="width: 70px;">VK only:</span>
										<span class="provider-chip">
											<span class="badge">VK</span>
											<span>VK</span>
										</span>
										<span class="text-muted">только VK</span>
									</div>
									<div class="fw-bold">{{ $p['vk_only'] ?? 0 }}</div>
								</div>
								
								{{-- Ya only --}}
								<div class="d-flex align-items-center justify-content-between mt-2">
									<div class="d-flex align-items-center gap-2">
										<span class="text-muted" style="width: 70px;">Ya only:</span>
										<span class="provider-chip">
											<span class="badge">Ya</span>
											<span>Ya</span>
										</span>
										<span class="text-muted">только Yandex</span>
									</div>
									<div class="fw-bold">{{ $p['ya_only'] ?? 0 }}</div>
								</div>
								
								{{-- TG+VK --}}
								<div class="d-flex align-items-center justify-content-between border-top pt-3 mt-2">
									<div class="d-flex align-items-center gap-2">
										<span class="text-muted" style="width: 70px;">TG+VK:</span>
										<span class="provider-chip">
											<span class="badge">TG</span>
											<span>TG</span>
										</span>
										<span class="provider-chip">
											<span class="badge">VK</span>
											<span>VK</span>
										</span>
									</div>
									<div class="fw-bold">{{ $p['tg_vk'] ?? 0 }}</div>
								</div>
								
								{{-- TG+Ya --}}
								<div class="d-flex align-items-center justify-content-between mt-2">
									<div class="d-flex align-items-center gap-2">
										<span class="text-muted" style="width: 70px;">TG+Ya:</span>
										<span class="provider-chip">
											<span class="badge">TG</span>
											<span>TG</span>
										</span>
										<span class="provider-chip">
											<span class="badge">Ya</span>
											<span>Ya</span>
										</span>
									</div>
									<div class="fw-bold">{{ $p['tg_ya'] ?? 0 }}</div>
								</div>
								
								{{-- Ya+VK --}}
								<div class="d-flex align-items-center justify-content-between mt-2">
									<div class="d-flex align-items-center gap-2">
										<span class="text-muted" style="width: 70px;">Ya+VK:</span>
										<span class="provider-chip">
											<span class="badge">Ya</span>
											<span>Ya</span>
										</span>
										<span class="provider-chip">
											<span class="badge">VK</span>
											<span>VK</span>
										</span>
									</div>
									<div class="fw-bold">{{ $p['ya_vk'] ?? 0 }}</div>
								</div>
								
								{{-- Ya+VK+TG --}}
								<div class="d-flex align-items-center justify-content-between mt-2">
									<div class="d-flex align-items-center gap-2">
										<span class="text-muted" style="width: 70px;">Ya+VK+TG:</span>
										<span class="provider-chip">
											<span class="badge">Ya</span>
											<span>Ya</span>
										</span>
										<span class="provider-chip">
											<span class="badge">VK</span>
											<span>VK</span>
										</span>
										<span class="provider-chip">
											<span class="badge">TG</span>
											<span>TG</span>
										</span>
									</div>
									<div class="fw-bold">{{ $p['ya_vk_tg'] ?? 0 }}</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				
				{{-- RESTRICTIONS --}}
				<div class="col-12 col-lg-4">
					<div class="card">
						<div class="card-body">
							<div class="fw-semibold fs-5 mb-2">Блокировки</div>
							<div class="text-muted small mb-3">
								Активные: ends_at NULL или ends_at &gt; now()
							</div>
							
							<div class="d-flex align-items-center justify-content-between border-top py-2 small">
								<div class="font-monospace">Event All</div>
								<div class="fw-bold">{{ $eventAllRestrictions ?? 0 }}</div>
							</div>
							
							@php($map = $restrictionByEvent ?? [])
							@if(!empty($map))
							@foreach($map as $eid => $cnt)
							<div class="d-flex align-items-center justify-content-between border-top py-2 small">
								<div class="font-monospace">Event_{{ (int)$eid }}</div>
								<div class="fw-bold">{{ (int)$cnt }}</div>
							</div>
							@endforeach
							@else
							<div class="border-top py-2 text-muted small">
								Нет активных блокировок по конкретным event_id.
							</div>
							@endif
							
							<div class="mt-4">
								<a href="{{ route('admin.users.index') }}" class="btn btn-secondary w-100 text-center">
									Перейти к пользователям
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		{{-- ROW 4: ROLES + ORGANIZER REQUESTS --}}
		<div class="ramka">
			<div class="row">
				<div class="col-12 col-lg-6">
					<div class="card">
						<div class="card-body">
							<div class="fw-semibold fs-5 mb-3">Роли</div>
							<div class="small">
								@foreach($roles as $r)
								<div class="d-flex align-items-center justify-content-between border-top py-2">
									<div class="font-monospace">{{ $r->role ?? 'null' }}</div>
									<div class="fw-semibold">{{ $r->c }}</div>
								</div>
								@endforeach
							</div>
						</div>
					</div>
				</div>
				
				<div class="col-12 col-lg-6">
					<div class="card">
						<div class="card-body">
							<div class="fw-semibold fs-5 mb-3">Organizer requests</div>
							<div class="small">
								@forelse($organizerRequests as $r)
								<div class="d-flex align-items-center justify-content-between border-top py-2">
									<div class="font-monospace">{{ $r->status ?? 'null' }}</div>
									<div class="fw-semibold">{{ $r->c }}</div>
								</div>
								@empty
								<div class="text-muted small">Нет данных (или таблицы organizer_requests).</div>
								@endforelse
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	
</x-voll-layout>	