{{-- resources/views/profile/show.blade.php --}}
<x-voll-layout body_class="profile-page">
    
    <x-slot name="title">
        Ваш профиль
	</x-slot>
    
    <x-slot name="description">Ваш профиль: 
        @php
		$user = auth()->user();
        @endphp
        @if(!empty($user->first_name) || !empty($user->last_name))
		{{ trim($user->first_name . ' ' . $user->last_name) }}
        @else
		Пользователь #{{ $user->id }}
        @endif
	</x-slot>
    
    <x-slot name="canonical">
        {{ route('profile.show') }}
	</x-slot> 
    
	
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item">
                <span itemprop="name">Ваш профиль</span>
			</a>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
    
    <x-slot name="h1">Ваш профиль</x-slot>
    
    <x-slot name="h2">
        @php
		$user = auth()->user();
        @endphp
        @if(!empty($user->first_name) || !empty($user->last_name))
		{{ trim($user->first_name . ' ' . $user->last_name) }}
        @else
		Пользователь #{{ $user->id }}
        @endif
	</x-slot>
    
    <x-slot name="t_description">
        Здесь отображаются данные вашей анкеты. 
	</x-slot>
    
    
    {{-- FLASH --}}
    @if (session('status'))
	<div class="container">	
		<div class="ramka">
			<div class="alert alert-success">
				{{ session('status') }}
			</div>
		</div>
	</div>
    @endif
    
    @if (session('error'))
	<div class="container">	
		<div class="ramka">
			<div class="alert alert-error">
				{{ session('error') }}
			</div>
		</div>
	</div>
    @endif
    
    @php
    /** @var \App\Models\User $u */
    $u = auth()->user();
    $u->loadMissing(['city', 'classicPositions', 'beachZones']);
    
    $posMap = [
    'setter'   => 'Связующий',
    'outside'  => 'Доигровщик',
    'opposite' => 'Диагональный',
    'middle'   => 'Центральный блокирующий',
    'libero'   => 'Либеро',
    ];
    
    $classicPrimary = optional($u->classicPositions)->firstWhere('is_primary', true)?->position;
    $classicExtras  = optional($u->classicPositions)
    ?->where('is_primary', false)
    ->pluck('position')->values()->all() ?? [];
    
    $beachPrimary = optional($u->beachZones)->firstWhere('is_primary', true)?->zone;
    $beachExtras  = optional($u->beachZones)
    ?->where('is_primary', false)
    ->pluck('zone')->values()->all() ?? [];
    
    $age   = method_exists($u, 'ageYears') ? $u->ageYears() : null;
    $birth = $u->birth_date ? $u->birth_date->format('Y-m-d') : '—';
    
    // provider in session: telegram|vk|yandex|null
    $provider = session('auth_provider');
    
    $hasTg = !empty($u?->telegram_id);
    $hasVk = !empty($u?->vk_id);
    $hasYa = !empty($u?->yandex_id);
    
    $linkedCount = (int)$hasTg + (int)$hasVk + (int)$hasYa;
    
    // “provider looks off” (после неуспешной привязки мог остаться мусор в сессии)
    $providerLooksOff = false;
    if ($provider === 'telegram' && !$hasTg && ($hasVk || $hasYa)) $providerLooksOff = true;
    if ($provider === 'vk' && !$hasVk && ($hasTg || $hasYa)) $providerLooksOff = true;
    if ($provider === 'yandex' && !$hasYa && ($hasTg || $hasVk)) $providerLooksOff = true;
    
    // link urls
    $vkLinkUrl     = route('auth.vk.redirect', ['link' => 1]);
    $yandexLinkUrl = route('auth.yandex.redirect', ['link' => 1]);
    
    $allLinked = $hasTg && $hasVk && $hasYa;
    
    // Telegram widget settings
    $tgBotUsername = config('services.telegram.bot_username');
    
    // ✅ Важно для LINK: Telegram widget не вызывает redirect(), поэтому intent передаем в callback явно
    $tgAuthUrl = route('auth.telegram.callback', ['intent' => 'link'], true);
    
    // can unlink only if more than one provider linked (чтобы не потерять доступ)
    $canUnlink = $linkedCount > 1;
    
    // UI helpers
    $providerIcon = function (?string $p) {
    $p = $p ?: 'unknown';
    $base = 'provider-icon';
    $dot  = 'provider-dot';
    $txt  = 'provider-text';
    
    if ($p === 'vk') {
    return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#2787F5;"></span><span class="'.$txt.'">VK</span></span>';
    }
    if ($p === 'telegram') {
    return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#2AABEE;"></span><span class="'.$txt.'">Telegram</span></span>';
    }
    if ($p === 'yandex') {
    return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#FF0000;"></span><span class="'.$txt.'">Yandex</span></span>';
    }
    return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#9CA3AF;"></span><span class="'.$txt.'">—</span></span>';
    };
    
    $badge = function (bool $ok) {
    if ($ok) {
    return '<span class="badge badge-success"></span>';
    }
    return '<span class="badge badge-muted"></span>';
    };
    
    $miniIcon = function (string $p) {
    $dot  = 'provider-dot';
    if ($p === 'vk') return '<span title="VK" class="'.$dot.'" style="background:#2787F5;"></span>';
    if ($p === 'telegram') return '<span title="Telegram" class="'.$dot.'" style="background:#2AABEE;"></span>';
    if ($p === 'yandex') return '<span title="Yandex" class="'.$dot.'" style="background:#FF0000;"></span>';
    return '<span class="'.$dot.'" style="background:#9CA3AF;"></span>';
    };
    @endphp
	

	
    <div class="container">
        <div class="row">
			<div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
				<div class="sticky">
					<div class="card-ramka">
						<div class="row">
							<div class="col-3 col-lg-12">
								<div class="profile-avatar">
									<img
									src="{{ $u->profile_photo_url }}"
									alt="avatar"
									class="avatar"
									/>			
								</div>
							</div>
							<div class="col-9 col-lg-12">
								<nav class="menu-nav">
									<a href="{{ route('profile.show') }}" class="menu-item active">
										<strong class="cd menu-text">Ваш профиль</strong>
									</a>
									<a href="{{ url('/profile/complete') }}" class="menu-item">
										<span class="menu-text">Редактировать профиль</span>
									</a>
									<a href="{{ route('user.photos') }}" class="menu-item">
										<span class="menu-text">Ваши фотографии</span>
									</a>
									<form method="POST" action="{{ route('logout') }}" class="logout-form" x-data>
										@csrf
										<button type="submit" class="menu-item">Выйти</button>
									</form>							
								</nav>	
							</div>	
						</div>
					</div>
				</div> 	
			</div> 
			<div class="col-lg-8 col-xl-9 order-1">
				<div class="ramka pb-2">  
					{{-- Анкета игрока --}}
					
					<h2 class="-mt-05">Персональные данные</h2>
					<div class="row mb-0">
						<div class="col-sm-6 pb-0">
							<ul class="list mb-0">	
								<li><span class="b-600">Фамилия:</span> {{ $u->last_name ?? '—' }}</li>
								<li><span class="b-600">Имя:</span> {{ $u->first_name ?? '—' }}</li>
								<li><span class="b-600">Отчество:</span> {{ $u->patronymic ?? '—' }}</li>
								<li><span class="b-600">Телефон:</span> {{ $u->phone ?? '—' }}</li>
							</ul>	
						</div>	
						<div class="col-sm-6 pb-0">
							<ul class="list mb-0">	
								<li>
									<span class="b-600">Пол:</span>		
										@if($u->gender === 'm') Мужчина
										@elseif($u->gender === 'f') Женщина
										@else — @endif
																
								</li>
								<li>
									<span class="b-600">Рост:</span>			
									
										{{ !empty($u->height_cm) ? ($u->height_cm.' см') : '—' }}
														
								</li>
								<li>
									<span class="b-600">Город:</span>		
									
										@if($u->city)
										{{ $u->city->name }}@if($u->city->region) ({{ $u->city->region }})@endif
										@else
										—
										@endif
																
								</li>
								<li><span class="b-600">Дата рождения:</span> {{ $birth }} 					@if(!is_null($age))
									({{ $age }} лет)
								@endif</li>
							</ul>								
						</div>
					</div>
					
				</div>
				

				<div class="ramka">  	
					<h2 class="-mt-05">Навыки в волейболе</h2>
					<div class="row">
						<div class="col-md-6 col-lg-12 col-xl-6">
							<div class="card">
								<p class="b-600 mb-1">Классический волейбол</p>
								
								<div class="level-wrap">
									<div class="level-levelmark levelmark level-{{ $u->classic_level ?? '—' }}">
										<div class="f-11">Уровень: </div>
										<div class="f-22 l-13">{{ $u->classic_level ?? '—' }}</div>
										<div class="f-11">{{ level_name($u->classic_level) ?? '—' }}</div>
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
									<div class="level-levelmark levelmark level-{{ $u->beach_level ?? '—' }}">
										<div class="f-11">Уровень: </div>
										<div class="f-22 l-13">{{ $u->beach_level ?? '—' }}</div>
										<div class="f-11">{{ level_name($u->beach_level) ?? '—' }}</div>
									</div>	
									<div class="level-level">	
										<ul class="list">
											<li>
												<span class="b-600">Зона игры:</span><br>
												
										
											@if(!empty($u->beach_universal))
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
				<div class="ramka">  	
					
					{{-- Привязка провайдеров --}}
					
					<h2 class="-mt-05">Привязка входов</h2>
					<p>Привяжите дополнительные способы входа к текущему аккаунту.</p>
					
					<p>
						<span class="text-muted">Текущий вход (сессия):</span>
						{!! $providerIcon($provider) !!}
					</p>
					
					@if($allLinked)
					<p>Все способы входа уже привязаны !</p>
					@else
					<p>Как привязать:</p>
					<ul class="list">
						<li>Нажмите кнопку нужного провайдера ниже.</li>
						<li>Подтвердите вход у провайдера.</li>
						<li>После возврата на сайт провайдер привяжется к текущему аккаунту.</li>
					</ul>
					@endif					
					
					
					<div class="mt-2"> 
						
						<div class="row provider-cards">
							{{-- VK card --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-3">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon icon-vk"></span>
										<span class="provider-card__title">ВКонтакте</span>
									</div>
									
									<div class="provider-card__status">
										{!! $badge($hasVk) !!}
									</div>
									
									<div class="provider-card__actions">
										@if($hasVk)
										@if($canUnlink)
										<form method="POST" 
										action="{{ route('account.unlink.vk') }}" 
										onsubmit="return confirm('Отвязать VK от аккаунта?');">
											@csrf
											{{-- btn-danger --}}
											<button type="submit" class="w-100 btn btn-small btn-secondary">
												Отвязать
											</button>
										</form>
										@else
										<button class="w-100 btn btn-small btn-secondary" disabled>
											Нельзя отвязать
										</button>
										@endif
										@else
										<a href="{{ $vkLinkUrl }}" class="w-100 btn btn-small">
											Привязать
										</a>
										@endif
									</div>
								</div>
							</div>
							{{-- Yandex card --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-3">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon icon-yandex"></span>
										<span class="provider-card__title">Yandex</span>
									</div>
									
									<div class="provider-card__status">
										{!! $badge($hasYa) !!}
									</div>
									
									<div class="provider-card__actions">
										@if($hasYa)
										@if($canUnlink)
										<form method="POST" 
										action="{{ route('account.unlink.yandex') }}" 
										onsubmit="return confirm('Отвязать Yandex от аккаунта?');">
											@csrf
											{{-- btn-danger --}}
											<button type="submit" class="w-100 btn btn-small btn-secondary">
												Отвязать
											</button>
										</form>
										@else
										<button class="w-100 btn btn-small btn-secondary" disabled>
											Нельзя отвязать
										</button>
										@endif
										@else
										<a href="{{ $yandexLinkUrl }}" class="w-100 btn btn-small">
											Привязать
										</a>
										@endif
									</div>
								</div>
							</div>
							{{-- Telegram card --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-3">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon icon-tg"></span>
										<span class="provider-card__title">Telegram</span>
									</div>
									
									<div class="provider-card__status">
										{!! $badge($hasTg) !!}
									</div>
									
									<div class="provider-card__actions">
										@if($hasTg)
										@if($canUnlink)
										<form method="POST" 
										action="{{ route('account.unlink.telegram') }}" 
										onsubmit="return confirm('Отвязать Telegram от аккаунта?');">
											@csrf
											{{-- btn-danger --}}
											<button type="submit" class="w-100 btn btn-small btn-secondary">
												Отвязать
											</button>
										</form>
										@else
										<button class="w-100 btn btn-small btn-secondary" disabled>
											Нельзя отвязать
										</button>
										@endif
										@else
										@if(empty($tgBotUsername))
										<div class="provider-error">
											Ошибка настройки бота
										</div>
										<button class="w-100 btn btn-disabled btn-small" disabled>
											Привязать
										</button>
										@else
										<div class="provider-telegram">
											{{-- Виджет Telegram --}}
											<script
											async
											src="https://telegram.org/js/telegram-widget.js?22"
											data-telegram-login="{{ $tgBotUsername }}"
											data-size="large"
											data-radius="10"
											data-userpic="false"
											data-request-access="write"
											data-auth-url="{{ $tgAuthUrl }}"
											></script>
											
											<div class="w-100 provider-telegram-btn btn btn-small">
												Привязать
											</div>
										</div>
										@endif
										@endif
									</div>
								</div>
							</div>
							{{-- MAX card (placeholder) --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-3">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon icon-max"></span>
										<span class="provider-card__title">MAX</span>
									</div>
									
									<div class="provider-card__status">
										<span class="badge badge-muted"></span>
									</div>
									
									<div class="provider-card__actions">
										<button class="btn btn-disabled w-100 btn-small" disabled>
											В разработке
										</button>
									</div>
								</div>
							</div>
						</div>
						
					</div>
					
					
					
					
					
					
					
					
					@if(($hasTg || $hasVk || $hasYa) && !$canUnlink)
					<p class="mt-2">Отвязка последнего способа входа запрещена — сначала привяжите ещё один.</p>
					@endif
					
					
					@if($providerLooksOff)
					<div class="alert alert-info">
						Провайдер в сессии мог измениться из‑за неуспешной попытки привязки.
						Ориентируйтесь на статусы “привязан/не привязан”.
					</div>
					@endif
					
					
					
					
					
					
					
				</div>	
				<div class="ramka">  	
					
					{{-- ✅ Приватность --}}
					
					<h2 class="-mt-05">Приватность</h2>
					
					<form class="form" method="POST" action="{{ route('profile.contact_privacy.update') }}">
						@csrf
						
						
						
						<label for="allow_user_contact" class="checkbox-item">
							<input type="hidden" name="allow_user_contact" value="0">
							<input type="checkbox" 
							class="form-check-input" 
							name="allow_user_contact" 
							value="1"
							id="allow_user_contact"
							@checked((bool)($u->allow_user_contact ?? true))>
							
							<div class="custom-checkbox"></div>
							<span>Разрешить другим пользователям писать вам в Telegram/VK со страницы профиля.</span>
						</label>
						
						
						
						<p>Кнопки “Написать” видны только авторизованным пользователям и только если вы включили этот переключатель.</p>									
						
						
						<div class="mt-2 m-center">
							<button type="submit" class="btn">
								Сохранить
							</button>
						</div>
						
					</form>
					
					
					
				</div>	
				<div class="ramka">  	
					
					{{-- ✅ Удаление аккаунта — заявка админу --}}
					
					
					<h2 class="mt-0">Удаление аккаунта</h2>
					<p>Самостоятельное удаление отключено. Вы можете отправить администратору заявку на удаление.</p>
					<p>Заявка попадёт администратору. После обработки аккаунт будет удалён/деактивирован.</p>
					
					<div class="mt-2 m-center">
						<form method="POST"
						action="{{ route('account.delete.request') }}"
						onsubmit="return confirm('Отправить заявку на удаление аккаунта администратору?');">
							@csrf
							<button type="submit" class="btn btn-outline-secondary">
								Запросить удаление аккаунта
							</button>
						</form>
					</div>
					
					
				</div>
			</div>
		</div>
	</div>	
</x-voll-layout>