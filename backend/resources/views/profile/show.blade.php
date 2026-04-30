{{-- resources/views/profile/show.blade.php --}}

<x-voll-layout body_class="profile-page">
    
    <x-slot name="title">
        Мой профиль
	</x-slot>
    
    <x-slot name="description">Мой профиль: 
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
                <span itemprop="name">Мой профиль</span>
			</a>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
    
    <x-slot name="h1">Мой профиль</x-slot>
    
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

    @if (session('duplicate_user_id'))
    @php $dupeUser = \App\Models\User::find(session('duplicate_user_id')); @endphp
    @if ($dupeUser)
	<div class="container">
		<div class="ramka" style="border-left: 4px solid #f59e0b; background: #fffbeb;">
			<div class="f-16">
				<strong>Возможный дубль аккаунта</strong><br>
				Найден другой аккаунт с таким же номером телефона:
				<strong>{{ $dupeUser->name ?: 'ID #'.$dupeUser->id }}</strong>
				({{ $dupeUser->email }}).
				Если это вы — обратитесь к администратору для объединения аккаунтов,
				или <a href="/admin/users/duplicates">перейдите на страницу дублей</a> (для администраторов).
			</div>
		</div>
	</div>
    @endif
    @endif
    
    @php
    /** @var \App\Models\User $u */
    $u = auth()->user();
	$u->refresh();
	
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
    
    
    $hasTg = !empty($u?->telegram_id);
    $hasVk = !empty($u?->vk_id);
    $hasYa = !empty($u?->yandex_id);
    $hasApple = !empty($u?->apple_id);
	// персональные уведомления
    $hasMaxNotify = !empty($u?->max_chat_id);
    $hasTelegramNotify = !empty($u?->telegram_notify_chat_id);
    $hasVkNotify = !empty($u?->vk_notify_user_id);
    
    // provider in session: telegram|vk|yandex|null
    $provider = session('auth_provider');
    $providerId = (string) session('auth_provider_id', '');
    
    if (!$provider && $providerId !== '') {
	if ($hasTg && (string) $u->telegram_id === $providerId) {
	$provider = 'telegram';
	} elseif ($hasVk && (string) $u->vk_id === $providerId) {
	$provider = 'vk';
	} elseif ($hasYa && (string) $u->yandex_id === $providerId) {
	$provider = 'yandex';
	} elseif ($hasApple && (string) $u->apple_id === $providerId) {
	$provider = 'apple';
	}
    }
    
    // fallback 1: если в сессии ничего нет, но привязан только один вход
    if (!$provider) {
	$linkedProviders = array_filter([
	'telegram' => $hasTg,
	'vk' => $hasVk,
	'yandex' => $hasYa,
	'apple' => $hasApple,
	]);
    
	if (count($linkedProviders) === 1) {
	$provider = array_key_first($linkedProviders);
	}
    }
    
    // fallback 2: если определить нельзя, но входы есть
    if (!$provider && ($hasTg || $hasVk || $hasYa || $hasApple)) {
	$provider = 'unknown';
    }

    $linkedCount = (int)$hasTg + (int)$hasVk + (int)$hasYa + (int)$hasApple;
    
    // “provider looks off” (после неуспешной привязки мог остаться мусор в сессии)
	
    
    // link urls
    $vkLinkUrl     = route('auth.vk.redirect', ['link' => 1]);
    $yandexLinkUrl = route('auth.yandex.redirect', ['link' => 1]);
    
    $allLinked = $hasTg && $hasVk && $hasYa && $hasApple;
    
    $tgBotUsername = config('services.telegram.bot_username');
    
    // can unlink only if more than one provider linked (чтобы не потерять доступ)
    $canUnlink = $linkedCount > 1;
    
	$hasPendingOrganizerRequest = (bool)($hasPendingOrganizerRequest ?? ($hasPendingRequest ?? false));
	
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
	return '<span class="'.$base.'"><span class="'.$dot.'" style="background:#9CA3AF;"></span><span class="'.$txt.'">Не определён</span></span>';
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
						@include('profile._menu', [
						'menuUser'   => $user,
						'activeMenu' => 'profile',
						])
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
				@php
    $providerCount = (int)!empty($u?->telegram_id) + (int)!empty($u?->vk_id) + (int)!empty($u?->yandex_id) + (int)!empty($u?->apple_id);
@endphp

@if(session('show_providers_hint') || $providerCount < 4)
<div class="ramka" id="providers-hint">
    <div class="d-flex fvc gap-2">
        <span style="font-size:2.4rem">🔐</span>
        <div>
            <div class="b-600 mb-05">
                @if($providerCount === 1)
                    Привязан только 1 способ входа
                @elseif($providerCount === 2)
                    Привязано 2 из 3 способов входа
                @endif
            </div>
            <p>
                Привяжите все три провайдера (Telegram, VK, Яндекс) — это защитит аккаунт от потери доступа
                и поможет системе не создавать дубли.
                <a href="#providers" class="cd b-600">Привязать →</a>
            </p>
        </div>
    </div>
</div>
@endif

<div class="ramka" id="providers">  	
					
                    {{-- Привязка провайдеров --}}
					
					<h2 class="-mt-05">Привязка входов</h2>
					<p>Привяжите дополнительные способы входа к текущему аккаунту.</p>
					
					<p>
                        <span class="text-muted">Текущий вход:</span>
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
						
						<div class="row provider-cards fc">
							{{-- VK card --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
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
											<button type="submit" class="btn-alert w-100 btn btn-small btn-secondary"
											data-title="Отвязать VK от аккаунта?"
											data-icon="warning"
											data-confirm-text="Да, отвязать"
											data-cancel-text="Отмена">
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
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
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
											<button type="submit" class="w-100 btn btn-alert btn-small btn-secondary"
											data-title="Отвязать Yandex от аккаунта?"
											data-icon="warning"
											data-confirm-text="Да, отвязать"
											data-cancel-text="Отмена">											
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
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
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
											<button type="submit" class="btn-alert w-100 btn btn-small btn-secondary"
											data-title="Отвязать Telegram от аккаунта?"
											data-icon="warning"
											data-confirm-text="Да, отвязать"
											data-cancel-text="Отмена">												
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
										<a href="{{ route('auth.telegram.redirect', ['return' => url()->current()]) }}"
										   class="w-100 btn btn-small btn-tg">
											Telegram
										</a>
										@endif
										@endif
									</div>
								</div>
							</div>
							{{-- Apple card --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
								<div class="card">
									<div class="provider-card__header">
										<span class="provider-card__icon icon-apple"></span>
										<span class="provider-card__title">Apple</span>
									</div>

									<div class="provider-card__status">
										{!! $badge($hasApple) !!}
									</div>

									<div class="provider-card__actions">
										@if($hasApple)
										@if($canUnlink)
										<form method="POST"
										action="{{ route('account.unlink.apple') }}"
										onsubmit="return confirm('Отвязать Apple ID от аккаунта?');">
											@csrf
											<button type="submit" class="btn-alert w-100 btn btn-small btn-secondary"
											data-title="Отвязать Apple ID от аккаунта?"
											data-icon="warning"
											data-confirm-text="Да, отвязать"
											data-cancel-text="Отмена">
												Отвязать
											</button>
										</form>
										@else
										<button class="w-100 btn btn-small btn-secondary" disabled>
											Нельзя отвязать
										</button>
										@endif
										@else
										<a href="{{ route('auth.apple.redirect', ['link' => 1]) }}" class="w-100 btn btn-small">
											Привязать
										</a>
										@endif
									</div>
								</div>
							</div>
							{{-- MAX card (placeholder) --}}
							<div class="col-6 col-md-3 col-lg-6 col-xl-4">
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
					
					
				</div>	
				
				<div class="ramka is-app-only" style="display:none">
					<h2 class="-mt-05">Быстрый вход</h2>
					<p>Face ID / Touch ID для входа в приложение.</p>
					<div id="biometric-status" class="mb-2"></div>
					<button id="biometric-disable-btn" class="btn btn-secondary btn-small" style="display:none">
						Отключить Face ID
					</button>
					<button id="biometric-enable-btn" class="btn btn-small" style="display:none">
						Включить Face ID
					</button>
				</div>

				<div class="ramka">
                    <h2 class="-mt-05">Уведомления и рассылки</h2>
					
                    <p class="mb-15">
                        Не пропустите ни одного важного события — подпишитесь на уведомления, и вы всегда будете в курсе
                        новостей, анонсов мероприятий и своевременных напоминаний о начале.
					</p>
					
                    <div class="row provider-cards">
                        {{-- Telegram --}}
                        <div class="col-md-4">
                            <div class="card">
                                <div class="provider-card__header">
                                    <span class="provider-card__icon icon-tg"></span>
                                    <span class="provider-card__title">Уведомления в Telegram</span>
								</div>
								
                                @if($hasTelegramNotify)
								<div class="provider-card__status">
									<span class="badge badge-success"></span>
								</div>
								
								<ul class="list">
									<li>Личные уведомления в <b>Telegram</b> включены.</li>
								</ul>
								
								<form method="POST" action="{{ route('profile.telegram.disconnect') }}" class="mt-3">
									@csrf
									<button
									type="submit"
									class="w-100 btn btn-small btn-secondary btn-alert"
									data-title="Отключить уведомления в Telegram?"
									data-icon="warning"
									data-confirm-text="Да, отключить"
									data-cancel-text="Отмена"
									>
										Отключить
									</button>
								</form>
                                @else
								<div class="provider-card__status">
									<span class="badge badge-muted"></span>
								</div>
								
								<ul class="list f-16">
									<li>Хотите получать уведомления в <b>Telegram</b>?</li>
								</ul>
								
								<button type="button" id="connect-telegram-btn" class="w-100 btn btn-small">
									Подключить Telegram
								</button>
								
								<div id="connect-telegram-result" class="mt-1 hidden">
									<ul class="list f-16">
										<li>Нажмите на ссылку ниже</li>
										<li>Откройте личный чат с Telegram-ботом</li>
										<li>Нажмите <b>Start</b></li>
										<li>После этого личные уведомления подключатся автоматически</li>
									</ul>
									
									<a
									style="word-break: break-all"
									id="connect-telegram-link"
									href="#"
									target="_blank"
									rel="noopener"
									class="f-16 b-600"
									></a>
								</div>
                                @endif
							</div>
						</div>
						
						
                        {{-- MAX --}}
                        <div class="col-md-4">
                            <div class="card">
                                <div class="provider-card__header">
                                    <span class="provider-card__icon icon-max"></span>
                                    <span class="provider-card__title">Уведомления в MAX</span>
								</div>
								
                                @if($hasMaxNotify)
								<div class="provider-card__status">
									<span class="badge badge-success"></span>
								</div>
								
								<ul class="list">
									<li>Личные уведомления в <b>MAX</b> включены.</li>
								</ul>
								
								<form method="POST" action="{{ route('profile.max.disconnect') }}" class="mt-3">
									@csrf
									<button type="submit" class="w-100 btn btn-small btn-secondary btn-alert" data-title="Отключить MAX-уведомления?" data-icon="warning" data-confirm-text="Да, отключить" data-cancel-text="Отмена">
										Отключить
									</button>
								</form>
                                @else
								<div class="provider-card__status">
									<span class="badge badge-muted"></span>
								</div>
								
								<ul class="list f-16">
									<li>Хотите получать уведомления в <b>MAX</b>?</li>
								</ul>
								
								<button type="button" id="connect-max-btn" class="w-100 btn btn-small">
									Подключить MAX
								</button>
								
								<div id="connect-max-result" class="mt-1 hidden">
									<ul class="list f-16">
										<li>Нажмите на ссылку ниже</li>
										<li>Откройте личный чат с ботом MAX</li>
										<li>Нажмите <b>«Начать»</b></li>
										<li>После этого личные уведомления подключатся автоматически</li>
									</ul>
									
									<a
									style="word-break: break-all"
									id="connect-max-link"
									href="#"
									target="_blank"
									rel="noopener"
									class="f-16 b-600"
									></a>
								</div>
                                @endif
							</div>
						</div>
						
                        {{-- VK --}}
                        <div class="col-md-4">
                            <div class="card">
                                <div class="provider-card__header">
                                    <span class="provider-card__icon icon-vk"></span>
                                    <span class="provider-card__title">Уведомления в VK</span>
								</div>
								
                                @if($hasVkNotify)
								<div class="provider-card__status">
									<span class="badge badge-success"></span>
								</div>
								
								<ul class="list">
									<li>Личные уведомления во <b>VK</b> включены.</li>
								</ul>
								
								<form method="POST" action="{{ route('profile.vk.disconnect') }}" class="mt-3">
									@csrf
									<button
									type="submit"
									class="w-100 btn btn-small btn-secondary btn-alert"
									data-title="Отключить уведомления во VK?"
									data-icon="warning"
									data-confirm-text="Да, отключить"
									data-cancel-text="Отмена"
									>
										Отключить
									</button>
								</form>
                                @else
								<div class="provider-card__status">
									<span class="badge badge-muted"></span>
								</div>
                                
								<ul class="list f-16">
									<li>Хотите получать уведомления во <b>VK</b>?</li>
								</ul>
								
								<button type="button" id="connect-vk-btn" class="w-100 btn btn-small">
									Подключить VK
								</button>					
                                
								<div id="connect-vk-result" class="mt-1 hidden">
									<ul class="list f-16">
										<li>Нажмите на ссылку ниже</li>
										<li>Откройте личный диалог с VK-ботом</li>
										<li>Команда уже скопирована в буфер обмена</li>
										<li>Просто вставьте её в чат и отправьте</li>
										<li>После этого личные уведомления подключатся автоматически</li>
									</ul>
									
									
									
									<a
									style="word-break: break-all"
									id="connect-vk-link"
									href="#"
									target="_blank"
									rel="noopener"
									class="f-16 b-600"
									></a>
									
									<div id="connect-vk-command-box" class="mt-1 hidden">
										<div class="f-16 text-muted">Команда для бота (скопирована в буфер):</div>
										<div style="overflow-wrap:break-word" id="connect-vk-command" class="f-16 b-600"></div>
										<div id="connect-vk-copy-hint" class="f-14 text-muted mt-05 hidden"></div>
									</div>
								</div>
                                @endif
							</div>
						</div>
						
						
						
					</div>

					@if(($hasTelegramNotify || $hasVkNotify || $hasMaxNotify) && in_array((string)($u->role ?? 'user'), ['admin', 'organizer', 'staff'], true))
					<div class="mt-2 pt-2" style="border-top:1px solid var(--border-color, #e0e0e0)">
						<div class="f-15 b-600 mb-05">🔔 Уведомления о записях игроков</div>
						<p class="f-14 text-muted mb-1">
							При каждой записи или отмене записи на ваши мероприятия — личное сообщение от нашего бота через Telegram, VK или MAX.
						</p>
						<form method="POST" action="{{ route('profile.notification_channels.settings') }}" class="d-flex align-items-center gap-1">
							@csrf
							<label class="d-flex align-items-center gap-05" style="cursor:pointer;font-size:1rem">
								<input type="hidden" name="notify_player_registrations" value="0">
								<input type="checkbox" name="notify_player_registrations" value="1"
									   onchange="this.form.submit()"
									   {{ $notifyPlayerRegistrations ? 'checked' : '' }}>
								<span>{{ $notifyPlayerRegistrations ? 'Включены' : 'Выключены' }}</span>
							</label>
						</form>
					</div>
					@endif
				</div>
				
				@php
                $canManageNotificationChannels = in_array((string)($u->role ?? 'user'), ['admin', 'organizer', 'staff'], true);
                
                $notificationChannels = collect();
                
                if (
				$canManageNotificationChannels &&
				\Illuminate\Support\Facades\Schema::hasTable('user_notification_channels')
                ) {
				$notificationChannels = \Illuminate\Support\Facades\DB::table('user_notification_channels')
				->where('user_id', (int) $u->id)
				->orderBy('created_at')
				->get([
				'id',
				'title',
				'platform',
				'chat_id',
				'is_verified',
				]);
                }
                @endphp
				@if($canManageNotificationChannels)
                <div class="ramka">
                    <h2 class="-mt-05">Каналы уведомлений</h2>
					
                    @if($notificationChannels->isNotEmpty())
					<p><b>Список подключенных 📣:</b></p>
					<ol class="list">
						@foreach($notificationChannels as $channel)
						<li>
							{{ $channel->title ?: ('Канал #' . $channel->id) }}
							@if(!empty($channel->platform))
							<span class="text-muted">({{ strtoupper($channel->platform) }})</span>
							@endif
							@if(!(bool) $channel->is_verified)
							<span class="text-muted">— не подтверждён</span>
							@endif
						</li>
						@endforeach
					</ol>
                    @endif
					
                    <p>
                        Подключайте Telegram / VK / MAX каналы для анонсов мероприятий,
                        открытия регистрации и обновления списков участников.
					</p>
					
                    <div class="mt-2 m-center">
                        <a href="{{ route('profile.notification_channels') }}" class="btn">
                            Управление каналами уведомлений
						</a>
					</div>
				</div>
				@endif
				
				
				
				@if(auth()->check() && (auth()->user()->isOrganizer() || auth()->user()->isAdmin()))
				@php $paymentSettings = \App\Models\PaymentSetting::where('organizer_id', auth()->id())->first(); @endphp
				<div class="ramka">
					<h2 class="-mt-05">💳 Платёжная система</h2>
					
					{{-- Выбор назначения --}}
					<div class="card mb-2">
						<div class="f-15 b-600 mb-1">Настройте приём оплаты</div>
						<div class="tabs-content">
							<div class="tabs">
								<div class="tab active" data-tab="pay-events">🏐 Для мероприятий</div>
								@if(auth()->user()->isAdmin())
								<div class="tab" data-tab="pay-premium">👑 Premium и реклама</div>
								@endif
								<div class="tab-highlight"></div>
							</div>
							<div class="tab-panes">
								
								{{-- Вкладка: Мероприятия --}}
								<div class="tab-pane active" id="pay-events">
									<div class="mt-1">
										@if($paymentSettings?->yoomoney_verified)
										<div class="f-16 cs b-600">✅ Платежи настроены (ЮМани)</div>
										<div class="f-15 mt-05" style="opacity:.6">Shop ID: {{ $paymentSettings->yoomoney_shop_id }}</div>
										@elseif($paymentSettings && ($paymentSettings->tbank_link || $paymentSettings->sber_link))
										<div class="f-16 cd b-600">🔗 Настроены платежи по ссылке</div>
										@if($paymentSettings->tbank_link)
										<div class="f-15 mt-05" style="opacity:.6">Т-Банк: {{ $paymentSettings->tbank_link }}</div>
										@endif
										@if($paymentSettings->sber_link)
										<div class="f-15 mt-05" style="opacity:.6">Сбер: {{ $paymentSettings->sber_link }}</div>
										@endif
										@else
										<div class="f-15" style="opacity:.5">⚙️ Платежи не настроены</div>
										@endif
										<div class="mt-2">
											<a href="{{ route('profile.payment_settings') }}" class="btn btn-secondary">⚙️ Настроить оплату</a>
										</div>
									</div>
								</div>
								
								{{-- Вкладка: Premium и реклама (только Admin) --}}
								@if(auth()->user()->isAdmin())
								<div class="tab-pane" id="pay-premium">
									<div class="mt-1">
										@php
										$premiumPayment = \App\Models\PlatformPaymentSetting::first();
										@endphp
										@if($premiumPayment)
										<div class="f-16 b-600 mb-1">Текущий метод:
											<span class="cd">{{ match($premiumPayment->method) {
												'tbank_link' => '🏦 Т-Банк (по ссылке)',
												'sber_link'  => '💚 Сбер (по ссылке)',
												'yoomoney'   => '🟡 ЮМани',
												default       => $premiumPayment->method,
											} }}</span>
										</div>
										@else
										<div class="f-15" style="opacity:.5">⚙️ Платежи за Premium не настроены</div>
										@endif
										<div class="mt-2">
											<a href="{{ route('admin.platform_payment_settings') }}" class="btn btn-secondary">⚙️ Настроить оплату Premium</a>
										</div>
									</div>
								</div>
								@endif
								
							</div>
						</div>
					</div>
				</div>
				@endif
				
				
				@if(auth()->check() && (auth()->user()->isOrganizer() || auth()->user()->isAdmin()))
				@php
				$mySchools = \App\Models\VolleyballSchool::where('organizer_id', auth()->id())->get();
				$allSchools = auth()->user()->isAdmin()
				? \App\Models\VolleyballSchool::with('organizer:id,first_name,last_name')->orderBy('name')->get()
				: collect();
				@endphp
				<div class="ramka">
					<h2 class="-mt-05">Школы волейбола</h2>
					
					@if(auth()->user()->isAdmin() && $allSchools->isNotEmpty())
					<div class="b-600 mb-1 f-16">Все школы на платформе:</div>
					@foreach($allSchools as $s)
					<div class="d-flex between fvc mb-1 card">
						<div class="d-flex fvc gap-2">
							@php $sLogo = $s->getFirstMediaUrl('logo', 'thumb'); @endphp
							@if($sLogo)<img src="{{ $sLogo }}" alt="" style="width:3.2rem;height:3.2rem;border-radius:50%;object-fit:cover">@endif
							<div>
								<div class="b-600">{{ $s->name }}</div>
								<div class="f-14" style="opacity:.6">{{ trim($s->organizer?->first_name . ' ' . $s->organizer?->last_name) }} · {{ $s->is_published ? '✅' : '⏸' }}</div>
							</div>
						</div>
						<div class="d-flex gap-1">
							<a href="{{ route('volleyball_school.show', $s->slug) }}" class="btn btn-small btn-secondary">👁</a>
							<a href="{{ route('volleyball_school.edit') }}?id={{ $s->id }}" class="btn btn-small btn-secondary">✏️</a>
						</div>
					</div>
					@endforeach
					<div class="mt-2">
						<a href="{{ route('volleyball_school.create') }}" class="btn btn-secondary">+ Создать для организатора</a>
					</div>
					
					@elseif($mySchools->isNotEmpty())
					@php $s = $mySchools->first(); $sLogo = $s->getFirstMediaUrl('logo', 'thumb'); @endphp
					<div class="d-flex fvc gap-2 mb-2">
						@if($sLogo)<img src="{{ $sLogo }}" alt="logo" style="width:4rem;height:4rem;border-radius:50%;object-fit:cover">@endif
						<div>
							<div class="b-600">{{ $s->name }}</div>
							<div class="f-14 mt-05" style="opacity:.6">{{ $s->is_published ? '✅ Опубликовано' : '⏸ Скрыто' }}</div>
						</div>
					</div>
					<div class="d-flex gap-1">
						<a href="{{ route('volleyball_school.show', $s->slug) }}" class="btn btn-secondary">Открыть</a>
						<a href="{{ route('volleyball_school.edit') }}?id={{ $s->id }}" class="btn btn-secondary">Редактировать</a>
					</div>
					
					@else
					<p>Создайте публичную страницу вашей школы или волейбольного сообщества — там будут отображаться ваши мероприятия, описание и контакты.</p>
					<a href="{{ route('volleyball_school.create') }}" class="btn">Создать страницу школы</a>
					@endif
				</div>
				@endif
				
				<div class="ramka">
					@php $activePremium = auth()->user()->activePremium(); @endphp
					<h2 class="-mt-05">👑 Premium подписка</h2>
					<div class="card">
						@if($activePremium)
						<div class="d-flex between fvc">
							<div>
								<div class="f-18 b-600" style="color:#f5c842;">👑 Premium активен</div>
								<div class="f-15 mt-05" style="opacity:.6;">
									До {{ $activePremium->expires_at->format('d.m.Y') }}
									· {{ match($activePremium->plan) {
									'trial'   => 'Пробный период',
									'month'   => '1 месяц',
									'quarter' => '3 месяца',
									'year'    => 'Год',
									} }}
								</div>
							</div>
							<div class="d-flex gap-1">
								<a href="{{ route('premium.settings') }}" class="btn btn-secondary">⚙️ Настройки</a>
								<a href="{{ route('premium.index') }}" class="btn btn-secondary">Продлить</a>
							</div>
						</div>
						@else
						<div class="row row2">
							<div class="col-md-8">
								<div class="f-17 b-600 mb-1">Откройте возможности Premium</div>
								<ul class="list f-15">
									<li>👑 Золотой аватар — выделяйтесь среди игроков</li>
									<li>🥇 Приоритет в очереди резерва</li>
									<li>👥 Друзья и гости профиля</li>
									<li>📊 Детальная история игр и аналитика</li>
									<li>🔔 Недельная сводка игр в вашем городе</li>
								</ul>
							</div>
							<div class="col-md-4 text-center" style="display:flex;flex-direction:column;justify-content:center;gap:1rem;">
								<a href="{{ route('premium.index') }}" class="btn">👑 Подключить Premium</a>
								<div class="f-14" style="opacity:.5;">от 199₽ / месяц</div>
							</div>
						</div>
						@endif
					</div>
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
				
				
				
				{{-- ========================= ORGANIZER REQUEST ========================= --}}
				@auth
				@if (($user->role ?? 'user') === 'user')
				<div class="ramka form">  	
					<h2 class="-mt-05">Хочу стать организатором мероприятий</h2>
					
					<p>Организатор может создавать мероприятия, управлять участниками и назначать помощников.</p>
					
					@if (!empty($hasPendingOrganizerRequest))
					<div class="alert alert-info">Ваша заявка уже отправлена и ожидает рассмотрения.</div>
					@else
					<form method="POST" action="{{ route('organizer.request') }}">
						@csrf
						<div class="mb-1">
							<label>Комментарий (необязательно)</label>
							<textarea name="message" rows="3" placeholder="Например: регулярно организую игры и хочу делать это через Volley"></textarea>
						</div>
						<button type="submit" class="btn">Отправить заявку</button>
					</form>
					@endif
				</div>
				@endif
				@endauth				
				
				
				
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
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			async function copyToClipboard(text) {
				if (!text) return false;
				
				if (navigator.clipboard && window.isSecureContext) {
					try {
						await navigator.clipboard.writeText(text);
						return true;
						} catch (e) {
						console.warn('Clipboard API copy failed', e);
					}
				}
				
				try {
					const textarea = document.createElement('textarea');
					textarea.value = text;
					textarea.setAttribute('readonly', '');
					textarea.style.position = 'fixed';
					textarea.style.left = '-9999px';
					textarea.style.top = '0';
					document.body.appendChild(textarea);
					textarea.focus();
					textarea.select();
					
					const ok = document.execCommand('copy');
					document.body.removeChild(textarea);
					return ok;
					} catch (e) {
					console.warn('Fallback copy failed', e);
					return false;
				}
			}
			
			function setupBindButton(buttonId, resultId, linkId, url, payload = {}, options = {}) {
				const button = document.getElementById(buttonId);
				if (!button) {
					console.warn('Bind button not found:', buttonId);
					return;
				}
				
				button.addEventListener('click', async function () {
					button.disabled = true;
					
					try {
						const res = await fetch(url, {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': '{{ csrf_token() }}',
								'Accept': 'application/json',
								'Content-Type': 'application/json',
							},
							body: JSON.stringify(payload),
						});
						
						let data = null;
						try {
							data = await res.json();
							} catch (e) {
							console.error('Invalid JSON response', e);
							alert('Сервер вернул некорректный ответ');
							return;
						}
						
						if (!res.ok || !data?.ok) {
							alert(data?.message || 'Не удалось создать ссылку');
							return;
						}
						
						const box = document.getElementById(resultId);
						const link = document.getElementById(linkId);
						
						if (link && data.link) {
							link.href = data.link;
							link.textContent = data.link;
						}
						
						if (options.commandBoxId && options.commandId) {
							const commandBox = document.getElementById(options.commandBoxId);
							const commandEl = document.getElementById(options.commandId);
							const copyHint = options.copyHintId
							? document.getElementById(options.copyHintId)
							: null;
							
							if (commandBox && commandEl && data.command) {
								commandEl.textContent = data.command;
								commandBox.classList.remove('hidden');
								
								const copied = await copyToClipboard(data.command);
								if (copied && copyHint) {
									copyHint.classList.remove('hidden');
								}
							}
						}
						
						if (box) {
							box.classList.remove('hidden');
						}
						
						if (options.autoOpenLink && data.link) {
							window.open(data.link, '_blank', 'noopener');
						}
						} catch (e) {
						console.error(e);
						alert('Ошибка запроса при создании ссылки');
						} finally {
						button.disabled = false;
					}
				});
			}
			
			setupBindButton(
			'connect-max-btn',
			'connect-max-result',
			'connect-max-link',
			'{{ route('profile.max.generate_link') }}',
			{ kind: 'personal' }
			);
			
			setupBindButton(
			'connect-telegram-btn',
			'connect-telegram-result',
			'connect-telegram-link',
			'{{ route('profile.telegram.generate_link') }}',
			{ kind: 'personal' }
			);
			
			setupBindButton(
			'connect-vk-btn',
			'connect-vk-result',
			'connect-vk-link',
			'{{ route('profile.vk.generate_link') }}',
			{ kind: 'personal' },
			{
				commandBoxId: 'connect-vk-command-box',
				commandId: 'connect-vk-command',
				copyHintId: 'connect-vk-copy-hint',
				autoOpenLink: false,
			}
			);
		});
	</script>

	<script>
	(function() {
		if (!navigator.userAgent.includes('VolleyPlayApp') || !window.Capacitor) return;

		document.querySelectorAll('.is-app-only').forEach(function(el) { el.style.display = ''; });

		var NativeBiometric = window.Capacitor.Plugins.NativeBiometric;
		if (!NativeBiometric) return;

		var statusEl     = document.getElementById('biometric-status');
		var disableBtn   = document.getElementById('biometric-disable-btn');
		var enableBtn    = document.getElementById('biometric-enable-btn');

		async function refreshStatus() {
			try {
				var avail = await NativeBiometric.isAvailable();
				if (!avail.isAvailable) {
					if (statusEl) statusEl.textContent = 'Face ID / Touch ID недоступен на этом устройстве.';
					return;
				}
				try {
					var creds = await NativeBiometric.getCredentials({ server: 'volleyplay.club' });
					if (creds && creds.password) {
						if (statusEl) statusEl.textContent = '✅ Face ID включён.';
						if (disableBtn) disableBtn.style.display = '';
						if (enableBtn) enableBtn.style.display = 'none';
						return;
					}
				} catch (e) { /* нет credentials */ }
				if (statusEl) statusEl.textContent = 'Face ID не настроен.';
				if (disableBtn) disableBtn.style.display = 'none';
				if (enableBtn) enableBtn.style.display = '';
			} catch (e) {
				if (statusEl) statusEl.textContent = 'Не удалось проверить статус.';
			}
		}

		if (disableBtn) {
			disableBtn.addEventListener('click', async function() {
				disableBtn.disabled = true;
				try {
					await fetch('/api/biometric/revoke', {
						method: 'DELETE',
						headers: {
							'Accept': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
						},
						credentials: 'same-origin'
					});
					await NativeBiometric.deleteCredentials({ server: 'volleyplay.club' });
					await refreshStatus();
				} catch (e) {
					alert('Ошибка при отключении Face ID');
				} finally {
					disableBtn.disabled = false;
				}
			});
		}

		if (enableBtn) {
			enableBtn.addEventListener('click', async function() {
				enableBtn.disabled = true;
				try {
					var avail = await NativeBiometric.isAvailable();
					if (!avail.isAvailable) { alert('Face ID недоступен.'); return; }

					var token = crypto.randomUUID();
					var resp = await fetch('/api/biometric/register', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
						},
						credentials: 'same-origin',
						body: JSON.stringify({ biometric_token: token })
					});

					if (resp.ok) {
						await NativeBiometric.setCredentials({
							username: 'volleyplay_user',
							password: token,
							server: 'volleyplay.club'
						});
						await refreshStatus();
					}
				} catch (e) {
					alert('Ошибка при включении Face ID');
				} finally {
					enableBtn.disabled = false;
				}
			});
		}

		refreshStatus();
	})();
	</script>
</x-voll-layout>